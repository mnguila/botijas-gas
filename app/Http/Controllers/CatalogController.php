<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\Product;
use App\Models\ProductStockEntry;
use App\Models\ProductStandStock;
use App\Models\StockDamageEvent;
use App\Services\InventoryBalances;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CatalogController extends Controller
{
    public function products(InventoryBalances $balances)
    {
        $products = Product::orderBy('brand')->orderBy('weight_kg')->get();
        $standDamaged = ProductStandStock::selectRaw('product_id, SUM(damaged_quantity) as total')
            ->groupBy('product_id')->pluck('total', 'product_id');
        return view('catalog.products', [
            'products' => $products,
            'centralAvailable' => $products->mapWithKeys(fn (Product $product) => [$product->id => $balances->centralQuantity($product)]),
            'damagedTotals' => $products->mapWithKeys(fn (Product $product) => [$product->id => $product->damaged_quantity + (int) ($standDamaged[$product->id] ?? 0)]),
            'archivedProducts' => Product::onlyTrashed()->orderByDesc('deleted_at')->get(),
            'stockEntries' => ProductStockEntry::with(['product', 'createdBy'])->latest('id')->paginate(50),
            'damageEvents' => StockDamageEvent::with(['product', 'location', 'saleItem', 'createdBy'])
                ->latest('id')->paginate(30, ['*'], 'damage_page'),
        ]);
    }

    public function storeProduct(Request $request)
    {
        $data = $request->validate([
            'brand' => ['required', 'string', 'max:100'],
            'weight_kg' => ['required', 'integer', 'min:1', 'max:999'],
            'description' => ['nullable', 'string', 'max:1000'],
            'quantity' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'damaged_quantity' => ['nullable', 'integer', 'min:0', 'lte:quantity'],
            'purchase_price' => ['required', 'numeric', 'min:0'],
            'reseller_price' => ['nullable', 'numeric', 'min:0'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
        ]);
        $existing = Product::withTrashed()->where('brand', $data['brand'])->where('weight_kg', $data['weight_kg'])->first();
        if ($existing) {
            throw ValidationException::withMessages(['brand' => $existing->trashed()
                ? 'Esta marca e peso estão no histórico. Restaure o produto em vez de criar outro.'
                : 'Esta marca e peso já estão cadastrados.']);
        }
        DB::transaction(function () use ($data, $request) {
            $initialQuantity = (int) $data['quantity'];
            $product = Product::create([
                ...$data, 'quantity' => 0, 'damaged_quantity' => 0,
                'reseller_price' => $data['reseller_price'] ?? 0,
                'unit_price' => $data['unit_price'] ?? 0,
            ]);
            if ($initialQuantity > 0) {
                $this->recordStockEntry($product, $initialQuantity, (int) ($data['damaged_quantity'] ?? 0), 'initial', 'Estoque inicial', $request->user()->id);
            }
        });
        return back()->with('success', 'Produto cadastrado.');
    }

    public function editProduct(Product $product)
    {
        return view('catalog.edit-product', compact('product'));
    }

    public function updateProduct(Request $request, Product $product)
    {
        $data = $request->validate([
            'brand' => ['required', 'string', 'max:100'],
            'weight_kg' => ['required', 'integer', 'min:1', 'max:999'],
            'description' => ['nullable', 'string', 'max:1000'],
            'purchase_price' => ['required', 'numeric', 'min:0'],
            'reseller_price' => ['nullable', 'numeric', 'min:0'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
        ]);
        if (Product::withTrashed()->where('brand', $data['brand'])->where('weight_kg', $data['weight_kg'])->where('id', '!=', $product->id)->exists()) {
            throw ValidationException::withMessages(['brand' => 'Esta marca e peso já pertencem a outro produto, ativo ou arquivado.']);
        }
        $product->update([
            ...$data,
            'reseller_price' => $data['reseller_price'] ?? 0,
            'unit_price' => $data['unit_price'] ?? 0,
        ]);
        return redirect()->route('products.index')->with('success', 'Produto atualizado.');
    }

    public function addProductStock(Request $request, Product $product)
    {
        $data = $request->validate([
            'quantity_added' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'damaged_quantity_added' => ['nullable', 'integer', 'min:0', 'lte:quantity_added'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        DB::transaction(fn () => $this->recordStockEntry(
            $product, (int) $data['quantity_added'], (int) ($data['damaged_quantity_added'] ?? 0), 'addition', $data['note'] ?? null, $request->user()->id
        ));
        return back()->with('success', 'Quantidade adicionada ao estoque.');
    }

    private function recordStockEntry(Product $product, int $quantity, int $damaged, string $source, ?string $note, int $userId): void
    {
        $updated = Product::whereKey($product->id)->where('quantity', '<=', 1000000000 - $quantity)->increment('quantity', $quantity);
        if ($updated !== 1) {
            throw ValidationException::withMessages(['quantity_added' => 'Não foi possível adicionar esta quantidade. Verifique o saldo e tente novamente.']);
        }
        $after = Product::whereKey($product->id)->value('quantity');
        if ($damaged > 0) Product::whereKey($product->id)->increment('damaged_quantity', $damaged);
        ProductStockEntry::create([
            'product_id' => $product->id,
            'created_by_user_id' => $userId,
            'quantity_added' => $quantity,
            'damaged_quantity_added' => $damaged,
            'quantity_before' => $after - $quantity,
            'quantity_after' => $after,
            'source' => $source,
            'note' => $note,
        ]);
        if ($damaged > 0) StockDamageEvent::create([
            'product_id' => $product->id, 'kind' => 'receipt', 'quantity' => $damaged,
            'reason' => $note ?: 'Avaria detetada na entrada ao armazém',
            'created_by_user_id' => $userId,
        ]);
    }

    public function reportCentralDamage(Request $request, Product $product, InventoryBalances $balances)
    {
        $reason = $request->input('reason');
        if (is_string($reason)) $request->merge(['reason' => trim($reason)]);
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'reason' => ['required', 'string', 'max:500'],
        ]);
        DB::transaction(function () use ($product, $balances, $data, $request) {
            $product = $balances->lockProduct($product->id);
            if ($balances->centralQuantity($product) < $data['quantity']) {
                throw ValidationException::withMessages(['quantity' => 'Não há botijas disponíveis suficientes no armazém.']);
            }
            $product->increment('damaged_quantity', $data['quantity']);
            StockDamageEvent::create([
                'product_id' => $product->id, 'kind' => 'detected', 'quantity' => $data['quantity'],
                'reason' => $data['reason'], 'created_by_user_id' => $request->user()->id,
            ]);
        });

        return back()->with('success', 'Avaria registada. As botijas deixaram de estar disponíveis para venda.');
    }

    public function destroyProduct(Product $product)
    {
        $product->delete();
        return back()->with('success', 'Produto arquivado no histórico.');
    }

    public function restoreProduct(int $product)
    {
        Product::onlyTrashed()->findOrFail($product)->restore();
        return back()->with('success', 'Produto restaurado.');
    }

    public function locations()
    {
        return view('catalog.locations', [
            'locations' => Location::where('is_sales_point', true)->where('kind', 'physical')->orderBy('name')->get(),
        ]);
    }

    public function storeLocation(Request $request)
    {
        if (is_string($request->input('name'))) $request->merge(['name' => trim($request->input('name'))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:locations,name'],
            'kind' => ['sometimes', Rule::in(['physical'])],
            'plate' => ['prohibited'],
        ]);
        Location::create([
            'name' => $data['name'], 'kind' => 'physical', 'is_sales_point' => true,
        ]);
        return back()->with('success', 'Local de venda cadastrado.');
    }

    public function updateLocation(Request $request, Location $location)
    {
        abort_unless($location->kind === 'physical' && $location->is_sales_point, 404);
        if (is_string($request->input('name'))) $request->merge(['name' => trim($request->input('name'))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('locations', 'name')->ignore($location->id)],
        ]);
        $location->update(['name' => $data['name']]);

        return redirect()->route('locations.index')->with('success', 'Nome do local atualizado.');
    }

    public function updateLocationStatus(Location $location)
    {
        abort_unless($location->kind === 'physical' && $location->is_sales_point, 404);
        $location->update(['is_active' => ! $location->is_active]);

        return redirect()->route('locations.index')->with('success', $location->is_active
            ? 'Local ativado e disponível para novas operações.'
            : 'Local desativado. O histórico e o estoque foram mantidos.');
    }
}
