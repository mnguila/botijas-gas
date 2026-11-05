@extends('layouts.app')
@section('title', 'Histórico geral de vendas')
@section('content')
<div class="operations-heading"><div>
    <span class="section-eyebrow">Vendas de botijas</span>
    <h2 class="page-title">Histórico geral de vendas</h2>
    <p class="page-subtitle">Consulte vendas e reembolsos por dia, semana, mês e posto de venda.</p>
</div>@if(auth()->user()->hasPermission('sales.create'))<a class="small-link" href="{{ route('sales.index') }}">← Voltar às vendas</a>@endif</div>

<section class="panel sales-history-filter">
    <form method="get" action="{{ route('sales.history') }}">
        <div class="fields three">
            <label>Período<select name="period">
                <option value="all" @selected($period['key'] === 'all')>Todas as datas</option>
                <option value="day" @selected($period['key'] === 'day')>Vendas diárias</option>
                <option value="week" @selected($period['key'] === 'week')>Vendas semanais</option>
                <option value="month" @selected($period['key'] === 'month')>Vendas mensais</option>
            </select></label>
            <label>Data de referência<input type="date" name="date" value="{{ $period['date'] }}"></label>
            <label>Posto de venda<select name="location_id"><option value="">Todos os postos</option>@foreach($points as $point)<option value="{{ $point->id }}" @selected($selectedPoint?->id === $point->id)>{{ $point->name }}</option>@endforeach</select></label>
        </div>
        <div class="form-actions"><button type="submit">Consultar vendas</button><a class="small-link" href="{{ route('sales.history.pdf', array_filter(['period' => $period['key'], 'date' => $period['date'], 'location_id' => $selectedPoint?->id])) }}">Exportar este período em PDF ↓</a></div>
    </form>
    <p class="muted">A semana começa na segunda-feira. Os valores são líquidos: reembolsos são descontados na data em que ocorreram. Vendas canceladas ficam de fora. O resumo por posto usa a data de referência. As despesas aprovadas são um total da empresa.</p>
</section>

@php
    $currentRevenue = $summary['wholesale']['revenue_cents'] + $summary['unit']['revenue_cents'];
    $previousRevenue = $previousSummary ? $previousSummary['summary']['wholesale']['revenue_cents'] + $previousSummary['summary']['unit']['revenue_cents'] : null;
    $revenueChange = $previousRevenue > 0 ? ($currentRevenue - $previousRevenue) / $previousRevenue * 100 : null;
@endphp
<div class="sales-summary sales-history-summary">
    <div class="sales-summary-card"><span>A grosso · {{ $period['label'] }}</span><strong>{{ number_format($summary['wholesale']['revenue_cents'] / 100, 2, ',', '.') }} MT</strong><small>{{ number_format($summary['wholesale']['orders'], 0, ',', '.') }} vendas · {{ number_format($summary['wholesale']['quantity'], 0, ',', '.') }} botijas líquidas</small>@if($summary['wholesale']['refund_cents'] > 0)<small>Reembolsos: {{ number_format($summary['wholesale']['refund_cents'] / 100, 2, ',', '.') }} MT</small>@endif @if($canViewSalesProfit)<small class="history-profit">Lucro: {{ number_format($summary['wholesale']['margin_cents'] / 100, 2, ',', '.') }} MT</small>@endif</div>
    <div class="sales-summary-card"><span>Por unidade · {{ $period['label'] }}</span><strong>{{ number_format($summary['unit']['revenue_cents'] / 100, 2, ',', '.') }} MT</strong><small>{{ number_format($summary['unit']['orders'], 0, ',', '.') }} vendas · {{ number_format($summary['unit']['quantity'], 0, ',', '.') }} botijas líquidas</small>@if($summary['unit']['refund_cents'] > 0)<small>Reembolsos: {{ number_format($summary['unit']['refund_cents'] / 100, 2, ',', '.') }} MT</small>@endif @if($canViewSalesProfit)<small class="history-profit">Lucro: {{ number_format($summary['unit']['margin_cents'] / 100, 2, ',', '.') }} MT</small>@endif</div>
    <div class="sales-summary-card"><span>Total · {{ $period['label'] }}</span><strong>{{ number_format($currentRevenue / 100, 2, ',', '.') }} MT</strong><small>{{ number_format($summary['wholesale']['orders'] + $summary['unit']['orders'], 0, ',', '.') }} vendas · {{ number_format($summary['wholesale']['quantity'] + $summary['unit']['quantity'], 0, ',', '.') }} botijas</small>@if($canViewSalesProfit)<small class="history-profit">Lucro total: {{ number_format(($summary['wholesale']['margin_cents'] + $summary['unit']['margin_cents']) / 100, 2, ',', '.') }} MT</small>@endif</div>
    <div class="sales-summary-card"><span>Despesas operacionais · Empresa · {{ $period['label'] }}</span><strong>{{ number_format($expenseSummary['amount_cents'] / 100, 2, ',', '.') }} MT</strong><small>{{ number_format($expenseSummary['count'], 0, ',', '.') }} {{ $expenseSummary['count'] === 1 ? 'despesa aprovada' : 'despesas aprovadas' }}</small></div>
