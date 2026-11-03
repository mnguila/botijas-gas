@extends('layouts.app')
@section('title', 'Grupos')
@section('content')
<span class="section-eyebrow">Administração</span>
<h2 class="page-title">Grupos de utilizadores</h2>
<p class="page-subtitle">Crie grupos com acesso às áreas necessárias.</p>
<section class="panel">
    <h2>Adicionar grupo</h2>
    <form method="post" action="{{ route('access.groups.store') }}">@csrf
        <label>Nome do grupo<input name="name" maxlength="100" value="{{ old('name') }}" required></label>
        @include('access.permission-fields', ['selectedPermissionIds' => old('permissions', [])])
        <button type="submit">Adicionar grupo</button>
    </form>
</section>
<section class="panel">
    <h2>Grupos registados</h2>
    <div class="tablewrap"><table>
        <thead><tr><th>Grupo</th><th>Utilizadores</th><th>Permissões</th><th>Ação</th></tr></thead>
        <tbody>@forelse($groups as $group)<tr>
            <td><strong>{{ $group->name }}</strong></td>
            <td>{{ $group->users_count }}</td>
            <td>{{ $group->permissions->pluck('label')->join(', ') ?: '—' }}</td>
            <td>@if($grantScope->canManageGroup(auth()->user(), $group))<a class="small-link" href="{{ route('access.groups.edit', $group) }}">Editar</a>@endif</td>
        </tr>@empty<tr><td colspan="4" class="muted">Ainda não há grupos.</td></tr>@endforelse</tbody>
    </table></div>
</section>
@endsection
