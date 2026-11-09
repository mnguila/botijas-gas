@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')
<div class="dashboard-hero">
    <div>
        <span class="dashboard-eyebrow">Visão geral do negócio</span>
        <h2>Investimento em produtos</h2>
        <p>Veja as botijas registadas e o capital investido em cada produto.</p>
    </div>
    <a class="dashboard-action" href="{{ route('products.index') }}">Gerir produtos <span aria-hidden="true">→</span></a>
</div>

<div class="dashboard-metrics">
    <div class="dashboard-metric featured">
        <span class="metric-label">Investimento total</span>
        <strong>{{ number_format($totalInvestmentCents / 100, 2, ',', '.') }} <small>MT</small></strong>
        <span class="metric-help">Inclui o investimento nas botijas avariadas</span>
    </div>
    <div class="dashboard-metric">
        <span class="metric-label">Botijas disponíveis</span>
        <strong>{{ number_format($totalUnits, 0, ',', '.') }}</strong>
        <span class="metric-help">Sem as avariadas</span>
    </div>
    <div class="dashboard-metric">
        <span class="metric-label">Tipos de produto</span>
        <strong>{{ number_format($productCount, 0, ',', '.') }}</strong>
        <span class="metric-help">Produtos ativos no cadastro</span>
    </div>
    <div class="dashboard-metric">
        <span class="metric-label">Produtos com estoque</span>
        <strong>{{ number_format($productsWithStock, 0, ',', '.') }} <small>/ {{ number_format($productCount, 0, ',', '.') }}</small></strong>
        <span class="metric-help">Com unidades disponíveis</span>
    </div>
    <div class="dashboard-metric">
        <span class="metric-label">Botijas avariadas</span>
        <strong>{{ number_format($damagedUnits, 0, ',', '.') }}</strong>
        <span class="metric-help">{{ number_format($damagedInvestmentCents / 100, 2, ',', '.') }} MT de investimento indisponível</span>
    </div>
</div>

@if($unpricedCount > 0)
    <div class="dashboard-warning" role="note">
        <strong>{{ $unpricedCount }} {{ $unpricedCount === 1 ? 'produto tem' : 'produtos têm' }} botijas sem custo do fornecedor.</strong>
        <span>Preencha o custo na página Produtos para que o investimento total reflita essas unidades.</span>
    </div>
@endif

@if($canViewSalesProfit)
<section class="dashboard-profit-section" aria-labelledby="dashboard-profit-title">
    <div class="dashboard-panel-heading"><div><span class="dashboard-kicker">Projeção</span><h3 id="dashboard-profit-title">Lucro potencial do estoque</h3></div><span class="dashboard-hint">{{ number_format($projectedUnits, 0, ',', '.') }} botijas com preços completos</span></div>
    <div class="dashboard-profit-grid">
        <div class="dashboard-profit-card">
            <span>Ao preço de revendedor</span>
            <strong>{{ number_format($resellerProfitCents / 100, 2, ',', '.') }} <small>MT</small></strong>
            <p>Se todas as {{ number_format($projectedUnits, 0, ',', '.') }} botijas forem vendidas a revendedor.</p>
        </div>
        <div class="dashboard-profit-card dashboard-profit-card-unit">
            <span>Ao preço por unidade</span>
            <strong>{{ number_format($unitProfitCents / 100, 2, ',', '.') }} <small>MT</small></strong>
            <p>Se as mesmas botijas forem vendidas por unidade.</p>
        </div>
    </div>
    <p class="muted">Cada valor é um cenário separado: (preço de venda − custo do fornecedor) × botijas disponíveis. Avariadas, em trânsito e produtos sem preços completos ficam fora da projeção.</p>
</section>
@endif

