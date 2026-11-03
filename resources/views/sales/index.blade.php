@extends('layouts.app')
@section('title', 'Vendas')
@section('content')
<div class="operations-heading">
    <div><span class="section-eyebrow">Operações</span><h2 class="page-title">Vendas de botijas</h2><p class="page-subtitle">Carregamentos de camiões são vendas a grosso. As bancas vendem por unidade a partir do estoque que receberam.</p></div>
    @if(auth()->user()->hasPermission('allocations.manage'))<a class="small-link" href="{{ route('allocations.index') }}">Alocar às bancas →</a>@endif
</div>

<div class="sales-summary">
    <div class="sales-summary-card">
        <span>Grosso · hoje</span>
        <strong>{{ number_format($metrics['wholesale']['today_revenue_cents'] / 100, 2, ',', '.') }} MT</strong>
        <small>{{ number_format($metrics['wholesale']['today_quantity'], 0, ',', '.') }} botijas
            @if($canViewSalesProfit)
                · margem {{ number_format($metrics['wholesale']['today_margin_cents'] / 100, 2, ',', '.') }} MT
            @endif
        </small>
    </div>
    <div class="sales-summary-card">
        <span>Unidade · hoje</span>
        <strong>{{ number_format($metrics['unit']['today_revenue_cents'] / 100, 2, ',', '.') }} MT</strong>
        <small>{{ number_format($metrics['unit']['today_quantity'], 0, ',', '.') }} botijas
            @if($canViewSalesProfit)
                · margem {{ number_format($metrics['unit']['today_margin_cents'] / 100, 2, ',', '.') }} MT
            @endif
        </small>
    </div>
    <div class="sales-summary-card">
        <span>Vendas acumuladas · hoje</span>
        <strong>{{ number_format(($metrics['wholesale']['today_revenue_cents'] + $metrics['unit']['today_revenue_cents']) / 100, 2, ',', '.') }} MT</strong>
        <small>{{ $assignedPoint?->name ?? 'Todos os locais' }}</small>
        @if($canViewSalesProfit)
            <small>Margem do dia {{ number_format(($metrics['wholesale']['today_margin_cents'] + $metrics['unit']['today_margin_cents']) / 100, 2, ',', '.') }} MT</small>
        @endif
    </div>
</div>

<section class="sales-stock-section" aria-labelledby="sales-stock-title">
    <div class="sales-stock-heading"><span class="section-eyebrow">Disponibilidade</span><h3 id="sales-stock-title">Estoque para venda</h3></div>
    <div class="sales-stock-cards">
        @if($canWholesale)
        <article class="sales-stock-card sales-stock-central">
            <span>Estoque central · grosso</span>
            <strong>{{ number_format($sellableProducts->sum(fn ($product) => (int) ($centralStock[$product->id] ?? 0)), 0, ',', '.') }} <small>botijas</small></strong>
            <ul>@forelse($sellableProducts as $product)
                <li><span>{{ $product->label }}</span><b>{{ number_format($centralStock[$product->id] ?? 0, 0, ',', '.') }}</b></li>
            @empty<li>Ainda não há produtos com preços definidos.</li>@endforelse</ul>
        </article>
        @endif
        @if($canUnit)
            @forelse($stands as $stand)
            <article class="sales-stock-card">
                <span>{{ $stand->name }} · unidade</span>
                <strong>{{ number_format($sellableProducts->sum(fn ($product) => (int) ($standStock[$stand->id][$product->id] ?? 0)), 0, ',', '.') }} <small>botijas</small></strong>
                <ul>@forelse($sellableProducts as $product)
                    <li><span>{{ $product->label }}</span><b>{{ number_format($standStock[$stand->id][$product->id] ?? 0, 0, ',', '.') }}</b></li>
                @empty<li>Ainda não há produtos com preços definidos.</li>@endforelse</ul>
            </article>
            @empty
            <article class="sales-stock-card"><span>Postos de venda</span><p>Ainda não há bancas ou postos disponíveis.</p></article>
            @endforelse
        @endif
    </div>
</section>

@if($sellableProducts->isEmpty())
    <p class="muted">Ainda não há produtos disponíveis para venda. Defina o custo do fornecedor e os preços de revendedor e por unidade em Produtos.</p>
@endif

