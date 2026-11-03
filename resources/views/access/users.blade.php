@extends('layouts.app')
@section('title', 'Utilizadores')
@section('content')
<span class="section-eyebrow">Administração</span>
<h2 class="page-title">Utilizadores</h2>
<p class="page-subtitle">Crie contas e atribua grupos ou permissões individuais, incluindo vendas e alocações.</p>
<form method="post" action="{{ route('access.users.store') }}">@csrf
    <section class="panel">
        <h2>Adicionar utilizador</h2>
    
            <div class="fields three">
                <label>Nome<input name="name" maxlength="100" value="{{ old('name') }}" required></label>
                <label>Username<input name="username" maxlength="50" value="{{ old('username') }}" autocomplete="off" required></label>
                <label>Password<input type="password" name="password" minlength="10" autocomplete="new-password" required></label>
                <label>Confirmar password<input type="password" name="password_confirmation" minlength="10" autocomplete="new-password" required></label>
            </div>
            <label class="check"><input type="checkbox" name="is_active" value="1" checked> Conta ativa</label>
            <fieldset class="permission-list"><legend>Grupos</legend>@forelse($groups as $group)
                <label class="check"><input type="checkbox" name="groups[]" value="{{ $group->id }}">{{ $group->name }}</label>
            @empty<span class="muted">Ainda não há grupos.</span>@endforelse</fieldset>
        
    </section>

    <section class="panel">
        <h2>Permissões individuais</h2>
        <p class="muted">As opções estão organizadas por módulo.</p>
            @include('access.permission-fields', ['selectedPermissionIds' => old('permissions', [])])
    </section>

    <button type="submit">Adicionar utilizador</button>

</form>
<section class="panel">
    <h2>Utilizadores registados</h2>
    <form class="user-search-form" method="get" action="{{ route('access.users') }}" role="search">
        <label for="user-search">Procurar utilizador<input id="user-search" type="search" name="q" maxlength="100" value="{{ $search }}" placeholder="Nome ou username"></label>
        <button type="submit">Procurar</button>
        @if($search !== '')<a class="small-link" href="{{ route('access.users') }}">Limpar pesquisa</a>@endif
    </form>
    <p class="muted">{{ number_format($users->total(), 0, ',', '.') }} {{ $users->total() === 1 ? 'utilizador encontrado' : 'utilizadores encontrados' }}@if($search !== '') para “{{ $search }}”@endif</p>
    <div class="tablewrap"><table>
        <thead><tr><th>Nome</th><th>Username</th><th>Estado</th><th>Grupos</th><th>Permissões individuais</th><th>Ação</th></tr></thead>
        <tbody>@forelse($users as $user)<tr>
            <td><strong>{{ $user->name }}</strong>@if($user->is_super_admin) <span class="pill">Administrador principal</span>@endif</td>
            <td>{{ $user->username }}</td>
            <td>{{ $user->is_active ? 'Ativo' : 'Inativo' }}</td>
            <td>{{ $user->groups->pluck('name')->join(', ') ?: '—' }}</td>
            <td>{{ $user->directPermissions->pluck('label')->join(', ') ?: '—' }}</td>
            <td>@if($grantScope->canManageUser(auth()->user(), $user))<a class="small-link" href="{{ route('access.users.edit', $user) }}">Editar</a>@endif</td>
        </tr>@empty<tr><td colspan="6" class="muted">{{ $search !== '' ? 'Nenhum utilizador corresponde à pesquisa.' : 'Ainda não há utilizadores.' }}</td></tr>@endforelse</tbody>
    </table></div>
    @if($users->hasPages())<nav class="history-pages" aria-label="Páginas dos utilizadores">@if($users->previousPageUrl())<a href="{{ $users->previousPageUrl() }}">← Anterior</a>@endif<span>Página {{ $users->currentPage() }} de {{ $users->lastPage() }}</span>@if($users->nextPageUrl())<a href="{{ $users->nextPageUrl() }}">Próxima →</a>@endif</nav>@endif
</section>
@endsection
