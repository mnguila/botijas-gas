<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductStandStock;
use App\Models\AllocationRequestItem;

class InventoryBalances
{
    public function lockProduct(int $productId, bool $includeTrashed = false): Product
    {
        $query = Product::whereKey($productId);
        if ($includeTrashed) $query->withTrashed();
        $product = $query->lockForUpdate()->firstOrFail();
        // Também obtém um bloqueio de escrita em SQLite, onde FOR UPDATE não é aplicado.
        $product->touch();

        return $product->fresh();
    }

    public function centralQuantity(Product $product): int
    {
        return $this->centralPhysicalQuantity($product) - $product->damaged_quantity;
    }

    public function centralPhysicalQuantity(Product $product): int
    {
        $inStands = (int) ProductStandStock::where('product_id', $product->id)->sum('quantity');

        return $product->quantity - $inStands - $this->inTransitQuantity($product);
    }

    public function inTransitQuantity(Product $product): int
    {
        return (int) AllocationRequestItem::where('product_id', $product->id)
            ->whereHas('request', fn ($query) => $query->where('status', 'in_transit'))->sum('quantity');
    }
}
