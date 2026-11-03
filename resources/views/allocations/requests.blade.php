@extends('layouts.app')
@section('title', 'Pedidos às bancas')
@section('content')
@php
    $user = auth()->user();
    $canRequest = $user->hasPermission('allocations.request');
    $canApprove = $user->hasPermission('allocations.approve');
    $canDispatch = $user->hasPermission('allocations.dispatch');
    $canReceive = $user->hasPermission('allocations.receive');
    $statusNames = ['pending' => 'Pendente', 'approved' => 'Aprovado', 'rejected' => 'Rejeitado', 'in_transit' => 'Em trânsito', 'received' => 'Recebido'];
@endphp
<div class="operations-heading"><div><span class="section-eyebrow">Distribuição</span><h2 class="page-title">Pedidos às bancas</h2><p class="page-subtitle">Solicite botijas, aprove a saída, entregue a guia ao motorista e confirme a chegada com o documento assinado.</p></div>@if($user->hasPermission('allocations.manage'))<a class="small-link" href="{{ route('allocations.index') }}">Ver estoque por local →</a>@endif</div>

@if($canRequest)
<section class="panel">
    <h2>Novo pedido de botijas</h2>
    @if($assignedPoint && ! $assignedPoint->is_active)
    <p class="muted">O seu posto está inativo. Não é possível criar novos pedidos até ser ativado.</p>
    @else
    <form method="post" action="{{ route('allocation-requests.store') }}">@csrf
        @if($assignedPoint)<p>Destino: <strong>{{ $assignedPoint->name }}</strong></p>@else
        <label>Banca de destino<select name="location_id" required><option value="">Selecione a banca</option>@foreach($stands as $stand)<option value="{{ $stand->id }}" @selected(old('location_id') == $stand->id)>{{ $stand->name }}</option>@endforeach</select></label>@endif
        <div class="tablewrap"><table><thead><tr><th>Produto</th><th>Quantidade solicitada</th></tr></thead><tbody>
        @forelse($products as $product)<tr><td>{{ $product->label }}</td><td><input type="number" name="quantities[{{ $product->id }}]" min="0" max="1000000000" value="{{ old('quantities.'.$product->id) }}" placeholder="0" aria-label="Quantidade de {{ $product->label }}"></td></tr>@empty<tr><td colspan="2">Não há produtos disponíveis para pedir.</td></tr>@endforelse
        </tbody></table></div>
        <label>Observação <span class="muted">(opcional)</span><input name="note" maxlength="500" value="{{ old('note') }}"></label>
        <button type="submit" @disabled($products->isEmpty() || (!$assignedPoint && $stands->isEmpty()))>Enviar pedido para aprovação</button>
    </form>
    @endif
</section>
@endif

<section class="panel"><h2>Pedidos registados</h2>
@forelse($requests as $allocationRequest)
<article class="allocation-request-card">
    <div class="operations-heading"><div><strong>Pedido #{{ str_pad((string) $allocationRequest->id, 6, '0', STR_PAD_LEFT) }}</strong> · {{ $allocationRequest->location?->name }} <span class="muted">· {{ $allocationRequest->created_at->format('d/m/Y H:i') }}</span></div><strong>{{ $statusNames[$allocationRequest->status] ?? $allocationRequest->status }}</strong></div>
    <p class="muted">Solicitado por {{ $allocationRequest->requestedBy?->name ?? '—' }}@if($allocationRequest->note) · {{ $allocationRequest->note }}@endif</p>
    <div class="tablewrap"><table><thead><tr><th>Produto</th><th class="num">Quantidade</th></tr></thead><tbody>@foreach($allocationRequest->items as $item)<tr><td>{{ $item->product?->label ?? 'Produto removido' }}</td><td class="num">{{ number_format($item->quantity, 0, ',', '.') }}</td></tr>@endforeach</tbody><tfoot><tr><th>Total</th><th class="num">{{ number_format($allocationRequest->items->sum('quantity'), 0, ',', '.') }}</th></tr></tfoot></table></div>
    @if($allocationRequest->status === 'pending' && $canApprove && ($user->is_super_admin || $allocationRequest->requested_by_user_id !== $user->id))
    <div class="allocation-request-actions"><form method="post" action="{{ route('allocation-requests.approve', $allocationRequest) }}">@csrf<button type="submit">Aprovar pedido</button></form><form method="post" action="{{ route('allocation-requests.reject', $allocationRequest) }}">@csrf<label>Motivo da rejeição<input name="rejection_reason" maxlength="500" required></label><button type="submit" class="button-secondary">Rejeitar</button></form></div>
    @endif
    @if($allocationRequest->status === 'rejected')<p>Motivo: {{ $allocationRequest->rejection_reason }}</p>@endif
    @if($allocationRequest->status === 'approved' && $canDispatch)
    <form method="post" action="{{ route('allocation-requests.dispatch', $allocationRequest) }}">@csrf<div class="fields two"><label>Matrícula do camião<input name="truck_plate" maxlength="30" required></label><label>Nome do motorista<input name="driver_name" maxlength="100" required></label></div><button type="submit">Registar saída e gerar guia PDF</button></form>
    @endif
    @if(in_array($allocationRequest->status, ['in_transit', 'received'], true))
    <p>Motorista: <strong>{{ $allocationRequest->driver_name }}</strong> · Camião: <strong>{{ $allocationRequest->truck_plate }}</strong></p>
    <p><a class="small-link" href="{{ route('allocation-requests.guide', $allocationRequest) }}">Descarregar guia de saída PDF</a></p>
    @endif
    @if($allocationRequest->status === 'in_transit' && $canReceive && ($user->is_super_admin || $canApprove || $canDispatch || $assignedPoint?->id === $allocationRequest->location_id))
    <form method="post" action="{{ route('allocation-requests.receive', $allocationRequest) }}" enctype="multipart/form-data">@csrf<label>Documento de entrega devolvido pelo motorista <small>(PDF, JPG ou PNG, até 10 MB)</small><input type="file" name="driver_document" accept=".pdf,.jpg,.jpeg,.png" required></label><button type="submit">Confirmar entrega na banca</button></form>
    @endif
    @if($allocationRequest->status === 'received')<p>Recebido por {{ $allocationRequest->receivedBy?->name ?? '—' }} em {{ $allocationRequest->received_at?->format('d/m/Y H:i') }} · <a class="small-link" href="{{ route('allocation-requests.driver-document', $allocationRequest) }}">Descarregar documento do motorista</a></p>@endif
</article>
@empty<p class="muted">Ainda não há pedidos.</p>@endforelse
@if($requests->hasPages())<div>{{ $requests->links() }}</div>@endif
</section>
<style>.allocation-request-card{border:1px solid #dce6e7;border-radius:14px;padding:20px;margin:18px 0;background:#fff}.allocation-request-actions{display:flex;flex-wrap:wrap;gap:20px;align-items:end}.allocation-request-actions form{display:flex;align-items:end;gap:12px}.allocation-request-card form{margin-top:14px}.allocation-request-card input[type=file]{display:block;margin:8px 0 12px}.allocation-request-card .tablewrap{margin-top:12px}</style>
@endsection
