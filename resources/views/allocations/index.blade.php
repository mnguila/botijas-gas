@extends('layouts.app')
@section('title', 'Alocar às bancas')
@section('content')
<div class="operations-heading">
    <div><span class="section-eyebrow">Distribuição</span><h2 class="page-title">Alocar botijas às bancas</h2><p class="page-subtitle">A alocação move botijas do estoque central para uma banca. O total de botijas permanece igual.</p></div>
    @if(auth()->user()->hasPermission('sales.create'))<a class="small-link" href="{{ route('sales.index') }}">Ver vendas →</a>@endif
</div>
@if(collect(['allocations.request', 'allocations.approve', 'allocations.dispatch', 'allocations.receive'])->contains(fn ($permission) => auth()->user()->hasPermission($permission)))<p><a class="small-link" href="{{ route('allocation-requests.index') }}">Pedidos, guia de saída e confirmação de entrega →</a></p>@endif

@if(auth()->user()->is_super_admin)
<section class="panel">
    <h2>Alocação direta · administrador</h2>
    @if($allocatableProducts->isEmpty() || $activeStands->isEmpty())
        <p class="muted">Cadastre uma banca e defina o custo e os dois preços de venda de pelo menos um produto antes de fazer uma alocação.</p>
    @else
        <form method="post" action="{{ route('allocations.store') }}">@csrf
            <div class="fields three">
                <label>Produto<select name="product_id" required>@foreach($allocatableProducts as $product)<option value="{{ $product->id }}" @selected(old('product_id') == $product->id)>{{ $product->label }} · central: {{ number_format($centralStock[$product->id] ?? 0, 0, ',', '.') }}</option>@endforeach</select></label>
                <label>Banca ou posto<select name="location_id" required>@foreach($activeStands as $stand)<option value="{{ $stand->id }}" @selected(old('location_id') == $stand->id)>{{ $stand->name }}</option>@endforeach</select></label>
                <label>Quantidade a enviar<input type="number" name="quantity" min="1" max="1000000000" value="{{ old('quantity') }}" required></label>
                <label class="field-wide">Observação <span class="muted">(opcional)</span><input name="note" maxlength="255" value="{{ old('note') }}" placeholder="Referência ou responsável pela entrega"></label>
            </div>
            <button type="submit">Alocar botijas</button>
        </form>
    @endif
</section>
@endif

<section class="panel">
    <h2>Estoque por local</h2>
    <div class="tablewrap"><table>
        <thead><tr><th>Produto</th><th class="num">Estoque central</th><th class="num">Em trânsito</th>@foreach($stands as $stand)<th class="num">{{ $stand->name }}@unless($stand->is_active) (inativo)@endunless</th>@endforeach<th class="num">Total disponível</th></tr></thead>
        <tbody>@forelse($products as $product)<tr>
            <td><strong>{{ $product->label }}</strong>
                @unless($product->isReadyForSale())
                    <br><small class="muted">Pendente de preços · não pode ser alocado</small>
                @endunless
            </td>
            <td class="num">{{ number_format($centralStock[$product->id] ?? 0, 0, ',', '.') }} disponíveis @if($centralDamaged[$product->id] > 0)<small class="sale-date">{{ number_format($centralDamaged[$product->id], 0, ',', '.') }} avariadas</small>@endif</td>
            <td class="num">{{ number_format($inTransitStock[$product->id] ?? 0, 0, ',', '.') }}</td>
            @foreach($stands as $stand)@php($physical = (int) ($standStock->get($product->id)?->get($stand->id) ?? 0))@php($damaged = (int) ($standDamaged->get($product->id)?->get($stand->id) ?? 0))<td class="num">{{ number_format($physical - $damaged, 0, ',', '.') }} disponíveis @if($damaged > 0)<small class="sale-date">{{ number_format($damaged, 0, ',', '.') }} avariadas</small>@endif</td>@endforeach
            <td class="num"><strong>{{ number_format($product->quantity - $centralDamaged[$product->id] - ($standDamaged->get($product->id)?->sum() ?? 0), 0, ',', '.') }}</strong></td>
        </tr>@empty<tr><td colspan="{{ $stands->count() + 4 }}" class="muted">Ainda não há produtos.</td></tr>@endforelse</tbody>
        <tfoot><tr class="allocation-totals">
            <th scope="row">Total por local</th>
            <th class="num">{{ number_format($centralTotal, 0, ',', '.') }}</th>
            <th class="num">{{ number_format($inTransitTotal, 0, ',', '.') }}</th>
            @foreach($stands as $stand)
                <th class="num">{{ number_format($standTotals[$stand->id] ?? 0, 0, ',', '.') }}</th>
            @endforeach
            <th class="num">{{ number_format($overallTotal, 0, ',', '.') }}</th>
        </tr></tfoot>
    </table></div>
</section>

<section class="panel">
    <h2>Alocações recentes</h2>
    <div class="tablewrap"><table>
        <thead><tr><th>Data</th><th>Produto</th><th>Banca</th><th class="num">Quantidade</th><th>Registado por</th><th>Observação</th></tr></thead>
        <tbody>@forelse($allocations as $allocation)<tr>
            <td>{{ $allocation->created_at->format('d/m/Y H:i') }}</td>
            <td>{{ $allocation->product->label }}</td>
            <td>{{ $allocation->location->name }}</td>
            <td class="num">{{ number_format($allocation->quantity, 0, ',', '.') }}</td>
            <td>{{ $allocation->createdBy?->name ?? '—' }}</td>
            <td>{{ $allocation->note ?? '—' }}</td>
        </tr>@empty<tr><td colspan="6" class="muted">Ainda não há alocações.</td></tr>@endforelse</tbody>
    </table></div>
    @if($allocations->hasPages())<nav class="history-pages" aria-label="Páginas das alocações">@if($allocations->previousPageUrl())<a href="{{ $allocations->previousPageUrl() }}">← Anterior</a>@endif<span>Página {{ $allocations->currentPage() }} de {{ $allocations->lastPage() }}</span>@if($allocations->nextPageUrl())<a href="{{ $allocations->nextPageUrl() }}">Próxima →</a>@endif</nav>@endif
</section>
@endsection
