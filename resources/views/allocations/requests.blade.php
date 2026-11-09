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

<div class="allocation-page-header">
    <div>
        <span class="section-eyebrow">Distribuição de estoque</span>
        <h2 class="page-title">Pedidos às bancas</h2>
        <p class="page-subtitle">Acompanhe cada pedido desde a solicitação até à entrega confirmada na banca.</p>
    </div>
    @if($user->hasPermission('allocations.manage'))<a class="small-link" href="{{ route('allocations.index') }}">Ver estoque por local <span aria-hidden="true">→</span></a>@endif
</div>

<div class="allocation-request-stats" aria-label="Resumo dos pedidos">
    <div><span>Pendentes</span><strong>{{ number_format($statusCounts['pending'] ?? 0, 0, ',', '.') }}</strong><small>Aguardam aprovação</small></div>
    <div><span>Aprovados</span><strong>{{ number_format($statusCounts['approved'] ?? 0, 0, ',', '.') }}</strong><small>Aguardam saída</small></div>
    <div><span>Em trânsito</span><strong>{{ number_format($statusCounts['in_transit'] ?? 0, 0, ',', '.') }}</strong><small>A caminho das bancas</small></div>
    <div><span>Recebidos</span><strong>{{ number_format($statusCounts['received'] ?? 0, 0, ',', '.') }}</strong><small>Entrega confirmada</small></div>
</div>

@if($canRequest)
<section class="panel allocation-new-request">
    <div class="allocation-section-heading"><div><span class="section-eyebrow">Nova solicitação</span><h2>Novo pedido de botijas</h2></div><span class="allocation-step">01 / 04</span></div>
    @if($assignedPoint && ! $assignedPoint->is_active)
        <p class="muted">O seu posto está inativo. Não é possível criar novos pedidos até ser ativado.</p>
    @else
    <form method="post" action="{{ route('allocation-requests.store') }}">@csrf
        <div class="allocation-destination">
            @if($assignedPoint)
                <span>Banca de destino</span><strong>{{ $assignedPoint->name }}</strong>
            @else
                <label>Banca de destino<select name="location_id" required><option value="">Selecione a banca</option>@foreach($stands as $stand)<option value="{{ $stand->id }}" @selected(old('location_id') == $stand->id)>{{ $stand->name }}</option>@endforeach</select></label>
            @endif
        </div>
        <div class="allocation-request-label"><strong>Produtos e quantidades</strong><small>Informe a quantidade necessária em cada tipo de botija.</small></div>
        <div class="allocation-product-grid">
            @forelse($products as $product)
                <label class="allocation-product-row"><span>{{ $product->label }}</span><input type="number" name="quantities[{{ $product->id }}]" min="0" max="1000000000" value="{{ old('quantities.'.$product->id) }}" placeholder="0" aria-label="Quantidade de {{ $product->label }}"></label>
            @empty
                <p class="muted">Não há produtos disponíveis para pedir.</p>
            @endforelse
        </div>
        <label class="allocation-request-note">Observação <span class="muted">(opcional)</span><input name="note" maxlength="500" value="{{ old('note') }}" placeholder="Ex.: Necessário para o fim de semana"></label>
        <div class="allocation-form-actions"><span>O pedido será enviado para aprovação antes da saída do estoque.</span><button type="submit" @disabled($products->isEmpty() || (!$assignedPoint && $stands->isEmpty()))>Enviar pedido para aprovação</button></div>
    </form>
    @endif
</section>
@endif