</div>

@if($previousSummary)
<section class="panel">
    <h2>Comparação com o {{ $previousSummary['label'] }}</h2>
    <p class="muted">Vendas efetivas {{ $selectedPoint ? 'de '.$selectedPoint->name : 'de todos os canais' }}: <strong>{{ number_format($previousRevenue / 100, 2, ',', '.') }} MT</strong> no período anterior e <strong>{{ number_format($currentRevenue / 100, 2, ',', '.') }} MT</strong> no período selecionado.
        @if($revenueChange !== null)<strong>{{ $revenueChange >= 0 ? '+' : '' }}{{ number_format($revenueChange, 1, ',', '.') }}%</strong>.
        @elseif($currentRevenue > 0)Houve vendas no período selecionado; o anterior não teve vendas.
        @else Não houve vendas em nenhum dos dois períodos.@endif
    </p>
</section>
@endif

<section class="panel">
    <h2>Vendas por posto · {{ $pointDaily['label'] }}</h2>
    <p class="muted">Vendas por unidade menos reembolsos feitos nesta data. Um saldo negativo indica que houve mais reembolsos do que vendas no dia. As vendas a grosso aparecem no total geral quando todos os postos estão selecionados.</p>
    <div class="tablewrap"><table>
        <thead><tr><th>Posto de venda</th><th class="num">Vendas</th><th class="num">Botijas líquidas</th><th class="num">Valor líquido</th>@if($canViewSalesProfit)<th class="num">Margem</th>@endif</tr></thead>
        <tbody>@forelse($pointDaily['rows'] as $row)<tr>
            <td><a href="{{ route('sales.history', ['period' => $period['key'], 'date' => $period['date'], 'location_id' => $row['location']->id]) }}"><strong>{{ $row['location']->name }}</strong></a></td>
            <td class="num">{{ number_format($row['orders'], 0, ',', '.') }}</td>
            <td class="num">{{ number_format($row['quantity'], 0, ',', '.') }}</td>
            <td class="num"><strong>{{ number_format($row['revenue_cents'] / 100, 2, ',', '.') }} MT</strong></td>
            @if($canViewSalesProfit)<td class="num">{{ number_format($row['margin_cents'] / 100, 2, ',', '.') }} MT</td>@endif
        </tr>@empty<tr><td colspan="{{ $canViewSalesProfit ? 5 : 4 }}" class="muted">Ainda não existem postos de venda físicos.</td></tr>@endforelse</tbody>
        @if($pointDaily['rows'])<tfoot><tr><th>Total das bancas</th><th class="num">{{ number_format($pointDaily['totals']['orders'], 0, ',', '.') }}</th><th class="num">{{ number_format($pointDaily['totals']['quantity'], 0, ',', '.') }}</th><th class="num">{{ number_format($pointDaily['totals']['revenue_cents'] / 100, 2, ',', '.') }} MT</th>@if($canViewSalesProfit)<th class="num">{{ number_format($pointDaily['totals']['margin_cents'] / 100, 2, ',', '.') }} MT</th>@endif</tr></tfoot>@endif
    </table></div>
</section>

<section class="panel">
    <h2>Produtos por posto · {{ $pointDaily['label'] }}</h2>
    <p class="muted">Botijas e valores mostram vendas menos reembolsos na data de referência. O estoque é o saldo disponível agora, sem as avariadas. Até 5 botijas indica estoque baixo.</p>
    <div class="tablewrap"><table>
        <thead><tr><th>Posto</th><th>Produto</th><th class="num">Botijas líquidas</th><th class="num">Valor líquido</th><th class="num">Estoque disponível</th>@if($canViewSalesProfit)<th class="num">Margem</th>@endif</tr></thead>
        <tbody>@php($hasProducts = false)@foreach($pointDaily['rows'] as $row)@foreach($row['products'] as $product)@php($hasProducts = true)<tr>
            <td>{{ $row['location']->name }}</td><td>{{ $product['label'] }}</td>
            <td class="num">{{ number_format($product['quantity'], 0, ',', '.') }}</td>
            <td class="num">{{ number_format($product['revenue_cents'] / 100, 2, ',', '.') }} MT</td>
            <td class="num">{{ number_format($product['stock'], 0, ',', '.') }}@if($product['stock'] === 0) <small class="muted">Esgotado</small>@elseif($product['stock'] <= 5) <small class="muted">Estoque baixo</small>@endif</td>
            @if($canViewSalesProfit)<td class="num">{{ number_format($product['margin_cents'] / 100, 2, ',', '.') }} MT</td>@endif
        </tr>@endforeach @endforeach
        @unless($hasProducts)<tr><td colspan="{{ $canViewSalesProfit ? 6 : 5 }}" class="muted">Não há produtos vendidos nem estoque nos postos selecionados.</td></tr>@endunless</tbody>
    </table></div>
