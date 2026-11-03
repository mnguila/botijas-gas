<?php

namespace Tests\Feature;

use App\Models\AccessGroup;
use App\Models\Location;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductStockEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CatalogAndAccessTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $user = User::create(['name' => 'Administrador', 'username' => 'admin', 'password' => 'supersecret123', 'is_active' => true]);
        $user->is_super_admin = true;
        $user->save();
        return $user;
    }

    public function test_catalog_access_sales_and_allocation_pages_are_available(): void
    {
        $this->actingAs($this->owner());
        $this->get('/')->assertRedirect('/modules');
        $this->get('/dashboard')->assertOk()->assertSee('Investimento em produtos');
        $this->get('/products')->assertOk()->assertSee('Adicionar produto');
        $this->get('/locations')->assertOk()->assertSee('Adicionar local de venda');
        $this->get('/access/users')->assertOk()->assertSee('Adicionar utilizador');
        $this->get('/access/groups')->assertOk()->assertSee('Adicionar grupo');
        $this->get('/sales')->assertOk()->assertSee('Vendas de botijas');
        $this->get('/allocations')->assertOk()->assertSee('Alocar botijas às bancas');
        foreach (['/stock', '/movements'] as $path) $this->get($path)->assertNotFound();
        $this->assertFalse(Schema::hasTable('stock_movements'));
        $this->assertTrue(Schema::hasColumn('users', 'sales_location_id'));
    }

    public function test_product_and_sales_location_registration(): void
    {
        $this->actingAs($this->owner());
        $this->post('/products', ['brand' => 'Petro Gas', 'weight_kg' => 9, 'description' => 'Botija doméstica de 9 kg', 'quantity' => 50, 'purchase_price' => 680, 'reseller_price' => 750, 'unit_price' => 790])->assertSessionHas('success');
        $this->assertSame(1, Product::count());
        $this->assertSame(50, Product::firstOrFail()->quantity);
        $this->assertSame('Botija doméstica de 9 kg', Product::firstOrFail()->description);
        $this->get('/products')->assertOk()->assertSee('Botija doméstica de 9 kg');
        $this->assertDatabaseHas('product_stock_entries', ['product_id' => Product::firstOrFail()->id, 'quantity_added' => 50, 'quantity_before' => 0, 'quantity_after' => 50, 'source' => 'initial']);
        $this->post('/products', ['brand' => 'Petro Gas', 'weight_kg' => 9, 'quantity' => 50, 'purchase_price' => 680, 'reseller_price' => 750, 'unit_price' => 790])->assertSessionHasErrors('brand');
        $this->get('/locations')->assertOk()->assertSee('Nome do local físico')
            ->assertDontSee('Carro')->assertDontSee('name="plate"', false)
            ->assertDontSee('name="kind"', false);
        $this->post('/locations', ['name' => 'Carro A', 'kind' => 'vehicle'])->assertSessionHasErrors('kind');
        $this->post('/locations', ['name' => 'Local com matrícula', 'plate' => 'ABC-123-MP'])->assertSessionHasErrors('plate');
        $this->post('/locations', ['name' => 'Banca 1'])->assertSessionHas('success');
        $this->assertDatabaseHas('locations', ['name' => 'Banca 1', 'kind' => 'physical', 'plate' => null, 'is_sales_point' => true]);
        $this->assertSame(1, Location::count());
    }

    public function test_sales_location_can_be_renamed_deactivated_and_reactivated_without_losing_stock(): void
    {
        $this->actingAs($this->owner());
        $product = Product::create(['brand' => 'Petro Gas', 'weight_kg' => 9, 'quantity' => 10, 'purchase_price' => 100, 'reseller_price' => 130, 'unit_price' => 150]);
        $stand = Location::create(['name' => 'Banca Antiga', 'kind' => 'physical', 'is_sales_point' => true]);
        $other = Location::create(['name' => 'Banca Outra', 'kind' => 'physical', 'is_sales_point' => true]);
        $this->post('/allocations', ['product_id' => $product->id, 'location_id' => $stand->id, 'quantity' => 4])->assertSessionHas('success');

        $this->patch("/locations/{$stand->id}", ['name' => ' '.$other->name.' '])->assertSessionHasErrors('name');
        $this->patch("/locations/{$stand->id}", ['name' => 'Banca Centro'])->assertSessionHas('success');
        $this->assertSame('Banca Centro', $stand->fresh()->name);
        $this->patch("/locations/{$stand->id}/status")->assertSessionHas('success');
        $this->assertFalse($stand->fresh()->is_active);
        $this->get('/locations')->assertOk()->assertSee('Banca Centro')->assertSee('Inativo')->assertSee('Ativar');
        $this->get('/allocations')->assertOk()->assertSee('Banca Centro (inativo)')->assertViewHas('standTotals', fn ($totals) => $totals[$stand->id] === 4);
        $this->get('/sales')->assertOk()->assertViewHas('stands', fn ($stands) => ! $stands->contains('id', $stand->id));
        $this->get('/sales/points')->assertOk()->assertViewHas('salesPoints', fn ($points) => ! $points->contains('id', $stand->id));
        $this->get('/sales/history')->assertOk()->assertViewHas('points', fn ($points) => $points->contains('id', $stand->id));
        $this->post('/allocations', ['product_id' => $product->id, 'location_id' => $stand->id, 'quantity' => 1])->assertSessionHasErrors('location_id');
        $this->post('/allocation-requests', ['location_id' => $stand->id, 'quantities' => [$product->id => 1]])->assertSessionHasErrors('location_id');
        $this->post('/sales/unit', ['product_id' => $product->id, 'location_id' => $stand->id, 'quantity' => 1])->assertSessionHasErrors('location_id');
        $this->assertDatabaseHas('product_stand_stocks', ['product_id' => $product->id, 'location_id' => $stand->id, 'quantity' => 4]);

        $this->patch("/locations/{$stand->id}/status")->assertSessionHas('success');
        $this->assertTrue($stand->fresh()->is_active);
        $this->post('/sales/unit', ['product_id' => $product->id, 'location_id' => $stand->id, 'quantity' => 1])->assertSessionHas('success');
    }

    public function test_dashboard_shows_investment_per_active_product_and_totals(): void
    {
        $this->actingAs($this->owner());
        Product::create(['brand' => 'Petro Gas', 'weight_kg' => 9, 'quantity' => 3, 'purchase_price' => 100.25, 'reseller_price' => 130, 'unit_price' => 150]);
        Product::create(['brand' => 'Galp', 'weight_kg' => 11, 'quantity' => 2, 'purchase_price' => 200, 'reseller_price' => 240, 'unit_price' => 260]);
        Product::create(['brand' => 'Sem custo', 'weight_kg' => 19, 'quantity' => 4, 'purchase_price' => 0, 'reseller_price' => 0, 'unit_price' => 0]);
        $archived = Product::create(['brand' => 'Antigo', 'weight_kg' => 48, 'quantity' => 10, 'purchase_price' => 500, 'reseller_price' => 600, 'unit_price' => 700]);
        $archived->delete();

        $this->get('/dashboard')->assertOk()
            ->assertViewHas('totalInvestmentCents', 70075)
            ->assertViewHas('totalUnits', 9)
            ->assertViewHas('productCount', 3)
            ->assertViewHas('productsWithStock', 3)
            ->assertViewHas('unpricedCount', 1)
            ->assertViewHas('resellerProfitCents', 16925)
            ->assertViewHas('unitProfitCents', 26925)
            ->assertViewHas('projectedUnits', 5)
            ->assertSee('700,75 MT')->assertSee('300,75 MT')->assertSee('400,00 MT')
            ->assertSee('Lucro potencial do estoque')->assertSee('169,25')->assertSee('269,25')
            ->assertSee('Sem custo')->assertDontSee('Botija 48Kg · Antigo');
    }

    public function test_profit_projection_excludes_incomplete_prices_and_shows_possible_loss(): void
    {
        $this->actingAs($this->owner());
        Product::create(['brand' => 'Completo', 'weight_kg' => 9, 'quantity' => 3, 'purchase_price' => 100, 'reseller_price' => 120, 'unit_price' => 150]);
        Product::create(['brand' => 'Sem preço unitário', 'weight_kg' => 11, 'quantity' => 4, 'purchase_price' => 100, 'reseller_price' => 120, 'unit_price' => 0]);
        Product::create(['brand' => 'Margem negativa', 'weight_kg' => 6, 'quantity' => 2, 'purchase_price' => 100, 'reseller_price' => 90, 'unit_price' => 110]);

        $this->get('/dashboard')->assertOk()
            ->assertViewHas('resellerProfitCents', 4000)
            ->assertViewHas('unitProfitCents', 17000)
            ->assertViewHas('projectedUnits', 5)
            ->assertSee('-20,00 MT')->assertSee('40,00')->assertSee('170,00');
    }

    public function test_legacy_vehicle_is_not_offered_as_a_new_sales_location(): void
    {
        $this->actingAs($this->owner());
        $vehicle = Location::create([
            'name' => 'Carro legado', 'kind' => 'vehicle',
            'plate' => 'ABC-123-MP', 'is_sales_point' => true,
        ]);
        $this->get('/locations')->assertOk()->assertDontSee('Carro legado');
        $this->get('/access/users')->assertOk()->assertDontSee('Carro legado');
        $this->post('/access/users', [
            'name' => 'Vendedor', 'username' => 'vendedor',
            'password' => 'password12345', 'password_confirmation' => 'password12345',
            'is_active' => 1, 'sales_location_id' => $vehicle->id,
        ])->assertSessionHasErrors('sales_location_id');
        $this->assertDatabaseHas('locations', ['id' => $vehicle->id, 'kind' => 'vehicle']);
    }

    public function test_product_can_be_edited_archived_and_restored_with_history_preserved(): void
    {
        $this->actingAs($this->owner());
        $product = Product::create(['brand' => 'Petro Gas', 'weight_kg' => 9, 'quantity' => 50, 'purchase_price' => 680, 'reseller_price' => 750, 'unit_price' => 790]);
        $this->get("/products/{$product->id}/edit")->assertOk()->assertSee('Quantidade atual:')->assertDontSee('name="quantity"', false);
        $this->patch("/products/{$product->id}", ['brand' => 'Petro Gas', 'weight_kg' => 9, 'description' => 'Botija para uso doméstico', 'quantity' => 35, 'purchase_price' => 690, 'reseller_price' => 760, 'unit_price' => 800])->assertRedirect('/products');
        $this->assertDatabaseHas('products', ['id' => $product->id, 'description' => 'Botija para uso doméstico', 'quantity' => 50, 'purchase_price' => 690]);
        $this->post("/products/{$product->id}/stock", ['quantity_added' => 15, 'note' => 'Fornecedor'])->assertSessionHas('success');
        $this->post("/products/{$product->id}/stock", ['quantity_added' => 5])->assertSessionHas('success');
        $this->post("/products/{$product->id}/stock", ['quantity_added' => 0])->assertSessionHasErrors('quantity_added');
        $this->assertSame(70, $product->fresh()->quantity);
        $this->assertDatabaseHas('product_stock_entries', ['product_id' => $product->id, 'quantity_added' => 15, 'quantity_before' => 50, 'quantity_after' => 65, 'note' => 'Fornecedor']);
        $this->assertDatabaseHas('product_stock_entries', ['product_id' => $product->id, 'quantity_added' => 5, 'quantity_before' => 65, 'quantity_after' => 70]);

        $this->delete("/products/{$product->id}")->assertSessionHas('success');
        $this->assertSoftDeleted('products', ['id' => $product->id]);
        $this->assertSame(0, Product::count());
        $this->get('/products')->assertOk()->assertSee('Histórico de produtos apagados')->assertSee('Botija 9Kg · Petro Gas')->assertSee('Botija para uso doméstico')->assertSee('Restaurar');
        $this->assertSame($product->id, ProductStockEntry::firstOrFail()->product->id);
        $this->post("/products/{$product->id}/stock", ['quantity_added' => 1])->assertNotFound();
        $this->post('/products', ['brand' => 'Petro Gas', 'weight_kg' => 9, 'quantity' => 1, 'purchase_price' => 690, 'reseller_price' => 760, 'unit_price' => 800])->assertSessionHasErrors('brand');

        $this->patch("/products/{$product->id}/restore")->assertSessionHas('success');
        $this->assertNotSoftDeleted('products', ['id' => $product->id]);
        $this->assertSame(70, $product->fresh()->quantity);
    }

    public function test_group_and_user_registration_control_access(): void
    {
        $this->artisan('db:seed')->assertSuccessful();
        $owner = $this->owner();
        $products = Permission::where('key', 'products.manage')->firstOrFail();
        $this->actingAs($owner);
        $this->post('/access/groups', ['name' => 'Catálogo', 'permissions' => [$products->id]])->assertSessionHas('success');
        $group = AccessGroup::where('name', 'Catálogo')->firstOrFail();
        $this->post('/access/users', [
            'name' => 'Operador', 'username' => 'operador', 'password' => 'operador12345',
            'password_confirmation' => 'operador12345', 'is_active' => 1, 'groups' => [$group->id],
        ])->assertSessionHas('success');
        $operator = User::where('username', 'operador')->firstOrFail();
        $this->assertNotSame('operador12345', $operator->password);
        $this->actingAs($operator);
        $this->get('/dashboard')->assertOk();
        $this->get('/products')->assertOk();
        $this->get('/locations')->assertForbidden();
        $this->get('/access/users')->assertForbidden();
    }

    public function test_users_can_be_searched_by_name_or_username(): void
    {
        $owner = $this->owner();
        $ana = User::create(['name' => 'Ana Manuel', 'username' => 'ana', 'password' => 'password12345', 'is_active' => true]);
        $bruno = User::create(['name' => 'Bruno Costa', 'username' => 'balcao.norte', 'password' => 'password12345', 'is_active' => true]);
        $this->actingAs($owner);

        $this->get('/access/users?q=Ana')->assertOk()
            ->assertSee('value="Ana"', false)
            ->assertViewHas('users', fn ($users) => $users->total() === 1 && $users->first()->id === $ana->id);
        $this->get('/access/users?q=balcao')->assertOk()
            ->assertViewHas('users', fn ($users) => $users->total() === 1 && $users->first()->id === $bruno->id);
        $this->get('/access/users?q=inexistente')->assertOk()->assertSee('Nenhum utilizador corresponde à pesquisa.');
        $this->get('/access/users')->assertOk()->assertViewHas('users', fn ($users) => $users->total() === 3);
    }

    public function test_sales_point_assignment_is_managed_in_the_sales_module(): void
    {
        $this->artisan('db:seed')->assertSuccessful();
        $owner = $this->owner();
        $seller = User::create(['name' => 'Vendedora', 'username' => 'vendedora', 'password' => 'password12345', 'is_active' => true]);
        $seller->directPermissions()->attach(Permission::where('key', 'sales.create')->firstOrFail());
        $stand = Location::create(['name' => 'Banca Norte', 'kind' => 'physical', 'is_sales_point' => true]);
        $vehicle = Location::create(['name' => 'Carro antigo', 'kind' => 'vehicle', 'is_sales_point' => true]);

        $this->actingAs($owner);
        $this->get('/access/users')->assertOk()->assertDontSee('name="sales_location_id"', false);
        $this->get(route('access.users.edit', $seller))->assertOk()->assertDontSee('name="sales_location_id"', false);
        $this->patch(route('access.users.update', $seller), [
            'name' => $seller->name, 'username' => $seller->username, 'is_active' => 1,
            'sales_location_id' => $stand->id,
        ])->assertSessionHasErrors('sales_location_id');
        $this->get('/sales/points')->assertOk()->assertSee('Vendedora')->assertSee('Banca Norte');
        $this->patch(route('sales.points.update', $seller), ['sales_location_id' => $vehicle->id])->assertSessionHasErrors('sales_location_id');
        $this->patch(route('sales.points.update', $seller), ['sales_location_id' => $stand->id])->assertSessionHas('success');
        $this->assertSame($stand->id, $seller->fresh()->sales_location_id);
        $this->patch(route('sales.points.update', $seller), ['sales_location_id' => ''])->assertSessionHas('success');
        $this->assertNull($seller->fresh()->sales_location_id);

        $this->actingAs($seller);
        $this->get('/sales/points')->assertForbidden();
    }

    public function test_access_manager_can_grant_only_authorized_module_permissions(): void
    {
        $this->artisan('db:seed')->assertSuccessful();
        $owner = $this->owner();
        $access = Permission::where('key', 'access.manage')->firstOrFail();
        $botijasGrant = Permission::where('key', 'access.grant.botijas')->firstOrFail();
        $sales = Permission::where('key', 'sales.create')->firstOrFail();
        $customs = Permission::where('key', 'customs.manage')->firstOrFail();
        $manager = User::create(['name' => 'Gestor de acessos', 'username' => 'gestor-acessos', 'password' => 'password12345', 'is_active' => true]);
        $manager->directPermissions()->attach([$access->id, $botijasGrant->id]);
        $target = User::create(['name' => 'Operador', 'username' => 'operador', 'password' => 'password12345', 'is_active' => true]);
        $protected = User::create(['name' => 'Despachante', 'username' => 'despachante', 'password' => 'password12345', 'is_active' => true]);
        $protected->directPermissions()->attach($customs);

        $this->actingAs($manager);
        $this->get('/access/users')->assertOk()->assertSee('Registar vendas a grosso e por unidade')
            ->assertViewHas('permissions', fn ($permissions) => $permissions->contains('id', $sales->id) && ! $permissions->contains('id', $customs->id));
        $this->post('/access/users', [
            'name' => 'Novo', 'username' => 'novo', 'password' => 'password12345',
            'password_confirmation' => 'password12345', 'is_active' => 1,
            'permissions' => [$customs->id],
        ])->assertSessionHasErrors('permissions.0');
        $this->post('/access/groups', ['name' => 'Aduana', 'permissions' => [$customs->id]])
            ->assertSessionHasErrors('permissions.0');
        $this->patch(route('access.users.update', $target), [
            'name' => $target->name, 'username' => $target->username, 'is_active' => 1,
            'permissions' => [$sales->id],
        ])->assertSessionHas('success');
        $this->assertTrue($target->fresh()->hasPermission('sales.create'));
        $this->get(route('access.users.edit', $protected))->assertForbidden();
        $this->patch(route('access.users.update', $protected), [
            'name' => $protected->name, 'username' => $protected->username,
            'password' => 'hijack12345', 'password_confirmation' => 'hijack12345',
        ])->assertForbidden();
        $this->assertFalse($protected->fresh()->hasPermission('sales.create'));

        $this->actingAs($owner);
        $this->get('/access/users')->assertOk()->assertSee('Atribuir acessos do módulo Vendas de botijas');
    }

    public function test_username_login_and_inactive_account(): void
    {
        $owner = $this->owner();
        $this->get('/products')->assertRedirect('/login');
        $this->post('/login', ['username' => 'ADMIN', 'password' => 'supersecret123'])->assertRedirect('/modules');
        $this->post('/logout')->assertRedirect('/login');
        $owner->is_active = false;
        $owner->save();
        $this->post('/login', ['username' => 'admin', 'password' => 'supersecret123'])->assertSessionHasErrors('username');
    }

    public function test_module_menus_separate_sales_and_access_management_by_permission(): void
    {
        $this->get('/modules')->assertRedirect('/login');
        $this->artisan('db:seed')->assertSuccessful();

        $owner = $this->owner();
        $this->actingAs($owner);
        $this->get('/modules')->assertOk()->assertSee('Vendas de botijas')
            ->assertSee('Gestão de acessos')
            ->assertSee('href="'.route('dashboard.index').'"', false)
            ->assertSee('href="'.route('access.users').'"', false)
            ->assertSee('MÓDULO 01')->assertSee('MÓDULO 02');
        $this->get('/sales')->assertOk()->assertSee('Vendas de botijas')
            ->assertSee('OPERAÇÃO')->assertSee('CONFIGURAÇÃO')
            ->assertSee('href="'.route('allocations.index').'"', false)
            ->assertSee('href="'.route('products.index').'"', false)
            ->assertSee('href="'.route('locations.index').'"', false)
            ->assertDontSee('href="'.route('access.users').'"', false)
            ->assertDontSee('href="'.route('access.groups').'"', false);
        $this->get('/access/users')->assertOk()->assertSee('Gestão de acessos')
            ->assertSee('href="'.route('access.groups').'"', false)
            ->assertDontSee('href="'.route('sales.index').'"', false)
            ->assertDontSee('href="'.route('products.index').'"', false);
        $this->get('/profile')->assertOk()->assertSee('Escolher módulo')
            ->assertDontSee('href="'.route('sales.index').'"', false)
            ->assertDontSee('href="'.route('access.users').'"', false);

        $seller = User::create(['name' => 'Vendedor', 'username' => 'vendedor', 'password' => 'password12345', 'is_active' => true]);
        $seller->directPermissions()->attach(Permission::where('key', 'sales.create')->firstOrFail());
        $this->actingAs($seller);
        $this->get('/modules')->assertOk()->assertSee('href="'.route('sales.index').'"', false)
            ->assertSee('Gestão de acessos')
            ->assertSee('Sem acesso atribuído')
            ->assertDontSee('href="'.route('access.users').'"', false);
        $this->get('/sales')->assertOk()->assertSee('Vendas')->assertDontSee('href="'.route('access.users').'"', false);
        $this->get('/access/users')->assertForbidden();

        $accessManager = User::create(['name' => 'Gestor de acessos', 'username' => 'gestor', 'password' => 'password12345', 'is_active' => true]);
        $accessManager->directPermissions()->attach(Permission::where('key', 'access.manage')->firstOrFail());
        $this->actingAs($accessManager);
        $this->get('/modules')->assertOk()->assertSee('href="'.route('access.users').'"', false)
            ->assertDontSee('href="'.route('sales.index').'"', false);
        $this->get('/access/groups')->assertOk()->assertSee('Gestão de acessos')
            ->assertSee('href="'.route('access.users').'"', false)
            ->assertDontSee('href="'.route('sales.index').'"', false);
        $this->get('/sales')->assertForbidden();

        $withoutAccess = User::create(['name' => 'Sem acesso', 'username' => 'semacesso', 'password' => 'password12345', 'is_active' => true]);
        $this->actingAs($withoutAccess);
        $this->get('/modules')->assertOk()->assertSee('Sem acesso atribuído')->assertDontSee('Abrir módulo');
        $this->get('/sales')->assertForbidden();
    }

    public function test_sales_profit_is_visible_only_with_individual_permission(): void
    {
        $this->artisan('db:seed')->assertSuccessful();
        $owner = $this->owner();
        $salesPermission = Permission::where('key', 'sales.create')->firstOrFail();
        $dashboardPermission = Permission::where('key', 'products.manage')->firstOrFail();
        $profitPermission = Permission::where('key', 'sales.profit.view')->firstOrFail();
        $group = AccessGroup::create(['name' => 'Vendedores']);
        $group->permissions()->attach([$salesPermission->id, $dashboardPermission->id]);
        Product::create(['brand' => 'Produto de teste', 'weight_kg' => 9, 'quantity' => 2, 'purchase_price' => 100, 'reseller_price' => 140, 'unit_price' => 160]);
        $point = Location::create(['name' => 'Banca 1', 'kind' => 'physical', 'is_sales_point' => true]);
        $seller = User::create([
            'name' => 'Vendedor', 'username' => 'vendedor', 'password' => 'password12345',
            'is_active' => true, 'sales_location_id' => $point->id,
        ]);
        $seller->groups()->attach($group);

        $this->actingAs($seller);
        $this->get('/sales')->assertOk()->assertDontSee('margem')->assertDontSee('Margem');
        $this->get('/dashboard')->assertOk()->assertDontSee('Margem')->assertDontSee('Lucro potencial')->assertDontSee('Lucro revendedor');
        $this->assertFalse($seller->canViewSalesProfit());

        $this->actingAs($owner);
        $this->get('/access/users')->assertOk()->assertSee('Ver margem de lucro das vendas');
        $this->get('/access/groups')->assertOk()->assertDontSee('Ver margem de lucro das vendas');
        $this->post('/access/groups', ['name' => 'Lucros', 'permissions' => [$profitPermission->id]])
            ->assertSessionHasErrors('permissions.0');
        $this->post('/access/users', [
            'name' => 'Gestor', 'username' => 'gestor', 'password' => 'password12345',
            'password_confirmation' => 'password12345', 'is_active' => 1,
            'permissions' => [$profitPermission->id],
        ])->assertSessionHas('success');
        $this->assertTrue(User::where('username', 'gestor')->firstOrFail()->canViewSalesProfit());

        $this->patch(route('access.users.update', $seller), [
            'name' => $seller->name, 'username' => $seller->username, 'is_active' => 1,
            'groups' => [$group->id],
            'permissions' => [$profitPermission->id],
        ])->assertSessionHas('success');
        $this->actingAs($seller);
        $this->get('/sales')->assertOk()->assertSee('margem')->assertSee('Margem');
        $this->get('/dashboard')->assertOk()->assertSee('Margem de hoje')->assertSee('Lucro potencial do estoque');

        $this->actingAs($owner);
        $this->patch(route('access.users.update', $seller), [
            'name' => $seller->name, 'username' => $seller->username, 'is_active' => 1,
            'groups' => [$group->id],
        ])->assertSessionHas('success');
        $this->actingAs($seller);
        $this->get('/sales')->assertOk()->assertDontSee('margem')->assertDontSee('Margem');
        $this->get('/dashboard')->assertOk()->assertDontSee('Margem')->assertDontSee('Lucro potencial')->assertDontSee('Lucro revendedor');
    }
}