<div class="operation-grid">
    @if($canWholesale)
    <section class="panel operation-panel">
        <span class="operation-label">01 · Camiões</span><h2>Venda a grosso</h2>
        <p class="muted">O carregamento desconta imediatamente o estoque central e regista uma venda pelo preço de revendedor.</p>
        <form method="post" action="{{ route('sales.wholesale') }}" data-sale-confirm="Venda a grosso">@csrf
            <input type="hidden" name="form_type" value="wholesale">
            <div class="fields">
                <label>Matrícula do camião<input name="truck_plate" maxlength="30" value="{{ old('truck_plate', $assignedPoint?->plate) }}" placeholder="Ex.: ABC-123-MP" @readonly($assignedPoint?->plate) required></label>
                <label>Nome do motorista<input name="driver_name" maxlength="100" value="{{ old('driver_name') }}" required></label>
                <label class="field-wide">Cliente<select name="customer_id"><option value="">Cliente genérico</option>@foreach($customers as $customer)<option value="{{ $customer->id }}" @selected(old('form_type') === 'wholesale' && old('customer_id') == $customer->id)>{{ $customer->name }}@if($customer->nuit) · NUIT {{ $customer->nuit }}@endif</option>@endforeach</select></label>
            </div>
            <div class="sale-items-heading"><strong>Produtos da venda a grosso</strong><small>Adicione cada tipo de botija com a respectiva quantidade. Todos ficarão na mesma venda e guia.</small></div>
            <div class="sale-items" data-sale-items>
                @foreach(old('form_type') === 'wholesale' ? old('items', [['product_id' => '', 'quantity' => '']]) : [['product_id' => '', 'quantity' => '']] as $index => $item)
                <div class="sale-item-row" data-sale-item>
                    <label>Produto<select name="items[{{ $index }}][product_id]" data-item-product required>@foreach($sellableProducts as $product)<option value="{{ $product->id }}" data-price="{{ $product->reseller_price }}" @selected(($item['product_id'] ?? '') == $product->id)>{{ $product->label }}  · {{ number_format($product->reseller_price, 2, ',', '.') }} MT</option>@endforeach</select></label>
                    <label>Quantidade<input type="number" name="items[{{ $index }}][quantity]" data-item-quantity min="1" max="1000000000" value="{{ $item['quantity'] ?? '' }}" required></label>
                    <button type="button" class="item-remove" data-remove-item aria-label="Remover item">×</button>
                </div>
                @endforeach
            </div>
            <div class="sale-add-toolbar"><button type="button" class="item-add" data-add-item @disabled($sellableProducts->count() < 2)>+ Adicionar produto à mesma venda</button><span data-sale-item-count aria-live="polite">1 produto nesta venda</span></div>
            @if($sellableProducts->count() < 2)<p class="muted sale-add-help">Para adicionar vários tipos, defina os preços de outro produto no catálogo.</p>@endif
            <div class="sale-form-total"><span>Total previsto</span><strong data-sale-total>0,00 MT</strong></div>
            <label class="field-wide">Observação <span class="muted">(opcional)</span><input name="note" maxlength="255" value="{{ old('note') }}"></label>
            <label class="check guide-option"><input type="checkbox" name="generate_delivery_guide" value="1" @checked(old('generate_delivery_guide'))>Gerar guia de entrega em PDF após registar</label>
            <br><button type="submit" @disabled($sellableProducts->isEmpty())>Registar venda a grosso</button>
        </form>
    </section>
    @endif

    @if($canUnit)
    <section class="panel operation-panel">
        <span class="operation-label">02 · Bancas</span><h2>Venda por unidade</h2>
        <p class="muted">A venda desconta a quantidade da banca escolhida e usa o preço por unidade do produto.</p>
        <form method="post" action="{{ route('sales.unit') }}" data-sale-confirm="Venda por unidade">@csrf
            <input type="hidden" name="form_type" value="unit">
            <div class="fields">
                <label>Banca ou posto<select name="location_id" required>@foreach($stands as $stand)<option value="{{ $stand->id }}" @selected(old('location_id') == $stand->id)>{{ $stand->name }}</option>@endforeach</select></label>
                <label>Cliente<select name="customer_id"><option value="">Cliente genérico</option>@foreach($customers as $customer)<option value="{{ $customer->id }}" @selected(old('form_type') === 'unit' && old('customer_id') == $customer->id)>{{ $customer->name }}@if($customer->nuit) · NUIT {{ $customer->nuit }}@endif</option>@endforeach</select></label>
            </div>
            <div class="sale-items-heading"><strong>Itens da venda</strong><small>Todos os itens saem da banca escolhida</small></div>
            <div class="sale-items" data-sale-items>
                @foreach(old('form_type') === 'unit' ? old('items', [['product_id' => '', 'quantity' => '']]) : [['product_id' => '', 'quantity' => '']] as $index => $item)
                <div class="sale-item-row" data-sale-item>
                    <label>Produto<select name="items[{{ $index }}][product_id]" data-item-product required>@foreach($sellableProducts as $product)<option value="{{ $product->id }}" data-price="{{ $product->unit_price }}" @selected(($item['product_id'] ?? '') == $product->id)>{{ $product->label }} · {{ number_format($product->unit_price, 2, ',', '.') }} MT</option>@endforeach</select></label>
                    <label>Quantidade<input type="number" name="items[{{ $index }}][quantity]" data-item-quantity min="1" max="1000000000" value="{{ $item['quantity'] ?? '' }}" required></label>
                    <button type="button" class="item-remove" data-remove-item aria-label="Remover item">×</button>
                </div>
                @endforeach
            </div>
            <div class="sale-add-toolbar"><button type="button" class="item-add" data-add-item @disabled($sellableProducts->count() < 2)>+ Adicionar outro produto</button><span data-sale-item-count aria-live="polite">1 produto nesta venda</span></div>
            <div class="sale-form-total"><span>Total previsto</span><strong data-sale-total>0,00 MT</strong></div>
            <label>Observação <span class="muted">(opcional)</span><input name="note" maxlength="255" value="{{ old('note') }}"></label>
            <button type="submit" @disabled($sellableProducts->isEmpty() || $stands->isEmpty())>Registar venda por unidade</button>
        </form>
    </section>
    @endif
