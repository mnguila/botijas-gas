<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductSale;
use App\Models\ProductStandStock;
use App\Models\StockDamageEvent;
use App\Services\InventoryBalances;
use App\Services\SalesMetrics;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request, SalesMetrics $salesMetrics, InventoryBalances $balances)
    {
        $month = $request->validate(['month' => ['nullable', 'date_format:Y-m']])['month'] ?? now()->format('Y-m');
        $monthStart = CarbonImmutable::parse($month.'-01', config('app.timezone'));
        $canViewSalesHistory = $request->user()->hasPermission('sales.history.view');
        $topPurchased = $canViewSalesHistory ? $this->topPurchased($monthStart) : collect();
        $highestPurchased = $topPurchased->max('total') ?? 0;
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
                'available_quantity' => max(0, $product->quantity - $damaged),
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
        $totalUnits = $rows->sum('available_quantity');
        $productCount = $products->count();
        $productsWithStock = $rows->filter(fn (array $row) => $row['available_quantity'] > 0)->count();
        $unpricedCount = $products->filter(fn (Product $product) => $product->quantity > 0 && (float) $product->purchase_price <= 0)->count();
        $topRows = $rows->filter(fn (array $row) => $row['investment_cents'] > 0)->take(6);
        $highestInvestmentCents = $topRows->max('investment_cents') ?? 0;
        $salesMetrics = auth()->user()->hasPermission('sales.create') ? $salesMetrics->build(auth()->user()) : null;

        return view('dashboard.index', compact(
            'rows', 'totalInvestmentCents', 'damagedInvestmentCents', 'damagedUnits', 'resellerProfitCents', 'unitProfitCents', 'projectedUnits', 'totalUnits', 'productCount', 'productsWithStock', 'unpricedCount', 'topRows', 'highestInvestmentCents', 'salesMetrics', 'canViewSalesProfit', 'canViewSalesHistory', 'month', 'topPurchased', 'highestPurchased'
        ));
    }

    private function topPurchased(CarbonImmutable $monthStart)
    {
        $start = $monthStart->format('Y-m-d H:i:s');
        $end = $monthStart->addMonth()->format('Y-m-d H:i:s');
        $sold = ProductSale::query()
            ->select('product_id', 'channel')->selectRaw('SUM(quantity) AS total')
            ->whereHas('order', fn ($orders) => $orders->whereNull('cancelled_at'))
            ->where('sold_at', '>=', $start)->where('sold_at', '<', $end)
            ->groupBy('product_id', 'channel')->get();
        $refunded = StockDamageEvent::query()
            ->join('product_sales', 'stock_damage_events.product_sale_id', '=', 'product_sales.id')
            ->join('sale_orders', 'product_sales.sale_order_id', '=', 'sale_orders.id')
            ->where('stock_damage_events.kind', 'refund')
            ->whereNull('sale_orders.cancelled_at')
            ->where('stock_damage_events.created_at', '>=', $start)
            ->where('stock_damage_events.created_at', '<', $end)
            ->select('stock_damage_events.product_id', 'product_sales.channel')
            ->selectRaw('SUM(stock_damage_events.quantity) AS total')
            ->groupBy('stock_damage_events.product_id', 'product_sales.channel')->get();

        $quantities = [];
        foreach ($sold as $row) {
            $quantities[$row->product_id][$row->channel] = (int) $row->total;
        }
        foreach ($refunded as $row) {
            $quantities[$row->product_id][$row->channel] = ($quantities[$row->product_id][$row->channel] ?? 0) - (int) $row->total;
        }
        $products = Product::withTrashed()->whereIn('id', array_keys($quantities))->get()->keyBy('id');

        return collect($quantities)->map(function (array $channels, int $productId) use ($products) {
            $wholesale = max(0, $channels['wholesale'] ?? 0);
            $unit = max(0, $channels['unit'] ?? 0);

            return ['product' => $products->get($productId), 'wholesale' => $wholesale, 'unit' => $unit, 'total' => $wholesale + $unit];
        })->filter(fn (array $row) => $row['product'] && $row['total'] > 0)
            ->sortByDesc('total')->take(5)->values();
    }
}
