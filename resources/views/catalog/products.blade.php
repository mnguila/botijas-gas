@extends('layouts.app')
@section('title', 'Produtos')
@section('content')
<span class="section-eyebrow">Catálogo</span>
<h2 class="page-title">Produtos</h2>
<p class="page-subtitle">Registe as botijas recebidas e indique quantas chegaram avariadas. Estas ficam no estoque físico, mas indisponíveis para venda.</p>

<section class="panel">
    <h2>Adicionar produto</h2>
    <form method="post" action="{{ route('products.store') }}">@csrf
        <div class="fields three">
            <label>Marca<input name="brand" maxlength="100" value="{{ old('brand') }}" ></label>
            <label>Peso (kg)<input type="number" name="weight_kg" min="1" max="999" value="{{ old('weight_kg') }}" ></label>
            <label>Quantidade inicial de botijas<input type="number" name="quantity" min="0" max="1000000000" value="{{ old('quantity', 0) }}" ></label>
            <label>Inicialmente avariadas<input type="number" name="damaged_quantity" min="0" max="1000000000" value="{{ old('damaged_quantity', 0) }}"></label>
            <label>Custo fornecedor (MT)<input type="number" name="purchase_price" step="0.01" min="0" value="{{ old('purchase_price') }}" ></label>
            <label>Preço revendedor (MT) <span class="muted">(opcional)</span><input type="number" name="reseller_price" step="0.01" min="0" value="{{ old('reseller_price') }}"></label>
            <label>Preço unidade (MT) <span class="muted">(opcional)</span><input type="number" name="unit_price" step="0.01" min="0" value="{{ old('unit_price') }}"></label>
            <label class="field-wide">Descrição do produto <span class="muted">(opcional)</span><textarea name="description" maxlength="1000" rows="3" placeholder="Ex.: Botija de gás doméstico com 9 kg">{{ old('description') }}</textarea></label>
        </div>
        <p class="muted">O produto só poderá ser vendido ou enviado às bancas quando o custo e os dois preços de venda estiverem definidos.</p>
        <button type="submit">Adicionar produto</button>
    </form>
</section>

<section class="panel">
    <h2>Produtos registados</h2>
    <div class="tablewrap"><table>
        <thead><tr><th>Produto</th><th>Descrição</th><th class="num">stock atual</th><th class="num">Custo fornecedor</th><th class="num">Revendedor</th><th class="num">Unidade</th><th>Adicionar estoque</th><th>Ações</th></tr></thead>
        <tbody>@forelse($products as $product)<tr>
            <td><strong>{{ $product->label }}</strong>
                @unless($product->isReadyForSale())
                    <br><small class="muted">Pendente de preços</small>
                @endunless
            </td>
            <td class="product-description">{{ $product->description ?: '—' }}</td>
            <td class="num">{{ number_format($product->quantity, 0, ',', '.') }} físico<small class="sale-date">{{ number_format($centralAvailable[$product->id], 0, ',', '.') }} disponíveis no armazém · {{ number_format($damagedTotals[$product->id], 0, ',', '.') }} avariadas</small></td>
            <td class="num">{{ number_format($product->purchase_price, 2, ',', '.') }} MT</td>
            <td class="num">{{ (float) $product->reseller_price > 0 ? number_format($product->reseller_price, 2, ',', '.').' MT' : 'Por definir' }}</td>
            <td class="num">{{ (float) $product->unit_price > 0 ? number_format($product->unit_price, 2, ',', '.').' MT' : 'Por definir' }}</td>
            <td><form class="stock-add-form" method="post" action="{{ route('products.stock.add', $product) }}">@csrf
                <input aria-label="Quantidade a adicionar para {{ $product->label }}" type="number" name="quantity_added" min="1" max="1000000000" placeholder="Quantidade nova" required>
                <input aria-label="Avariadas na entrada de {{ $product->label }}" type="number" name="damaged_quantity_added" min="0" max="1000000000" placeholder="Avariadas" value="0">
                <input aria-label="Observação para {{ $product->label }}" name="note" maxlength="255" placeholder="Observação (opcional)">
                <button class="smallbtn" type="submit">Somar</button>
            </form></td>
            <td><div class="table-actions"><a class="small-link" href="{{ route('products.edit', $product) }}">Editar</a><form method="post" action="{{ route('products.destroy', $product) }}" onsubmit="return confirm('Apagar {{ $product->label }}? O produto ficará guardado no histórico.');">@csrf @method('DELETE')<button class="smallbtn danger" type="submit">Apagar</button></form></div><details class="damage-inline"><summary>Registar avaria no armazém</summary><form method="post" action="{{ route('products.damage.store', $product) }}">@csrf<label>Quantidade<input type="number" name="quantity" min="1" max="{{ max(1, $centralAvailable[$product->id]) }}" required></label><label>Motivo<input name="reason" maxlength="500" required></label><button class="smallbtn" type="submit" @disabled($centralAvailable[$product->id] < 1)>Registar</button></form></details></td>
        </tr>@empty<tr><td colspan="8" class="muted">Ainda não há produtos.</td></tr>@endforelse</tbody>
    </table></div>