</section>

<section class="panel">
    <h2>Vendas registadas · {{ $period['label'] }}</h2>
    <p class="muted">A lista abaixo mostra as vendas na data original. Os reembolsos aparecem separadamente pela data em que foram realizados.</p>
    <div class="tablewrap"><table>
        <thead><tr><th>Data</th><th>Tipo</th><th>Cliente</th><th>Produtos</th><th>Destino / posto</th><th class="num">Botijas</th><th class="num">Total</th><th>Registado por</th></tr></thead>
        <tbody>@forelse($orders as $order)
        @php($orderQuantity = $order->items->sum('quantity'))
        @php($orderTotal = $order->items->sum(fn ($item) => $item->quantity * $item->unit_price))
        <tr>
            <td><strong>#{{ str_pad((string) $order->items->min('id'), 6, '0', STR_PAD_LEFT) }}</strong><small class="sale-date">{{ $order->sold_at->format('d/m/Y H:i') }}</small></td>
            <td><span class="pill">{{ $order->channel === 'wholesale' ? 'Grosso' : 'Unidade' }}</span></td>
            <td><strong>{{ $order->customer?->name ?? 'Cliente genérico' }}</strong>@if($order->customer?->nuit)<small class="sale-date">NUIT {{ $order->customer->nuit }}</small>@endif</td>
            <td><div class="sale-lines">@foreach($order->items as $item)<div><strong>{{ $item->product->label }}</strong><small>{{ number_format($item->quantity, 0, ',', '.') }} × {{ number_format($item->unit_price, 2, ',', '.') }} MT</small></div>@endforeach</div></td>
            <td>@if($order->channel === 'wholesale'){{ $order->truck_plate }} · {{ $order->driver_name }}@else{{ $order->location?->name ?? '—' }}@endif</td>
            <td class="num">{{ number_format($orderQuantity, 0, ',', '.') }}</td>
            <td class="num"><strong>{{ number_format($orderTotal, 2, ',', '.') }} MT</strong></td>
            <td>{{ $order->createdBy?->name ?? '—' }}</td>
        </tr>@empty<tr><td colspan="8" class="muted">Não há vendas efetivas neste período.</td></tr>@endforelse</tbody>
    </table></div>
    @if($orders->hasPages())<nav class="history-pages" aria-label="Páginas das vendas">@if($orders->previousPageUrl())<a href="{{ $orders->previousPageUrl() }}">← Anterior</a>@endif<span>Página {{ $orders->currentPage() }} de {{ $orders->lastPage() }}</span>@if($orders->nextPageUrl())<a href="{{ $orders->nextPageUrl() }}">Próxima →</a>@endif</nav>@endif
</section>

<section class="panel">
    <h2>Reembolsos realizados · {{ $period['label'] }}</h2>
    <div class="tablewrap"><table>
        <thead><tr><th>Data</th><th>Venda original</th><th>Produto</th><th>Posto</th><th class="num">Botijas</th><th class="num">Valor devolvido</th><th>Motivo</th><th>Registado por</th></tr></thead>
        <tbody>@forelse($refunds as $refund)<tr>
            <td>{{ $refund->created_at->format('d/m/Y H:i') }}</td>
            <td>#{{ str_pad((string) $refund->product_sale_id, 6, '0', STR_PAD_LEFT) }}</td>
            <td>{{ $refund->saleItem?->product?->label ?? 'Produto removido' }}</td>
            <td>{{ $refund->location?->name ?? 'Armazém central' }}</td>
            <td class="num">{{ number_format($refund->quantity, 0, ',', '.') }}</td>
            <td class="num">{{ number_format($refund->refund_cents / 100, 2, ',', '.') }} MT</td>
            <td>{{ $refund->reason }}</td><td>{{ $refund->createdBy?->name ?? '—' }}</td>
        </tr>@empty<tr><td colspan="8" class="muted">Não há reembolsos neste período.</td></tr>@endforelse</tbody>
    </table></div>
    @if($refunds->hasPages()){{ $refunds->links() }}@endif
</section>
@endsection
