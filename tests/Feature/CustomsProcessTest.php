<?php

namespace Tests\Feature;

use App\Models\AccessGroup;
use App\Models\Customer;
use App\Models\CustomsProcess;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomsProcessTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $user = User::create(['name' => 'Administrador', 'username' => 'admin', 'password' => 'password12345', 'is_active' => true]);
        $user->forceFill(['is_super_admin' => true])->save();
        return $user;
    }

    private function customer(bool $active = true): Customer
    {
        return Customer::create([
            'name' => $active ? 'Importadora Maputo' : 'Importadora Inactiva',
            'type' => 'organization',
            'nuit' => $active ? '400111222' : '400111223',
            'physical_address' => 'Maputo',
            'is_active' => $active,
        ]);
    }

    public function test_responsible_picker_lists_all_active_customs_users_from_groups_and_individual_permissions(): void
    {
        $this->artisan('db:seed')->assertSuccessful();
        $owner = $this->owner();
        $permission = Permission::where('key', 'customs.manage')->firstOrFail();
        $group = AccessGroup::create(['name' => 'Despachantes']);
        $group->permissions()->attach($permission);
        $direct = User::create(['name' => 'Técnica Individual', 'username' => 'tecnica-individual', 'password' => 'password12345', 'is_active' => true]);
        $direct->directPermissions()->attach($permission);
        $inactive = User::create(['name' => 'Técnica Inativa', 'username' => 'tecnica-inativa', 'password' => 'password12345', 'is_active' => false]);
        $inactive->groups()->attach($group);
        User::create(['name' => 'Sem acesso', 'username' => 'sem-acesso', 'password' => 'password12345', 'is_active' => true]);
        for ($index = 1; $index <= 31; $index++) {
            $user = User::create(['name' => sprintf('Despachante %02d', $index), 'username' => 'despachante-'.$index, 'password' => 'password12345', 'is_active' => true]);
            $user->groups()->attach($group);
        }

        $this->actingAs($owner)->get('/customs-processes/create')->assertOk()
            ->assertSee('Técnica Individual')->assertSee('Despachante 31')
            ->assertSee('data-show-all-options', false)
            ->assertDontSee('Técnica Inativa')->assertDontSee('Sem acesso')
            ->assertViewHas('assignees', fn ($users) => $users->count() === 33 && $users->contains('id', $owner->id));
    }

    public function test_process_starts_with_only_importer_requirements_and_responsible_then_accepts_multiple_tasks(): void
    {
        Storage::fake('local');
        $owner = $this->owner();
        $customer = $this->customer();
        $this->actingAs($owner);

        $this->post('/customs-processes', [
            'customer_id' => $customer->id,
            'responsible_user_id' => $owner->id,
        ])->assertSessionHasErrors('documents');
        $this->post('/customs-processes', [
            'customer_id' => $customer->id,
            'documents' => [UploadedFile::fake()->create('requerimento.pdf', 5, 'application/pdf')],
        ])->assertSessionHasErrors('responsible_user_id');
        $this->assertSame(0, CustomsProcess::count());

        $response = $this->post('/customs-processes', [
            'customer_id' => $customer->id,
            'responsible_user_id' => $owner->id,
            'documents' => [
                UploadedFile::fake()->create('requerimento-a.pdf', 5, 'application/pdf'),
                UploadedFile::fake()->create('requerimento-b.pdf', 5, 'application/pdf'),
            ],
        ])->assertSessionHas('success');

        $process = CustomsProcess::firstOrFail();
        $response->assertRedirect(route('customs.show', $process));
        $this->assertNull($process->entry_number);
        $this->assertNull($process->entry_date);
        $this->assertSame('colocado', $process->stage);
        $this->assertSame($owner->id, $process->responsible_user_id);
        $this->assertSame(2, $process->documents()->count());
        $this->get(route('customs.show', $process))->assertOk()->assertSee('Processo #'.$process->id);
        $this->get(route('customs.index'))->assertOk()->assertSee('Processo #'.$process->id);

        foreach (['Preparar documentos', 'Submeter requerimento'] as $title) {
            $this->post(route('customs.tasks.store', $process), [
                'title' => $title, 'status' => 'pendente',
            ])->assertRedirect(route('customs.show', $process));
        }
        $this->assertSame(2, $process->tasks()->count());
        $this->get(route('customs.show', $process))->assertOk()
            ->assertSee('Preparar documentos')->assertSee('Submeter requerimento');
    }

    public function test_process_registration_editing_business_days_and_private_attachments(): void
    {
        Storage::fake('local');
        $owner = $this->owner();
        $this->actingAs($owner);
        $customer = $this->customer();

        $this->get('/modules')->assertOk()->assertSee('Processos Aduaneiros');
        $this->get('/customs-processes/create')->assertOk()
            ->assertSee('Cliente / Importador')->assertSee('Técnico despachante responsável')
            ->assertSee('Requerimentos')
            ->assertSee('Importadora Maputo')->assertSee('Procurar pelo nome ou NUIT')
            ->assertSee('Procurar técnico pelo nome')
            ->assertSee('data-importer-search', false)
            ->assertSee('data-responsible-search', false);

        $this->post('/customs-processes', [
            'entry_number' => 'ENT-2026-001', 'entry_date' => '2026-10-09', 'customer_id' => $customer->id,
            'responsible_user_id' => $owner->id,
            'documents' => [UploadedFile::fake()->create('requerimento.pdf', 100, 'application/pdf')],
            'invoice_reference' => 'FAT-001', 'subject' => 'Importação de botijas',
            'at_technician' => 'Técnico A', 'stage' => 'em_tratamento',
        ])->assertSessionHasErrors('invoice_attachment');
        $this->assertSame(0, CustomsProcess::count());

        $this->post('/customs-processes', [
            'entry_number' => 'ENT-2026-001', 'entry_date' => '2026-10-09', 'customer_id' => $customer->id,
            'responsible_user_id' => $owner->id,
            'documents' => [UploadedFile::fake()->create('requerimento.pdf', 100, 'application/pdf')],
            'invoice_reference' => 'FAT-001', 'invoice_attachment' => UploadedFile::fake()->create('fatura.pdf', 100, 'application/pdf'),
            'subject' => 'Importação de botijas', 'at_technician' => 'Técnico A',
            'stage' => 'em_tratamento', 'communication' => 'Pedido de esclarecimento',
            'communication_attachment' => UploadedFile::fake()->create('comunicacao.pdf', 100, 'application/pdf'),
            'exit_date' => '2026-10-13', 'archive_location' => 'Armário A',
        ])->assertSessionHas('success');

        $process = CustomsProcess::firstOrFail();
        $this->assertSame(2, $process->events()->count());
        $this->assertSame('created', $process->events()->first()->type);
        $this->get("/customs-processes/{$process->id}")->assertOk()
            ->assertSee('Ver linha do tempo')->assertSee('Tarefas do processo')
            ->assertDontSee('Dados do processo')->assertDontSee('Processo registado')
            ->assertDontSee('FAT-001')->assertDontSee('Armário A')
            ->assertSee('href="'.route('customs.timeline', $process).'"', false);
        $this->get(route('customs.timeline', $process))->assertOk()
            ->assertSee('Linha do tempo')->assertSee('Processo registado')
            ->assertSee('Administrador')->assertSee('FAT-001')->assertSee('Armário A')
            ->assertSee('Técnico A')->assertSee('Pedido de esclarecimento');
        $this->get(route('customs.edit', $process))->assertOk()
            ->assertSee('href="'.route('customs.attachment', [$process, 'invoice']).'"', false)
            ->assertSee('href="'.route('customs.attachment', [$process, 'communication']).'"', false);
        $this->assertSame(2, $process->businessDaysElapsed());
        $this->assertSame($customer->id, $process->customer_id);
        Storage::disk('local')->assertExists([$process->invoice_attachment_path, $process->communication_attachment_path]);
        $this->get('/customs-processes')->assertOk()->assertSee('ENT-2026-001')
            ->assertSee('href="'.route('customs.show', $process).'"', false)
            ->assertSee('Importadora Maputo')->assertSee('Em tratamento')
            ->assertDontSee('Fatura / Referência')->assertDontSee('Assunto')
            ->assertDontSee('Armário A')->assertDontSee('Técnico A');
        $invoiceResponse = $this->get("/customs-processes/{$process->id}/attachments/invoice")->assertOk();
        $this->assertStringContainsString('private', $invoiceResponse->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', $invoiceResponse->headers->get('Cache-Control'));
        $this->get("/customs-processes/{$process->id}/attachments/communication")->assertOk();
        $this->get("/customs-processes/{$process->id}/attachments/other")->assertNotFound();

        $this->patch("/customs-processes/{$process->id}", [
            'entry_number' => 'ENT-2026-001', 'entry_date' => '2026-10-09', 'customer_id' => $customer->id,
            'invoice_reference' => 'FAT-001', 'remove_invoice_attachment' => 1,
            'stage' => 'concluido', 'exit_date' => '2026-10-13',
        ])->assertSessionHasErrors('invoice_attachment');
        $this->assertSame('em_tratamento', $process->fresh()->stage);

        $oldInvoicePath = $process->invoice_attachment_path;
        $this->patch("/customs-processes/{$process->id}", [
            'entry_number' => 'ENT-2026-001', 'entry_date' => '2026-10-09', 'customer_id' => $customer->id,
            'invoice_reference' => '', 'remove_invoice_attachment' => 1,
            'stage' => 'concluido', 'exit_date' => '2026-10-13',
            'communication' => 'Pedido de esclarecimento',
        ])->assertRedirect('/customs-processes')->assertSessionHas('success');
        $this->assertNull($process->fresh()->invoice_attachment_path);
        $this->assertSame(3, $process->events()->count());
        $updateEvent = $process->events()->latest('id')->firstOrFail();
        $this->assertSame('updated', $updateEvent->type);
        $this->assertContains(['label' => 'Estágio', 'from' => 'Em tratamento', 'to' => 'Concluído'], $updateEvent->changes);
        $this->assertContains(['label' => 'Anexo da fatura', 'from' => 'fatura.pdf', 'to' => null, 'action' => 'removido'], $updateEvent->changes);
        $this->get(route('customs.timeline', $process))->assertOk()
            ->assertSee('Processo atualizado')->assertSee('Concluído')->assertSee('Removido: fatura.pdf');
        Storage::disk('local')->assertMissing($oldInvoicePath);
        $this->get("/customs-processes/{$process->id}/attachments/invoice")->assertNotFound();
        $this->get("/customs-processes/{$process->id}/attachments/communication")->assertOk();
    }

    public function test_module_permission_and_process_validation(): void
    {
        Storage::fake('local');
        $this->artisan('db:seed')->assertSuccessful();
        $group = AccessGroup::create(['name' => 'Aduaneiros']);
        $group->permissions()->attach(Permission::where('key', 'customs.manage')->firstOrFail());
        $agent = User::create(['name' => 'Agente', 'username' => 'agente', 'password' => 'password12345', 'is_active' => true]);
        $agent->groups()->attach($group);
        $customer = $this->customer();
        $inactive = $this->customer(false);
        $this->actingAs($agent);

        $this->get('/')->assertRedirect('/modules');
        $this->get('/modules')->assertOk()->assertSee('href="'.route('customs.index').'"', false);
        $this->get('/customs-processes')->assertOk()->assertDontSee('href="'.route('customers.index').'"', false);
        $this->get('/customers')->assertForbidden();

        $base = ['entry_number' => 'ENT-002', 'entry_date' => '2026-10-13', 'customer_id' => $customer->id, 'stage' => 'recebido', 'responsible_user_id' => $agent->id];
        $request = fn (array $changes = []) => [...$base, 'documents' => [UploadedFile::fake()->create('requerimento.pdf', 5, 'application/pdf')], ...$changes];
        $this->post('/customs-processes', $request(['customer_id' => $inactive->id]))->assertSessionHasErrors('customer_id');
        $this->post('/customs-processes', $request(['stage' => 'inventado']))->assertSessionHasErrors('stage');
        $this->post('/customs-processes', $request(['exit_date' => '2026-10-12']))->assertSessionHasErrors('exit_date');
        $this->post('/customs-processes', $request(['communication' => 'Carta enviada']))->assertSessionHasErrors('communication_attachment');
        $this->post('/customs-processes', $request(['invoice_attachment' => UploadedFile::fake()->create('codigo.php', 10, 'text/x-php')]))->assertSessionHasErrors('invoice_attachment');
        $this->assertSame(0, CustomsProcess::count());
        $this->post('/customs-processes', $request())->assertSessionHas('success');
        $process = CustomsProcess::firstOrFail();
        $this->patch("/customs-processes/{$process->id}", $base)->assertSessionHas('success');
        $this->assertSame(2, $process->events()->count());
        $this->post('/customs-processes', $request())->assertSessionHasErrors('entry_number');

        $outsider = User::create(['name' => 'Outro', 'username' => 'outro', 'password' => 'password12345', 'is_active' => true]);
        $this->actingAs($outsider);
        $this->get('/customs-processes')->assertForbidden();
        $this->get('/customs-processes/1/edit')->assertForbidden();
        $this->get('/customs-processes/1')->assertForbidden();
        $this->get('/customs-processes/1/timeline')->assertForbidden();
        $this->get('/customs-processes/1/attachments/invoice')->assertForbidden();
        $this->post('/customs-processes', $base)->assertForbidden();
    }

    public function test_process_has_responsible_tasks_multiple_private_documents_and_timeline(): void
    {
        Storage::fake('local');
        $this->artisan('db:seed')->assertSuccessful();
        $owner = $this->owner();
        $agent = User::create(['name' => 'Responsável Aduaneiro', 'username' => 'responsavel', 'password' => 'password12345', 'is_active' => true]);
        $agent->directPermissions()->attach(Permission::where('key', 'customs.manage')->firstOrFail());
        $customer = $this->customer();
        $this->actingAs($owner);

        $this->get('/customs-processes/create')->assertOk()
            ->assertSee('Cliente / Importador')->assertSee('Técnico despachante responsável')
            ->assertSee('Requerimentos')->assertSee('Responsável Aduaneiro');

        $this->post('/customs-processes', [
            'entry_number' => 'ADU-100', 'entry_date' => '2026-10-16',
            'customer_id' => $customer->id, 'responsible_user_id' => $agent->id,
            'stage' => 'recebido',
            'documents' => [
                UploadedFile::fake()->create('requerimento-a.pdf', 100, 'application/pdf'),
                UploadedFile::fake()->create('requerimento-b.pdf', 100, 'application/pdf'),
            ],
        ])->assertSessionHas('success');

        $process = CustomsProcess::firstOrFail();
        $this->assertSame($agent->id, $process->responsible_user_id);
        $this->assertSame($owner->id, $process->created_by_user_id);
        $this->get(route('customs.edit', $process))->assertOk()
            ->assertSee('data-responsible-search', false)
            ->assertSee('Procurar técnico pelo nome');
        $this->assertSame(2, $process->documents()->count());
        $this->assertSame(2, $process->events()->count());
        $document = $process->documents()->firstOrFail();
        Storage::disk('local')->assertExists($document->path);
        $documentResponse = $this->get(route('customs.documents.download', [$process, $document]))->assertOk();
        $this->assertStringContainsString('private', $documentResponse->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', $documentResponse->headers->get('Cache-Control'));
        $this->get(route('customs.show', $process))->assertOk()
            ->assertSee('Requerimentos e documentos')->assertSee('requerimento-a.pdf')
            ->assertSee('requerimento-b.pdf')->assertSee('Tarefas do processo')
            ->assertSee('Responsável Aduaneiro')->assertDontSee('Documentos adicionados');
        $this->get(route('customs.timeline', $process))->assertOk()->assertSee('Documentos adicionados');

        $this->post(route('customs.documents.store', $process), [
            'documents' => [UploadedFile::fake()->create('pedido-adicional.pdf', 100, 'application/pdf')],
        ])->assertRedirect(route('customs.show', $process));
        $this->assertSame(3, $process->documents()->count());

        $this->post(route('customs.tasks.store', $process), [
            'title' => 'Submeter requerimento', 'description' => 'Entregar nas alfândegas',
            'status' => 'pendente', 'due_date' => '2026-10-20', 'assigned_user_id' => $agent->id,
        ])->assertRedirect(route('customs.show', $process));
        $task = $process->tasks()->firstOrFail();
        $this->assertSame($agent->id, $task->assigned_user_id);
        $this->patch(route('customs.tasks.update', [$process, $task]), [
            'title' => 'Submeter requerimento', 'description' => 'Entregar nas alfândegas',
            'status' => 'concluida', 'due_date' => '2026-10-20', 'assigned_user_id' => $agent->id,
        ])->assertRedirect(route('customs.show', $process));
        $this->assertSame('concluida', $task->fresh()->status);
        $this->assertSame(5, $process->events()->count());
        $this->get(route('customs.show', $process))->assertOk()
            ->assertSee('Submeter requerimento')->assertSee('Concluída')
            ->assertDontSee('class="customs-timeline"', false)
            ->assertSee('pedido-adicional.pdf');
        $this->get(route('customs.timeline', $process))->assertOk()
            ->assertSee('Tarefa adicionada')->assertSee('Tarefa atualizada');

        $this->patch(route('customs.update', $process), [
            'entry_number' => 'ADU-100', 'entry_date' => '2026-10-16',
            'customer_id' => $customer->id, 'stage' => 'recebido',
            'responsible_user_id' => $owner->id,
        ])->assertSessionHas('success');
        $this->assertSame($owner->id, $process->fresh()->responsible_user_id);
        $this->assertContains(
            ['label' => 'Responsável', 'from' => 'Responsável Aduaneiro', 'to' => 'Administrador'],
            $process->events()->latest('id')->firstOrFail()->changes
        );

        $otherProcess = CustomsProcess::create([
            'entry_number' => 'ADU-101', 'entry_date' => '2026-10-16',
            'customer_id' => $customer->id, 'stage' => 'recebido',
            'created_by_user_id' => $owner->id, 'responsible_user_id' => $owner->id,
        ]);
        $this->get(route('customs.documents.download', [$otherProcess, $document]))->assertNotFound();
        $this->patch(route('customs.tasks.update', [$otherProcess, $task]), [
            'title' => 'Inválido', 'status' => 'concluida',
        ])->assertNotFound();

        $outsider = User::create(['name' => 'Sem acesso', 'username' => 'semacesso', 'password' => 'password12345', 'is_active' => true]);
        $this->actingAs($outsider);
        $this->get(route('customs.documents.download', [$process, $document]))->assertForbidden();
        $this->post(route('customs.tasks.store', $process), ['title' => 'Outra', 'status' => 'pendente'])->assertForbidden();
    }

    public function test_responsible_creates_task_with_process_updates_and_private_task_documents(): void
    {
        Storage::fake('local');
        $this->artisan('db:seed')->assertSuccessful();
        $owner = $this->owner();
        $customer = $this->customer();
        $permission = Permission::where('key', 'customs.manage')->firstOrFail();
        $responsible = User::create(['name' => 'Técnica Responsável', 'username' => 'responsavel-task', 'password' => 'password12345', 'is_active' => true]);
        $responsible->directPermissions()->attach($permission);
        $colleague = User::create(['name' => 'Outro Técnico', 'username' => 'outro-tecnico', 'password' => 'password12345', 'is_active' => true]);
        $colleague->directPermissions()->attach($permission);

        $this->actingAs($owner)->post('/customs-processes', [
            'customer_id' => $customer->id, 'responsible_user_id' => $responsible->id,
            'documents' => [UploadedFile::fake()->create('requerimento.pdf', 5, 'application/pdf')],
        ])->assertSessionHas('success');
        $process = CustomsProcess::firstOrFail();

        $this->actingAs($colleague);
        $this->get(route('customs.tasks.create', $process))->assertForbidden();
        $this->post(route('customs.tasks.store', $process), ['title' => 'Inválida', 'status' => 'pendente'])->assertForbidden();

        $this->actingAs($responsible);
        $show = $this->get(route('customs.show', $process))->assertOk()->assertSee('+ Criar tarefa');
        $this->assertStringNotContainsString('class="customs-timeline"', $show->getContent());
        $this->get(route('customs.timeline', $process))->assertOk()->assertSee('Processo registado');
        $this->get(route('customs.tasks.create', $process))->assertOk()
            ->assertSee('Nº de entrada')->assertSee('Estágio do processo')->assertSee('Documentos da tarefa');

        $this->post(route('customs.tasks.store', $process), [
            'title' => 'Submeter declaração', 'status' => 'em_curso',
            'description' => 'Entrega nas alfândegas',
            'entry_number' => 'ENT-300', 'entry_date' => '2026-10-29',
            'process_stage' => 'em_tratamento', 'at_technician' => 'Técnico AT A',
            'invoice_reference' => 'FAT-300',
            'invoice_attachment' => UploadedFile::fake()->create('fatura.pdf', 5, 'application/pdf'),
            'communication' => 'Declaração apresentada',
            'communication_attachment' => UploadedFile::fake()->create('comunicacao.pdf', 5, 'application/pdf'),
            'documents' => [UploadedFile::fake()->create('declaracao.pdf', 5, 'application/pdf')],
        ])->assertSessionHas('success')->assertRedirect(route('customs.show', $process));

        $process->refresh();
        $task = $process->tasks()->firstOrFail();
        $document = $task->documents()->firstOrFail();
        $this->assertSame('ENT-300', $process->entry_number);
        $this->assertSame('em_tratamento', $process->stage);
        $this->assertSame($responsible->id, $task->assigned_user_id);
        $this->assertSame($process->id, $document->customs_process_id);
        Storage::disk('local')->assertExists($document->path);
        $this->get(route('customs.tasks.documents.download', [$process, $task, $document]))->assertOk();
        $this->get(route('customs.documents.download', [$process, $document]))->assertNotFound();
        $this->get(route('customs.show', $process))->assertOk()
            ->assertSee('declaracao.pdf')->assertSee('ENT-300')->assertSee('Em tratamento')
            ->assertSee('Estágio do processo')->assertSee('Submeter declaração');

        $this->patch(route('customs.tasks.update', [$process, $task]), [
            'title' => 'Submeter declaração', 'status' => 'concluida',
            'process_stage' => 'concluido',
            'documents' => [UploadedFile::fake()->create('comprovativo.pdf', 5, 'application/pdf')],
        ])->assertSessionHas('success');
        $this->assertSame(2, $task->documents()->count());
        $this->assertSame('concluido', $process->fresh()->stage);
        $this->assertSame('concluida', $task->fresh()->status);
    }

    public function test_customs_documents_tasks_and_responsible_validation_preserve_existing_process(): void
    {
        Storage::fake('local');
        $this->artisan('db:seed')->assertSuccessful();
        $owner = $this->owner();
        $customer = $this->customer();
        $outsider = User::create(['name' => 'Sem permissão', 'username' => 'sempermissao', 'password' => 'password12345', 'is_active' => true]);
        $this->actingAs($owner);
        $base = ['entry_number' => 'ADU-200', 'entry_date' => '2026-10-16', 'customer_id' => $customer->id, 'stage' => 'recebido', 'responsible_user_id' => $owner->id];

        $this->post('/customs-processes', [...$base, 'responsible_user_id' => $outsider->id,
            'documents' => [UploadedFile::fake()->create('requerimento.pdf', 5, 'application/pdf')]])
            ->assertSessionHasErrors('responsible_user_id');
        $this->post('/customs-processes', [...$base, 'documents' => [UploadedFile::fake()->create('script.php', 5, 'text/x-php')]])
            ->assertSessionHasErrors('documents.0');
        $this->assertSame(0, CustomsProcess::count());

        $this->post('/customs-processes', [...$base,
            'documents' => [UploadedFile::fake()->create('requerimento.pdf', 5, 'application/pdf')]])->assertSessionHas('success');
        $process = CustomsProcess::firstOrFail();
        $this->assertSame($owner->id, $process->responsible_user_id);
        $this->post(route('customs.documents.store', $process), [
            'documents' => [UploadedFile::fake()->create('script.php', 5, 'text/x-php')],
        ])->assertSessionHasErrors('documents.0');
        $this->post(route('customs.documents.store', $process), [
            'documents' => array_map(fn ($index) => UploadedFile::fake()->create("doc-{$index}.pdf", 5, 'application/pdf'), range(1, 11)),
        ])->assertSessionHasErrors('documents');
        $this->assertSame(1, $process->documents()->count());
        $this->assertSame(2, $process->events()->count());

        $this->post(route('customs.tasks.store', $process), [
            'title' => 'Rever documentação', 'status' => 'inventado',
        ])->assertSessionHasErrors('status');
        $this->post(route('customs.tasks.store', $process), [
            'title' => 'Rever documentação', 'status' => 'pendente', 'assigned_user_id' => $outsider->id,
        ])->assertSessionHasErrors('assigned_user_id');
        $this->assertSame(0, $process->tasks()->count());

        $this->post(route('customs.tasks.store', $process), [
            'title' => 'Rever documentação', 'status' => 'pendente',
        ])->assertSessionHas('success');
        $task = $process->tasks()->firstOrFail();
        $this->assertSame($owner->id, $task->assigned_user_id);
        $this->patch(route('customs.tasks.update', [$process, $task]), [
            'title' => 'Rever documentação', 'status' => 'pendente', 'assigned_user_id' => $owner->id,
        ])->assertSessionHas('success');
        $this->assertSame(3, $process->events()->count());

        $this->patch(route('customs.update', $process), [
            ...$base, 'documents' => [UploadedFile::fake()->create('requerimento-final.pdf', 10, 'application/pdf')],
        ])->assertSessionHas('success');
        $this->assertSame(2, $process->documents()->count());
        $this->assertSame(4, $process->events()->count());
    }
}
