<div class="importer-picker" data-searchable-picker data-show-all-options>
    <label for="responsible-native">Técnico despachante responsável</label>
    <select id="responsible-native" name="responsible_user_id" data-searchable-native required>
        <option value="">Selecione o utilizador</option>
        @foreach($assignees as $assignee)
            <option value="{{ $assignee->id }}" @selected(old('responsible_user_id', $process?->responsible_user_id) == $assignee->id)>{{ $assignee->name }}</option>
        @endforeach
    </select>

    <div class="importer-combobox" data-searchable-combobox hidden>
        <input id="responsible-search" type="search" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="responsible-options" autocomplete="off" placeholder="Procurar técnico pelo nome" data-searchable-search data-responsible-search>
        <input type="hidden" name="responsible_user_id" data-searchable-id disabled>
        <div id="responsible-options" class="importer-options" role="listbox" data-searchable-options hidden></div>
    </div>
</div>
