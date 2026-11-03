@extends('layouts.app')
@section('title', 'Despesas Operacionais')
@section('content')
@php
    $canCreate = auth()->user()->hasPermission('expenses.create');
    $canApprove = auth()->user()->hasPermission('expenses.approve');
    $labels = ['pending' => 'Pendente', 'approved' => 'Aprovada', 'rejected' => 'Rejeitada', 'cancelled' => 'Anulada'];
@endphp
<div class="operations-heading"><div><span class="section-eyebrow">Vendas de botijas</span><h2 class="page-title">Despesas Operacionais</h2><p class="page-subtitle">Registe de forma simples os custos da atividade, como reparações, combustível e outros imprevistos.</p></div></div>

<div class="dashboard-metrics finance-metrics">
    <div class="dashboard-metric featured"><span class="metric-label">Despesas aprovadas · {{ $month }}</span><strong>{{ number_format($approvedCents / 100, 2, ',', '.') }} <small>MT</small></strong><span class="metric-help">Total confirmado no período</span></div>
    <div class="dashboard-metric"><span class="metric-label">Por aprovar · {{ $month }}</span><strong>{{ number_format($pendingCents / 100, 2, ',', '.') }} <small>MT</small></strong><span class="metric-help">Aguardam decisão</span></div>
</div>

@if($canCreate)
<section class="panel">
    <h2>Registar despesa</h2>
    <form method="post" action="{{ route('expenses.store') }}" enctype="multipart/form-data">@csrf
        <div class="fields three">
            <label>Data da despesa<input type="date" name="expense_date" value="{{ old('expense_date', today()->toDateString()) }}" max="{{ today()->toDateString() }}" required></label>
            <label>Categoria<select name="category" required><option value="">Selecione</option>@foreach($categories as $category)<option value="{{ $category }}" @selected(old('category') === $category)>{{ $category }}</option>@endforeach</select></label>
            <label>Valor (MT)<input type="number" name="amount" min="0.01" step="0.01" value="{{ old('amount') }}" placeholder="0,00" required></label>
            <label>Comprovativo <span class="muted">(opcional, até 10 MB)</span><input type="file" name="receipt" accept=".pdf,.jpg,.jpeg,.png"></label>
            <label class="field-wide">O que aconteceu?<textarea name="description" rows="3" maxlength="2000" minlength="5" required placeholder="Explique a avaria, reparação ou outro motivo da despesa">{{ old('description') }}</textarea></label>
        </div>
        <button type="submit">Enviar despesa para aprovação</button>
    </form>
</section>
@endif

<section class="panel">
    <div class="operations-heading"><h2>Histórico de despesas</h2><form method="get" action="{{ route('expenses.index') }}" class="finance-filter"><label>Mês<input type="month" name="month" value="{{ $month }}"></label><label>Estado<select name="status">@foreach(['all' => 'Todos', 'pending' => 'Pendentes', 'approved' => 'Aprovadas', 'rejected' => 'Rejeitadas', 'cancelled' => 'Anuladas'] as $key => $label)<option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>@endforeach</select></label><button type="submit">Filtrar</button></form></div>
    <div class="tablewrap"><table><thead><tr><th>Data</th><th>Categoria</th><th>Registado por</th><th class="num">Valor</th><th>Estado</th><th>Comprovativo e ações</th></tr></thead><tbody>
    @forelse($expenses as $expense)
        <tr><td>{{ $expense->expense_date->format('d/m/Y') }}</td><td><strong>{{ $expense->category }}</strong></td><td>{{ $expense->createdBy?->name ?? '—' }}</td><td class="num"><strong>{{ number_format($expense->amount_cents / 100, 2, ',', '.') }} MT</strong></td><td>{{ $labels[$expense->status] ?? $expense->status }}@if($expense->status === 'approved')<br><small class="muted">{{ $expense->approvedBy?->name }} · {{ $expense->approved_at?->format('d/m/Y H:i') }}</small>@endif </td><td>
            <button type="button" class="expense-detail-button" data-expense-detail data-expense-category="{{ $expense->category }}" data-expense-description="{{ $expense->description }}" data-expense-reason="{{ $expense->rejection_reason ?? $expense->cancellation_reason ?? '' }}" data-expense-status="{{ $labels[$expense->status] ?? $expense->status }}">Ver detalhes</button>
            @if($expense->receipt_path)<a class="small-link" href="{{ route('expenses.receipt', $expense) }}">Comprovativo</a>@else<span class="muted">Sem comprovativo</span>@endif
            @if($canApprove && $expense->status === 'pending' && (auth()->user()->is_super_admin || $expense->created_by_user_id !== auth()->id()))
                <form method="post" action="{{ route('expenses.approve', $expense) }}" class="finance-inline-form">@csrf<button type="submit">Aprovar</button></form>
                <button type="button" class="expense-action-button" data-expense-action data-expense-action-url="{{ route('expenses.reject', $expense) }}" data-expense-action-title="Rejeitar despesa" data-expense-action-verb="Rejeitar">Rejeitar</button>
            @elseif($canApprove && $expense->status === 'approved')
                <button type="button" class="expense-action-button" data-expense-action data-expense-action-url="{{ route('expenses.cancel', $expense) }}" data-expense-action-title="Anular despesa" data-expense-action-verb="Anular">Anular</button>
            @endif
        </td></tr>
    @empty<tr><td colspan="6" class="muted">Sem despesas neste período.</td></tr>@endforelse
    </tbody></table></div>
    @if($expenses->hasPages()){{ $expenses->links() }}@endif
