<?php

namespace Tests\Feature;

use App\Models\OperationalExpense;
use App\Models\CashAccount;
use App\Models\Permission;
use App\Models\User;
use App\Services\AccessGrantScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OperationalExpenseFlowTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $name, array $keys): User
    {
        $user = User::create(['name' => $name, 'username' => strtolower($name), 'password' => 'password123', 'is_active' => true]);
        foreach ($keys as $key) $user->directPermissions()->attach(Permission::firstOrCreate(['key' => $key], ['label' => config('access.permissions')[$key]]));
        return $user;
    }

    public function test_expenses_are_inside_the_botijas_module_and_its_access_scope(): void
    {
        $operator = $this->user('Operador', ['expenses.create']);
        $this->actingAs($operator)->get('/modules')->assertOk()
            ->assertViewHas('modules', fn ($modules) => $modules->filter(fn ($module) => $module['entry_url'])->pluck('key')->all() === ['botijas']
                && $modules->first()['entry_url'] === route('expenses.index'));
        $this->get('/expenses')->assertOk()
            ->assertSee('MÓDULO ATIVO')
            ->assertSee('href="'.route('expenses.index').'"', false)
            ->assertSee('Vendas de botijas');

        $manager = $this->user('Gestor', ['access.manage', 'access.grant.botijas']);
        $allowed = app(AccessGrantScope::class)->permissions($manager)->pluck('key')->all();
        $this->assertContains('expenses.create', $allowed);
        $this->assertContains('expenses.approve', $allowed);
        $this->assertContains('expenses.view', $allowed);
        $this->assertArrayNotHasKey('financeiro', config('modules'));
        $this->assertArrayNotHasKey('financeiro', config('access.modules'));
    }

    public function test_simple_expense_can_be_approved_and_cancelled_without_cash_or_truck_fields(): void
    {
        Storage::fake('local');
        $clerk = $this->user('Operador', ['expenses.create']);
        $approver = $this->user('Gestor', ['expenses.approve', 'expenses.view']);
        $outsider = $this->user('Outro', ['expenses.create']);

        $this->actingAs($clerk)->get('/expenses')->assertOk()->assertSee('Registar despesa')
            ->assertDontSee('Caixa de origem')->assertDontSee('Matrícula do camião');
        $this->post('/expenses', [
            'expense_date' => now()->toDateString(), 'category' => 'Reparação de viatura',
            'description' => 'Reparação urgente de uma avaria', 'amount' => '250.50',
            'receipt' => UploadedFile::fake()->create('recibo.pdf', 20, 'application/pdf'),
        ])->assertSessionHas('success');

        $expense = OperationalExpense::firstOrFail();
        $this->assertSame('pending', $expense->status);
        $this->assertNull($expense->cash_account_id);
        $this->assertNull($expense->vehicle_plate);
        $this->assertSame(25050, $expense->amount_cents);
        $this->actingAs($outsider)->get("/expenses/{$expense->id}/receipt")->assertForbidden();
        $this->actingAs($clerk)->get("/expenses/{$expense->id}/receipt")->assertOk();
        $this->post("/expenses/{$expense->id}/approve")->assertForbidden();

        $this->actingAs($approver)->get('/expenses')->assertOk()
            ->assertSee('Ver detalhes')
            ->assertSee('id="expense-reason-dialog"', false)
            ->assertSee('data-expense-action-title="Rejeitar despesa"', false)
            ->assertDontSee('<small>Reparação urgente de uma avaria</small>', false)
            ->assertDontSee('<input name="reason"', false);
        $this->post("/expenses/{$expense->id}/approve")->assertSessionHas('success');
        $this->assertSame('approved', $expense->fresh()->status);
        $this->post("/expenses/{$expense->id}/approve")->assertStatus(409);
        $this->get('/expenses')->assertOk()->assertSee('250,50');
        $this->post("/expenses/{$expense->id}/cancel", ['reason' => 'Registo duplicado'])->assertSessionHas('success');
        $this->assertSame('cancelled', $expense->fresh()->status);
        $this->post("/expenses/{$expense->id}/cancel", ['reason' => 'Outra vez'])->assertStatus(409);
        $this->get('/expenses')->assertOk()->assertSee('data-expense-reason="Registo duplicado"', false)
            ->assertDontSee('<small class="muted">Registo duplicado</small>', false);
        $this->get('/cash-accounts')->assertNotFound();
    }

    public function test_rejection_and_invalid_expense_do_not_enter_approved_totals(): void
    {
        $clerk = $this->user('Operador', ['expenses.create']);
        $approver = $this->user('Aprovador', ['expenses.approve']);
        $this->actingAs($clerk)->get('/expenses')->assertOk()
            ->assertSee('max="'.today()->toDateString().'"', false);
        $this->post('/expenses', [
            'expense_date' => today()->addDay()->toDateString(), 'category' => 'Outros',
            'description' => 'Despesa lançada para amanhã', 'amount' => '80.00',
        ])->assertSessionHasErrors('expense_date');
        $this->assertSame(0, OperationalExpense::count());
        $this->actingAs($clerk)->post('/expenses', [
            'expense_date' => now()->toDateString(), 'category' => 'Outros',
            'description' => 'Equipamento avariado', 'amount' => '0.00',
        ])->assertSessionHasErrors('amount');
        $this->post('/expenses', [
            'expense_date' => now()->toDateString(), 'category' => 'Outros',
            'description' => 'Equipamento avariado', 'amount' => '80.00',
        ])->assertSessionHas('success');
        $expense = OperationalExpense::firstOrFail();
        $this->actingAs($approver)->post("/expenses/{$expense->id}/reject", ['reason' => 'Sem documentação suficiente'])->assertSessionHas('success');
        $this->assertSame('rejected', $expense->fresh()->status);
        $this->get('/expenses')->assertViewHas('approvedCents', 0);
        $this->post("/expenses/{$expense->id}/approve")->assertStatus(409);
    }

    public function test_existing_pending_expense_with_old_cash_reference_remains_approvable(): void
    {
        $creator = $this->user('Operador', ['expenses.create']);
        $approver = $this->user('Aprovador', ['expenses.approve']);
        $account = CashAccount::firstOrFail();
        $expense = OperationalExpense::forceCreate([
            'expense_date' => now()->toDateString(), 'category' => 'Outros',
            'description' => 'Despesa registada antes da simplificação', 'amount_cents' => 9000,
            'cash_account_id' => $account->id, 'vehicle_plate' => 'ABC-123-MP',
            'status' => 'pending', 'created_by_user_id' => $creator->id,
        ]);

        $this->actingAs($approver)->post("/expenses/{$expense->id}/approve")->assertSessionHas('success');
        $this->assertSame('approved', $expense->fresh()->status);
        $this->assertSame(0, $account->fresh()->balance_cents);
        $this->get('/expenses')->assertOk()->assertSee('Despesa registada antes da simplificação')
            ->assertDontSee('ABC-123-MP');
    }
}
