@foreach(config('access.modules') as $module)
    @php($modulePermissions = $permissions->whereIn('key', $module['permissions']))
    @if($modulePermissions->isNotEmpty())
        <fieldset class="permission-list"><legend>{{ $module['name'] }}</legend>
            @foreach($modulePermissions as $permission)
                <label class="check"><input type="checkbox" name="permissions[]" value="{{ $permission->id }}" @checked(in_array($permission->id, $selectedPermissionIds))>{{ $permission->label }}</label>
            @endforeach
        </fieldset>
    @endif
@endforeach
@php($delegationPermissions = $permissions->filter(fn ($permission) => str_starts_with($permission->key, 'access.grant.')))
@if($delegationPermissions->isNotEmpty())
    <fieldset class="permission-list"><legend>Delegação de acessos</legend>
        @foreach($delegationPermissions as $permission)
            <label class="check"><input type="checkbox" name="permissions[]" value="{{ $permission->id }}" @checked(in_array($permission->id, $selectedPermissionIds))>{{ $permission->label }}</label>
        @endforeach
    </fieldset>
@endif
