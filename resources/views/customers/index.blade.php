@extends('layouts.app')
@section('title', 'Clientes')
@section('content')
<span class="section-eyebrow">Gestão de Clientes</span>
<h2 class="page-title">Clientes</h2>
<p class="page-subtitle">Registe pessoas e organizações, guarde os seus contactos e controle quem está activo.</p>

<div class="customer-summary">
    <div><span>Total de clientes</span><strong>{{ number_format($totalCustomers, 0, ',', '.') }}</strong></div>
    <div><span>Clientes activos</span><strong>{{ number_format($activeCustomers, 0, ',', '.') }}</strong></div>
    <div><span>Clientes inactivos</span><strong>{{ number_format($totalCustomers - $activeCustomers, 0, ',', '.') }}</strong></div>
</div>

<section class="panel">
    <h2>Adicionar cliente</h2>
    <form method="post" action="{{ route('customers.store') }}">@csrf
        @include('customers.form', ['customer' => null])
        <button type="submit">Registar cliente</button>
    </form>
</section>

<section class="panel">
    <h2>Clientes registados</h2>
    <div class="tablewrap"><table>
        <thead><tr><th>Cliente</th><th>Tipo</th><th>NUIT</th><th>Contactos</th><th>Endereço físico</th><th>Estado</th><th>Ações</th></tr></thead>
        <tbody>@forelse($customers as $customer)<tr>
            <td><strong>{{ $customer->name }}</strong></td>
            <td>{{ $customer->type === 'person' ? 'Pessoa' : 'Organização' }}</td>
            <td>{{ $customer->nuit ?: 'Por preencher' }}</td>
            <td><div class="customer-contact-tags">@foreach($customer->contacts as $contact)<span><b>{{ ['email' => 'Email', 'phone' => 'Telefone', 'mobile' => 'Telemóvel'][$contact->type] ?? $contact->type }}:</b> {{ $contact->value }}</span>@endforeach</div></td>
            <td class="product-description">{{ $customer->physical_address }}</td>
            <td><span class="pill {{ $customer->is_active ? 'active-pill' : 'cancelled-pill' }}">{{ $customer->is_active ? 'Activo' : 'Inactivo' }}</span></td>
            <td><div class="table-actions"><a class="small-link" href="{{ route('customers.edit', $customer) }}">Editar</a>
                <form method="post" action="{{ route('customers.status', $customer) }}">@csrf @method('PATCH')
                    <button class="smallbtn {{ $customer->is_active ? 'danger' : '' }}" type="submit">{{ $customer->is_active ? 'Desactivar' : 'Activar' }}</button>
                </form>
            </div></td>
        </tr>@empty<tr><td colspan="7" class="muted">Ainda não há clientes registados.</td></tr>@endforelse</tbody>
    </table></div>
    @if($customers->hasPages())
        <nav class="history-pages" aria-label="Páginas de clientes">
            @if($customers->previousPageUrl())<a href="{{ $customers->previousPageUrl() }}">← Anterior</a>@endif
            <span>Página {{ $customers->currentPage() }} de {{ $customers->lastPage() }}</span>
            @if($customers->nextPageUrl())<a href="{{ $customers->nextPageUrl() }}">Próxima →</a>@endif
        </nav>
    @endif
</section>
@endsection
