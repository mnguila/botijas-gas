@extends('layouts.app')
@section('title', 'Editar cliente')
@section('content')
<span class="section-eyebrow">Gestão de Clientes</span>
<h2 class="page-title">Editar cliente</h2>
<p class="page-subtitle">Actualize os dados de {{ $customer->name }}.</p>
<section class="panel">
    <h2>Dados do cliente</h2>
    <form method="post" action="{{ route('customers.update', $customer) }}">@csrf @method('PATCH')
        @include('customers.form')
        <div class="form-actions"><button type="submit">Guardar alterações</button><a href="{{ route('customers.index') }}">Cancelar</a></div>
    </form>
</section>
@endsection
