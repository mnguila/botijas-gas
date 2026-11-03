@extends('layouts.app')
@section('title', 'Editar grupo')
@section('content')
<span class="section-eyebrow">Administração</span>
<h2 class="page-title">Editar grupo</h2>
<p class="page-subtitle">{{ $group->name }}</p>
<section class="panel">
    <form method="post" action="{{ route('access.groups.update', $group) }}">@csrf @method('PATCH')
        <label>Nome do grupo<input name="name" maxlength="100" value="{{ old('name', $group->name) }}" required></label>
        @include('access.permission-fields', ['selectedPermissionIds' => old('permissions', $group->permissions->pluck('id')->all())])
        <div class="form-actions"><button type="submit">Guardar grupo</button><a href="{{ route('access.groups') }}">Cancelar</a></div>
    </form>
</section>
@endsection
