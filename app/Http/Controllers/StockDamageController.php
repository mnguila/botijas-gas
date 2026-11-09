<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\Product;
use App\Models\ProductStandStock;
use App\Models\StockDamageEvent;
use App\Services\InventoryBalances;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StockDamageController extends Controller
{
    public function index(Request $request, InventoryBalances $balances)
    {
        $user = $request->user();
        $canManage = $user->hasPermission('products.manage');
        abort_unless($canManage || $user->hasPermission('sales.create'), 403);

        $products = Product::orderBy('brand')->orderBy('weight_kg')->get();
        $stands = Location::where('kind', 'physical')->where('is_sales_point', true)->orderBy('name')->get();
        if (! $canManage && $user->salesLocation) {
            $stands = $stands->where('id', $user->salesLocation->id)->values();
        }
        $stocks = ProductStandStock::whereIn('product_id', $products->pluck('id'))
            ->whereIn('location_id', $stands->pluck('id'))->get()
            ->keyBy(fn (ProductStandStock $stock) => $stock->product_id.'-'.$stock->location_id);
        $centralAvailable = $canManage
            ? $products->mapWithKeys(fn (Product $product) => [$product->id => $balances->centralQuantity($product)])
            : collect();
        $damageBalances = collect();
        foreach ($products as $product) {
            if ($canManage && $product->damaged_quantity > 0) {
                $damageBalances->push(['product' => $product, 'location' => null, 'quantity' => $product->damaged_quantity]);
            }
            foreach ($stands as $stand) {
                $damaged = $stocks->get($product->id.'-'.$stand->id)?->damaged_quantity ?? 0;
                if ($damaged > 0) $damageBalances->push(['product' => $product, 'location' => $stand, 'quantity' => $damaged]);
            }
        }
        $events = StockDamageEvent::with(['product', 'location', 'receivedLocation', 'saleItem', 'createdBy']);
        if (! $canManage && $user->salesLocation) {
            $events->where('location_id', $user->salesLocation->id);
        } elseif (! $canManage) {
            $events->where('created_by_user_id', $user->id);
        }

        return view('damages.index', [
            'products' => $products, 'stands' => $stands, 'stocks' => $stocks,
            'centralAvailable' => $centralAvailable,
            'centralDamaged' => $canManage ? $products->sum('damaged_quantity') : 0,
            'standDamaged' => $stocks->sum('damaged_quantity'),
            'damageBalances' => $damageBalances,
            'canManage' => $canManage,
            'events' => $events->orderByDesc('created_at')->orderByDesc('id')->paginate(30),
        ]);
    }

    public function store(Request $request, InventoryBalances $balances)
    {
        $user = $request->user();
        $canManage = $user->hasPermission('products.manage');
        abort_unless($canManage || $user->hasPermission('sales.create'), 403);
        if (is_string($request->input('reason'))) $request->merge(['reason' => trim($request->input('reason'))]);
        $data = $request->validate([
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')->whereNull('deleted_at')],
            'location_id' => ['nullable', 'integer', Rule::exists('locations', 'id')->where('kind', 'physical')->where('is_sales_point', true)],
            'source' => ['prohibited'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'reason' => ['required', 'string', 'max:500'],
        ]);
        $locationId = $canManage ? (isset($data['location_id']) ? (int) $data['location_id'] : null) : $user->salesLocation?->id;
        if (! $canManage) {
            abort_unless($locationId, 403);
            abort_if(isset($data['location_id']) && (int) $data['location_id'] !== $locationId, 403);
        }

        DB::transaction(function () use ($request, $data, $locationId, $balances) {
            $product = $balances->lockProduct((int) $data['product_id']);
            if ($locationId === null) {
                if ($balances->centralQuantity($product) < $data['quantity']) {
                    throw ValidationException::withMessages(['quantity' => 'Não há botijas disponíveis suficientes no armazém.']);
                }
                $product->increment('damaged_quantity', (int) $data['quantity']);
            } else {
                $stock = ProductStandStock::where('product_id', $product->id)
                    ->where('location_id', $locationId)->lockForUpdate()->first();
                if (! $stock || $stock->available_quantity < $data['quantity']) {
                    throw ValidationException::withMessages(['quantity' => 'Não há botijas disponíveis suficientes neste posto.']);
                }
                $stock->increment('damaged_quantity', (int) $data['quantity']);
            }
            StockDamageEvent::create([
                'product_id' => $product->id, 'location_id' => $locationId,
                'kind' => 'detected', 'quantity' => (int) $data['quantity'],
                'reason' => $data['reason'], 'created_by_user_id' => $request->user()->id,
            ]);
        });

        return redirect()->route('damages.index')->with('success', 'Avaria registada. As botijas deixaram de estar disponíveis para venda.');
    }

    public function replace(Request $request, InventoryBalances $balances)
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')->whereNull('deleted_at')],
            'location_id' => ['nullable', 'integer', Rule::exists('locations', 'id')->where('kind', 'physical')->where('is_sales_point', true)],
            'destination' => ['required', Rule::in(['central', 'same'])],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'supplier_name' => ['required', 'string', 'max:150'],
            'reference' => ['nullable', 'string', 'max:150'],
        ]);
        $locationId = isset($data['location_id']) ? (int) $data['location_id'] : null;
        if ($locationId === null && $data['destination'] !== 'central') {
            throw ValidationException::withMessages(['destination' => 'Para avarias no armazém, a reposição deve entrar no armazém.']);
        }

        DB::transaction(function () use ($request, $data, $locationId, $balances) {
            $product = $balances->lockProduct((int) $data['product_id']);
            if ($locationId === null) {
                if ($product->damaged_quantity < $data['quantity']) {
                    throw ValidationException::withMessages(['quantity' => 'A quantidade excede as botijas avariadas no armazém.']);
                }
                $product->decrement('damaged_quantity', (int) $data['quantity']);
            } else {
                $stock = ProductStandStock::where('product_id', $product->id)
                    ->where('location_id', $locationId)->lockForUpdate()->first();
                if (! $stock || $stock->damaged_quantity < $data['quantity']) {
                    throw ValidationException::withMessages(['quantity' => 'A quantidade excede as botijas avariadas neste posto.']);
                }
                $stock->decrement('damaged_quantity', (int) $data['quantity']);
                if ($data['destination'] === 'central') $stock->decrement('quantity', (int) $data['quantity']);
            }
            StockDamageEvent::create([
                'product_id' => $product->id, 'location_id' => $locationId,
                'received_location_id' => $data['destination'] === 'same' ? $locationId : null,
                'kind' => 'supplier_replacement', 'quantity' => (int) $data['quantity'],
                'reason' => 'Troca pelo fornecedor', 'supplier_name' => trim($data['supplier_name']),
                'reference' => isset($data['reference']) ? trim($data['reference']) : null,
                'created_by_user_id' => $request->user()->id,
            ]);
        });

        return redirect()->route('damages.index')->with('success', 'Troca do fornecedor registada. As botijas recebidas estão disponíveis no local indicado.');
    }
}
