<?php

namespace Tests\Feature;

use App\Models\AccessGroup;
use App\Models\Location;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_see_own_profile_and_change_password_safely(): void
    {
        $this->get('/profile')->assertRedirect('/login');

        $point = Location::create(['name' => 'Banca Centro', 'kind' => 'physical', 'is_sales_point' => true]);
        $permission = Permission::create(['key' => 'sales.create', 'label' => 'Registar vendas']);
        $group = AccessGroup::create(['name' => 'Vendedores']);
        $group->permissions()->attach($permission);
        $user = User::create([
            'name' => 'Maria Vendedora', 'username' => 'maria', 'password' => 'senha-antiga-123',
            'is_active' => true, 'sales_location_id' => $point->id,
        ]);
        $user->groups()->attach($group);

        $this->actingAs($user);
        $this->get('/profile')->assertOk()->assertSee('Maria Vendedora')->assertSee('maria')
            ->assertSee('Banca Centro')->assertSee('Vendedores')->assertSee('Registar vendas')
            ->assertSee('Guardar nova password');
        $this->get('/access/users')->assertForbidden();

        $this->patch('/profile/password', [
            'current_password' => 'errada', 'password' => 'senha-nova-456',
            'password_confirmation' => 'senha-nova-456',
        ])->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check('senha-antiga-123', $user->fresh()->password));

        $this->patch('/profile/password', [
            'current_password' => 'senha-antiga-123', 'password' => 'senha-nova-456',
            'password_confirmation' => 'diferente',
        ])->assertSessionHasErrors('password');

        $this->patch('/profile/password', [
            'current_password' => 'senha-antiga-123', 'password' => 'senha-nova-456',
            'password_confirmation' => 'senha-nova-456',
        ])->assertRedirect('/profile')->assertSessionHas('success');
        $this->assertTrue(Hash::check('senha-nova-456', $user->fresh()->password));

        $this->post('/logout')->assertRedirect('/login');
        $this->post('/login', ['username' => 'maria', 'password' => 'senha-antiga-123'])->assertSessionHasErrors('username');
        $this->post('/login', ['username' => 'maria', 'password' => 'senha-nova-456'])->assertRedirect('/modules');
    }
}
