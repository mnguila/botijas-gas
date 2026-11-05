<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\Product;
use App\Models\ProductAllocation;
use App\Models\ProductStandStock;
use App\Services\InventoryBalances;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AllocationController extends Controller
{
    public function index(InventoryBalances $balances)
    {
        $products = Product::orderBy('brand')->orderBy('weight_kg')->get();
        $allocatableProducts = $products->filter(fn (Product $product) => $product->isReadyForSale())->values();
        $stands = Location::where('is_sales_point', true)->where('kind', 'physical')->orderBy('name')->get();
        $activeStands = $stands->where('is_active', true);
        $standStock = ProductStandStock::all()->groupBy('product_id')->map(fn ($balances) => $balances->pluck('quantity', 'location_id'));
        $standDamaged = ProductStandStock::all()->groupBy('product_id')->map(fn ($balances) => $balances->pluck('damaged_quantity', 'location_id'));
        $centralStock = $products->mapWithKeys(fn (Product $product) => [$product->id => $balances->centralQuantity($product)]);
        $centralDamaged = $products->mapWithKeys(fn (Product $product) => [$product->id => $product->damaged_quantity]);
        $inTransitStock = $products->mapWithKeys(fn (Product $product) => [$product->id => $balances->inTransitQuantity($product)]);
        $centralTotal = $centralStock->sum() + $centralDamaged->sum();
        $inTransitTotal = $inTransitStock->sum();
        $standTotals = $stands->mapWithKeys(fn (Location $stand) => [
            $stand->id => $products->sum(fn (Product $product) => (int) ($standStock->get($product->id)?->get($stand->id) ?? 0)),
        ]);
        $overallTotal = $products->sum('quantity');
        $allocations = ProductAllocation::with(['product', 'location', 'createdBy'])->latest('id')->paginate(30);

        return view('allocations.index', compact('products', 'allocatableProducts', 'stands', 'activeStands', 'standStock', 'standDamaged', 'centralStock', 'centralDamaged', 'inTransitStock', 'centralTotal', 'inTransitTotal', 'standTotals', 'overallTotal', 'allocations'));
    }

    public function store(Request $request, InventoryBalances $balances)
    {
        abort_unless($request->user()->is_super_admin, 403);
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($data, $request, $balances) {
            $product = $balances->lockProduct((int) $data['product_id']);
            if (! $product->isReadyForSale()) {
                throw ValidationException::withMessages(['product_id' => 'Defina o custo do fornecedor e os preços de revendedor e por unidade antes de alocar este produto.']);
            }
            $stand = Location::whereKey($data['location_id'])->where('is_sales_point', true)
                ->where('kind', 'physical')->where('is_active', true)->first();
            if (! $stand) throw ValidationException::withMessages(['location_id' => 'Escolha uma banca ou posto físico.']);

            $available = $balances->centralQuantity($product);
            if ($available < $data['quantity']) {
                throw ValidationException::withMessages(['quantity' => "Estoque central insuficiente. Disponível: {$available}."]);
            }

            ProductStandStock::firstOrCreate(
                ['product_id' => $product->id, 'location_id' => $stand->id], ['quantity' => 0]
            )->increment('quantity', (int) $data['quantity']);
            ProductAllocation::create([
                'product_id' => $product->id,
                'location_id' => $stand->id,
                'quantity' => (int) $data['quantity'],
                'created_by_user_id' => $request->user()->id,
                'note' => $data['note'] ?? null,
            ]);
        });

        return back()->with('success', 'Botijas alocadas à banca. O total de estoque não mudou.');
    }
}