<section class="panel allocation-request-history">
    <div class="allocation-section-heading"><div><span class="section-eyebrow">Acompanhamento</span><h2>Pedidos registados</h2></div><span class="allocation-history-count">{{ number_format($requests->total(), 0, ',', '.') }} {{ $requests->total() === 1 ? 'pedido' : 'pedidos' }}</span></div>
    <div class="allocation-request-list">
    @forelse($requests as $allocationRequest)
        <article class="allocation-request-card">
            <div class="allocation-card-head">
                <div><span class="allocation-request-id">Pedido #{{ str_pad((string) $allocationRequest->id, 6, '0', STR_PAD_LEFT) }}</span><h3>{{ $allocationRequest->location?->name ?? 'Local removido' }}</h3><small>{{ $allocationRequest->created_at->format('d/m/Y · H:i') }} · Solicitado por {{ $allocationRequest->requestedBy?->name ?? '—' }}</small></div>
                <span class="allocation-status status-{{ $allocationRequest->status }}">{{ $statusNames[$allocationRequest->status] ?? $allocationRequest->status }}</span>
            </div>
            @if($allocationRequest->note)<p class="allocation-card-note"><strong>Observação:</strong> {{ $allocationRequest->note }}</p>@endif
            <div class="allocation-card-items">
                <div class="allocation-card-items-title"><strong>Botijas solicitadas</strong><span>{{ number_format($allocationRequest->items->sum('quantity'), 0, ',', '.') }} no total</span></div>
                <ul>@foreach($allocationRequest->items as $item)<li><span>{{ $item->product?->label ?? 'Produto removido' }}</span><strong>{{ number_format($item->quantity, 0, ',', '.') }}</strong></li>@endforeach</ul>
            </div>

            @if($allocationRequest->status === 'pending' && $canApprove && ($user->is_super_admin || $allocationRequest->requested_by_user_id !== $user->id))
                <div class="allocation-card-action allocation-approval-actions">
                    <form method="post" action="{{ route('allocation-requests.approve', $allocationRequest) }}">@csrf<button type="submit">Aprovar pedido</button></form>
                    <form method="post" action="{{ route('allocation-requests.reject', $allocationRequest) }}">@csrf<label>Motivo da rejeição<input name="rejection_reason" maxlength="500" placeholder="Informe o motivo" required></label><button type="submit" class="button-secondary">Rejeitar</button></form>
                </div>
            @endif
            @if($allocationRequest->status === 'rejected')<p class="allocation-card-message"><strong>Motivo da rejeição:</strong> {{ $allocationRequest->rejection_reason }}</p>@endif
            @if($allocationRequest->status === 'approved' && $canDispatch)
                <form class="allocation-card-action allocation-dispatch-form" method="post" action="{{ route('allocation-requests.dispatch', $allocationRequest) }}">@csrf
                    <strong>Registar saída do estoque</strong>
                    <div class="fields"><label>Matrícula do camião<input name="truck_plate" maxlength="30" required></label><label>Nome do motorista<input name="driver_name" maxlength="100" required></label></div>
                    <button type="submit">Registar saída e gerar guia PDF</button>
                </form>
            @endif
            @if(in_array($allocationRequest->status, ['in_transit', 'received'], true))
                <div class="allocation-transport"><div><span>Motorista</span><strong>{{ $allocationRequest->driver_name }}</strong></div><div><span>Camião</span><strong>{{ $allocationRequest->truck_plate }}</strong></div><a class="small-link" href="{{ route('allocation-requests.guide', $allocationRequest) }}">Descarregar guia de saída PDF</a></div>
            @endif
            @if($allocationRequest->status === 'in_transit' && $canReceive && ($user->is_super_admin || $canApprove || $canDispatch || $assignedPoint?->id === $allocationRequest->location_id))
                <form class="allocation-card-action allocation-receive-form" method="post" action="{{ route('allocation-requests.receive', $allocationRequest) }}" enctype="multipart/form-data">@csrf
                    <label>Documento de entrega devolvido pelo motorista <small>PDF, JPG ou PNG · até 10 MB</small><input type="file" name="driver_document" accept=".pdf,.jpg,.jpeg,.png" required></label>
                    <button type="submit">Confirmar entrega na banca</button>
                </form>
            @endif
            @if($allocationRequest->status === 'received')<div class="allocation-card-received"><span>Recebido por {{ $allocationRequest->receivedBy?->name ?? '—' }} em {{ $allocationRequest->received_at?->format('d/m/Y H:i') }}</span><a class="small-link" href="{{ route('allocation-requests.driver-document', $allocationRequest) }}">Descarregar documento do motorista</a></div>@endif
        </article>
    @empty
        <div class="allocation-empty"><strong>Ainda não há pedidos registados</strong><p>Os pedidos enviados às bancas aparecerão aqui com o respetivo estado.</p></div>
    @endforelse
    </div>
    @if($requests->hasPages())<div class="allocation-pagination">{{ $requests->links() }}</div>@endif
</section>
@endsection