<div class="dashboard-grid">
    <section class="dashboard-panel">
        <div class="dashboard-panel-heading">
            <div><span class="dashboard-kicker">Destaques</span><h3>Maiores investimentos</h3></div>
            <span class="dashboard-hint">Até 6 produtos</span>
        </div>
        @forelse($topRows as $row)
            @php($product = $row['product'])
            <div class="investment-item">
                <div class="investment-item-heading"><strong>{{ $product->label }}</strong><span>{{ number_format($row['investment_cents'] / 100, 2, ',', '.') }} MT</span></div>
                <div class="investment-track"><div class="investment-fill" style="width: {{ $highestInvestmentCents > 0 ? round($row['investment_cents'] / $highestInvestmentCents * 100, 1) : 0 }}%"></div></div>
                <small>{{ number_format($product->quantity, 0, ',', '.') }} botijas físicas · {{ $totalInvestmentCents > 0 ? number_format($row['investment_cents'] / $totalInvestmentCents * 100, 1, ',', '.') : '0,0' }}% do investimento</small>
            </div>
        @empty
            <div class="dashboard-empty">Ainda não há produtos com quantidade e custo do fornecedor registados.</div>
        @endforelse
    </section>

    <section class="dashboard-panel dashboard-purchased" aria-labelledby="top-purchased-title">
        <div class="dashboard-panel-heading"><div><span class="dashboard-kicker">Procura dos clientes</span><h3 id="top-purchased-title">Botijas mais compradas</h3></div></div>
        @if($canViewSalesHistory)
            <form method="GET" action="{{ route('dashboard.index') }}" class="dashboard-month-filter">
                <label for="dashboard-month">Mês</label>
                <input id="dashboard-month" type="month" name="month" value="{{ $month }}">
                <button type="submit">Ver</button>
            </form>
            @if($topPurchased->isNotEmpty())
                <div class="purchased-legend"><span><i class="purchased-key wholesale"></i> A grosso</span><span><i class="purchased-key unit"></i> Por unidade</span></div>
                <div class="purchased-chart">
                    @foreach($topPurchased as $row)
                        <div class="purchased-row">
                            <div class="purchased-heading"><strong>{{ $row['product']->label }}</strong><span>{{ number_format($row['total'], 0, ',', '.') }} botijas</span></div>
                            <div class="purchased-track" role="img" aria-label="{{ $row['product']->label }}: {{ $row['wholesale'] }} a grosso e {{ $row['unit'] }} por unidade">
                                <span class="purchased-bar wholesale" style="width: {{ $highestPurchased > 0 ? round($row['wholesale'] / $highestPurchased * 100, 2) : 0 }}%"></span>
                                <span class="purchased-bar unit" style="width: {{ $highestPurchased > 0 ? round($row['unit'] / $highestPurchased * 100, 2) : 0 }}%"></span>
                            </div>
                            <small>{{ number_format($row['wholesale'], 0, ',', '.') }} a grosso · {{ number_format($row['unit'], 0, ',', '.') }} por unidade</small>
                        </div>
                    @endforeach
                </div>
                <p class="muted">Quantidade vendida no mês, descontando cancelamentos e reembolsos realizados nesse mês.</p>
            @else
                <div class="dashboard-empty">Sem vendas de botijas para este mês.</div>
            @endif
        @else
            <div class="dashboard-empty">O gráfico está disponível para utilizadores com acesso ao histórico geral de vendas.</div>
        @endif
    </section>
</div>

@if($salesMetrics)
<section class="dashboard-panel dashboard-sales-panel">
    <div class="dashboard-panel-heading"><div><span class="dashboard-kicker">Vendas</span><h3>Grosso e unidade</h3></div>@if(auth()->user()->hasPermission('sales.create'))<a class="small-link" href="{{ route('sales.index') }}">Registar venda →</a>@endif</div>
    <div class="tablewrap"><table class="dashboard-table">
        <thead><tr>
            <th>Tipo</th><th class="num">Botijas vendidas</th><th class="num">Total de vendas</th>
            @if($canViewSalesProfit)
                <th class="num">Margem</th>
            @endif
            <th class="num">Vendas de hoje</th>
            @if($canViewSalesProfit)
                <th class="num">Margem de hoje</th>
            @endif
        </tr></thead>
        <tbody>@foreach(['wholesale' => 'A grosso · camiões', 'unit' => 'Por unidade · bancas'] as $channel => $label)<tr>
            <td><strong>{{ $label }}</strong></td>
            <td class="num">{{ number_format($salesMetrics[$channel]['quantity'], 0, ',', '.') }}</td>
            <td class="num investment-value">{{ number_format($salesMetrics[$channel]['revenue_cents'] / 100, 2, ',', '.') }} MT</td>
            @if($canViewSalesProfit)
                <td class="num">{{ number_format($salesMetrics[$channel]['margin_cents'] / 100, 2, ',', '.') }} MT</td>
            @endif
            <td class="num">{{ number_format($salesMetrics[$channel]['today_revenue_cents'] / 100, 2, ',', '.') }} MT</td>
            @if($canViewSalesProfit)
                <td class="num">{{ number_format($salesMetrics[$channel]['today_margin_cents'] / 100, 2, ',', '.') }} MT</td>
            @endif
        </tr>@endforeach</tbody>
        <tfoot><tr>
            <th>Total</th>
            <th class="num">{{ number_format($salesMetrics['wholesale']['quantity'] + $salesMetrics['unit']['quantity'], 0, ',', '.') }}</th>
            <th class="num">{{ number_format(($salesMetrics['wholesale']['revenue_cents'] + $salesMetrics['unit']['revenue_cents']) / 100, 2, ',', '.') }} MT</th>
            @if($canViewSalesProfit)
                <th class="num">{{ number_format(($salesMetrics['wholesale']['margin_cents'] + $salesMetrics['unit']['margin_cents']) / 100, 2, ',', '.') }} MT</th>
            @endif
            <th class="num">{{ number_format(($salesMetrics['wholesale']['today_revenue_cents'] + $salesMetrics['unit']['today_revenue_cents']) / 100, 2, ',', '.') }} MT</th>
            @if($canViewSalesProfit)
                <th class="num">{{ number_format(($salesMetrics['wholesale']['today_margin_cents'] + $salesMetrics['unit']['today_margin_cents']) / 100, 2, ',', '.') }} MT</th>
            @endif
        </tr></tfoot>
    </table></div>
    @if($canViewSalesProfit)
        <p class="muted">A margem usa o preço de venda e o custo do fornecedor guardados no momento de cada venda.</p>
    @endif
