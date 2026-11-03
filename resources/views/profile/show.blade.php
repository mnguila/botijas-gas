@extends('layouts.app')
@section('title', 'Meu perfil')
@section('content')
<span class="section-eyebrow">Conta</span>
<h2 class="page-title">Meu perfil</h2>
<p class="page-subtitle">Consulte os seus dados de acesso e mantenha a sua password atualizada.</p>

<div class="profile-grid">
    <div>
        <section class="profile-identity">
            <span class="profile-avatar">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
            <div><span class="profile-overline">PERFIL DE UTILIZADOR</span><h3>{{ $user->name }}</h3><p>{{ '@'.$user->username }}</p></div>
            <span class="profile-status"><span></span>{{ $user->is_active ? 'Conta ativa' : 'Conta inativa' }}</span>
        </section>
        <section class="panel profile-panel">
            <h2>Dados da conta</h2>
            <dl class="profile-facts">
                <div><dt>Nome</dt><dd>{{ $user->name }}</dd></div>
                <div><dt>Username</dt><dd>{{ $user->username }}</dd></div>
                <div><dt>Posto de venda</dt><dd>{{ $user->salesLocation?->display_name ?? 'Sem posto atribuído' }}</dd></div>
                <div><dt>Tipo de conta</dt><dd>{{ $user->is_super_admin ? 'Administrador principal' : 'Utilizador' }}</dd></div>
            </dl>
        </section>
        <section class="panel profile-panel">
            <h2>Acessos</h2>
            <div class="profile-access-row"><strong>Grupos</strong><div class="profile-tags">@forelse($user->groups as $group)<span class="profile-tag">{{ $group->name }}</span>@empty<span class="muted">Nenhum grupo atribuído</span>@endforelse</div></div>
            <div class="profile-access-row"><strong>Permissões</strong><div class="profile-tags">@if($user->is_super_admin)<span class="profile-tag profile-tag-primary">Acesso total</span>@endif @forelse($permissions as $permission)<span class="profile-tag">{{ $permission->label }}</span>@empty @if(!$user->is_super_admin)<span class="muted">Nenhuma permissão atribuída</span>@endif @endforelse</div></div>
        </section>
    </div>
    <section class="panel profile-password-panel">
        <span class="profile-overline">SEGURANÇA DA CONTA</span>
        <h2>Alterar password</h2>
        <p class="muted">Confirme a password atual e escolha uma nova com pelo menos 10 caracteres.</p>
        <form method="post" action="{{ route('profile.password.update') }}">@csrf @method('PATCH')
            <label>Password atual<input type="password" name="current_password" autocomplete="current-password" required></label>
            <label>Nova password<input type="password" name="password" minlength="10" autocomplete="new-password" required></label>
            <label>Confirmar nova password<input type="password" name="password_confirmation" minlength="10" autocomplete="new-password" required></label>
            <button type="submit">Guardar nova password</button>
        </form>
    </section>
</div>
@endsection