</section>

<dialog class="sale-confirm-dialog" id="expense-reason-dialog" aria-labelledby="expense-reason-title">
    <div class="sale-confirm-dialog-content">
        <h2 id="expense-reason-title">Motivo da decisão</h2>
        <p>Indique o motivo para manter o histórico da despesa completo.</p>
        <form method="post" data-expense-reason-form>@csrf
            <label class="expense-modal-label">Motivo<textarea name="reason" rows="4" maxlength="500" required placeholder="Escreva o motivo"></textarea></label>
            <div class="sale-confirm-actions"><button type="button" class="sale-confirm-back" data-expense-modal-close>Voltar</button><button type="submit" class="sale-confirm-submit" data-expense-modal-submit>Confirmar</button></div>
        </form>
    </div>
</dialog>
<dialog class="sale-confirm-dialog" id="expense-detail-dialog" aria-labelledby="expense-detail-title">
    <div class="sale-confirm-dialog-content">
        <h2 id="expense-detail-title">Detalhes da despesa</h2>
        <p data-expense-detail-category></p>
        <dl class="sale-confirm-details"><div><dt>Descrição</dt><dd data-expense-detail-description></dd></div><div data-expense-detail-reason-row hidden><dt>Motivo da decisão</dt><dd data-expense-detail-reason></dd></div></dl>
        <div class="sale-confirm-actions"><button type="button" class="sale-confirm-back" data-expense-detail-close>Fechar</button></div>
    </div>
</dialog>

@if($byCategory->isNotEmpty())
<section class="panel"><h2>Despesas aprovadas por categoria · {{ $month }}</h2><div class="tablewrap"><table><thead><tr><th>Categoria</th><th class="num">Total</th></tr></thead><tbody>@foreach($byCategory as $row)<tr><td>{{ $row->category }}</td><td class="num">{{ number_format($row->total_cents / 100, 2, ',', '.') }} MT</td></tr>@endforeach</tbody></table></div></section>
@endif
<style>.finance-metrics{margin:24px 0}.finance-filter{display:flex;align-items:end;gap:12px;flex-wrap:wrap}.finance-filter label{min-width:140px}.finance-inline-form{display:inline-flex;gap:6px;align-items:center;margin:8px 7px 0 0}.finance-inline-form button{white-space:nowrap}.expense-detail-button,.expense-action-button{display:inline-flex;align-items:center;min-height:34px;margin:8px 7px 0 0;padding:7px 10px;border:1px solid #d8e6e3;border-radius:8px;background:#fff;color:#0a5960;font-size:12px;font-weight:700;cursor:pointer}.expense-detail-button:hover,.expense-action-button:hover{background:#eff7f5}.expense-modal-label{display:block;margin-top:20px;font-weight:700}.expense-modal-label textarea{display:block;width:100%;margin-top:8px;resize:vertical}#expense-detail-dialog,#expense-reason-dialog{overflow:auto}#expense-detail-dialog .sale-confirm-details dd{text-align:left;white-space:pre-wrap}#expense-detail-dialog .sale-confirm-details>div[hidden]{display:none}</style>
@push('scripts')
<script>
const expenseReasonDialog = document.querySelector('#expense-reason-dialog');
const expenseReasonForm = expenseReasonDialog.querySelector('[data-expense-reason-form]');
const expenseReasonInput = expenseReasonForm.querySelector('[name="reason"]');
document.querySelectorAll('[data-expense-action]').forEach(button => button.addEventListener('click', () => {
    expenseReasonForm.action = button.dataset.expenseActionUrl;
    expenseReasonDialog.querySelector('#expense-reason-title').textContent = button.dataset.expenseActionTitle;
    expenseReasonDialog.querySelector('[data-expense-modal-submit]').textContent = button.dataset.expenseActionVerb;
    expenseReasonInput.value = '';
    expenseReasonDialog.showModal();
    expenseReasonInput.focus();
}));
expenseReasonDialog.querySelector('[data-expense-modal-close]').addEventListener('click', () => expenseReasonDialog.close());

const expenseDetailDialog = document.querySelector('#expense-detail-dialog');
document.querySelectorAll('[data-expense-detail]').forEach(button => button.addEventListener('click', () => {
    expenseDetailDialog.querySelector('[data-expense-detail-category]').textContent = `${button.dataset.expenseCategory} · ${button.dataset.expenseStatus}`;
    expenseDetailDialog.querySelector('[data-expense-detail-description]').textContent = button.dataset.expenseDescription;
    expenseDetailDialog.querySelector('[data-expense-detail-reason]').textContent = button.dataset.expenseReason;
    expenseDetailDialog.querySelector('[data-expense-detail-reason-row]').hidden = !button.dataset.expenseReason;
    expenseDetailDialog.showModal();
}));
expenseDetailDialog.querySelector('[data-expense-detail-close]').addEventListener('click', () => expenseDetailDialog.close());
</script>
@endpush
@endsection
