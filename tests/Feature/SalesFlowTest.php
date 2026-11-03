<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\OperationalExpense;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductAllocation;
use App\Models\ProductSale;
use App\Models\ProductStandStock;
use App\Models\SaleOrder;
use App\Models\Permission;
use App\Models\AccessGroup;
use App\Models\User;
use App\Services\SalesMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesFlowTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $user = User::create(['name' => 'Administrador', 'username' => 'admin', 'password' => 'supersecret123', 'is_active' => true]);
        $user->is_super_admin = true;
        $user->save();
        return $user;
    }

    public function test_product_without_both_sale_prices_stays_in_stock_but_cannot_be_allocated_or_sold(): void
    {
        $this->actingAs($this->owner());
        $stand = Location::create(['name' => 'Banca 1', 'kind' => 'physical', 'is_sales_point' => true]);
        $this->post('/products', [
            'brand' => 'Nova Gás', 'weight_kg' => 9, 'quantity' => 8, 'purchase_price' => 100,
        ])->assertSessionHas('success');
        $product = Product::firstOrFail();
        $this->assertSame(8, $product->quantity);
        $this->assertSame(0.0, (float) $product->reseller_price);
        $this->assertSame(0.0, (float) $product->unit_price);
        $this->get('/products')->assertOk()->assertSee('Pendente de preços')->assertSee('Por definir');
        $this->get('/sales')->assertOk()->assertViewHas('sellableProducts', fn ($products) => $products->isEmpty());
        $this->get('/allocations')->assertOk()->assertViewHas('allocatableProducts', fn ($products) => $products->isEmpty());

        $this->post('/allocations', ['product_id' => $product->id, 'location_id' => $stand->id, 'quantity' => 2])
            ->assertSessionHasErrors('product_id');
        $this->post('/sales/wholesale', [
            'product_id' => $product->id, 'quantity' => 1,
            'truck_plate' => 'ABC-123-MP', 'driver_name' => 'Motorista',
        ])->assertSessionHasErrors('items.0.product_id');
        $this->post('/sales/unit', [
            'product_id' => $product->id, 'location_id' => $stand->id, 'quantity' => 1,
        ])->assertSessionHasErrors('items.0.product_id');
        $this->assertSame(8, $product->fresh()->quantity);
        $this->assertSame(0, ProductAllocation::count());
        $this->assertSame(0, ProductSale::count());

        $this->patch("/products/{$product->id}", [
            'brand' => 'Nova Gás', 'weight_kg' => 9, 'purchase_price' => 100,
            'reseller_price' => 130,
        ])->assertSessionHas('success');
        $this->get('/sales')->assertViewHas('sellableProducts', fn ($products) => $products->isEmpty());

        $this->patch("/products/{$product->id}", [
            'brand' => 'Nova Gás', 'weight_kg' => 9, 'purchase_price' => 100,
            'reseller_price' => 130, 'unit_price' => 150,
        ])->assertSessionHas('success');
        $this->get('/sales')->assertViewHas('sellableProducts', fn ($products) => $products->pluck('id')->all() === [$product->id]);
        $this->get('/allocations')->assertViewHas('allocatableProducts', fn ($products) => $products->pluck('id')->all() === [$product->id]);
        $this->post('/allocations', ['product_id' => $product->id, 'location_id' => $stand->id, 'quantity' => 2])
            ->assertSessionHas('success');
        $this->post('/sales/unit', [
            'product_id' => $product->id, 'location_id' => $stand->id, 'quantity' => 1,
        ])->assertSessionHas('success');
        $this->assertSame(1, ProductSale::count());
    }

    public function test_allocation_wholesale_and_unit_sales_keep_location_balances_and_history(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner);
        $product = Product::create(['brand' => 'Petro Gas', 'weight_kg' => 9, 'quantity' => 20, 'purchase_price' => 100, 'reseller_price' => 140, 'unit_price' => 160]);
        $stand = Location::create(['name' => 'Banca 1', 'kind' => 'physical', 'is_sales_point' => true]);

        $this->post('/allocations', ['product_id' => $product->id, 'location_id' => $stand->id, 'quantity' => 8])->assertSessionHas('success');
        $this->assertSame(20, $product->fresh()->quantity);
        $this->assertDatabaseHas('product_stand_stocks', ['product_id' => $product->id, 'location_id' => $stand->id, 'quantity' => 8]);
        $this->assertSame(1, ProductAllocation::count());

        $this->post('/sales/wholesale', ['product_id' => $product->id, 'quantity' => 5, 'truck_plate' => 'abc-123-mp', 'driver_name' => 'João Silva'])->assertSessionHas('success');
        $this->assertSame(15, $product->fresh()->quantity);
        $this->assertDatabaseHas('product_sales', ['product_id' => $product->id, 'channel' => 'wholesale', 'quantity' => 5, 'truck_plate' => 'ABC-123-MP', 'driver_name' => 'João Silva', 'unit_price' => 140, 'unit_cost' => 100, 'created_by_user_id' => $owner->id]);

        $this->post('/sales/unit', ['product_id' => $product->id, 'location_id' => $stand->id, 'quantity' => 3])->assertSessionHas('success');
        $this->assertSame(12, $product->fresh()->quantity);
        $this->assertSame(5, ProductStandStock::firstOrFail()->quantity);
        $this->assertDatabaseHas('product_sales', ['product_id' => $product->id, 'channel' => 'unit', 'location_id' => $stand->id, 'quantity' => 3, 'unit_price' => 160, 'unit_cost' => 100]);

        $metrics = app(SalesMetrics::class)->build();
        $this->assertSame(70000, $metrics['wholesale']['revenue_cents']);
        $this->assertSame(20000, $metrics['wholesale']['margin_cents']);
        $this->assertSame(48000, $metrics['unit']['revenue_cents']);
        $this->assertSame(18000, $metrics['unit']['margin_cents']);
        $this->get('/sales')->assertOk()->assertSee('ABC-123-MP')->assertSee('João Silva')->assertSee('1.180,00 MT')
            ->assertSee('data-sale-confirm="Venda a grosso"', false)
            ->assertSee('data-sale-confirm="Venda por unidade"', false)
            ->assertSee('data-sale-confirm-dialog', false)->assertSee('Confirmar venda')
            ->assertDontSee('Disponibilidade para vendas');
        $this->get('/dashboard')->assertOk()->assertSee('A grosso · camiões')->assertSee('Por unidade · bancas')->assertSee('1.180,00 MT');
        $this->get('/allocations')->assertOk()->assertSee('Banca 1');

        $this->post("/products/{$product->id}/stock", ['quantity_added' => 4])->assertSessionHas('success');
        $this->assertSame(16, $product->fresh()->quantity);
        $this->get('/allocations')->assertOk()->assertViewHas('centralStock', fn ($stock) => $stock[$product->id] === 11);

        $product->update(['reseller_price' => 200, 'unit_price' => 220, 'purchase_price' => 120]);
        $this->assertSame(140.0, (float) ProductSale::where('channel', 'wholesale')->firstOrFail()->unit_price);
        $this->assertSame(100.0, (float) ProductSale::where('channel', 'unit')->firstOrFail()->unit_cost);
        $product->delete();
        $this->get('/sales')->assertOk()->assertSee('ABC-123-MP')->assertSee('João Silva');
        $this->get('/dashboard')->assertOk()->assertSee('1.180,00 MT');
    }

    public function test_sales_page_shows_available_stock_for_every_product_at_the_selected_point(): void
    {
        $this->artisan('db:seed')->assertSuccessful();
        $productA = Product::create(['brand' => 'Petro Gas', 'weight_kg' => 9, 'quantity' => 20, 'purchase_price' => 100, 'reseller_price' => 140, 'unit_price' => 160]);
        $productB = Product::create(['brand' => 'Galp', 'weight_kg' => 6, 'quantity' => 7, 'purchase_price' => 80, 'reseller_price' => 100, 'unit_price' => 120]);
        $standA = Location::create(['name' => 'Banca Norte', 'kind' => 'physical', 'is_sales_point' => true]);
        $standB = Location::create(['name' => 'Banca Sul', 'kind' => 'physical', 'is_sales_point' => true]);
        ProductStandStock::create(['product_id' => $productA->id, 'location_id' => $standA->id, 'quantity' => 5]);
        ProductStandStock::create(['product_id' => $productB->id, 'location_id' => $standA->id, 'quantity' => 2]);
        ProductStandStock::create(['product_id' => $productA->id, 'location_id' => $standB->id, 'quantity' => 3]);

        $this->actingAs($this->owner());
        $response = $this->get('/sales')->assertOk()
            ->assertSee('Estoque para venda')->assertSee('Estoque central · grosso')
            ->assertSee('Banca Norte · unidade')->assertSee('Banca Sul · unidade')
            ->assertSee('sales-stock-cards', false)
            ->assertViewHas('centralStock', fn ($stock) => $stock[$productA->id] === 12 && $stock[$productB->id] === 5)
            ->assertViewHas('standStock', fn ($stock) => $stock[$standA->id][$productA->id] === 5
                && $stock[$standA->id][$productB->id] === 2 && $stock[$standB->id][$productA->id] === 3);
        $this->assertLessThan(
            strpos($response->getContent(), 'class="operation-grid"'),
            strpos($response->getContent(), 'class="sales-stock-cards"')
        );

        $seller = User::create(['name' => 'Banca Norte', 'username' => 'norte', 'password' => 'password12345', 'is_active' => true, 'sales_location_id' => $standA->id]);
        $seller->directPermissions()->attach(Permission::where('key', 'sales.create')->firstOrFail());
        $this->actingAs($seller);
        $this->get('/sales')->assertOk()->assertSee('Banca Norte')->assertSee('Botija 6Kg · Galp')
            ->assertDontSee('Banca Sul')->assertDontSee('Estoque central · grosso')
            ->assertViewHas('standStock', fn ($stock) => $stock->has($standA->id) && ! $stock->has($standB->id));
    }

    public function test_sales_accumulated_card_shows_only_todays_sales_at_the_users_location(): void
    {
        $this->artisan('db:seed')->assertSuccessful();
        $product = Product::create(['brand' => 'Petro Gas', 'weight_kg' => 9, 'quantity' => 20, 'purchase_price' => 100, 'reseller_price' => 140, 'unit_price' => 160]);
        $north = Location::create(['name' => 'Banca Norte', 'kind' => 'physical', 'is_sales_point' => true]);
        $south = Location::create(['name' => 'Banca Sul', 'kind' => 'physical', 'is_sales_point' => true]);
        $seller = User::create(['name' => 'Vendedor Norte', 'username' => 'vendedor-norte', 'password' => 'password12345', 'is_active' => true, 'sales_location_id' => $north->id]);
        $seller->directPermissions()->attach(Permission::where('key', 'sales.create')->firstOrFail());
        $seller->directPermissions()->attach(Permission::where('key', 'sales.profit.view')->firstOrFail());

        foreach ([[$north, 2, now()], [$north, 4, now()->subDay()], [$south, 3, now()]] as [$location, $quantity, $soldAt]) {
            $order = SaleOrder::create(['channel' => 'unit', 'location_id' => $location->id, 'sold_at' => $soldAt]);
            ProductSale::create([
                'sale_order_id' => $order->id, 'product_id' => $product->id, 'channel' => 'unit',
                'location_id' => $location->id, 'quantity' => $quantity, 'unit_price' => 160,
                'unit_cost' => 100, 'sold_at' => $soldAt,
            ]);
        }

        $response = $this->actingAs($seller)->get('/sales')->assertOk();
        $this->assertMatchesRegularExpression(
            '/<span>Vendas acumuladas · hoje<\/span>\s*<strong>320,00 MT<\/strong>\s*<small>Banca Norte<\/small>\s*<small>Margem do dia 120,00 MT<\/small>/u',
            $response->getContent()
        );
    }

    public function test_allocation_stock_table_shows_total_quantity_for_each_location(): void
    {
        $this->actingAs($this->owner());
        $first = Product::create(['brand' => 'Petro Gas', 'weight_kg' => 9, 'quantity' => 20, 'purchase_price' => 100, 'reseller_price' => 140, 'unit_price' => 160]);
        $second = Product::create(['brand' => 'Galp', 'weight_kg' => 6, 'quantity' => 10, 'purchase_price' => 80, 'reseller_price' => 100, 'unit_price' => 120]);
        $north = Location::create(['name' => 'Banca Norte', 'kind' => 'physical', 'is_sales_point' => true]);
        $south = Location::create(['name' => 'Banca Sul', 'kind' => 'physical', 'is_sales_point' => true]);
        ProductStandStock::create(['product_id' => $first->id, 'location_id' => $north->id, 'quantity' => 5]);
        ProductStandStock::create(['product_id' => $second->id, 'location_id' => $north->id, 'quantity' => 2]);
        ProductStandStock::create(['product_id' => $first->id, 'location_id' => $south->id, 'quantity' => 3]);
        ProductStandStock::create(['product_id' => $second->id, 'location_id' => $south->id, 'quantity' => 1]);

        $response = $this->get('/allocations')->assertOk()
            ->assertViewHas('centralTotal', 19)
            ->assertViewHas('overallTotal', 30)
            ->assertViewHas('standTotals', fn ($totals) => $totals[$north->id] === 7 && $totals[$south->id] === 4)
            ->assertSee('Total por local');
        $this->assertMatchesRegularExpression(
            '/<tr class="allocation-totals">\s*<th scope="row">Total por local<\/th>\s*<th class="num">19<\/th>\s*<th class="num">0<\/th>\s*<th class="num">7<\/th>\s*<th class="num">4<\/th>\s*<th class="num">30<\/th>/s',
            $response->getContent()
        );
    }

    public function test_new_permissions_can_be_assigned_to_existing_group_and_user(): void
    {
        $this->artisan('db:seed')->assertSuccessful();
        $owner = $this->owner();
        $sales = Permission::where('key', 'sales.create')->firstOrFail();
        $allocations = Permission::where('key', 'allocations.manage')->firstOrFail();
        $group = AccessGroup::create(['name' => 'Vendedores']);
        $seller = User::create(['name' => 'Vendedor', 'username' => 'vendedor', 'password' => 'vendedor12345', 'is_active' => true]);
        $seller->groups()->attach($group);

        $this->actingAs($owner);
        $this->get("/access/groups/{$group->id}/edit")->assertOk()->assertSee('Registar vendas a grosso e por unidade');
        $this->patch("/access/groups/{$group->id}", ['name' => 'Vendedores', 'permissions' => [$sales->id]])->assertSessionHas('success');
        $this->get("/access/users/{$seller->id}/edit")->assertOk()->assertSee('Alocar botijas às bancas');
        $this->patch("/access/users/{$seller->id}", ['name' => 'Vendedor', 'username' => 'vendedor', 'is_active' => 1, 'groups' => [$group->id], 'permissions' => [$allocations->id]])->assertSessionHas('success');

        $this->actingAs($seller->fresh());
        $this->get('/sales')->assertOk();
        $this->get('/allocations')->assertOk();
        $this->get('/dashboard')->assertForbidden();
        $this->get('/access/users')->assertForbidden();
    }

    public function test_sales_page_shows_only_shared_group_history_and_general_history_requires_permission(): void
    {
        $this->artisan('db:seed')->assertSuccessful();
        $product = Product::create(['brand' => 'Petro Gas', 'weight_kg' => 9, 'quantity' => 20, 'purchase_price' => 100, 'reseller_price' => 140, 'unit_price' => 160]);
        $salesPermission = Permission::where('key', 'sales.create')->firstOrFail();
        $historyPermission = Permission::where('key', 'sales.history.view')->firstOrFail();
        $groupA = AccessGroup::create(['name' => 'Equipa A']);
        $groupB = AccessGroup::create(['name' => 'Equipa B']);
        $groupA->permissions()->attach($salesPermission);
        $groupB->permissions()->attach($salesPermission);

        $sellerA = User::create(['name' => 'Vendedor A', 'username' => 'vendedora', 'password' => 'password12345', 'is_active' => true]);
        $colleagueA = User::create(['name' => 'Colega A', 'username' => 'colegaa', 'password' => 'password12345', 'is_active' => true]);
        $sellerB = User::create(['name' => 'Vendedor B', 'username' => 'vendedorb', 'password' => 'password12345', 'is_active' => true]);
        $auditor = User::create(['name' => 'Auditor', 'username' => 'auditor', 'password' => 'password12345', 'is_active' => true]);
        $sellerA->groups()->attach($groupA);
        $colleagueA->groups()->attach($groupA);
        $sellerB->groups()->attach($groupB);
        $auditor->directPermissions()->attach($historyPermission);

        foreach ([[$sellerA, 'AAA-111', 'Motorista A'], [$colleagueA, 'AAB-222', 'Motorista Colega'], [$sellerB, 'BBB-333', 'Motorista B']] as [$seller, $plate, $driver]) {
            $this->actingAs($seller);
            $this->post('/sales/wholesale', ['product_id' => $product->id, 'quantity' => 1, 'truck_plate' => $plate, 'driver_name' => $driver])
                ->assertSessionHas('success');
        }

        $otherSale = SaleOrder::where('created_by_user_id', $sellerB->id)->firstOrFail()->items()->firstOrFail();
        $this->actingAs($sellerA);
        $this->get('/sales')->assertOk()->assertSee('AAA-111')->assertSee('AAB-222')->assertDontSee('BBB-333');
        $this->get('/sales/history')->assertForbidden();
        $this->post("/sales/{$otherSale->id}/cancel")->assertForbidden();
        $this->get("/sales/{$otherSale->id}/delivery-guide")->assertForbidden();

        $this->actingAs($auditor);
        $this->get('/')->assertRedirect('/modules');
        $this->get('/sales')->assertForbidden();
        $this->get('/sales/history')->assertOk()->assertSee('AAA-111')->assertSee('AAB-222')->assertSee('BBB-333')
            ->assertDontSee('Registar venda a grosso')->assertDontSee('Cancelar');
        $this->get('/modules')->assertOk()->assertSee('href="'.route('sales.history').'"', false);
    }

    public function test_general_history_filters_daily_weekly_monthly_and_exports_matching_pdf(): void
    {
        $this->artisan('db:seed')->assertSuccessful();
        $owner = $this->owner();
        $auditor = User::create(['name' => 'Auditor', 'username' => 'auditor', 'password' => 'password12345', 'is_active' => true]);
        $auditor->directPermissions()->attach(Permission::where('key', 'sales.history.view')->firstOrFail());
        $product = Product::create(['brand' => 'Petro Gas', 'weight_kg' => 9, 'quantity' => 20, 'purchase_price' => 100, 'reseller_price' => 140, 'unit_price' => 160]);
        $stand = Location::create(['name' => 'Banca Centro', 'kind' => 'physical', 'is_sales_point' => true]);

        foreach ([
            ['2026-09-30 12:00:00', 'wholesale', 1, 140, false],
            ['2026-10-19 09:00:00', 'wholesale', 2, 140, false],
            ['2026-10-22 14:00:00', 'unit', 3, 160, false],
            ['2026-10-22 15:00:00', 'unit', 5, 160, true],
        ] as [$date, $channel, $quantity, $price, $cancelled]) {
            $order = SaleOrder::create([
                'channel' => $channel, 'location_id' => $channel === 'unit' ? $stand->id : null,
                'truck_plate' => $channel === 'wholesale' ? 'ABC-123-MP' : null,
                'driver_name' => $channel === 'wholesale' ? 'Motorista' : null,
                'created_by_user_id' => $owner->id, 'sold_at' => $date,
                'cancelled_at' => $cancelled ? $date : null,
            ]);
            ProductSale::create([
                'sale_order_id' => $order->id, 'product_id' => $product->id,
                'channel' => $channel, 'location_id' => $channel === 'unit' ? $stand->id : null,
                'quantity' => $quantity, 'unit_price' => $price, 'unit_cost' => 100,
                'created_by_user_id' => $owner->id, 'sold_at' => $date,
            ]);
        }

        foreach ([
            ['2026-09-30', 'approved', 1000],
            ['2026-10-20', 'approved', 1200],
            ['2026-10-22', 'approved', 5000],
            ['2026-10-22', 'pending', 2000],
            ['2026-10-22', 'rejected', 3000],
            ['2026-10-22', 'cancelled', 4000],
        ] as [$date, $status, $amountCents]) {
            OperationalExpense::create([
                'expense_date' => $date, 'category' => 'Outros',
                'description' => 'Despesa operacional de teste', 'amount_cents' => $amountCents,
                'status' => $status, 'created_by_user_id' => $owner->id,
            ]);
        }

        $this->actingAs($auditor);
        $this->get('/sales/history?period=day&date=2026-10-22')->assertOk()
            ->assertSee('Vendas diárias')->assertSee('Exportar este período em PDF')
            ->assertSee('Dia 22/10/2026')->assertSee('480,00 MT')
            ->assertSee('Despesas operacionais')->assertSee('50,00 MT')
            ->assertDontSee('Lucro:')->assertDontSee('Lucro total:')
            ->assertViewHas('expenseSummary', fn ($expenses) => $expenses['count'] === 1 && $expenses['amount_cents'] === 5000)
            ->assertViewHas('summary', fn ($summary) => $summary['unit']['orders'] === 1
                && $summary['unit']['quantity'] === 3 && $summary['unit']['revenue_cents'] === 48000
                && $summary['unit']['margin_cents'] === 18000)
            ->assertViewHas('orders', fn ($orders) => $orders->total() === 1);
        $this->get('/sales/history?period=week&date=2026-10-25')->assertOk()
            ->assertSee('Semana de 19/10/2026 a 25/10/2026')
            ->assertViewHas('expenseSummary', fn ($expenses) => $expenses['count'] === 2 && $expenses['amount_cents'] === 6200)
            ->assertViewHas('orders', fn ($orders) => $orders->total() === 2);
        $this->get('/sales/history?period=month&date=2026-10-01')->assertOk()
            ->assertSee('Mês de 10/2026')
            ->assertViewHas('expenseSummary', fn ($expenses) => $expenses['count'] === 2 && $expenses['amount_cents'] === 6200)
            ->assertViewHas('orders', fn ($orders) => $orders->total() === 2);
        $this->get('/sales/history')->assertOk()
            ->assertViewHas('expenseSummary', fn ($expenses) => $expenses['count'] === 3 && $expenses['amount_cents'] === 7200)
            ->assertViewHas('orders', fn ($orders) => $orders->total() === 3);
        $this->get('/sales/history?period=year&date=2026-10-22')->assertSessionHasErrors('period');
        $this->get('/sales/history?period=day&date=invalida')->assertSessionHasErrors('date');

        $pdf = $this->get('/sales/history/pdf?period=day&date=2026-10-22')->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('private', $pdf->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', $pdf->headers->get('Cache-Control'));
        $this->assertStringContainsString('historico-vendas-day-2026-10-22.pdf', $pdf->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF-1.4', $pdf->getContent());
        $this->assertStringContainsString('Banca Centro', $pdf->getContent());
        $this->assertStringNotContainsString('ABC-123-MP', $pdf->getContent());
        $this->assertStringNotContainsString('Lucro:', $pdf->getContent());
        $this->assertStringNotContainsString('MARGEM', $pdf->getContent());
        $this->assertStringContainsString('Despesas operacionais aprovadas: 50,00 MT', $pdf->getContent());

        $auditor->directPermissions()->attach(Permission::where('key', 'sales.profit.view')->firstOrFail());
        $this->get('/sales/history')->assertOk()
            ->assertViewHas('summary', fn ($summary) => $summary['wholesale']['margin_cents'] === 12000
                && $summary['unit']['margin_cents'] === 18000)
            ->assertSee('Lucro: 120,00 MT')->assertSee('Lucro: 180,00 MT')
            ->assertSee('Lucro total: 300,00 MT');
        $profitPdf = $this->get('/sales/history/pdf')->assertOk()->getContent();
        $this->assertStringContainsString('Lucro: 120,00 MT', $profitPdf);
        $this->assertStringContainsString('Lucro: 180,00 MT', $profitPdf);
        $this->assertStringContainsString('Lucro: 300,00 MT', $profitPdf);

        $outsider = User::create(['name' => 'Sem acesso', 'username' => 'semacesso', 'password' => 'password12345', 'is_active' => true]);
        $this->actingAs($outsider);
        $this->get('/sales/history/pdf?period=day&date=2026-10-22')->assertForbidden();
    }

    public function test_general_history_pdf_includes_sales_beyond_the_first_page(): void
    {
        $this->actingAs($this->owner());
        $product = Product::create(['brand' => 'Petro Gas', 'weight_kg' => 9, 'quantity' => 100, 'purchase_price' => 100, 'reseller_price' => 140, 'unit_price' => 160]);
        for ($number = 1; $number <= 52; $number++) {
            $order = SaleOrder::create([
                'channel' => 'wholesale', 'truck_plate' => 'ABC-123-MP',
                'driver_name' => 'Motorista', 'sold_at' => '2026-10-22 09:00:00',
            ]);
            ProductSale::create([
                'sale_order_id' => $order->id, 'product_id' => $product->id,
                'channel' => 'wholesale', 'quantity' => 1,
                'unit_price' => 140, 'unit_cost' => 100, 'sold_at' => $order->sold_at,
            ]);
        }

        $this->get('/sales/history?period=day&date=2026-10-22')->assertOk()
            ->assertViewHas('orders', fn ($orders) => $orders->total() === 52 && $orders->count() === 50)
            ->assertSee('period=day&amp;date=2026-10-22&amp;page=2', false);
        $pdf = $this->get('/sales/history/pdf?period=day&date=2026-10-22')->assertOk()->getContent();
        $this->assertStringContainsString('#000001', $pdf);
        $this->assertStringContainsString('#000052', $pdf);
        $this->assertMatchesRegularExpression('/\/Count (?:[2-9]|[1-9][0-9]+)/', $pdf);
    }

    public function test_general_history_shows_daily_sales_for_each_physical_point(): void
    {
        $this->artisan('db:seed')->assertSuccessful();
        $owner = $this->owner();
        $auditor = User::create(['name' => 'Auditor', 'username' => 'auditor', 'password' => 'password12345', 'is_active' => true]);
        $auditor->directPermissions()->attach(Permission::where('key', 'sales.history.view')->firstOrFail());
        $first = Product::create(['brand' => 'Petro Gas', 'weight_kg' => 9, 'quantity' => 20, 'purchase_price' => 100, 'reseller_price' => 140, 'unit_price' => 160]);
        $second = Product::create(['brand' => 'Nova Gás', 'weight_kg' => 6, 'quantity' => 20, 'purchase_price' => 50, 'reseller_price' => 75, 'unit_price' => 90]);
        $north = Location::create(['name' => 'Banca Norte', 'kind' => 'physical', 'is_sales_point' => true]);
        $south = Location::create(['name' => 'Banca Sul', 'kind' => 'physical', 'is_sales_point' => true]);
        $empty = Location::create(['name' => 'Banca Vazia', 'kind' => 'physical', 'is_sales_point' => true]);
        ProductStandStock::create(['product_id' => $first->id, 'location_id' => $north->id, 'quantity' => 4]);
        ProductStandStock::create(['product_id' => $second->id, 'location_id' => $north->id, 'quantity' => 2]);
        ProductStandStock::create(['product_id' => $first->id, 'location_id' => $south->id, 'quantity' => 5]);

        foreach ([
            [$north, '2026-10-22 10:00:00', false, [[$first, 2, 160, 100], [$second, 1, 90, 50]]],
            [$south, '2026-10-22 11:00:00', false, [[$first, 3, 160, 100]]],
            [$south, '2026-10-22 12:00:00', true, [[$first, 5, 160, 100]]],
            [$north, '2026-10-23 09:00:00', false, [[$first, 4, 160, 100]]],
        ] as [$point, $date, $cancelled, $items]) {
            $order = SaleOrder::create([
                'channel' => 'unit', 'location_id' => $point->id, 'created_by_user_id' => $owner->id,
                'sold_at' => $date, 'cancelled_at' => $cancelled ? $date : null,
            ]);
            foreach ($items as [$product, $quantity, $price, $cost]) {
                ProductSale::create([
                    'sale_order_id' => $order->id, 'product_id' => $product->id,
                    'channel' => 'unit', 'location_id' => $point->id, 'quantity' => $quantity,
                    'unit_price' => $price, 'unit_cost' => $cost, 'sold_at' => $date,
                ]);
            }
        }

        $this->actingAs($auditor)->get('/sales/history?period=day&date=2026-10-22')->assertOk()
            ->assertSee('Vendas por posto · 22/10/2026')->assertSee('Banca Vazia')
            ->assertSee('Total das bancas')->assertDontSee('<th class="num">Margem</th>', false)
            ->assertViewHas('pointDaily', function ($daily) use ($north, $south, $empty) {
                $rows = collect($daily['rows'])->keyBy(fn ($row) => $row['location']->id);
                return $rows[$north->id]['orders'] === 1 && $rows[$north->id]['quantity'] === 3
                    && $rows[$north->id]['revenue_cents'] === 41000 && $rows[$north->id]['margin_cents'] === 16000
                    && $rows[$south->id]['orders'] === 1 && $rows[$south->id]['quantity'] === 3
                    && $rows[$south->id]['revenue_cents'] === 48000
                    && $rows[$empty->id]['orders'] === 0 && $rows[$empty->id]['revenue_cents'] === 0
                    && $daily['totals']['orders'] === 2 && $daily['totals']['quantity'] === 6
                    && $daily['totals']['revenue_cents'] === 89000;
            });

        $auditor->directPermissions()->attach(Permission::where('key', 'sales.profit.view')->firstOrFail());
        $this->get('/sales/history?period=day&date=2026-10-22')->assertOk()
            ->assertSee('<th class="num">Margem</th>', false)
            ->assertSee('340,00 MT');
        $this->get('/sales/history?period=week&date=2026-10-25')->assertOk()
            ->assertViewHas('pointDaily', fn ($daily) => $daily['label'] === '25/10/2026'
                && $daily['totals']['orders'] === 0);

        $filtered = $this->get('/sales/history?period=day&date=2026-10-22&location_id='.$north->id)->assertOk()
            ->assertSee('Estoque atual')->assertSee('Produtos por posto')->assertSee('Estoque baixo');
        $filtered->assertViewHas('summary', fn ($summary) => $summary['unit']['orders'] === 1
                && $summary['unit']['revenue_cents'] === 41000 && $summary['wholesale']['orders'] === 0)
            ->assertViewHas('pointDaily', fn ($daily) => count($daily['rows']) === 1
                && $daily['rows'][0]['location']->id === $north->id
                && count($daily['rows'][0]['products']) === 2
                && collect($daily['rows'][0]['products'])->pluck('stock')->sort()->values()->all() === [2, 4])
            ->assertViewHas('orders', fn ($orders) => $orders->total() === 1);

        $this->get('/sales/history?period=day&date=2026-10-23&location_id='.$north->id)->assertOk()
            ->assertViewHas('previousSummary', fn ($previous) => $previous['summary']['unit']['revenue_cents'] === 41000)
            ->assertSee('dia anterior');

        $pdf = $this->get('/sales/history/pdf?period=day&date=2026-10-22&location_id='.$north->id)->assertOk()->getContent();
        $this->assertStringContainsString('VENDAS POR POSTO', $pdf);
        $this->assertStringContainsString('Banca Norte', $pdf);
        $this->assertStringNotContainsString('Banca Sul', $pdf);
        $this->assertStringContainsString('MARGEM', $pdf);
    }

    public function test_seller_is_restricted_to_assigned_stand_or_vehicle(): void
    {
        $this->artisan('db:seed')->assertSuccessful();
        $owner = $this->owner();
        $product = Product::create(['brand' => 'Petro Gas', 'weight_kg' => 9, 'quantity' => 20, 'purchase_price' => 100, 'reseller_price' => 140, 'unit_price' => 160]);
        $standOne = Location::create(['name' => 'Banca 1', 'kind' => 'physical', 'is_sales_point' => true]);
        $standTwo = Location::create(['name' => 'Banca 2', 'kind' => 'physical', 'is_sales_point' => true]);
        $vehicle = Location::create(['name' => 'Carro A', 'kind' => 'vehicle', 'plate' => 'ABC-123-MP', 'is_sales_point' => true]);
        $this->actingAs($owner);
        foreach ([$standOne, $standTwo] as $stand) {
            $this->post('/allocations', ['product_id' => $product->id, 'location_id' => $stand->id, 'quantity' => 4])->assertSessionHas('success');
        }
        $sales = Permission::where('key', 'sales.create')->firstOrFail();
        $standSeller = User::create(['name' => 'Banca Um', 'username' => 'banca1', 'password' => 'password12345', 'is_active' => true, 'sales_location_id' => $standOne->id]);
        $standSeller->directPermissions()->attach($sales);
        $vehicleSeller = User::create(['name' => 'Motorista', 'username' => 'motorista', 'password' => 'password12345', 'is_active' => true, 'sales_location_id' => $vehicle->id]);
        $vehicleSeller->directPermissions()->attach($sales);

        $this->actingAs($standSeller);
        $this->get('/sales')->assertOk()->assertSee('Banca 1')->assertDontSee('Registar venda a grosso');
        $this->post('/sales/unit', ['product_id' => $product->id, 'location_id' => $standTwo->id, 'quantity' => 1])->assertForbidden();
        $this->post('/sales/wholesale', ['product_id' => $product->id, 'quantity' => 1, 'truck_plate' => 'ABC-123-MP', 'driver_name' => 'João'])->assertForbidden();
        $this->post('/sales/unit', ['product_id' => $product->id, 'location_id' => $standOne->id, 'quantity' => 1])->assertSessionHas('success');

        $this->actingAs($vehicleSeller);
        $this->get('/sales')->assertOk()->assertDontSee('Registar venda por unidade');
        $this->post('/sales/unit', ['product_id' => $product->id, 'location_id' => $standOne->id, 'quantity' => 1])->assertForbidden();
        $this->post('/sales/wholesale', ['product_id' => $product->id, 'quantity' => 1, 'truck_plate' => 'OUTRA-111', 'driver_name' => 'João'])->assertSessionHasErrors('truck_plate');
        $this->post('/sales/wholesale', ['product_id' => $product->id, 'quantity' => 1, 'truck_plate' => 'abc-123-mp', 'driver_name' => 'João'])->assertSessionHas('success');
        $this->assertDatabaseHas('product_sales', ['channel' => 'wholesale', 'vehicle_location_id' => $vehicle->id, 'truck_plate' => 'ABC-123-MP']);
        $this->get('/sales')->assertOk()->assertDontSee('Banca Um');
        $this->assertSame(18, $product->fresh()->quantity);
    }

    public function test_insufficient_stock_and_invalid_stand_do_not_change_balances(): void
    {
        $this->actingAs($this->owner());
        $product = Product::create(['brand' => 'Petro Gas', 'weight_kg' => 9, 'quantity' => 10, 'purchase_price' => 100, 'reseller_price' => 140, 'unit_price' => 160]);
        $stand = Location::create(['name' => 'Banca 1', 'kind' => 'physical', 'is_sales_point' => true]);
        $truck = Location::create(['name' => 'Carro A', 'kind' => 'vehicle', 'is_sales_point' => true]);

        $this->post('/allocations', ['product_id' => $product->id, 'location_id' => $stand->id, 'quantity' => 8])->assertSessionHas('success');
        $this->post('/allocations', ['product_id' => $product->id, 'location_id' => $stand->id, 'quantity' => 3])->assertSessionHasErrors('quantity');
        $this->post('/allocations', ['product_id' => $product->id, 'location_id' => $truck->id, 'quantity' => 1])->assertSessionHasErrors('location_id');
        $this->post('/sales/wholesale', ['product_id' => $product->id, 'quantity' => 3, 'truck_plate' => 'ABC-123', 'driver_name' => 'João'])->assertSessionHasErrors('items.0.quantity');
        $this->post('/sales/wholesale', ['product_id' => $product->id, 'quantity' => 1, 'truck_plate' => '', 'driver_name' => 'João'])->assertSessionHasErrors('truck_plate');
        $this->post('/sales/unit', ['product_id' => $product->id, 'location_id' => $stand->id, 'quantity' => 9])->assertSessionHasErrors('items.0.quantity');
        $this->post('/sales/unit', ['product_id' => $product->id, 'location_id' => $truck->id, 'quantity' => 1])->assertSessionHasErrors('location_id');

        $this->assertSame(10, $product->fresh()->quantity);
        $this->assertSame(8, ProductStandStock::firstOrFail()->quantity);
        $this->assertSame(0, ProductSale::count());
        $this->assertSame(1, ProductAllocation::count());
    }

    public function test_cancellation_restores_the_correct_stock_and_removes_sales_from_metrics(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner);
        $product = Product::create(['brand' => 'Petro Gas', 'weight_kg' => 9, 'quantity' => 20, 'purchase_price' => 100, 'reseller_price' => 140, 'unit_price' => 160]);
        $stand = Location::create(['name' => 'Banca 1', 'kind' => 'physical', 'is_sales_point' => true]);

        $this->post('/allocations', ['product_id' => $product->id, 'location_id' => $stand->id, 'quantity' => 8])->assertSessionHas('success');
        $this->post('/sales/wholesale', ['product_id' => $product->id, 'quantity' => 5, 'truck_plate' => 'ABC-123-MP', 'driver_name' => 'João Silva'])->assertSessionHas('success');
        $wholesale = ProductSale::where('channel', 'wholesale')->firstOrFail();
        $this->post('/sales/unit', ['product_id' => $product->id, 'location_id' => $stand->id, 'quantity' => 3])->assertSessionHas('success');
        $unit = ProductSale::where('channel', 'unit')->firstOrFail();
        $this->assertSame(12, $product->fresh()->quantity);

        $this->post("/sales/{$unit->id}/cancel")->assertSessionHas('success');
        $this->assertSame(15, $product->fresh()->quantity);
        $this->assertSame(8, ProductStandStock::firstOrFail()->quantity);
        $this->assertNotNull($unit->fresh()->cancelled_at);
        $this->assertSame($owner->id, $unit->fresh()->cancelled_by_user_id);
        $this->assertSame(0, app(SalesMetrics::class)->build()['unit']['revenue_cents']);
        $this->post("/sales/{$unit->id}/cancel")->assertStatus(409);
        $this->assertSame(15, $product->fresh()->quantity);

        $product->delete();
        $this->post("/sales/{$wholesale->id}/cancel")->assertSessionHas('success');
        $this->assertSame(20, $product->fresh()->quantity);
        $this->assertSame(8, ProductStandStock::firstOrFail()->quantity);
        $this->assertSame(0, app(SalesMetrics::class)->build()['wholesale']['revenue_cents']);
        $this->get('/sales')->assertOk()->assertSee('João Silva')
            ->assertDontSee('<th class="num">Margem</th>', false)
            ->assertDontSee('<th>Estado</th>', false)
            ->assertDontSee('Cancelada');
        $this->get("/sales/{$wholesale->id}/delivery-guide")->assertStatus(409);
    }

    public function test_wholesale_guide_can_be_downloaded_and_is_limited_to_the_assigned_vehicle(): void
    {
        $this->artisan('db:seed')->assertSuccessful();
        $owner = $this->owner();
        $product = Product::create(['brand' => 'Petro Gas', 'weight_kg' => 9, 'quantity' => 10, 'purchase_price' => 100, 'reseller_price' => 140, 'unit_price' => 160]);
        $vehicle = Location::create(['name' => 'Carro A', 'kind' => 'vehicle', 'plate' => 'ABC-123-MP', 'is_sales_point' => true]);
        $otherVehicle = Location::create(['name' => 'Carro B', 'kind' => 'vehicle', 'plate' => 'DEF-456-MP', 'is_sales_point' => true]);
        $salesPermission = Permission::where('key', 'sales.create')->firstOrFail();
        $driver = User::create(['name' => 'Motorista', 'username' => 'motorista', 'password' => 'password12345', 'is_active' => true, 'sales_location_id' => $vehicle->id]);
        $driver->directPermissions()->attach($salesPermission);
        $otherDriver = User::create(['name' => 'Outro', 'username' => 'outro', 'password' => 'password12345', 'is_active' => true, 'sales_location_id' => $otherVehicle->id]);
        $otherDriver->directPermissions()->attach($salesPermission);

        $this->actingAs($driver);
        $this->post('/sales/wholesale', ['product_id' => $product->id, 'quantity' => 2, 'truck_plate' => 'ABC-123-MP', 'driver_name' => 'João Silva', 'generate_delivery_guide' => 1])
            ->assertRedirect();
        $sale = ProductSale::firstOrFail();
        $response = $this->get("/sales/{$sale->id}/delivery-guide")->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-1.4', $response->getContent());
        $this->assertStringContainsString('ABC-123-MP', $response->getContent());
        $this->assertStringContainsString(iconv('UTF-8', 'Windows-1252', 'João Silva'), $response->getContent());
        $this->assertStringContainsString('guia-entrega-000001.pdf', $response->headers->get('Content-Disposition'));
        $this->get('/sales')->assertOk()->assertSee('Guia PDF')->assertSee('Cancelar');

        $this->actingAs($otherDriver);
        $this->get("/sales/{$sale->id}/delivery-guide")->assertForbidden();
        $this->post("/sales/{$sale->id}/cancel")->assertForbidden();
        $this->assertSame(8, $product->fresh()->quantity);

        $this->actingAs($owner);
        $this->post("/sales/{$sale->id}/cancel")->assertSessionHas('success');
        $this->assertSame(10, $product->fresh()->quantity);
    }

    public function test_multi_item_sales_cancel_all_items_and_keep_their_prices(): void
    {
        $this->actingAs($this->owner());
        $first = Product::create(['brand' => 'Petro Gas', 'weight_kg' => 9, 'quantity' => 20, 'purchase_price' => 100, 'reseller_price' => 140, 'unit_price' => 160]);
        $second = Product::create(['brand' => 'Galp', 'weight_kg' => 11, 'quantity' => 20, 'purchase_price' => 180, 'reseller_price' => 240, 'unit_price' => 260]);
        $stand = Location::create(['name' => 'Banca 1', 'kind' => 'physical', 'is_sales_point' => true]);
        $this->post('/allocations', ['product_id' => $first->id, 'location_id' => $stand->id, 'quantity' => 5])->assertSessionHas('success');
        $this->post('/allocations', ['product_id' => $second->id, 'location_id' => $stand->id, 'quantity' => 6])->assertSessionHas('success');
        $this->get('/sales')->assertOk()->assertSee('Adicionar produto à mesma venda')
            ->assertSee('Todos ficarão na mesma venda e guia.')
            ->assertSee('data-sale-item-count', false);

        $this->post('/sales/wholesale', [
            'items' => [['product_id' => $first->id, 'quantity' => 3], ['product_id' => $second->id, 'quantity' => 4]],
            'truck_plate' => 'ABC-123-MP', 'driver_name' => 'João Silva', 'generate_delivery_guide' => 1,
        ])->assertRedirect();
        $order = SaleOrder::firstOrFail();
        $this->assertSame(2, $order->items()->count());
        $this->assertSame(17, $first->fresh()->quantity);
        $this->assertSame(16, $second->fresh()->quantity);
        $this->assertSame(138000, app(SalesMetrics::class)->build()['wholesale']['revenue_cents']);
        $guide = $this->get('/sales/'.$order->items()->firstOrFail()->id.'/delivery-guide')->assertOk();
        $this->assertStringContainsString('Petro Gas', $guide->getContent());
        $this->assertStringContainsString('Galp', $guide->getContent());
        $this->assertStringContainsString('1.380,00 MT', $guide->getContent());
        $this->get('/sales')->assertOk()->assertSee('Petro Gas')->assertSee('Galp')->assertSee('1.380,00 MT');

        $this->post('/sales/'.$order->items()->orderByDesc('id')->firstOrFail()->id.'/cancel')->assertSessionHas('success');
        $this->assertSame(20, $first->fresh()->quantity);
        $this->assertSame(20, $second->fresh()->quantity);
        $this->assertNotNull($order->fresh()->cancelled_at);
        $this->assertSame(2, $order->items()->whereNotNull('cancelled_at')->count());
        $this->assertSame(0, app(SalesMetrics::class)->build()['wholesale']['revenue_cents']);

        $this->post('/sales/unit', [
            'location_id' => $stand->id,
            'items' => [['product_id' => $first->id, 'quantity' => 2], ['product_id' => $second->id, 'quantity' => 3]],
        ])->assertSessionHas('success');
        $unitOrder = SaleOrder::where('channel', 'unit')->firstOrFail();
        $this->assertSame(18, $first->fresh()->quantity);
        $this->assertSame(17, $second->fresh()->quantity);
        $this->assertSame(3, ProductStandStock::where('product_id', $first->id)->value('quantity'));
        $this->assertSame(3, ProductStandStock::where('product_id', $second->id)->value('quantity'));
        $this->assertSame(110000, app(SalesMetrics::class)->build()['unit']['revenue_cents']);

        $this->post('/sales/'.$unitOrder->items()->firstOrFail()->id.'/cancel')->assertSessionHas('success');
        $this->assertSame(20, $first->fresh()->quantity);
        $this->assertSame(20, $second->fresh()->quantity);
        $this->assertSame(5, ProductStandStock::where('product_id', $first->id)->value('quantity'));
        $this->assertSame(6, ProductStandStock::where('product_id', $second->id)->value('quantity'));
        $this->assertSame(0, app(SalesMetrics::class)->build()['unit']['revenue_cents']);
    }

    public function test_invalid_multi_item_sale_rolls_back_every_product(): void
    {
        $this->actingAs($this->owner());
        $first = Product::create(['brand' => 'Petro Gas', 'weight_kg' => 9, 'quantity' => 10, 'purchase_price' => 100, 'reseller_price' => 140, 'unit_price' => 160]);
        $second = Product::create(['brand' => 'Galp', 'weight_kg' => 11, 'quantity' => 1, 'purchase_price' => 180, 'reseller_price' => 240, 'unit_price' => 260]);
        $this->post('/sales/wholesale', [
            'items' => [['product_id' => $first->id, 'quantity' => 2], ['product_id' => $second->id, 'quantity' => 2]],
            'truck_plate' => 'ABC-123-MP', 'driver_name' => 'João Silva',
        ])->assertSessionHasErrors('items.1.quantity');
        $this->assertSame(10, $first->fresh()->quantity);
        $this->assertSame(1, $second->fresh()->quantity);
        $this->assertSame(0, SaleOrder::count());
        $this->assertSame(0, ProductSale::count());

        $this->post('/sales/wholesale', [
            'items' => [['product_id' => $first->id, 'quantity' => 1], ['product_id' => $first->id, 'quantity' => 1]],
            'truck_plate' => 'ABC-123-MP', 'driver_name' => 'João Silva',
        ])->assertSessionHasErrors('items.0.product_id');
        $this->assertSame(0, SaleOrder::count());

        $stand = Location::create(['name' => 'Banca 1', 'kind' => 'physical', 'is_sales_point' => true]);
        $this->post('/allocations', ['product_id' => $first->id, 'location_id' => $stand->id, 'quantity' => 4])->assertSessionHas('success');
        $this->post('/allocations', ['product_id' => $second->id, 'location_id' => $stand->id, 'quantity' => 1])->assertSessionHas('success');
        $this->post('/sales/unit', [
            'location_id' => $stand->id,
            'items' => [['product_id' => $first->id, 'quantity' => 1], ['product_id' => $second->id, 'quantity' => 2]],
        ])->assertSessionHasErrors('items.1.quantity');
        $this->assertSame(10, $first->fresh()->quantity);
        $this->assertSame(1, $second->fresh()->quantity);
        $this->assertSame(4, ProductStandStock::where('product_id', $first->id)->value('quantity'));
        $this->assertSame(1, ProductStandStock::where('product_id', $second->id)->value('quantity'));
        $this->assertSame(0, SaleOrder::count());
    }

    public function test_delivery_guide_has_all_items_across_pages(): void
    {
        $this->actingAs($this->owner());
        $items = [];
        for ($number = 1; $number <= 12; $number++) {
            $product = Product::create(['brand' => 'Marca '.$number, 'weight_kg' => 9, 'quantity' => 2, 'purchase_price' => 100, 'reseller_price' => 140, 'unit_price' => 160]);
            $items[] = ['product_id' => $product->id, 'quantity' => 1];
        }
        $this->post('/sales/wholesale', ['items' => $items, 'truck_plate' => 'ABC-123-MP', 'driver_name' => 'João Silva'])->assertSessionHas('success');
        $order = SaleOrder::firstOrFail();
        $content = $this->get('/sales/'.$order->items()->firstOrFail()->id.'/delivery-guide')->assertOk()->getContent();
        $this->assertStringContainsString('/Count 2', $content);
        foreach (range(1, 12) as $number) {
            $this->assertStringContainsString('Marca '.$number, $content);
        }
    }

    public function test_sales_accept_registered_or_generic_customer_and_keep_customer_in_history_and_guide(): void
    {
        $this->actingAs($this->owner());
        $product = Product::create(['brand' => 'Petro Gas', 'weight_kg' => 9, 'quantity' => 20, 'purchase_price' => 100, 'reseller_price' => 140, 'unit_price' => 160]);
        $stand = Location::create(['name' => 'Banca 1', 'kind' => 'physical', 'is_sales_point' => true]);
        $customer = Customer::create(['name' => 'Empresa Cliente', 'type' => 'organization', 'nuit' => '400123459', 'physical_address' => 'Maputo', 'is_active' => true]);
        $inactive = Customer::create(['name' => 'Cliente Inactivo', 'type' => 'person', 'nuit' => '400123460', 'physical_address' => 'Matola', 'is_active' => false]);
        $this->post('/allocations', ['product_id' => $product->id, 'location_id' => $stand->id, 'quantity' => 6])->assertSessionHas('success');

        $this->get('/sales')->assertOk()->assertSee('Cliente genérico')->assertSee('Empresa Cliente')
            ->assertDontSee('Cliente Inactivo');
        $this->post('/sales/wholesale', [
            'product_id' => $product->id, 'quantity' => 2,
            'truck_plate' => 'ABC-123-MP', 'driver_name' => 'João Silva', 'customer_id' => $customer->id,
        ])->assertSessionHas('success');
        $wholesale = SaleOrder::where('channel', 'wholesale')->firstOrFail();
        $this->assertSame($customer->id, $wholesale->customer_id);
        $guide = $this->get('/sales/'.$wholesale->items()->firstOrFail()->id.'/delivery-guide')->assertOk()->getContent();
        $this->assertStringContainsString('Empresa Cliente', $guide);
        $this->assertStringContainsString('400123459', $guide);

        $this->post('/sales/unit', [
            'product_id' => $product->id, 'quantity' => 1, 'location_id' => $stand->id,
            'customer_id' => $customer->id,
        ])->assertSessionHas('success');
        $this->assertSame($customer->id, SaleOrder::where('channel', 'unit')->firstOrFail()->customer_id);
        $this->post('/sales/unit', [
            'product_id' => $product->id, 'quantity' => 1, 'location_id' => $stand->id,
            'customer_id' => '',
        ])->assertSessionHas('success');
        $this->assertNull(SaleOrder::where('channel', 'unit')->latest('id')->firstOrFail()->customer_id);

        $this->post('/sales/unit', [
            'product_id' => $product->id, 'quantity' => 1, 'location_id' => $stand->id,
            'customer_id' => $inactive->id,
        ])->assertSessionHasErrors('customer_id');
        $this->assertSame(3, SaleOrder::count());
        $customer->update(['is_active' => false]);
        $this->get('/sales')->assertOk()->assertSee('Empresa Cliente')->assertSee('Cliente genérico');
    }
}
