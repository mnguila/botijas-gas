<?php

namespace App\Http\Controllers;

use App\Models\CustomsProcess;
use App\Models\CustomsProcessTask;
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

class CustomsProcessTaskController extends Controller
{
    private const FIELDS = ['title', 'description', 'status', 'due_date', 'assigned_user_id'];
    private const PROCESS_FIELDS = [
        'entry_number' => 'Nº de entrada', 'entry_date' => 'Data de entrada',
        'invoice_reference' => 'Fatura / referência', 'subject' => 'Assunto',
        'at_technician' => 'Técnico AT', 'communication' => 'Comunicação',
        'exit_date' => 'Data de saída', 'archive_location' => 'Local do arquivo',
    ];

    private const LABELS = [
        'title' => 'Tarefa', 'description' => 'Descrição', 'status' => 'Estado',
        'due_date' => 'Prazo', 'assigned_user_id' => 'Responsável pela tarefa',
    ];

    public function create(Request $request, CustomsProcess $process)
    {
        $this->authorizeResponsible($request, $process);

        return view('customs.task-create', [
            'process' => $process->load(['customer', 'responsibleUser']),
            'taskStatuses' => config('customs.task_statuses'),
            'stages' => config('customs.stages'),
        ]);
    }

    public function store(Request $request, CustomsProcess $process)
    {
        $this->authorizeResponsible($request, $process);
        $data = $this->validatedData($request, $process);
        $data['status'] = $data['status'] ?? 'pendente';
        $data['assigned_user_id'] = $data['assigned_user_id'] ?? $process->responsible_user_id;
        $newPaths = [];
        $oldPaths = $this->replacedAttachmentPaths($request, $process);

        try {
            $attributes = $this->processAttributes($request, $process, $data, $newPaths);
            $documents = $this->storeDocuments($request, $newPaths);
            DB::transaction(function () use ($process, $request, $data, $attributes, $documents) {
                $processChanges = $this->updateProcess($process, $attributes);
                $task = $process->tasks()->create(Arr::only($data, self::FIELDS) + [
                    'created_by_user_id' => $request->user()->id,
                ]);
                $this->saveDocuments($process, $task, $request, $documents);
                $changes = array_merge($this->changes([], $task->getAttributes(), self::FIELDS), $processChanges);
                foreach ($documents as $document) $changes[] = ['label' => 'Documento da tarefa', 'from' => null, 'to' => $document['original_name']];
                $this->recordEvent($process, $request, 'task_created', $changes);
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($newPaths);
            throw $exception;
        }
        Storage::disk('local')->delete($oldPaths);

        return redirect()->route('customs.show', $process)->with('success', 'Tarefa adicionada ao processo.');
    }

    public function update(Request $request, CustomsProcess $process, CustomsProcessTask $task)
    {
        abort_unless($task->customs_process_id === $process->id, 404);
        $this->authorizeResponsible($request, $process);
        $data = $this->validatedData($request, $process, $task);
        $newPaths = [];
        $oldPaths = $this->replacedAttachmentPaths($request, $process);

        try {
            $attributes = $this->processAttributes($request, $process, $data, $newPaths);
            $documents = $this->storeDocuments($request, $newPaths);
            DB::transaction(function () use ($process, $task, $request, $data, $attributes, $documents) {
                $processChanges = $this->updateProcess($process, $attributes);
                $before = $task->getAttributes();
                $task->fill(Arr::only($data, self::FIELDS));
                $dirty = array_keys($task->getDirty());
                $changes = array_merge($this->changes($before, $task->getAttributes(), $dirty), $processChanges);
                if ($dirty !== []) $task->save();
                $this->saveDocuments($process, $task, $request, $documents);
                foreach ($documents as $document) $changes[] = ['label' => 'Documento da tarefa', 'from' => null, 'to' => $document['original_name']];
                if ($changes === []) return;
                array_unshift($changes, ['label' => 'Tarefa', 'from' => null, 'to' => $task->title, 'action' => 'atualizada']);
                $this->recordEvent($process, $request, 'task_updated', $changes);
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($newPaths);
            throw $exception;
        }
        Storage::disk('local')->delete($oldPaths);

        return redirect()->route('customs.show', $process)->with('success', 'Tarefa atualizada.');
    }

    public function downloadDocument(CustomsProcess $process, CustomsProcessTask $task, CustomsProcessDocument $document)
    {
        abort_unless($task->customs_process_id === $process->id
            && $document->customs_process_id === $process->id
            && $document->customs_process_task_id === $task->id, 404);
        abort_unless(Storage::disk('local')->exists($document->path), 404);

        return Storage::disk('local')->download($document->path, $document->original_name, [
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function validatedData(Request $request, CustomsProcess $process, ?CustomsProcessTask $task = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::in(array_keys(config('customs.task_statuses')))],
            'due_date' => ['nullable', 'date_format:Y-m-d'],
            'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'process_stage' => ['sometimes', Rule::in(array_keys(config('customs.stages')))],
            'entry_number' => ['sometimes', 'nullable', 'string', 'max:100', Rule::unique('customs_processes', 'entry_number')->ignore($process->id)],
            'entry_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'invoice_reference' => ['sometimes', 'nullable', 'string', 'max:255'],
            'subject' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'at_technician' => ['sometimes', 'nullable', 'string', 'max:150'],
            'communication' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'exit_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'archive_location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'invoice_attachment' => ['sometimes', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:10240'],
            'communication_attachment' => ['sometimes', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:10240'],
            'documents' => ['sometimes', 'array', 'min:1', 'max:10'],
            'documents.*' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:10240'],
        ]);

        if (filled($data['assigned_user_id'] ?? null)) {
            $user = User::findOrFail($data['assigned_user_id']);
            if ((! $user->is_active || ! $user->hasPermission('customs.manage'))
                && $user->id !== $task?->assigned_user_id) {
                throw ValidationException::withMessages(['assigned_user_id' => 'Selecione um utilizador activo com acesso aos processos aduaneiros.']);
            }
        }

        $entryDate = array_key_exists('entry_date', $data) ? $data['entry_date'] : $process->entry_date?->format('Y-m-d');
        $exitDate = array_key_exists('exit_date', $data) ? $data['exit_date'] : $process->exit_date?->format('Y-m-d');
        if ($exitDate && (! $entryDate || CarbonImmutable::parse($exitDate)->lt(CarbonImmutable::parse($entryDate)))) {
            throw ValidationException::withMessages(['exit_date' => 'A data de saída deve ser igual ou posterior à data de entrada.']);
        }
        foreach (['invoice' => 'invoice_reference', 'communication' => 'communication'] as $kind => $field) {
            $value = $data[$field] ?? $process->{$field};
            if (filled($value) && ! $request->hasFile($kind.'_attachment') && ! $process->{$kind.'_attachment_path'}) {
                throw ValidationException::withMessages([$kind.'_attachment' => 'Anexe o documento correspondente ao preencher este campo.']);
            }
        }

        return $data;
    }

    private function authorizeResponsible(Request $request, CustomsProcess $process): void
    {
        abort_unless($request->user()->is_super_admin || $request->user()->id === $process->responsible_user_id, 403);
    }

    private function processAttributes(Request $request, CustomsProcess $process, array $data, array &$newPaths): array
    {
        $attributes = Arr::only($data, array_keys(self::PROCESS_FIELDS));
        if (array_key_exists('process_stage', $data)) $attributes['stage'] = $data['process_stage'];

        foreach (['invoice', 'communication'] as $kind) {
            $file = $request->file($kind.'_attachment');
            if (! $file) continue;
            $path = $file->store('customs-processes', 'local');
            if (! $path) throw new RuntimeException('Não foi possível guardar o anexo.');
            $newPaths[] = $path;
            $attributes[$kind.'_attachment_path'] = $path;
            $attributes[$kind.'_attachment_name'] = basename($file->getClientOriginalName());
        }

        return $attributes;
    }

    private function replacedAttachmentPaths(Request $request, CustomsProcess $process): array
    {
        $paths = [];
        foreach (['invoice', 'communication'] as $kind) {
            if ($request->hasFile($kind.'_attachment') && $process->{$kind.'_attachment_path'}) {
                $paths[] = $process->{$kind.'_attachment_path'};
            }
        }

        return $paths;
    }

    private function updateProcess(CustomsProcess $process, array $attributes): array
    {
        if ($attributes === []) return [];
        $before = $process->getAttributes();
        $process->fill($attributes);
        $dirty = array_keys($process->getDirty());
        if ($dirty === []) return [];
        $process->save();

        $changes = [];
        foreach ($dirty as $field) {
            $label = self::PROCESS_FIELDS[$field] ?? match ($field) {
                'stage' => 'Estágio do processo',
                'invoice_attachment_path' => 'Anexo da fatura',
                'communication_attachment_path' => 'Anexo da comunicação',
                default => null,
            };
            if (! $label) continue;
            $from = $before[$field] ?? null;
            $to = $process->getAttributes()[$field] ?? null;
            if (str_ends_with($field, '_attachment_path')) {
                $from = $before[str_replace('_path', '_name', $field)] ?? null;
                $to = $process->getAttributes()[str_replace('_path', '_name', $field)] ?? null;
            } elseif ($field === 'stage') {
                $from = config('customs.stages.'.$from, $from);
                $to = config('customs.stages.'.$to, $to);
            } elseif (in_array($field, ['entry_date', 'exit_date'], true)) {
                $from = $from ? CarbonImmutable::parse($from)->format('d/m/Y') : null;
                $to = $to ? CarbonImmutable::parse($to)->format('d/m/Y') : null;
            }
            $changes[] = ['label' => $label, 'from' => $from, 'to' => $to];
        }

        return $changes;
    }

    private function storeDocuments(Request $request, array &$newPaths): array
    {
        $documents = [];
        foreach ($request->file('documents', []) as $file) {
            $path = $file->store('customs-processes/tasks', 'local');
            if (! $path) throw new RuntimeException('Não foi possível guardar o documento da tarefa.');
            $newPaths[] = $path;
            $documents[] = ['path' => $path, 'original_name' => basename($file->getClientOriginalName())];
        }

        return $documents;
    }

    private function saveDocuments(CustomsProcess $process, CustomsProcessTask $task, Request $request, array $documents): void
    {
        foreach ($documents as $document) {
            $task->documents()->create($document + [
                'customs_process_id' => $process->id,
                'uploaded_by_user_id' => $request->user()->id,
            ]);
        }
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

    private function changes(array $before, array $after, array $keys): array
    {
        $changes = [];
        foreach (self::LABELS as $field => $label) {
            if (! in_array($field, $keys, true)) continue;
            $from = $this->displayValue($field, $before[$field] ?? null);
            $to = $this->displayValue($field, $after[$field] ?? null);
            if ($from !== $to) $changes[] = compact('label', 'from', 'to');
        }

        return $changes;
    }

    private function displayValue(string $field, mixed $value): ?string
    {
        if ($value === null || $value === '') return null;
        if ($field === 'assigned_user_id') return User::find($value)?->name ?? 'Utilizador #'.$value;
        if ($field === 'status') return config('customs.task_statuses.'.$value, $value);
        if ($field === 'due_date') return CarbonImmutable::parse($value)->format('d/m/Y');

        return (string) $value;
    }
}
