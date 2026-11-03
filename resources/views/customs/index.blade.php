@extends('layouts.app')
@section('title', 'Processos Aduaneiros')
@section('content')
<div class="operations-heading"><div>
    <span class="section-eyebrow">Gestão aduaneira</span>
    <h2 class="page-title">Processos Aduaneiros</h2>
    <p class="page-subtitle">Consulte o cliente, o responsável e o estágio; selecione um processo para ver tarefas, documentos e histórico.</p>
</div><a class="small-link" href="{{ route('customs.create') }}">+ Novo processo</a></div>

<div class="customer-summary">
    <div><span>Total de processos</span><strong>{{ number_format($totalProcesses, 0, ',', '.') }}</strong></div>
    <div><span>Em curso</span><strong>{{ number_format($openProcesses, 0, ',', '.') }}</strong></div>
    <div><span>Com data de saída</span><strong>{{ number_format($totalProcesses - $openProcesses, 0, ',', '.') }}</strong></div>
</div>

<section class="panel">
    <h2>Processos registados</h2>
    <div class="tablewrap"><table>
        <thead><tr><th>Processo / Nº Entrada</th><th>Data Entrada</th><th>Cliente / Importador</th><th>Técnico despachante</th><th>Estágio</th><th>Dias úteis</th><th>Data Saída</th></tr></thead>
        <tbody>@forelse($processes as $process)<tr>
            <td><a class="customs-entry-link" href="{{ route('customs.show', $process) }}" aria-label="Ver detalhes de {{ $process->display_reference }}">{{ $process->display_reference }}</a></td>
            <td>{{ $process->entry_date?->format('d/m/Y') ?? '—' }}</td>
            <td>{{ $process->customer?->name ?? '—' }}</td>
            <td>{{ $process->responsibleUser?->name ?? $process->createdBy?->name ?? '—' }}</td>
            <td><span class="pill {{ $process->exit_date ? 'active-pill' : '' }}">{{ config('customs.stages.'.$process->stage, $process->stage) }}</span></td>
            <td class="num">{{ $process->businessDaysElapsed() === null ? '—' : number_format($process->businessDaysElapsed(), 0, ',', '.') }}</td>
            <td>{{ $process->exit_date?->format('d/m/Y') ?? 'Em curso' }}</td>
        </tr>@empty<tr><td colspan="7" class="muted">Ainda não há processos aduaneiros registados.</td></tr>@endforelse</tbody>
    </table></div>
    @if($processes->hasPages())<nav class="history-pages" aria-label="Páginas de processos">@if($processes->previousPageUrl())<a href="{{ $processes->previousPageUrl() }}">← Anterior</a>@endif<span>Página {{ $processes->currentPage() }} de {{ $processes->lastPage() }}</span>@if($processes->nextPageUrl())<a href="{{ $processes->nextPageUrl() }}">Próxima →</a>@endif</nav>@endif
</section>
@endsection
