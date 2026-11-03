<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Location;
use App\Models\OperationalExpense;
use App\Models\Product;
use App\Models\ProductSale;
use App\Models\ProductStandStock;
use App\Models\SaleOrder;
use App\Models\User;
use App\Services\InventoryBalances;
use App\Services\DeliveryGuidePdf;
use App\Services\SalesMetrics;
use App\Services\SalesHistoryPdf;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SalesController extends Controller
{
    public function index(InventoryBalances $balances, SalesMetrics $salesMetrics)
    {
        $assignedPoint = auth()->user()->is_super_admin ? null : auth()->user()->salesLocation;
        $canWholesale = ! $assignedPoint || $assignedPoint->kind === 'vehicle';
        $canUnit = ! $assignedPoint || $assignedPoint->kind !== 'vehicle';
        $products = Product::orderBy('brand')->orderBy('weight_kg')->get();
        $sellableProducts = $products->filter(fn (Product $product) => $product->isReadyForSale())->values();
        $customers = Customer::where('is_active', true)->orderBy('name')->get(['id', 'name', 'nuit']);
        $stands = Location::where('is_sales_point', true)->where('kind', 'physical')->where('is_active', true)->orderBy('name')->get();
        if ($assignedPoint) $stands = $stands->where('id', $assignedPoint->id)->values();
        $centralStock = $products->mapWithKeys(fn (Product $product) => [$product->id => $balances->centralQuantity($product)]);
        $standStock = ProductStandStock::whereIn('location_id', $stands->pluck('id'))
            ->whereIn('product_id', $products->pluck('id'))
            ->get(['location_id', 'product_id', 'quantity'])
            ->groupBy('location_id')
            ->map(fn ($rows) => $rows->pluck('quantity', 'product_id'));
        $orders = $this->groupSalesQuery(auth()->user())
            ->with(['items.product', 'customer', 'location', 'createdBy'])
            ->latest('id')->paginate(50);
        $metrics = $salesMetrics->build(auth()->user());
        $canViewSalesProfit = auth()->user()->canViewSalesProfit();

        return view('sales.index', compact('products', 'sellableProducts', 'customers', 'stands', 'centralStock', 'standStock', 'orders', 'metrics', 'assignedPoint', 'canWholesale', 'canUnit', 'canViewSalesProfit'));
    }

    public function history(Request $request)
    {
        $period = $this->historyPeriod($request);
        $point = $this->historyLocation($request);
        $query = $this->historyQuery($period, $point?->id);

        return view('sales.history', [
            'orders' => (clone $query)->with(['items.product', 'customer', 'location', 'createdBy'])
                ->orderByDesc('sold_at')->orderByDesc('id')->paginate(50)->withQueryString(),
            'summary' => $this->historySummary($query),
            'expenseSummary' => $this->historyExpenseSummary($period),
            'pointDaily' => $this->historyPointDaily($period['date'], $point?->id),
            'previousSummary' => $this->historyPreviousSummary($period, $point?->id),
            'points' => Location::where('kind', 'physical')->where('is_sales_point', true)->orderBy('name')->get(),
            'selectedPoint' => $point,
            'period' => $period,
            'canViewSalesProfit' => $request->user()->canViewSalesProfit(),
        ]);
    }

    public function historyPdf(Request $request, SalesHistoryPdf $pdf)
    {
        $period = $this->historyPeriod($request);
        $point = $this->historyLocation($request);
        $query = $this->historyQuery($period, $point?->id);
        $orders = (clone $query)->with(['items.product', 'customer', 'location', 'createdBy'])
            ->orderBy('sold_at')->orderBy('id')->get();
        $filename = 'historico-vendas-'.$period['key'].'-'.$period['date'].($point ? '-posto-'.$point->id : '').'.pdf';

        return response($pdf->render($orders, $period['label'].($point ? ' · '.$point->name : ''), $this->historySummary($query), $this->historyExpenseSummary($period), $request->user()->canViewSalesProfit(), $this->historyPointDaily($period['date'], $point?->id)), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function historyLocation(Request $request): ?Location
    {
        $data = $request->validate(['location_id' => ['nullable', 'integer', Rule::exists('locations', 'id')->where('kind', 'physical')->where('is_sales_point', true)]]);

        return isset($data['location_id']) ? Location::findOrFail($data['location_id']) : null;
    }

    private function historyPeriod(Request $request): array
    {
        $data = $request->validate([
            'period' => ['nullable', Rule::in(['all', 'day', 'week', 'month'])],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $key = $data['period'] ?? 'all';
        $date = $data['date'] ?? CarbonImmutable::today(config('app.timezone'))->format('Y-m-d');
        $reference = CarbonImmutable::parse($date, config('app.timezone'))->startOfDay();
        [$start, $end, $label] = match ($key) {
            'day' => [$reference, $reference->addDay(), 'Dia '.$reference->format('d/m/Y')],
            'week' => [
                $reference->startOfWeek(CarbonInterface::MONDAY), $reference->startOfWeek(CarbonInterface::MONDAY)->addWeek(),
                'Semana de '.$reference->startOfWeek(CarbonInterface::MONDAY)->format('d/m/Y').' a '.$reference->endOfWeek(CarbonInterface::SUNDAY)->format('d/m/Y'),
            ],
            'month' => [
                $reference->startOfMonth(), $reference->startOfMonth()->addMonth(),
                'Mês de '.$reference->format('m/Y'),
            ],
            default => [null, null, 'Todas as datas'],
        };

        return compact('key', 'date', 'start', 'end', 'label');
    }

    private function historyQuery(array $period, ?int $locationId = null): Builder
    {
        $query = SaleOrder::query()->whereNull('cancelled_at');
        if ($locationId !== null) $query->where('channel', 'unit')->where('location_id', $locationId);
        if ($period['start']) {
            $query->where('sold_at', '>=', $period['start']->format('Y-m-d H:i:s'))
                ->where('sold_at', '<', $period['end']->format('Y-m-d H:i:s'));
        }

        return $query;
    }

    private function historyPreviousSummary(array $period, ?int $locationId): ?array
    {
        if ($period['start'] === null) return null;
        $previousStart = match ($period['key']) {
            'day' => $period['start']->subDay(),
            'week' => $period['start']->subWeek(),
            'month' => $period['start']->subMonth(),
        };
        $previous = $period;
        $previous['start'] = $previousStart;
        $previous['end'] = $period['start'];

        return ['label' => match ($period['key']) {
            'day' => 'dia anterior', 'week' => 'semana anterior', 'month' => 'mês anterior',
        }, 'summary' => $this->historySummary($this->historyQuery($previous, $locationId))];
    }

    private function historyExpenseSummary(array $period): array
    {
        $query = OperationalExpense::where('status', 'approved');
        if ($period['start']) {
            $query->whereDate('expense_date', '>=', $period['start']->toDateString())
                ->whereDate('expense_date', '<', $period['end']->toDateString());
        }

        $total = $query->selectRaw('COUNT(*) as expense_count, COALESCE(SUM(amount_cents), 0) as expense_cents')->first();

        return ['count' => (int) $total->expense_count, 'amount_cents' => (int) $total->expense_cents];
    }

    private function historyPointDaily(string $date, ?int $locationId = null): array
    {
        $reference = CarbonImmutable::parse($date, config('app.timezone'))->startOfDay();
        $rows = [];
        $points = Location::where('kind', 'physical')->where('is_sales_point', true)->orderBy('name');
        if ($locationId !== null) $points->whereKey($locationId);
        foreach ($points->get() as $point) {
            $rows[$point->id] = [
                'location' => $point, 'orders' => 0, 'quantity' => 0,
                'revenue_cents' => 0, 'margin_cents' => 0, 'products' => [],
            ];
        }

        SaleOrder::where('channel', 'unit')->whereNull('cancelled_at')
            ->whereIn('location_id', array_keys($rows))
            ->where('sold_at', '>=', $reference->format('Y-m-d H:i:s'))
            ->where('sold_at', '<', $reference->addDay()->format('Y-m-d H:i:s'))
            ->with('items.product')
            ->chunkById(500, function ($orders) use (&$rows) {
                foreach ($orders as $order) {
                    if (! isset($rows[$order->location_id])) continue;
                    $rows[$order->location_id]['orders']++;
                    foreach ($order->items as $item) {
                        $priceCents = (int) round((float) $item->unit_price * 100);
                        $costCents = (int) round((float) $item->unit_cost * 100);
                        $rows[$order->location_id]['quantity'] += $item->quantity;
                        $rows[$order->location_id]['revenue_cents'] += $item->quantity * $priceCents;
                        $rows[$order->location_id]['margin_cents'] += $item->quantity * ($priceCents - $costCents);
                        $product = &$rows[$order->location_id]['products'][$item->product_id];
                        if (! isset($product)) $product = [
                            'label' => $item->product?->label ?? 'Produto removido',
                            'quantity' => 0, 'revenue_cents' => 0, 'margin_cents' => 0, 'stock' => 0,
                        ];
                        $product['quantity'] += $item->quantity;
                        $product['revenue_cents'] += $item->quantity * $priceCents;
                        $product['margin_cents'] += $item->quantity * ($priceCents - $costCents);
                        unset($product);
                    }
                }
            });

        $stocks = ProductStandStock::whereIn('location_id', array_keys($rows))->with('product')->get();
        foreach ($stocks as $stock) {
            if (! $stock->product) continue;
            $product = &$rows[$stock->location_id]['products'][$stock->product_id];
            if (! isset($product)) $product = [
                'label' => $stock->product->label,
                'quantity' => 0, 'revenue_cents' => 0, 'margin_cents' => 0, 'stock' => 0,
            ];
            $product['stock'] = $stock->quantity;
            unset($product);
        }
        foreach ($rows as &$row) {
            uasort($row['products'], fn ($a, $b) => strcmp($a['label'], $b['label']));
            $row['products'] = array_values($row['products']);
        }
        unset($row);

        return [
            'label' => $reference->format('d/m/Y'),
            'rows' => array_values($rows),
            'totals' => [
                'orders' => array_sum(array_column($rows, 'orders')),
                'quantity' => array_sum(array_column($rows, 'quantity')),
                'revenue_cents' => array_sum(array_column($rows, 'revenue_cents')),
                'margin_cents' => array_sum(array_column($rows, 'margin_cents')),
            ],
        ];
    }

    private function historySummary(Builder $query): array
    {
        $summary = [
            'wholesale' => ['orders' => 0, 'quantity' => 0, 'revenue_cents' => 0, 'margin_cents' => 0],
            'unit' => ['orders' => 0, 'quantity' => 0, 'revenue_cents' => 0, 'margin_cents' => 0],
        ];
        (clone $query)->with('items:id,sale_order_id,quantity,unit_price,unit_cost')
            ->chunkById(500, function ($orders) use (&$summary) {
                foreach ($orders as $order) {
                    $summary[$order->channel]['orders']++;
                    foreach ($order->items as $item) {
                        $summary[$order->channel]['quantity'] += $item->quantity;
                        $priceCents = (int) round((float) $item->unit_price * 100);
                        $costCents = (int) round((float) $item->unit_cost * 100);
                        $summary[$order->channel]['revenue_cents'] += $item->quantity * $priceCents;
                        $summary[$order->channel]['margin_cents'] += $item->quantity * ($priceCents - $costCents);
                    }
                }
            }, 'id');

        return $summary;
    }

    public function wholesale(Request $request, InventoryBalances $balances)
    {
        $this->normalizeLegacyItems($request);
        $plate = $request->input('truck_plate');
        $driver = $request->input('driver_name');
        $request->merge([
            'truck_plate' => is_string($plate) ? mb_strtoupper(trim($plate)) : $plate,
            'driver_name' => is_string($driver) ? trim($driver) : $driver,
        ]);
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'truck_plate' => ['required', 'string', 'max:30'],
            'driver_name' => ['required', 'string', 'max:100'],
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')->where('is_active', true)],
            'note' => ['nullable', 'string', 'max:255'],
            'generate_delivery_guide' => ['sometimes', 'boolean'],
        ]);
        $assignedPoint = $request->user()->is_super_admin ? null : $request->user()->salesLocation;
        abort_if($assignedPoint && $assignedPoint->kind !== 'vehicle', 403);
        if ($assignedPoint?->plate && $data['truck_plate'] !== mb_strtoupper($assignedPoint->plate)) {
            throw ValidationException::withMessages(['truck_plate' => 'A matrícula deve ser a do carro atribuído ao utilizador.']);
        }

        $order = DB::transaction(function () use ($data, $request, $balances, $assignedPoint) {
            $order = SaleOrder::create([
                'channel' => 'wholesale', 'customer_id' => $data['customer_id'] ?? null,
                'vehicle_location_id' => $assignedPoint?->id,
                'truck_plate' => $data['truck_plate'], 'driver_name' => $data['driver_name'],
                'created_by_user_id' => $request->user()->id, 'note' => $data['note'] ?? null,
                'sold_at' => now(),
            ]);

            foreach ($data['items'] as $index => $item) {
                $product = $balances->lockProduct((int) $item['product_id']);
                if (! $product->isReadyForSale()) {
                    throw ValidationException::withMessages(["items.{$index}.product_id" => "Defina o custo do fornecedor e os preços de revendedor e por unidade de {$product->label} antes da venda."]);
                }
                $available = $balances->centralQuantity($product);
                if ($available < $item['quantity']) {
                    throw ValidationException::withMessages(["items.{$index}.quantity" => "Estoque central insuficiente para {$product->label}. Disponível: {$available}."]);
                }

                $product->decrement('quantity', (int) $item['quantity']);
                ProductSale::create([
                    'sale_order_id' => $order->id, 'product_id' => $product->id,
                    'channel' => 'wholesale', 'vehicle_location_id' => $assignedPoint?->id,
                    'quantity' => (int) $item['quantity'], 'unit_price' => $product->reseller_price,
                    'unit_cost' => $product->purchase_price, 'truck_plate' => $data['truck_plate'],
                    'driver_name' => $data['driver_name'], 'created_by_user_id' => $request->user()->id,
                    'note' => $data['note'] ?? null, 'sold_at' => $order->sold_at,
                ]);
            }

            return $order;
        });

        if ($request->boolean('generate_delivery_guide')) {
            return redirect()->route('sales.delivery-guide', $order->items()->firstOrFail());
        }

        return back()->with('success', 'Carregamento registado como venda a grosso.');
    }

    public function unit(Request $request, InventoryBalances $balances)
    {
        $this->normalizeLegacyItems($request);
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')->where('is_active', true)],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $assignedPoint = $request->user()->is_super_admin ? null : $request->user()->salesLocation;
        abort_if($assignedPoint && ($assignedPoint->kind === 'vehicle' || $assignedPoint->id !== (int) $data['location_id']), 403);

        DB::transaction(function () use ($data, $request, $balances) {
            $stand = Location::whereKey($data['location_id'])->where('is_sales_point', true)
                ->where('kind', 'physical')->where('is_active', true)->first();
            if (! $stand) throw ValidationException::withMessages(['location_id' => 'Escolha uma banca ou posto físico.']);
            $order = SaleOrder::create([
                'channel' => 'unit', 'customer_id' => $data['customer_id'] ?? null,
                'location_id' => $stand->id,
                'created_by_user_id' => $request->user()->id,
                'note' => $data['note'] ?? null, 'sold_at' => now(),
            ]);

            foreach ($data['items'] as $index => $item) {
                $product = $balances->lockProduct((int) $item['product_id']);
                if (! $product->isReadyForSale()) {
                    throw ValidationException::withMessages(["items.{$index}.product_id" => "Defina o custo do fornecedor e os preços de revendedor e por unidade de {$product->label} antes da venda."]);
                }
                $standBalance = ProductStandStock::where('product_id', $product->id)
                    ->where('location_id', $stand->id)->first();
                if (! $standBalance || $standBalance->quantity < $item['quantity']) {
                    throw ValidationException::withMessages(["items.{$index}.quantity" => "Estoque insuficiente de {$product->label} na banca escolhida."]);
                }

                $standBalance->decrement('quantity', (int) $item['quantity']);
                $product->decrement('quantity', (int) $item['quantity']);
                ProductSale::create([
                    'sale_order_id' => $order->id, 'product_id' => $product->id,
                    'channel' => 'unit', 'location_id' => $stand->id,
                    'quantity' => (int) $item['quantity'], 'unit_price' => $product->unit_price,
                    'unit_cost' => $product->purchase_price, 'created_by_user_id' => $request->user()->id,
                    'note' => $data['note'] ?? null, 'sold_at' => $order->sold_at,
                ]);
            }
        });

        return back()->with('success', 'Venda por unidade registada.');
    }

    public function cancel(Request $request, ProductSale $sale, InventoryBalances $balances)
    {
        $this->authorizeSalePoint($request, $sale->order);

        DB::transaction(function () use ($request, $sale, $balances) {
            $order = SaleOrder::whereKey($sale->sale_order_id)->lockForUpdate()->firstOrFail();
            abort_if($order->cancelled_at, 409, 'Esta venda já foi cancelada.');

            foreach ($order->items()->orderBy('product_id')->get() as $item) {
                $product = $balances->lockProduct($item->product_id, true);
                $product->increment('quantity', $item->quantity);

                if ($order->channel === 'unit') {
                    $standBalance = ProductStandStock::firstOrCreate(
                        ['product_id' => $item->product_id, 'location_id' => $order->location_id],
                        ['quantity' => 0]
                    );
                    $standBalance->increment('quantity', $item->quantity);
                }
            }

            $cancelledAt = now();
            $order->update(['cancelled_at' => $cancelledAt, 'cancelled_by_user_id' => $request->user()->id]);
            $order->items()->update(['cancelled_at' => $cancelledAt, 'cancelled_by_user_id' => $request->user()->id]);
        });

        return back()->with('success', 'Venda cancelada. Quantidade devolvida ao estoque.');
    }

    public function deliveryGuide(Request $request, ProductSale $sale, DeliveryGuidePdf $pdf)
    {
        $order = $sale->order;
        $this->authorizeSalePoint($request, $order);
        abort_unless($order->channel === 'wholesale', 404);
        abort_if($order->cancelled_at, 409, 'Não é possível gerar guia para uma venda cancelada.');

        return response($pdf->render($order->load('items.product', 'createdBy', 'customer')), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="guia-entrega-'.str_pad((string) $order->items->min('id'), 6, '0', STR_PAD_LEFT).'.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function authorizeSalePoint(Request $request, SaleOrder $order): void
    {
        abort_unless($this->groupSalesQuery($request->user())->whereKey($order->id)->exists(), 403);
    }

    private function groupSalesQuery(User $user): Builder
    {
        $query = SaleOrder::query();
        if ($user->is_super_admin) return $query;

        $groupIds = $user->groups()->pluck('access_groups.id');
        $query->where(function (Builder $query) use ($user, $groupIds) {
            $query->where('created_by_user_id', $user->id);
            if ($groupIds->isNotEmpty()) {
                $query->orWhereHas('createdBy.groups', fn (Builder $groups) => $groups->whereIn('access_groups.id', $groupIds));
            }
        });

        if ($point = $user->salesLocation) {
            $point->kind === 'vehicle'
                ? $query->where('vehicle_location_id', $point->id)
                : $query->where('location_id', $point->id);
        }

        return $query;
    }

    private function normalizeLegacyItems(Request $request): void
    {
        if (! $request->has('items') && $request->has('product_id')) {
            $request->merge(['items' => [[
                'product_id' => $request->input('product_id'),
                'quantity' => $request->input('quantity'),
            ]]]);
        }
    }
}
