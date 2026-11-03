@extends('layouts.app')
@section('title', 'Editar produto')
@section('content')
<span class="section-eyebrow">Catálogo</span>
<h2 class="page-title">Editar produto</h2>
<p class="page-subtitle">{{ $product->label }}</p>
<section class="panel">
    <p class="muted">Quantidade atual: <strong>{{ number_format($product->quantity, 0, ',', '.') }}</strong>. Para aumentar o estoque, use “Adicionar estoque” na página Produtos.</p>
    <form method="post" action="{{ route('products.update', $product) }}">@csrf @method('PATCH')
        <div class="fields three">
            <label>Marca<input name="brand" maxlength="100" value="{{ old('brand', $product->brand) }}" required></label>
            <label>Peso (kg)<input type="number" name="weight_kg" min="1" max="999" value="{{ old('weight_kg', $product->weight_kg) }}" required></label>
            <label>Custo fornecedor (MT)<input type="number" name="purchase_price" step="0.01" min="0" value="{{ old('purchase_price', $product->purchase_price) }}" required></label>
            <label>Preço revendedor (MT) <span class="muted">(opcional)</span><input type="number" name="reseller_price" step="0.01" min="0" value="{{ old('reseller_price', (float) $product->reseller_price > 0 ? $product->reseller_price : '') }}"></label>
            <label>Preço unidade (MT) <span class="muted">(opcional)</span><input type="number" name="unit_price" step="0.01" min="0" value="{{ old('unit_price', (float) $product->unit_price > 0 ? $product->unit_price : '') }}"></label>
            <label class="field-wide">Descrição do produto <span class="muted">(opcional)</span><textarea name="description" maxlength="1000" rows="3">{{ old('description', $product->description) }}</textarea></label>
        </div>
        <p class="muted">O produto ficará disponível para vendas e alocações quando o custo e os dois preços de venda estiverem definidos.</p>
        <div class="form-actions"><button type="submit">Guardar alterações</button><a href="{{ route('products.index') }}">Cancelar</a></div>
    </form>
</section>
@endsection
