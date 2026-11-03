@extends('layouts.app')
@section('title', 'Atribuir postos')
@section('content')
<span class="section-eyebrow">Vendas de botijas</span>
<h2 class="page-title">Atribuir postos de venda</h2>
<p class="page-subtitle">Associe cada vendedor a uma banca ou local físico. O registo de utilizadores e as permissões são geridos em Gestão de acessos.</p>
<section class="panel">
    <h2>Utilizadores de vendas</h2>
    <div class="tablewrap"><table>
        <thead><tr><th>Utilizador</th><th>Username</th><th>Estado</th><th>Posto atual</th><th>Atribuição</th></tr></thead>
        <tbody>@forelse($users as $user)<tr>
            <td><strong>{{ $user->name }}</strong></td>
            <td>{{ $user->username }}</td>
            <td>{{ $user->is_active ? 'Ativo' : 'Inativo' }}</td>
            <td>{{ $user->salesLocation?->display_name ?? 'Sem posto' }}</td>
            <td><form class="point-assignment-form" method="post" action="{{ route('sales.points.update', $user) }}">@csrf @method('PATCH')
                <label class="sr-only" for="point-{{ $user->id }}">Posto de {{ $user->name }}</label>
                <select id="point-{{ $user->id }}" name="sales_location_id"><option value="">Sem posto atribuído</option>@foreach($salesPoints as $point)<option value="{{ $point->id }}" @selected($user->sales_location_id == $point->id)>{{ $point->display_name }}</option>@endforeach</select>
                <button type="submit">Guardar</button>
            </form></td>
        </tr>@empty<tr><td colspan="5" class="muted">Ainda não há utilizadores com acesso a vendas.</td></tr>@endforelse</tbody>
    </table></div>
</section>
@endsection