</div>

<dialog class="sale-confirm-dialog" data-sale-confirm-dialog aria-labelledby="sale-confirm-title" aria-describedby="sale-confirm-message">
    <div class="sale-confirm-dialog-content">
        <span class="section-eyebrow">Confirmação da venda</span>
        <h2 id="sale-confirm-title">Confirmar venda</h2>
        <p id="sale-confirm-message" data-sale-confirm-message></p>
        <dl class="sale-confirm-details">
            <div><dt>Cliente</dt><dd data-sale-confirm-customer></dd></div>
            <div><dt>Destino / posto</dt><dd data-sale-confirm-destination></dd></div>
            <div><dt>Total previsto</dt><dd data-sale-confirm-total></dd></div>
        </dl>
        <div class="sale-confirm-actions">
            <button type="button" class="sale-confirm-back" data-sale-confirm-back autofocus>Voltar e corrigir</button>
            <button type="button" class="sale-confirm-submit" data-sale-confirm-submit>Confirmar venda</button>
        </div>
    </div>
</dialog>

<section class="panel">
    <h2>Histórico de vendas</h2>
    <div class="tablewrap"><table>
        <thead><tr><th>Data</th><th>Tipo</th><th>Cliente</th><th>Produtos</th><th>Destino / posto</th><th class="num">Botijas</th><th class="num">Total</th><th>Registado por</th><th>Ações</th></tr></thead>
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
            <td>@if(!$order->cancelled_at && $order->items->isNotEmpty())<div class="table-actions">@if($order->channel === 'wholesale')<a class="small-link" href="{{ route('sales.delivery-guide', $order->items->first()) }}">Guia PDF</a>@endif<form method="post" action="{{ route('sales.cancel', $order->items->first()) }}" onsubmit="return confirm('Cancelar esta venda e devolver {{ $orderQuantity }} botijas ao estoque?')">@csrf<button class="smallbtn danger" type="submit">Cancelar</button></form></div>@else<span class="muted">—</span>@endif</td>
        </tr>@empty<tr><td colspan="9" class="muted">Ainda não há vendas.</td></tr>@endforelse</tbody>
    </table></div>
    @if($orders->hasPages())<nav class="history-pages" aria-label="Páginas das vendas">@if($orders->previousPageUrl())<a href="{{ $orders->previousPageUrl() }}">← Anterior</a>@endif<span>Página {{ $orders->currentPage() }} de {{ $orders->lastPage() }}</span>@if($orders->nextPageUrl())<a href="{{ $orders->nextPageUrl() }}">Próxima →</a>@endif</nav>@endif
