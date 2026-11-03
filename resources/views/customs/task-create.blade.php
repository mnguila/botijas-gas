@extends('layouts.app')
@section('title', 'Criar tarefa aduaneira')
@section('content')
<div class="operations-heading"><div>
    <span class="section-eyebrow">{{ $process->display_reference }} · {{ $process->customer?->name }}</span>
    <h2 class="page-title">Criar tarefa no processo</h2>
    <p class="page-subtitle">Registe o trabalho realizado, atualize os dados do processo e anexe os documentos desta tarefa.</p>
</div><a class="small-link" href="{{ route('customs.show', $process) }}">← Voltar ao processo</a></div>

<form method="post" action="{{ route('customs.tasks.store', $process) }}" enctype="multipart/form-data">@csrf
    <section class="panel">
        <h2>Nova tarefa</h2>
        <div class="fields">
            <label>Título da tarefa<input name="title" maxlength="255" value="{{ old('title') }}" required></label>
            <label>Estado da tarefa<select name="status" required>@foreach($taskStatuses as $key => $label)<option value="{{ $key }}" @selected(old('status', 'pendente') === $key)>{{ $label }}</option>@endforeach</select></label>
            <label>Prazo<input type="date" name="due_date" value="{{ old('due_date') }}"></label>
            <label>Técnico responsável<input value="{{ $process->responsibleUser?->name ?? '—' }}" readonly></label>
            <label class="field-wide">Descrição / atualização<textarea name="description" maxlength="5000" rows="4">{{ old('description') }}</textarea></label>
        </div>
        <label>Documentos da tarefa <span class="muted">(opcional)</span><input type="file" name="documents[]" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"></label>
        <small class="form-help">Até 10 ficheiros, com máximo de 10 MB cada. Ficam ligados a esta tarefa.</small>
    </section>

    <section class="panel">
        <h2>Atualizar dados do processo</h2>
        <p class="muted">Os dados alterados ficam registados na linha do tempo do processo.</p>
        <div class="fields three">
            <label>Nº de entrada<input name="entry_number" maxlength="100" value="{{ old('entry_number', $process->entry_number) }}"></label>
            <label>Data de entrada<input type="date" name="entry_date" value="{{ old('entry_date', $process->entry_date?->format('Y-m-d')) }}"></label>
            <label>Estágio do processo<select name="process_stage">@foreach($stages as $key => $label)<option value="{{ $key }}" @selected(old('process_stage', $process->stage) === $key)>{{ $label }}</option>@endforeach</select></label>
            <label>Fatura / referência<input name="invoice_reference" maxlength="255" value="{{ old('invoice_reference', $process->invoice_reference) }}"></label>
            <label>Anexo da fatura<input type="file" name="invoice_attachment" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"></label>
            <label>Técnico AT<input name="at_technician" maxlength="150" value="{{ old('at_technician', $process->at_technician) }}"></label>
            <label class="field-wide">Assunto<textarea name="subject" maxlength="2000" rows="3">{{ old('subject', $process->subject) }}</textarea></label>
            <label class="field-wide">Comunicação<textarea name="communication" maxlength="2000" rows="3">{{ old('communication', $process->communication) }}</textarea></label>
            <label>Anexo da comunicação<input type="file" name="communication_attachment" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"></label>
            <label>Data de saída<input type="date" name="exit_date" value="{{ old('exit_date', $process->exit_date?->format('Y-m-d')) }}"></label>
            <label>Local do arquivo<input name="archive_location" maxlength="255" value="{{ old('archive_location', $process->archive_location) }}"></label>
        </div>
        <p class="muted">Se preencher a fatura ou a comunicação pela primeira vez, anexe também o respetivo documento.</p>
    </section>
    <div class="form-actions"><button type="submit">Criar tarefa e guardar atualização</button><a href="{{ route('customs.show', $process) }}">Cancelar</a></div>
</form>
@endsection
