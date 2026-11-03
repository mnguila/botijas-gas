<?php

namespace Tests\Feature;

use App\Models\AccessGroup;
use App\Models\Customer;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_have_multiple_contacts_and_be_edited_or_deactivated(): void
    {
        $owner = User::create(['name' => 'Administrador', 'username' => 'admin', 'password' => 'password12345', 'is_active' => true]);
        $owner->forceFill(['is_super_admin' => true])->save();
        $this->actingAs($owner);

        $this->get('/modules')->assertOk()->assertSee('Gestão de Clientes');
        $this->get('/customers')->assertOk()->assertSee('Adicionar cliente');
        $this->post('/customers', [
            'name' => 'Mercado Central', 'type' => 'organization', 'nuit' => '400123456',
            'physical_address' => 'Av. 25 de Setembro, Maputo', 'is_active' => 1,
            'contacts' => [
                ['type' => 'email', 'value' => 'geral@mercado.example'],
                ['type' => 'phone', 'value' => '+258 21 123 456'],
                ['type' => 'mobile', 'value' => '+258 84 123 4567'],
                ['type' => 'mobile', 'value' => '+258 86 765 4321'],
            ],
        ])->assertRedirect('/customers')->assertSessionHas('success');

        $customer = Customer::firstOrFail();
        $this->assertTrue($customer->is_active);
        $this->assertCount(4, $customer->contacts);
        $this->get('/customers')->assertOk()->assertSee('Mercado Central')->assertSee('400123456')->assertSee('geral@mercado.example')->assertSee('+258 86 765 4321');

        $this->patch("/customers/{$customer->id}", [
            'name' => 'Mercado Central Lda', 'type' => 'organization', 'nuit' => '400123457',
            'physical_address' => 'Av. 25 de Setembro, n.º 10, Maputo', 'is_active' => 0,
            'contacts' => [['type' => 'email', 'value' => 'contacto@mercado.example']],
        ])->assertRedirect('/customers')->assertSessionHas('success');
        $this->assertFalse($customer->fresh()->is_active);
        $this->assertSame('400123457', $customer->fresh()->nuit);
        $this->assertSame(['contacto@mercado.example'], $customer->contacts()->pluck('value')->all());

        $this->patch("/customers/{$customer->id}/status")->assertSessionHas('success');
        $this->assertTrue($customer->fresh()->is_active);
        $this->patch("/customers/{$customer->id}/status")->assertSessionHas('success');
        $this->assertFalse($customer->fresh()->is_active);
    }

    public function test_customer_validation_and_module_permission(): void
    {
        $this->artisan('db:seed')->assertSuccessful();
        $permission = Permission::where('key', 'customers.manage')->firstOrFail();
        $group = AccessGroup::create(['name' => 'Clientes']);
        $group->permissions()->attach($permission);
        $manager = User::create(['name' => 'Gestora', 'username' => 'gestora', 'password' => 'password12345', 'is_active' => true]);
        $manager->groups()->attach($group);
        $this->actingAs($manager);

        $this->get('/')->assertRedirect('/modules');
        $this->get('/modules')->assertOk()->assertSee('href="'.route('customers.index').'"', false)
            ->assertDontSee('href="'.route('access.users').'"', false);
        $this->get('/customers')->assertOk()->assertSee('Gestão de Clientes')
            ->assertDontSee('href="'.route('sales.index').'"', false);
        $this->get('/access/users')->assertForbidden();

        $this->post('/customers', [
            'name' => 'Cliente A', 'type' => 'person', 'nuit' => '400123458', 'physical_address' => 'Maputo', 'is_active' => 1,
            'contacts' => [['type' => 'email', 'value' => 'invalido']],
        ])->assertSessionHasErrors('contacts.0.value');
        $this->assertSame(0, Customer::count());
        $this->post('/customers', [
            'name' => 'Cliente A', 'type' => 'person', 'nuit' => '400123458', 'physical_address' => 'Maputo',
            'contacts' => [['type' => 'mobile', 'value' => '+258 84 123 4567']],
        ])->assertSessionHas('success');
        $this->assertFalse(Customer::firstOrFail()->is_active);
        $this->post('/customers', [
            'name' => 'Cliente B', 'type' => 'organization', 'nuit' => '400123458', 'physical_address' => 'Matola',
            'contacts' => [['type' => 'mobile', 'value' => '+258 84 123 4568']],
        ])->assertSessionHasErrors('nuit');
        $this->post('/customers', [
            'name' => 'Cliente B', 'type' => 'organization', 'physical_address' => 'Matola',
            'contacts' => [['type' => 'mobile', 'value' => '+258 84 123 4568']],
        ])->assertSessionHasErrors('nuit');
        $this->assertSame(1, Customer::count());

        $outsider = User::create(['name' => 'Outro', 'username' => 'outro', 'password' => 'password12345', 'is_active' => true]);
        $this->actingAs($outsider);
        $this->get('/customers')->assertForbidden();
        $this->post('/customers', ['name' => 'Impossível'])->assertForbidden();
        $this->patch('/customers/1/status')->assertForbidden();
    }
}