</section>
@endsection
@push('scripts')
<script>
document.querySelectorAll('[data-sale-items]').forEach(list => {
    const form = list.closest('form');
    const add = form.querySelector('[data-add-item]');
    const count = form.querySelector('[data-sale-item-count]');
    const total = form.querySelector('[data-sale-total]');
    const rows = () => [...list.querySelectorAll('[data-sale-item]')];
    const refresh = () => {
        let amount = 0;
        rows().forEach((row, index) => {
            row.querySelector('[data-item-product]').name = `items[${index}][product_id]`;
            row.querySelector('[data-item-quantity]').name = `items[${index}][quantity]`;
            row.querySelector('[data-remove-item]').disabled = rows().length === 1;
            const price = Number(row.querySelector('[data-item-product]').selectedOptions[0]?.dataset.price || 0);
            const quantity = Number(row.querySelector('[data-item-quantity]').value || 0);
            amount += price * quantity;
        });
        add.disabled = rows().length >= Math.min(20, rows()[0].querySelector('[data-item-product]').options.length);
        count.textContent = rows().length === 1 ? '1 produto nesta venda' : `${rows().length} produtos nesta venda`;
        total.textContent = new Intl.NumberFormat('pt-MZ', {minimumFractionDigits: 2, maximumFractionDigits: 2}).format(amount) + ' MT';
    };
    add.addEventListener('click', () => {
        if (add.disabled) return;
        const selected = new Set(rows().map(existing => existing.querySelector('[data-item-product]').value));
        const row = rows()[0].cloneNode(true);
        const nextProduct = [...row.querySelector('[data-item-product]').options].find(option => !selected.has(option.value));
        row.querySelector('[data-item-product]').value = nextProduct?.value || '';
        row.querySelector('[data-item-quantity]').value = '';
        list.appendChild(row);
        refresh();
        row.querySelector('[data-item-product]').focus();
    });
    list.addEventListener('click', event => { if (event.target.closest('[data-remove-item]') && rows().length > 1) { event.target.closest('[data-sale-item]').remove(); refresh(); } });
    list.addEventListener('input', refresh);
    list.addEventListener('change', refresh);
    refresh();
});

const saleConfirmDialog = document.querySelector('[data-sale-confirm-dialog]');
const saleConfirmButton = saleConfirmDialog.querySelector('[data-sale-confirm-submit]');
let pendingSaleForm = null;
let saleConfirmed = false;

document.querySelectorAll('form[data-sale-confirm]').forEach(form => {
    form.addEventListener('submit', event => {
        if (saleConfirmed && pendingSaleForm === form) {
            saleConfirmButton.disabled = true;
            return;
        }

        event.preventDefault();
        pendingSaleForm = form;
        const type = form.dataset.saleConfirm;
        const customer = form.querySelector('[name="customer_id"]');
        const destination = type === 'Venda a grosso'
            ? form.querySelector('[name="truck_plate"]').value.trim()
            : form.querySelector('[name="location_id"]').selectedOptions[0]?.textContent.trim();

        saleConfirmDialog.querySelector('[data-sale-confirm-message]').textContent = `Confirma o registo desta ${type.toLowerCase()}?`;
        saleConfirmDialog.querySelector('[data-sale-confirm-customer]').textContent = customer.selectedOptions[0]?.textContent.trim() || 'Cliente genérico';
        saleConfirmDialog.querySelector('[data-sale-confirm-destination]').textContent = destination || '—';
        saleConfirmDialog.querySelector('[data-sale-confirm-total]').textContent = form.querySelector('[data-sale-total]').textContent;
        saleConfirmDialog.showModal();
    });
});

saleConfirmDialog.querySelector('[data-sale-confirm-back]').addEventListener('click', () => saleConfirmDialog.close());
saleConfirmDialog.addEventListener('close', () => {
    if (!saleConfirmed) pendingSaleForm = null;
});
saleConfirmButton.addEventListener('click', () => {
    if (!pendingSaleForm || saleConfirmed) return;
    saleConfirmed = true;
    pendingSaleForm.requestSubmit();
});
</script>
@endpush
