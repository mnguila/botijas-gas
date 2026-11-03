@extends('layouts.app')
@section('title', 'Linha do tempo do processo')
@section('content')
<div class="operations-heading"><div>
    <span class="section-eyebrow">Processos Aduaneiros · {{ $process->display_reference }}</span>
    <h2 class="page-title">Linha do tempo</h2>
    <p class="page-subtitle">{{ $process->customer?->name ?? 'Importador não identificado' }} · Histórico das alterações, tarefas e documentos deste processo.</p>
</div><a class="small-link" href="{{ route('customs.show', $process) }}">← Voltar ao processo</a></div>

<section class="panel">
    @if($events->isEmpty())<p class="muted">Ainda não há eventos registados neste processo.</p>@endif
    <ol class="customs-timeline">
        @foreach($events as $event)
            <li class="customs-timeline-item">
                <span class="customs-timeline-dot" aria-hidden="true"></span>
                <div class="customs-timeline-card">
                    <div class="customs-timeline-heading">
                        <strong>{{ match($event->type) { 'created' => 'Processo registado', 'baseline' => 'Início do histórico', 'documents_added' => 'Documentos adicionados', 'task_created' => 'Tarefa adicionada', 'task_updated' => 'Tarefa atualizada', default => 'Processo atualizado' } }}</strong>
                        <time datetime="{{ $event->created_at->toIso8601String() }}">{{ $event->created_at->format('d/m/Y H:i') }}</time>
                    </div>
                    <p class="customs-timeline-actor">Por {{ $event->actor_name ?: 'Utilizador não identificado' }}</p>
                    @if($event->type === 'baseline')
                        <p class="customs-timeline-note">Este processo já existia quando a linha do tempo foi ativada. As alterações anteriores não estão disponíveis.</p>
                    @elseif($event->changes)
                        <dl class="customs-timeline-changes">
                            @foreach($event->changes as $change)
                                <div>
                                    <dt>{{ $change['label'] }}</dt>
                                    <dd>
                                        @if(isset($change['action']))
                                            {{ ucfirst($change['action']) }}: {{ $change['to'] ?? $change['from'] ?? '—' }}
                                        @elseif(in_array($event->type, ['created', 'documents_added', 'task_created'], true))
                                            {{ $change['to'] ?? '—' }}
                                        @else
                                            <span>{{ $change['from'] ?? '—' }}</span><span aria-hidden="true"> → </span><strong>{{ $change['to'] ?? '—' }}</strong>
                                        @endif
                                    </dd>
                                </div>
                            @endforeach
                        </dl>
                    @endif
                </div>
            </li>
        @endforeach
    </ol>
</section>
@endsection
