@extends('layouts.app')
@section('title', 'Botijas avariadas')
@section('content')
<span class="section-eyebrow">Vendas de botijas</span>
<h2 class="page-title">Botijas avariadas</h2>
<p class="page-subtitle">Registe botijas com problemas e acompanhe as trocas feitas pelo fornecedor. As avariadas saem do estoque disponível e ficam registadas à parte até à reposição.</p>

<div class="damage-summary">
    <article><span>Total avariado</span><strong>{{ number_format($centralDamaged + $standDamaged, 0, ',', '.') }}</strong><small>Botijas indisponíveis</small></article>
    @if($canManage)<article><span>No armazém central</span><strong>{{ number_format($centralDamaged, 0, ',', '.') }}</strong><small>Aguardam reposição</small></article>@endif
    <article><span>Nas bancas</span><strong>{{ number_format($standDamaged, 0, ',', '.') }}</strong><small>Em locais de venda</small></article>
</div>

<div class="damage-forms">
    <section class="panel">
        <h2>Registar avaria</h2>
        <p class="muted">Para uma botija já vendida e devolvida pelo cliente, registe a troca ou o reembolso na venda correspondente.</p>
        <form method="post" action="{{ route('damages.store') }}">@csrf
            <div class="fields">
                <label>Tipo de botija<select name="product_id" required>
                    <option value="">Selecione o produto</option>
                    @foreach($products as $product)<option value="{{ $product->id }}" @selected(old('product_id') == $product->id)>{{ $product->label }}</option>@endforeach
                </select></label>
                @if($canManage)<label>Estoque a descontar<select name="location_id">
                    <option value="">Armazém central</option>
                    @foreach($stands as $stand)<option value="{{ $stand->id }}" @selected(old('location_id') == $stand->id)>{{ $stand->name }}</option>@endforeach
                </select></label>@endif
                <label>Quantidade avariada<input type="number" name="quantity" min="1" max="1000000000" value="{{ old('quantity') }}" required></label>
                <label>Motivo da avaria<input name="reason" maxlength="500" value="{{ old('reason') }}" required></label>
            </div>
            <p class="muted">A quantidade será retirada do estoque disponível do local indicado. Só pode registar avarias de botijas já existentes no estoque.</p>
            <button type="submit" @disabled($products->isEmpty() || (! $canManage && $stands->isEmpty()))>Registar avaria</button>
        </form>
    </section>

    @if($canManage)
    <section class="panel">
        <h2>Registar troca do fornecedor</h2>
        <p class="muted">Troque até à quantidade avariada. Quando a reposição de uma banca chegar ao armazém, o saldo disponível passa para o armazém.</p>
        <form method="post" action="{{ route('damages.replacements.store') }}">@csrf
            <div class="fields">
                <label>Tipo de botija<select name="product_id" required>
                    <option value="">Selecione o produto</option>
                    @foreach($products as $product)<option value="{{ $product->id }}" @selected(old('product_id') == $product->id)>{{ $product->label }}</option>@endforeach
                </select></label>
                <label>Origem das avariadas<select name="location_id">
                    <option value="">Armazém central</option>
                    @foreach($stands as $stand)<option value="{{ $stand->id }}" @selected(old('location_id') == $stand->id)>{{ $stand->name }}</option>@endforeach
                </select></label>
                <label>Quantidade substituída<input type="number" name="quantity" min="1" max="1000000000" required></label>
                <label>Onde as boas foram recebidas<select name="destination" required>
                    <option value="central">Armazém central</option>
                    <option value="same">Mesmo local das avariadas</option>
                </select></label>
                <label>Fornecedor<input name="supplier_name" maxlength="150" required></label>
                <label>Nº da guia ou referência <span class="muted">(opcional)</span><input name="reference" maxlength="150" placeholder="Ex.: GUIA-42"></label>
            </div>
            <button type="submit" @disabled($damageBalances->isEmpty())>Confirmar troca</button>
        </form>
    </section>
    @endif
</div>

<section class="panel">
    <h2>Botijas avariadas por local</h2>
    <div class="tablewrap"><table>
        <thead><tr><th>Produto</th><th>Local</th><th class="num">Avariadas</th></tr></thead>
        <tbody>@forelse($damageBalances as $row)<tr>
            <td><strong>{{ $row['product']->label }}</strong></td>
            <td>{{ $row['location']?->name ?? 'Armazém central' }}</td>
            <td class="num">{{ number_format($row['quantity'], 0, ',', '.') }}</td>
        </tr>@empty<tr><td colspan="3" class="muted">Não há botijas avariadas neste momento.</td></tr>@endforelse</tbody>
    </table></div>
</section>

<section class="panel">
    <h2>Histórico de avarias, trocas e reembolsos</h2>
    <div class="tablewrap"><table>
        <thead><tr><th>Data</th><th>Produto</th><th>Origem</th><th>Ocorrência</th><th>Destino da reposição</th><th class="num">Botijas</th><th class="num">Reembolso</th><th>Motivo / fornecedor</th><th>Registado por</th></tr></thead>
        <tbody>@forelse($events as $event)<tr>
            <td>{{ $event->created_at->format('d/m/Y H:i') }}</td>
            <td>{{ $event->product?->label ?? 'Produto removido' }}</td>
            <td>{{ $event->location?->name ?? 'Armazém central' }}</td>
            <td>{{ ['receipt' => 'Avaria na entrada', 'detected' => 'Avaria detetada', 'exchange' => 'Troca ao cliente', 'refund' => 'Reembolso', 'supplier_replacement' => 'Troca do fornecedor'][$event->kind] ?? $event->kind }}@if($event->saleItem) <small class="sale-date">Venda #{{ str_pad((string) $event->saleItem->id, 6, '0', STR_PAD_LEFT) }}</small>@endif</td>
            <td>{{ $event->kind === 'supplier_replacement' ? ($event->receivedLocation?->name ?? 'Armazém central') : '—' }}</td>
            <td class="num">{{ number_format($event->quantity, 0, ',', '.') }}</td>
            <td class="num">{{ $event->refund_cents ? number_format($event->refund_cents / 100, 2, ',', '.').' MT' : '—' }}</td>
            <td>{{ $event->supplier_name ?: $event->reason }}@if($event->reference)<small class="sale-date">Ref.: {{ $event->reference }}</small>@endif</td>
            <td>{{ $event->createdBy?->name ?? '—' }}</td>
        </tr>@empty<tr><td colspan="9" class="muted">Ainda não há ocorrências registadas.</td></tr>@endforelse</tbody>
    </table></div>
    @if($events->hasPages()){{ $events->links() }}@endif
</section>
@endsection
