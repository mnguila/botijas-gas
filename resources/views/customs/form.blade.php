<section class="customs-form-section">
    <h3>Dados principais</h3>
    <div class="fields three">
        <label>Nº Entrada <span class="muted">(quando atribuído)</span><input name="entry_number" maxlength="100" value="{{ old('entry_number', $process?->entry_number) }}" placeholder="Informe o número de entrada"></label>
        <label>Data Entrada <span class="muted">(quando conhecida)</span><input type="date" name="entry_date" value="{{ old('entry_date', $process?->entry_date?->format('Y-m-d')) }}" data-entry-date></label>
        <label>Dias Úteis a Correr<input type="number" value="{{ $process?->businessDaysElapsed() ?? '' }}" placeholder="Calculado automaticamente" readonly data-business-days><small class="form-help">Exclui sábados e domingos. A contagem começa no dia seguinte à entrada.</small></label>
        @include('customs.responsible-picker')
    </div>
</section>

<section class="customs-form-section">
    <h3>Cliente e referência</h3>
    <div class="fields">
        @include('customs.importer-picker')
        <label>Fatura / Referência<input name="invoice_reference" maxlength="255" value="{{ old('invoice_reference', $process?->invoice_reference) }}" placeholder="Informe a fatura ou referência" data-attachment-reason="invoice"></label>
        <div>
            <label>Anexo da Fatura<input type="file" name="invoice_attachment" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" data-attachment-file="invoice"></label>
            <small class="form-help">Obrigatório se preencher a fatura ou referência. PDF, imagem ou Word até 10 MB.</small>
            @if($process?->invoice_attachment_path)
                <div class="customs-existing-file"><a href="{{ route('customs.attachment', [$process, 'invoice']) }}">{{ $process->invoice_attachment_name }}</a><label class="check"><input type="checkbox" name="remove_invoice_attachment" value="1" data-remove-attachment="invoice"> Remover anexo</label></div>
            @endif
        </div>
        <label class="field-wide">Assunto<textarea name="subject" maxlength="2000" rows="3" placeholder="Descreva o assunto do processo">{{ old('subject', $process?->subject) }}</textarea></label>
    </div>
</section>

<section class="customs-form-section">
    <h3>Requerimentos e outros documentos</h3>
    <label>Anexar vários documentos<input type="file" name="documents[]" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"></label>
    <small class="form-help">Pode selecionar até 10 ficheiros de cada vez. PDF, imagem ou Word; o limite da aplicação é 10 MB por ficheiro e o servidor pode impor um limite inferior. Pode adicionar mais documentos depois de abrir o processo.</small>
    @if($process?->exists)<a class="small-link" href="{{ route('customs.show', $process) }}">Ver documentos já anexados</a>@endif
</section>

<section class="customs-form-section">
    <h3>Detalhes adicionais</h3>
    <div class="fields three">
        <label>Técnico AT<input name="at_technician" maxlength="150" value="{{ old('at_technician', $process?->at_technician) }}" placeholder="Informe o técnico responsável"></label>
        <label>Estágio<select name="stage" required><option value="">Selecione o status</option>@foreach($stages as $key => $label)<option value="{{ $key }}" @selected(old('stage', $process?->stage) === $key)>{{ $label }}</option>@endforeach</select></label>
        <label>Comunicação<input name="communication" maxlength="2000" value="{{ old('communication', $process?->communication) }}" placeholder="Informe a comunicação" data-attachment-reason="communication"></label>
        <div>
            <label>Anexo da Comunicação<input type="file" name="communication_attachment" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" data-attachment-file="communication"></label>
            <small class="form-help">Obrigatório se preencher a comunicação. PDF, imagem ou Word até 10 MB.</small>
            @if($process?->communication_attachment_path)
                <div class="customs-existing-file"><a href="{{ route('customs.attachment', [$process, 'communication']) }}">{{ $process->communication_attachment_name }}</a><label class="check"><input type="checkbox" name="remove_communication_attachment" value="1" data-remove-attachment="communication"> Remover anexo</label></div>
            @endif
        </div>
        <label>Data Saída<input type="date" name="exit_date" value="{{ old('exit_date', $process?->exit_date?->format('Y-m-d')) }}" data-exit-date></label>
        <label>Local do Arquivo<input name="archive_location" maxlength="255" value="{{ old('archive_location', $process?->archive_location) }}" placeholder="Informe o local do arquivo"></label>
    </div>
</section>

@push('scripts')
<script src="{{ asset('js/customs-searchable-select.js') }}?v={{ filemtime(public_path('js/customs-searchable-select.js')) }}"></script>
<script>
const entryDate = document.querySelector('[data-entry-date]');
const exitDate = document.querySelector('[data-exit-date]');
const businessDays = document.querySelector('[data-business-days]');
function refreshBusinessDays() {
    if (!entryDate.value) { businessDays.value = ''; return; }
    const start = new Date(`${entryDate.value}T00:00:00Z`);
    const today = new Intl.DateTimeFormat('sv-SE', {timeZone: 'Africa/Maputo'}).format(new Date());
    const end = new Date(`${exitDate.value || today}T00:00:00Z`);
    if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime()) || end < start) { businessDays.value = ''; return; }
    let count = 0;
    for (let day = new Date(start.getTime() + 86400000); day <= end; day.setUTCDate(day.getUTCDate() + 1)) {
        if (day.getUTCDay() !== 0 && day.getUTCDay() !== 6) count++;
    }
    businessDays.value = count;
}
entryDate.addEventListener('change', refreshBusinessDays);
exitDate.addEventListener('change', refreshBusinessDays);
refreshBusinessDays();
const existingAttachments = {{ Js::from([
    'invoice' => (bool) ($process?->invoice_attachment_path),
    'communication' => (bool) ($process?->communication_attachment_path),
]) }};
for (const kind of ['invoice', 'communication']) {
    const reason = document.querySelector(`[data-attachment-reason="${kind}"]`);
    const file = document.querySelector(`[data-attachment-file="${kind}"]`);
    const remove = document.querySelector(`[data-remove-attachment="${kind}"]`);
    const existing = existingAttachments[kind];
    const refreshRequired = () => { file.required = reason.value.trim() !== '' && (!existing || (remove && remove.checked)); };
    reason.addEventListener('input', refreshRequired);
    remove?.addEventListener('change', refreshRequired);
    refreshRequired();
}
</script>
@endpush
