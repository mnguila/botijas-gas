<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductStandStock;
use App\Services\InventoryBalances;
use App\Services\SalesMetrics;

class DashboardController extends Controller
{
    public function index(SalesMetrics $salesMetrics, InventoryBalances $balances)
    {
        $canViewSalesProfit = auth()->user()->canViewSalesProfit();
        $products = Product::orderBy('brand')->orderBy('weight_kg')->get();
        $standDamaged = ProductStandStock::selectRaw('product_id, SUM(damaged_quantity) as total')
            ->groupBy('product_id')->pluck('total', 'product_id');
        $rows = $products->map(function (Product $product) use ($canViewSalesProfit, $standDamaged, $balances) {
            $unitCostCents = (int) round((float) $product->purchase_price * 100);
            $canProjectProfit = $canViewSalesProfit && $product->isReadyForSale();
            $damaged = $product->damaged_quantity + (int) ($standDamaged[$product->id] ?? 0);
            $sellable = max(0, $product->quantity - $damaged - $balances->inTransitQuantity($product));

            return [
                'product' => $product,
                'investment_cents' => $unitCostCents * $product->quantity,
                'damaged_quantity' => $damaged,
                'damaged_investment_cents' => $unitCostCents * $damaged,
                'sellable_quantity' => $sellable,
                'reseller_profit_cents' => $canProjectProfit
                    ? ((int) round((float) $product->reseller_price * 100) - $unitCostCents) * $sellable
                    : null,
                'unit_profit_cents' => $canProjectProfit
                    ? ((int) round((float) $product->unit_price * 100) - $unitCostCents) * $sellable
                    : null,
            ];
        })->sortByDesc('investment_cents')->values();

        $totalInvestmentCents = $rows->sum('investment_cents');
        $damagedInvestmentCents = $rows->sum('damaged_investment_cents');
        $damagedUnits = $rows->sum('damaged_quantity');
        $resellerProfitCents = $rows->sum('reseller_profit_cents');
        $unitProfitCents = $rows->sum('unit_profit_cents');
        $projectedUnits = $rows->sum(fn (array $row) => $row['reseller_profit_cents'] !== null ? $row['sellable_quantity'] : 0);
        $totalUnits = $products->sum('quantity');
        $productCount = $products->count();
        $productsWithStock = $products->where('quantity', '>', 0)->count();
        $unpricedCount = $products->filter(fn (Product $product) => $product->quantity > 0 && (float) $product->purchase_price <= 0)->count();
        $topRows = $rows->filter(fn (array $row) => $row['investment_cents'] > 0)->take(6);
        $highestInvestmentCents = $topRows->max('investment_cents') ?? 0;
        $salesMetrics = auth()->user()->hasPermission('sales.create') ? $salesMetrics->build(auth()->user()) : null;

        return view('dashboard.index', compact(
            'rows', 'totalInvestmentCents', 'damagedInvestmentCents', 'damagedUnits', 'resellerProfitCents', 'unitProfitCents', 'projectedUnits', 'totalUnits', 'productCount', 'productsWithStock', 'unpricedCount', 'topRows', 'highestInvestmentCents', 'salesMetrics', 'canViewSalesProfit'
        ));
    }
}
