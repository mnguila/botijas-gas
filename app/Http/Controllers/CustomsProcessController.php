<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomsProcess;
use App\Models\CustomsProcessDocument;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class CustomsProcessController extends Controller
{
    private const FIELDS = [
        'entry_number', 'entry_date', 'customer_id', 'invoice_reference',
        'subject', 'at_technician', 'stage', 'communication', 'exit_date', 'archive_location',
        'responsible_user_id',
    ];

    private const FIELD_LABELS = [
        'entry_number' => 'Nº de entrada', 'entry_date' => 'Data de entrada',
        'customer_id' => 'Importador', 'invoice_reference' => 'Fatura / referência',
        'subject' => 'Assunto', 'at_technician' => 'Técnico AT',
        'stage' => 'Estágio', 'communication' => 'Comunicação',
        'exit_date' => 'Data de saída', 'archive_location' => 'Local do arquivo',
        'responsible_user_id' => 'Responsável',
    ];

    public function index()
    {
        return view('customs.index', [
            'processes' => CustomsProcess::with(['customer', 'createdBy', 'responsibleUser'])->latest('id')->paginate(25),
            'totalProcesses' => CustomsProcess::count(),
            'openProcesses' => CustomsProcess::whereNull('exit_date')->count(),
        ]);
    }

    public function create()
    {
        return view('customs.create', $this->formData());
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);
        $attributes = Arr::only($data, self::FIELDS);
        $attributes['created_by_user_id'] = $request->user()->id;
        $newPaths = [];

        try {
            $this->storeUploadedAttachments($request, $attributes, $newPaths);
            $documents = $this->storeAdditionalDocuments($request, $newPaths);
            $process = DB::transaction(function () use ($attributes, $request, $documents) {
                $process = CustomsProcess::create($attributes);
                $changes = $this->describeChanges([], $process->getAttributes(), array_keys($attributes));
                $this->recordEvent($process, $request, 'created', $changes);
                $this->saveDocuments($process, $request, $documents);
                return $process;
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($newPaths);
            throw $exception;
        }

        return redirect()->route('customs.show', $process)->with('success', 'Processo aduaneiro iniciado. Pode adicionar tarefas.');
    }

    public function edit(CustomsProcess $process)
    {
        return view('customs.edit', $this->formData($process));
    }

    public function show(CustomsProcess $process)
    {
        return view('customs.show', [
            'process' => $process->load(['customer', 'createdBy', 'responsibleUser', 'tasks.assignedUser', 'tasks.createdBy', 'tasks.documents.uploadedBy', 'documents.uploadedBy']),
            'assignees' => $this->assignableUsers($process),
            'taskStatuses' => config('customs.task_statuses'),
        ]);
    }

    public function timeline(CustomsProcess $process)
    {
        return view('customs.timeline', [
            'process' => $process->load(['customer', 'responsibleUser']),
            'events' => $process->events()->orderByDesc('created_at')->orderByDesc('id')->get(),
        ]);
    }

    public function update(Request $request, CustomsProcess $process)
    {
        $data = $this->validatedData($request, $process);
        $attributes = Arr::only($data, self::FIELDS);
        $newPaths = [];
        $oldPaths = [];

        foreach (['invoice', 'communication'] as $kind) {
            if ($process->{$kind.'_attachment_path'} && ($request->boolean('remove_'.$kind.'_attachment') || $request->hasFile($kind.'_attachment'))) {
                $oldPaths[] = $process->{$kind.'_attachment_path'};
            }
            if ($request->boolean('remove_'.$kind.'_attachment')) {
                $attributes[$kind.'_attachment_path'] = null;
                $attributes[$kind.'_attachment_name'] = null;
            }
        }

        try {
            $this->storeUploadedAttachments($request, $attributes, $newPaths);
            $documents = $this->storeAdditionalDocuments($request, $newPaths);
            DB::transaction(function () use ($process, $attributes, $request, $documents) {
                $before = $process->getAttributes();
                $process->fill($attributes);
                $dirty = array_keys($process->getDirty());
                if ($dirty !== []) {
                    $changes = $this->describeChanges($before, $process->getAttributes(), $dirty);
                    $process->save();
                    $this->recordEvent($process, $request, 'updated', $changes);
                }
                $this->saveDocuments($process, $request, $documents);
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($newPaths);
            throw $exception;
        }

        Storage::disk('local')->delete($oldPaths);

        return redirect()->route('customs.index')->with('success', 'Processo aduaneiro atualizado.');
    }

    public function downloadAttachment(CustomsProcess $process, string $kind)
    {
        abort_unless(in_array($kind, ['invoice', 'communication'], true), 404);
        $path = $process->{$kind.'_attachment_path'};
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download(
            $path,
            $process->{$kind.'_attachment_name'} ?: basename($path),
            ['Cache-Control' => 'private, no-store']
        );
    }

    public function addDocuments(Request $request, CustomsProcess $process)
    {
        $request->validate($this->documentRules());
        $newPaths = [];
        try {
            $documents = $this->storeAdditionalDocuments($request, $newPaths);
            DB::transaction(fn () => $this->saveDocuments($process, $request, $documents));
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($newPaths);
            throw $exception;
        }

        return redirect()->route('customs.show', $process)->with('success', 'Documentos adicionados ao processo.');
    }

    public function downloadDocument(CustomsProcess $process, CustomsProcessDocument $document)
    {
        abort_unless($document->customs_process_id === $process->id && $document->customs_process_task_id === null, 404);
        abort_unless(Storage::disk('local')->exists($document->path), 404);

        return Storage::disk('local')->download(
            $document->path,
            $document->original_name,
            ['Cache-Control' => 'private, no-store']
        );
    }

    private function formData(?CustomsProcess $process = null): array
    {
        $customers = Customer::where('is_active', true)->orderBy('name')->get();
        if ($process && ! $process->customer->is_active) {
            $customers->prepend($process->customer);
        }

        return [
            'process' => $process,
            'customers' => $customers,
            'stages' => config('customs.stages'),
            'assignees' => $this->assignableUsers($process),
        ];
    }

    private function validatedData(Request $request, ?CustomsProcess $process = null): array
    {
        $creating = $process === null;
        $data = $request->validate([
            'entry_number' => ['nullable', 'string', 'max:100', Rule::unique('customs_processes', 'entry_number')->ignore($process?->id)],
            'entry_date' => ['nullable', 'date_format:Y-m-d', 'required_with:exit_date'],
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'invoice_reference' => ['nullable', 'string', 'max:255'],
            'invoice_attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:10240'],
            'remove_invoice_attachment' => ['sometimes', 'boolean'],
            'subject' => ['nullable', 'string', 'max:2000'],
            'at_technician' => ['nullable', 'string', 'max:150'],
            'stage' => [$creating ? 'nullable' : 'required', Rule::in(array_keys(config('customs.stages')))],
            'communication' => ['nullable', 'string', 'max:2000'],
            'communication_attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:10240'],
            'remove_communication_attachment' => ['sometimes', 'boolean'],
            'exit_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:entry_date'],
            'archive_location' => ['nullable', 'string', 'max:255'],
            'responsible_user_id' => [$creating ? 'required' : 'nullable', 'integer', 'exists:users,id'],
            ...$this->documentRules($creating),
        ]);

        if ($creating) $data['stage'] = $data['stage'] ?? 'colocado';
        $data['responsible_user_id'] = $data['responsible_user_id'] ?? $process?->responsible_user_id ?? $request->user()->id;
        $responsible = User::findOrFail($data['responsible_user_id']);
        if ((! $responsible->is_active || ! $responsible->hasPermission('customs.manage'))
            && $responsible->id !== $process?->responsible_user_id) {
            throw ValidationException::withMessages(['responsible_user_id' => 'Selecione um utilizador activo com acesso aos processos aduaneiros.']);
        }

        $customer = Customer::findOrFail($data['customer_id']);
        if (! $customer->is_active && $customer->id !== $process?->customer_id) {
            throw ValidationException::withMessages(['customer_id' => 'Selecione um importador activo.']);
        }

        foreach (['invoice' => 'invoice_reference', 'communication' => 'communication'] as $kind => $field) {
            $hasAttachment = $request->hasFile($kind.'_attachment')
                || ($process?->{$kind.'_attachment_path'} && ! $request->boolean('remove_'.$kind.'_attachment'));
            if (filled($data[$field] ?? null) && ! $hasAttachment) {
                throw ValidationException::withMessages([
                    $kind.'_attachment' => $kind === 'invoice'
                        ? 'Anexe a fatura quando preencher a fatura ou referência.'
                        : 'Anexe a comunicação quando preencher o campo de comunicação.',
                ]);
            }
        }

        return $data;
    }

    private function storeUploadedAttachments(Request $request, array &$attributes, array &$newPaths): void
    {
        foreach (['invoice', 'communication'] as $kind) {
            $file = $request->file($kind.'_attachment');
            if (! $file) continue;

            $path = $file->store('customs-processes', 'local');
            if (! $path) throw new RuntimeException('Não foi possível guardar o anexo.');

            $newPaths[] = $path;
            $attributes[$kind.'_attachment_path'] = $path;
            $attributes[$kind.'_attachment_name'] = basename($file->getClientOriginalName());
        }
    }

    private function documentRules(bool $required = true): array
    {
        return [
            'documents' => [$required ? 'required' : 'sometimes', 'array', 'min:1', 'max:10'],
            'documents.*' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:10240'],
        ];
    }

    private function storeAdditionalDocuments(Request $request, array &$newPaths): array
    {
        $documents = [];
        foreach ($request->file('documents', []) as $file) {
            $path = $file->store('customs-processes/documents', 'local');
            if (! $path) throw new RuntimeException('Não foi possível guardar o documento.');
            $newPaths[] = $path;
            $documents[] = ['path' => $path, 'original_name' => basename($file->getClientOriginalName())];
        }

        return $documents;
    }

    private function saveDocuments(CustomsProcess $process, Request $request, array $documents): void
    {
        if ($documents === []) return;
        $changes = [];
        foreach ($documents as $document) {
            $process->documents()->create($document + ['uploaded_by_user_id' => $request->user()->id]);
            $changes[] = ['label' => 'Documento adicionado', 'from' => null, 'to' => $document['original_name']];
        }
        $this->recordEvent($process, $request, 'documents_added', $changes);
    }

    private function assignableUsers(?CustomsProcess $process = null)
    {
        $users = User::where('is_active', true)
            ->where(function ($query) {
                $query->where('is_super_admin', true)
                    ->orWhereHas('directPermissions', fn ($permissions) => $permissions->where('key', 'customs.manage'))
                    ->orWhereHas('groups.permissions', fn ($permissions) => $permissions->where('key', 'customs.manage'));
            })
            ->orderBy('name')->get();
        if ($process?->responsibleUser && ! $users->contains('id', $process->responsible_user_id)) {
            $users->prepend($process->responsibleUser);
        }
        foreach ($process?->tasks ?? [] as $task) {
            if ($task->assignedUser && ! $users->contains('id', $task->assigned_user_id)) {
                $users->prepend($task->assignedUser);
            }
        }

        return $users;
    }

    private function recordEvent(CustomsProcess $process, Request $request, string $type, array $changes): void
    {
        $process->events()->create([
            'user_id' => $request->user()->id,
            'actor_name' => $request->user()->name,
            'type' => $type,
            'changes' => $changes,
            'created_at' => now(),
        ]);
    }

    private function describeChanges(array $before, array $after, array $keys): array
    {
        $changes = [];
        foreach (self::FIELD_LABELS as $field => $label) {
            if (! in_array($field, $keys, true)) continue;
            $from = $this->displayValue($field, $before[$field] ?? null);
            $to = $this->displayValue($field, $after[$field] ?? null);
            if ($from !== $to) $changes[] = compact('label', 'from', 'to');
        }

        foreach (['invoice' => 'Anexo da fatura', 'communication' => 'Anexo da comunicação'] as $kind => $label) {
            if (! in_array($kind.'_attachment_path', $keys, true)) continue;
            $from = $before[$kind.'_attachment_name'] ?? null;
            $to = $after[$kind.'_attachment_name'] ?? null;
            $action = $from && $to ? 'substituído' : ($to ? 'adicionado' : 'removido');
            $changes[] = compact('label', 'from', 'to', 'action');
        }

        return $changes;
    }

    private function displayValue(string $field, mixed $value): ?string
    {
        if ($value === null || $value === '') return null;
        if ($field === 'customer_id') return Customer::find($value)?->name ?? 'Cliente #'.$value;
        if ($field === 'responsible_user_id') return User::find($value)?->name ?? 'Utilizador #'.$value;
        if ($field === 'stage') return config('customs.stages.'.$value, $value);
        if (in_array($field, ['entry_date', 'exit_date'], true)) return CarbonImmutable::parse($value)->format('d/m/Y');

        return (string) $value;
    }
}
