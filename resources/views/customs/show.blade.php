@extends('layouts.app')
@section('title', 'Detalhes do processo')
@section('content')
<div class="operations-heading"><div>
    <span class="section-eyebrow">Processos Aduaneiros</span>
    <h2 class="page-title">{{ $process->display_reference }}</h2>
    <p class="page-subtitle">{{ $process->customer?->name ?? 'Importador não identificado' }} · Técnico responsável: {{ $process->responsibleUser?->name ?? '—' }}</p>
</div><div class="table-actions"><a class="small-link" href="{{ route('customs.timeline', $process) }}">Ver linha do tempo</a><a class="small-link" href="{{ route('customs.edit', $process) }}">Editar processo</a></div></div>

<div class="customer-summary">
    <div><span>Processo iniciado</span><strong>{{ $process->created_at->format('d-m-Y H:i') }}</strong></div>
    <div><span>Estágio atual</span><strong>{{ config('customs.stages.'.$process->stage, $process->stage) }}</strong></div>
    <div><span>Dias úteis desde a entrada</span><strong>{{ $process->businessDaysElapsed() === null ? '—' : number_format($process->businessDaysElapsed(), 0, ',', '.') }}</strong></div>
</div>

<section class="panel" id="documents">
    <h2>Requerimentos e documentos</h2>
    @if($process->documents->whereNull('customs_process_task_id')->isEmpty())<p class="muted">Ainda não há documentos adicionais neste processo.</p>@endif
    <div class="customs-document-list">
        @foreach($process->documents->whereNull('customs_process_task_id')->sortByDesc('id') as $document)
            <div class="customs-document-row">
                <div><a href="{{ route('customs.documents.download', [$process, $document]) }}">{{ $document->original_name }}</a><small>Adicionado em {{ $document->created_at->format('d/m/Y H:i') }} por {{ $document->uploadedBy?->name ?? 'Utilizador não identificado' }}</small></div>
                <a class="small-link" href="{{ route('customs.documents.download', [$process, $document]) }}">Descarregar</a>
            </div>
        @endforeach
    </div>
    <form method="post" action="{{ route('customs.documents.store', $process) }}" enctype="multipart/form-data" class="customs-inline-form">@csrf
        <label>Adicionar requerimentos ou outros documentos<input type="file" name="documents[]" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" required></label>
        <small class="form-help">Até 10 ficheiros por envio. O limite da aplicação é 10 MB por ficheiro; o servidor pode impor um limite inferior.</small>
        <button type="submit">Adicionar documentos</button>
    </form>
</section>

<section class="panel" id="tasks">
    <div class="operations-heading"><div><h2>Tarefas do processo</h2><p class="muted">Cada tarefa regista o trabalho do técnico e os seus documentos.</p></div>
    @if(auth()->user()->is_super_admin || auth()->id() === $process->responsible_user_id)<a class="small-link" href="{{ route('customs.tasks.create', $process) }}">+ Criar tarefa</a>@endif</div>
    @if($process->tasks->isEmpty())<p class="muted">Ainda não há tarefas neste processo.</p>@endif
    <div class="customs-task-list">
        @foreach($process->tasks->sortBy('id') as $task)
            <article class="customs-task-card">
                <div class="customs-task-heading"><strong>{{ $task->title }}</strong><span class="pill {{ $task->status === 'concluida' ? 'active-pill' : '' }}">{{ $taskStatuses[$task->status] ?? $task->status }}</span></div>
                @if($task->description)<p>{{ $task->description }}</p>@endif
                <div class="customs-task-meta"><span>Responsável: {{ $task->assignedUser?->name ?? 'Não atribuído' }}</span><span>Prazo: {{ $task->due_date?->format('d/m/Y') ?? 'Sem prazo' }}</span></div>
                @if($task->documents->isNotEmpty())<div class="customs-task-documents"><strong>Documentos da tarefa</strong>@foreach($task->documents as $document)<a href="{{ route('customs.tasks.documents.download', [$process, $task, $document]) }}">{{ $document->original_name }}</a>@endforeach</div>@endif
                @if(auth()->user()->is_super_admin || auth()->id() === $process->responsible_user_id)
                <details class="customs-task-edit"><summary>Editar tarefa</summary>
                    <form method="post" action="{{ route('customs.tasks.update', [$process, $task]) }}" enctype="multipart/form-data">@csrf @method('PATCH')
                        <div class="fields"><label>Título<input name="title" maxlength="255" value="{{ $task->title }}" required></label>
                        <label>Estado<select name="status" required>@foreach($taskStatuses as $key => $label)<option value="{{ $key }}" @selected($task->status === $key)>{{ $label }}</option>@endforeach</select></label>
                        <label>Estágio do processo<select name="process_stage">@foreach(config('customs.stages') as $key => $label)<option value="{{ $key }}" @selected($process->stage === $key)>{{ $label }}</option>@endforeach</select></label>
                        <label>Prazo<input type="date" name="due_date" value="{{ $task->due_date?->format('Y-m-d') }}"></label>
                        <label>Responsável<select name="assigned_user_id"><option value="">Não atribuído</option>@foreach($assignees as $assignee)<option value="{{ $assignee->id }}" @selected($task->assigned_user_id === $assignee->id)>{{ $assignee->name }}</option>@endforeach</select></label>
                        <label class="field-wide">Descrição<textarea name="description" maxlength="5000" rows="3">{{ $task->description }}</textarea></label>
                        <label class="field-wide">Adicionar documentos à tarefa<input type="file" name="documents[]" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"></label></div>
                        <button type="submit">Guardar tarefa</button>
                    </form>
                </details>
                @endif
            </article>
        @endforeach
    </div>
</section>

<div class="table-actions customs-process-footer"><a class="small-link" href="{{ route('customs.index') }}">← Voltar aos processos</a><a class="small-link" href="{{ route('customs.timeline', $process) }}">Ver linha do tempo →</a></div>
@endsection
