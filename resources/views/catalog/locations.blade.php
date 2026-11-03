@extends('layouts.app')
@section('title', 'Locais de venda')
@section('content')
<span class="section-eyebrow">Pontos de venda</span>
<h2 class="page-title">Locais de venda</h2>
<p class="page-subtitle">Registe, renomeie e ative ou desative bancas, lojas e outros pontos físicos.</p>

<section class="panel">
    <h2>Adicionar local de venda</h2>
    <form method="post" action="{{ route('locations.store') }}">@csrf
        <label>Nome do local físico<input name="name" maxlength="100" value="{{ old('name') }}" placeholder="Ex.: Banca 1 ou Loja Sede" required></label>
        <button type="submit">Adicionar local</button>
    </form>
</section>

<section class="panel">
    <h2>Locais registados</h2>
    <p class="muted">Desativar impede novas vendas e alocações. O estoque, as vendas anteriores e os pedidos já registados permanecem no histórico.</p>
    <div class="tablewrap"><table>
        <thead><tr><th>Nome do local físico</th><th>Estado</th><th>Alterar nome</th><th>Ação</th></tr></thead>
        <tbody>@forelse($locations as $location)<tr>
            <td><strong>{{ $location->name }}</strong></td>
            <td><span class="pill">{{ $location->is_active ? 'Ativo' : 'Inativo' }}</span></td>
            <td><form method="post" action="{{ route('locations.update', $location) }}" class="location-name-form">@csrf @method('PATCH')
                <label class="sr-only" for="location-name-{{ $location->id }}">Novo nome de {{ $location->name }}</label>
                <input id="location-name-{{ $location->id }}" name="name" value="{{ $location->name }}" maxlength="100" required>
                <button type="submit" class="smallbtn">Guardar</button>
            </form></td>
            <td><form method="post" action="{{ route('locations.status', $location) }}">@csrf @method('PATCH')
                <button type="submit" class="smallbtn {{ $location->is_active ? 'danger' : '' }}">{{ $location->is_active ? 'Desativar' : 'Ativar' }}</button>
            </form></td>
        </tr>@empty<tr><td colspan="4" class="muted">Ainda não há locais de venda.</td></tr>@endforelse</tbody>
    </table></div>
</section>
<style>.location-name-form{display:flex;align-items:center;gap:8px}.location-name-form input{min-width:190px;max-width:320px}</style>
@endsection
