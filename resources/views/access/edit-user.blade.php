@extends('layouts.app')
@section('title', 'Editar utilizador')
@section('content')
<span class="section-eyebrow">Administração</span>
<h2 class="page-title">Editar utilizador</h2>
<p class="page-subtitle">{{ $user->name }} · {{ $user->username }}</p>
<section class="panel">
    <form method="post" action="{{ route('access.users.update', $user) }}">@csrf @method('PATCH')
        <div class="fields three">
            <label>Nome<input name="name" maxlength="100" value="{{ old('name', $user->name) }}" required></label>
            <label>Username<input name="username" maxlength="50" value="{{ old('username', $user->username) }}" required></label>
            <label>Nova password <span class="muted">(deixe em branco para manter)</span><input type="password" name="password" minlength="10" autocomplete="new-password"></label>
            <label>Confirmar nova password<input type="password" name="password_confirmation" minlength="10" autocomplete="new-password"></label>
        </div>
        @if($user->is_super_admin)
            <p class="muted">Administrador principal: acesso total permanente.</p>
        @else
            <label class="check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active))> Conta ativa</label>
            <fieldset class="permission-list"><legend>Grupos</legend>@foreach($groups as $group)
                <label class="check"><input type="checkbox" name="groups[]" value="{{ $group->id }}" @checked(in_array($group->id, old('groups', $user->groups->pluck('id')->all())))>{{ $group->name }}</label>
            @endforeach</fieldset>
            <h3>Permissões individuais</h3><p class="muted">As opções estão organizadas por módulo. A margem de lucro é sempre atribuída individualmente.</p>
            @include('access.permission-fields', ['selectedPermissionIds' => old('permissions', $user->directPermissions->pluck('id')->all())])
        @endif
        <div class="form-actions"><button type="submit">Guardar utilizador</button><a href="{{ route('access.users') }}">Cancelar</a></div>
    </form>
</section>
@endsection
