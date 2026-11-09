@extends('layouts.app')
@section('title', 'Produtos')
@section('content')
<span class="section-eyebrow">Catálogo</span>
<h2 class="page-title">Produtos</h2>
<p class="page-subtitle">Registe produtos e entradas de estoque. As botijas com problemas são acompanhadas no menu Botijas avariadas.</p>

<section class="panel">
    <h2>Adicionar produto</h2>
    <form method="post" action="{{ route('products.store') }}">@csrf
        <div class="fields three">
            <label>Marca<input name="brand" maxlength="100" value="{{ old('brand') }}" ></label>
            <label>Peso (kg)<input type="number" name="weight_kg" min="1" max="999" value="{{ old('weight_kg') }}" ></label>
            <label>Quantidade inicial de botijas<input type="number" name="quantity" min="0" max="1000000000" value="{{ old('quantity', 0) }}" ></label>
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
        <thead><tr><th>Produto</th><th>Descrição</th><th class="num">Estoque disponível</th><th class="num">Custo fornecedor</th><th class="num">Revendedor</th><th class="num">Unidade</th><th>Adicionar estoque</th><th>Ações</th></tr></thead>
        <tbody>@forelse($products as $product)<tr>
            <td><strong>{{ $product->label }}</strong>
                @unless($product->isReadyForSale())
                    <br><small class="muted">Pendente de preços</small>
                @endunless
            </td>
            <td class="product-description">{{ $product->description ?: '—' }}</td>
            <td class="num">{{ number_format($product->quantity - $damagedTotals[$product->id], 0, ',', '.') }} disponíveis<small class="sale-date">{{ number_format($centralAvailable[$product->id], 0, ',', '.') }} no armazém · {{ number_format($damagedTotals[$product->id], 0, ',', '.') }} avariadas à parte</small></td>
            <td class="num">{{ number_format($product->purchase_price, 2, ',', '.') }} MT</td>
            <td class="num">{{ (float) $product->reseller_price > 0 ? number_format($product->reseller_price, 2, ',', '.').' MT' : 'Por definir' }}</td>
            <td class="num">{{ (float) $product->unit_price > 0 ? number_format($product->unit_price, 2, ',', '.').' MT' : 'Por definir' }}</td>
            <td><form class="stock-add-form" method="post" action="{{ route('products.stock.add', $product) }}">@csrf
                <input aria-label="Quantidade a adicionar para {{ $product->label }}" type="number" name="quantity_added" min="1" max="1000000000" placeholder="Quantidade nova" required>
                <input aria-label="Observação para {{ $product->label }}" name="note" maxlength="255" placeholder="Observação (opcional)">
                <button class="smallbtn" type="submit">Somar</button>
            </form></td>
            <td><div class="table-actions"><a class="small-link" href="{{ route('products.edit', $product) }}">Editar</a>@if($product->stock_entries_exists || $product->quantity > 0)<span class="muted" title="Só é possível apagar produtos sem estoque e sem histórico de entradas">Não pode apagar</span>@else<form method="post" action="{{ route('products.destroy', $product) }}" onsubmit="return confirm('Apagar {{ $product->label }}? O produto ficará guardado no histórico.');">@csrf @method('DELETE')<button class="smallbtn danger" type="submit">Apagar</button></form>@endif</div></td>
        </tr>@empty<tr><td colspan="8" class="muted">Ainda não há produtos.</td></tr>@endforelse</tbody>
    </table></div>
</section>

<section class="panel">
    <h2>Histórico de entradas e saldo inicial</h2>
    <div class="tablewrap"><table>
        <thead><tr><th>Data</th><th>Produto</th><th class="num">Quantidade adicionada</th><th class="num">Stock anterior</th><th class="num">Stock depois</th><th>Registado por</th><th>Observação</th></tr></thead>
        <tbody>@forelse($stockEntries as $entry)<tr>
            <td>{{ $entry->created_at->format('d/m/Y H:i') }}</td>
            <td>{{ $entry->product?->label ?? 'Produto removido #'.$entry->product_id }}@if($entry->product?->trashed()) <span class="pill">Arquivado</span>@endif</td>
            <td class="num">+{{ number_format($entry->quantity_added, 0, ',', '.') }}</td>
            <td class="num">{{ number_format($entry->quantity_before, 0, ',', '.') }}</td>
            <td class="num">{{ number_format($entry->quantity_after, 0, ',', '.') }}</td>
            <td>{{ $entry->createdBy?->name ?? '—' }}</td>
            <td>{{ $entry->note ?? ($entry->source === 'initial' ? 'Estoque inicial' : '—') }}</td>
        </tr>@empty<tr><td colspan="7" class="muted">Ainda não há entradas registadas.</td></tr>@endforelse</tbody>
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
