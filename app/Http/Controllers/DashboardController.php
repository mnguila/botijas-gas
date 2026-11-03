<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\SalesMetrics;

class DashboardController extends Controller
{
    public function index(SalesMetrics $salesMetrics)
    {
        $canViewSalesProfit = auth()->user()->canViewSalesProfit();
        $products = Product::orderBy('brand')->orderBy('weight_kg')->get();
        $rows = $products->map(function (Product $product) use ($canViewSalesProfit) {
            $unitCostCents = (int) round((float) $product->purchase_price * 100);
            $canProjectProfit = $canViewSalesProfit && $product->isReadyForSale();

            return [
                'product' => $product,
                'investment_cents' => $unitCostCents * $product->quantity,
                'reseller_profit_cents' => $canProjectProfit
                    ? ((int) round((float) $product->reseller_price * 100) - $unitCostCents) * $product->quantity
                    : null,
                'unit_profit_cents' => $canProjectProfit
                    ? ((int) round((float) $product->unit_price * 100) - $unitCostCents) * $product->quantity
                    : null,
            ];
        })->sortByDesc('investment_cents')->values();

        $totalInvestmentCents = $rows->sum('investment_cents');
        $resellerProfitCents = $rows->sum('reseller_profit_cents');
        $unitProfitCents = $rows->sum('unit_profit_cents');
        $projectedUnits = $rows->sum(fn (array $row) => $row['reseller_profit_cents'] !== null ? $row['product']->quantity : 0);
        $totalUnits = $products->sum('quantity');
        $productCount = $products->count();
        $productsWithStock = $products->where('quantity', '>', 0)->count();
        $unpricedCount = $products->filter(fn (Product $product) => $product->quantity > 0 && (float) $product->purchase_price <= 0)->count();
        $topRows = $rows->filter(fn (array $row) => $row['investment_cents'] > 0)->take(6);
        $highestInvestmentCents = $topRows->max('investment_cents') ?? 0;
        $salesMetrics = auth()->user()->hasPermission('sales.create') ? $salesMetrics->build(auth()->user()) : null;

        return view('dashboard.index', compact(
            'rows', 'totalInvestmentCents', 'resellerProfitCents', 'unitProfitCents', 'projectedUnits', 'totalUnits', 'productCount', 'productsWithStock', 'unpricedCount', 'topRows', 'highestInvestmentCents', 'salesMetrics', 'canViewSalesProfit'
        ));
    }
}