</section>
@endif

<section class="dashboard-panel dashboard-table-panel">
    <div class="dashboard-panel-heading"><div><span class="dashboard-kicker">Detalhamento</span><h3>Investimento por produto</h3></div><span class="dashboard-hint">{{ number_format($productCount, 0, ',', '.') }} produtos ativos</span></div>
    <div class="tablewrap"><table class="dashboard-table">
        <thead><tr><th>Produto</th><th class="num">Disponíveis</th><th class="num">Custo fornecedor</th><th class="num">Investimento físico</th>@if($canViewSalesProfit)<th class="num">Lucro revendedor</th><th class="num">Lucro unidade</th>@endif<th class="num">Parte do total</th></tr></thead>
        <tbody>@forelse($rows as $row)
            @php($product = $row['product'])
            <tr>
                <td><strong>{{ $product->label }}</strong>@if($product->quantity > 0 && (float) $product->purchase_price <= 0) <span class="cost-warning">Sem custo</span>@endif</td>
                <td class="num">{{ number_format($row['available_quantity'], 0, ',', '.') }}@if($row['damaged_quantity'] > 0)<small>{{ number_format($row['damaged_quantity'], 0, ',', '.') }} avariadas à parte</small>@endif</td>
                <td class="num">{{ number_format($product->purchase_price, 2, ',', '.') }} MT</td>
                <td class="num investment-value">{{ number_format($row['investment_cents'] / 100, 2, ',', '.') }} MT</td>
                @if($canViewSalesProfit)
                    <td class="num dashboard-profit-value">@if($row['reseller_profit_cents'] !== null){{ number_format($row['reseller_profit_cents'] / 100, 2, ',', '.') }} MT<small>Preço {{ number_format($product->reseller_price, 2, ',', '.') }} MT</small>@else — @endif</td>
                    <td class="num dashboard-profit-value">@if($row['unit_profit_cents'] !== null){{ number_format($row['unit_profit_cents'] / 100, 2, ',', '.') }} MT<small>Preço {{ number_format($product->unit_price, 2, ',', '.') }} MT</small>@else — @endif</td>
                @endif
                <td class="num">{{ $totalInvestmentCents > 0 ? number_format($row['investment_cents'] / $totalInvestmentCents * 100, 1, ',', '.') : '0,0' }}%</td>
            </tr>
        @empty<tr><td colspan="{{ $canViewSalesProfit ? 7 : 5 }}" class="dashboard-empty">Ainda não há produtos registados.</td></tr>@endforelse</tbody>
        <tfoot><tr><th>Total</th><th class="num">{{ number_format($totalUnits, 0, ',', '.') }}</th><th></th><th class="num">{{ number_format($totalInvestmentCents / 100, 2, ',', '.') }} MT</th>@if($canViewSalesProfit)<th class="num">{{ number_format($resellerProfitCents / 100, 2, ',', '.') }} MT</th><th class="num">{{ number_format($unitProfitCents / 100, 2, ',', '.') }} MT</th>@endif<th class="num">{{ $totalInvestmentCents > 0 ? '100,0' : '0,0' }}%</th></tr></tfoot>
    </table></div>
</section>
@endsection