</section>

<section class="panel">
    <h2>Histórico de entradas e saldo inicial</h2>
    <div class="tablewrap"><table>
        <thead><tr><th>Data</th><th>Produto</th><th class="num">Quantidade adicionada</th><th class="num">Avariadas na entrada</th><th class="num">Stock anterior</th><th class="num">Stock depois</th><th>Registado por</th><th>Observação</th></tr></thead>
        <tbody>@forelse($stockEntries as $entry)<tr>
            <td>{{ $entry->created_at->format('d/m/Y H:i') }}</td>
            <td>{{ $entry->product->label }}@if($entry->product->trashed()) <span class="pill">Arquivado</span>@endif</td>
            <td class="num">+{{ number_format($entry->quantity_added, 0, ',', '.') }}</td>
            <td class="num">{{ number_format($entry->damaged_quantity_added, 0, ',', '.') }}</td>
            <td class="num">{{ number_format($entry->quantity_before, 0, ',', '.') }}</td>
            <td class="num">{{ number_format($entry->quantity_after, 0, ',', '.') }}</td>
            <td>{{ $entry->createdBy?->name ?? '—' }}</td>
            <td>{{ $entry->note ?? ($entry->source === 'initial' ? 'Estoque inicial' : '—') }}</td>
        </tr>@empty<tr><td colspan="8" class="muted">Ainda não há entradas registadas.</td></tr>@endforelse</tbody>
    </table></div>
    @if($stockEntries->hasPages())
        <nav class="history-pages" aria-label="Páginas do histórico">
            @if($stockEntries->previousPageUrl())<a href="{{ $stockEntries->previousPageUrl() }}">← Anterior</a>@endif
            <span>Página {{ $stockEntries->currentPage() }} de {{ $stockEntries->lastPage() }}</span>
            @if($stockEntries->nextPageUrl())<a href="{{ $stockEntries->nextPageUrl() }}">Próxima →</a>@endif
        </nav>
    @endif
</section>

<section class="panel">
    <h2>Histórico de avarias, trocas e reembolsos</h2>
    <div class="tablewrap"><table>
        <thead><tr><th>Data</th><th>Produto</th><th>Local</th><th>Ocorrência</th><th class="num">Botijas</th><th class="num">Reembolso</th><th>Motivo</th><th>Registado por</th></tr></thead>
        <tbody>@forelse($damageEvents as $event)<tr>
            <td>{{ $event->created_at->format('d/m/Y H:i') }}</td><td>{{ $event->product?->label ?? 'Produto removido' }}</td><td>{{ $event->location?->name ?? 'Armazém central' }}</td>
            <td>{{ ['receipt' => 'Avaria na entrada', 'detected' => 'Avaria detetada', 'exchange' => 'Troca', 'refund' => 'Reembolso'][$event->kind] ?? $event->kind }}@if($event->saleItem) <small class="sale-date">Venda #{{ str_pad((string) $event->saleItem->id, 6, '0', STR_PAD_LEFT) }}</small>@endif</td>
            <td class="num">{{ number_format($event->quantity, 0, ',', '.') }}</td><td class="num">{{ $event->refund_cents ? number_format($event->refund_cents / 100, 2, ',', '.').' MT' : '—' }}</td><td>{{ $event->reason }}</td><td>{{ $event->createdBy?->name ?? '—' }}</td>
        </tr>@empty<tr><td colspan="8" class="muted">Ainda não há avarias registadas.</td></tr>@endforelse</tbody>
    </table></div>
    @if($damageEvents->hasPages()){{ $damageEvents->links() }}@endif
</section>

<section class="panel">
    <h2>Histórico de produtos apagados</h2>
    <div class="tablewrap"><table>
        <thead><tr><th>Produto</th><th>Descrição</th><th class="num">Quantidade</th><th class="num">Custo fornecedor</th><th class="num">Revendedor</th><th class="num">Unidade</th><th>Apagado em</th><th>Ação</th></tr></thead>
        <tbody>@forelse($archivedProducts as $product)<tr>
            <td><strong>{{ $product->label }}</strong></td>
            <td class="product-description">{{ $product->description ?: '—' }}</td>
            <td class="num">{{ number_format($product->quantity, 0, ',', '.') }}</td>
            <td class="num">{{ number_format($product->purchase_price, 2, ',', '.') }} MT</td>
            <td class="num">{{ number_format($product->reseller_price, 2, ',', '.') }} MT</td>
            <td class="num">{{ number_format($product->unit_price, 2, ',', '.') }} MT</td>
            <td>{{ $product->deleted_at->format('d/m/Y H:i') }}</td>
            <td><form method="post" action="{{ route('products.restore', $product->id) }}">@csrf @method('PATCH')<button class="smallbtn" type="submit">Restaurar</button></form></td>
        </tr>@empty<tr><td colspan="8" class="muted">Nenhum produto apagado.</td></tr>@endforelse</tbody>
    </table></div>
</section>
@endsection
