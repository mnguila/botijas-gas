<div class="importer-picker" data-searchable-picker>
    <label for="importer-native">Cliente / Importador</label>
        <select id="importer-native" name="customer_id" data-searchable-native required>
            <option value="">Selecione o cliente</option>
                @foreach($customers as $customer)
            <option value="{{ $customer->id }}" @selected(old('customer_id', $process?->customer_id) == $customer->id)>{{ $customer->name }}@if($customer->nuit) · NUIT {{ $customer->nuit }}@endif{{ $customer->is_active ? '' : ' (inactivo)' }}</option>
                @endforeach
        </select>

     
    <div class="importer-combobox" data-searchable-combobox hidden>
        <input id="importer-search" type="search" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="importer-options" autocomplete="off" placeholder="Procurar pelo nome ou NUIT" data-searchable-search data-importer-search>
        <input type="hidden" name="customer_id" data-searchable-id disabled>
        <div id="importer-options" class="importer-options" role="listbox" data-searchable-options hidden></div>
    </div>
    
</div>
