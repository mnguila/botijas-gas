<?php

namespace Tests\Feature;

use App\Models\AllocationRequest;
use App\Models\Location;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductStandStock;
use App\Models\User;
use App\Services\InventoryBalances;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AllocationRequestFlowTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $name, array $permissions, ?Location $point = null): User
    {
        $user = User::create(['name' => $name, 'username' => strtolower($name), 'password' => 'password123', 'is_active' => true, 'sales_location_id' => $point?->id]);
        foreach ($permissions as $key) $user->directPermissions()->attach(Permission::firstOrCreate(['key' => $key], ['label' => config('access.permissions')[$key]]));
        return $user;
    }

    public function test_delivery_needs_driver_document_before_stock_enters_stand(): void
    {
        Storage::fake('local');
        $stand = Location::create(['name' => 'Banca Centro', 'kind' => 'physical', 'is_sales_point' => true]);
        $other = Location::create(['name' => 'Banca Norte', 'kind' => 'physical', 'is_sales_point' => true]);
        $first = Product::create(['brand' => 'Petro Gas', 'weight_kg' => 9, 'quantity' => 10, 'purchase_price' => 5, 'reseller_price' => 8, 'unit_price' => 10]);
        $second = Product::create(['brand' => 'Petro Gas', 'weight_kg' => 12, 'quantity' => 7, 'purchase_price' => 7, 'reseller_price' => 11, 'unit_price' => 13]);
        $clerk = $this->user('Balconista', ['allocations.request', 'allocations.receive'], $stand);
        $approver = $this->user('Aprovador', ['allocations.approve']);
        $dispatcher = $this->user('Expedidor', ['allocations.dispatch']);
        $outsider = $this->user('Outro', ['allocations.receive'], $other);
        $legacyManager = $this->user('Gestor', ['allocations.manage']);
        $this->actingAs($legacyManager)->post('/allocations', ['product_id' => $first->id, 'location_id' => $stand->id, 'quantity' => 1])->assertForbidden();

        $this->actingAs($clerk)->get('/allocation-requests')->assertOk()->assertSee('Novo pedido de botijas');
        $this->post('/allocation-requests', ['quantities' => [$first->id => 4, $second->id => 2]])->assertSessionHas('success');
        $order = AllocationRequest::firstOrFail();
        $this->assertSame('pending', $order->status);
        $this->actingAs($clerk)->post("/allocation-requests/{$order->id}/approve")->assertForbidden();

        $this->actingAs($approver)->post("/allocation-requests/{$order->id}/approve")->assertSessionHas('success');
        $this->assertSame(10, app(InventoryBalances::class)->centralQuantity($first));
        $this->actingAs($dispatcher)->post("/allocation-requests/{$order->id}/dispatch", ['truck_plate' => 'ABC-123-MP', 'driver_name' => 'João'])->assertRedirect(route('allocation-requests.guide', $order));
        $this->assertSame(6, app(InventoryBalances::class)->centralQuantity($first));
        $this->assertSame(0, ProductStandStock::count());
        $this->get("/allocation-requests/{$order->id}/guide")->assertOk()->assertHeader('Content-Type', 'application/pdf');

        $this->actingAs($outsider)->get("/allocation-requests/{$order->id}/guide")->assertForbidden();
        $this->post("/allocation-requests/{$order->id}/receive", ['driver_document' => UploadedFile::fake()->create('assinado.pdf', 20, 'application/pdf')])->assertForbidden();
        $this->actingAs($clerk)->post("/allocation-requests/{$order->id}/receive", [])->assertSessionHasErrors('driver_document');
        $this->assertSame('in_transit', $order->fresh()->status);
        $this->assertSame(0, ProductStandStock::count());

        $this->post("/allocation-requests/{$order->id}/receive", ['driver_document' => UploadedFile::fake()->create('guia-assinada.pdf', 20, 'application/pdf')])->assertSessionHas('success');
        $order->refresh();
        $this->assertSame('received', $order->status);
        $this->assertSame(4, ProductStandStock::where('product_id', $first->id)->where('location_id', $stand->id)->value('quantity'));
        $this->assertSame(2, ProductStandStock::where('product_id', $second->id)->where('location_id', $stand->id)->value('quantity'));
        $this->assertSame(6, app(InventoryBalances::class)->centralQuantity($first));
        $this->assertSame(0, app(InventoryBalances::class)->inTransitQuantity($first));
        Storage::disk('local')->assertExists($order->driver_document_path);
        $this->get("/allocation-requests/{$order->id}/driver-document")->assertOk();
        $this->post("/allocation-requests/{$order->id}/receive", ['driver_document' => UploadedFile::fake()->create('again.pdf', 20, 'application/pdf')])->assertStatus(409);
        $this->assertSame(4, ProductStandStock::where('product_id', $first->id)->value('quantity'));
    }
}
