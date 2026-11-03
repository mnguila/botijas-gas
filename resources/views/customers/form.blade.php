@php
    $contactRows = old('contacts', $customer?->contacts->map(fn ($contact) => ['type' => $contact->type, 'value' => $contact->value])->all() ?? [['type' => 'mobile', 'value' => '']]);
    $contactRows = array_values($contactRows ?: [['type' => 'mobile', 'value' => '']]);
@endphp
<div class="fields">
    <label>Nome do cliente<input name="name" maxlength="150" value="{{ old('name', $customer?->name) }}" required></label>
    <label>Tipo de cliente<select name="type" required>
        <option value="person" @selected(old('type', $customer?->type ?? 'person') === 'person')>Pessoa</option>
        <option value="organization" @selected(old('type', $customer?->type) === 'organization')>Organização</option>
    </select></label>
    <label>NUIT<input name="nuit" maxlength="30" value="{{ old('nuit', $customer?->nuit) }}" required></label>
    <label class="field-wide">Endereço físico<textarea name="physical_address" maxlength="2000" rows="3" required placeholder="Rua, número, bairro, cidade...">{{ old('physical_address', $customer?->physical_address) }}</textarea></label>
</div>
<fieldset class="customer-contacts">
    <legend>Contactos</legend>
    <p class="muted">Pode adicionar vários emails, telefones ou telemóveis.</p>
    <div data-contact-list data-next-index="{{ count($contactRows) }}">
        @foreach($contactRows as $index => $contact)
            <div class="customer-contact-row" data-contact-row>
                <label>Tipo<select name="contacts[{{ $index }}][type]" data-contact-type required>
                    <option value="email" @selected(($contact['type'] ?? '') === 'email')>Email</option>
                    <option value="phone" @selected(($contact['type'] ?? '') === 'phone')>Telefone</option>
                    <option value="mobile" @selected(($contact['type'] ?? '') === 'mobile')>Telemóvel</option>
                </select></label>
                <label>Contacto<input name="contacts[{{ $index }}][value]" data-contact-value maxlength="255" value="{{ $contact['value'] ?? '' }}" required></label>
                <button type="button" class="contact-remove" data-remove-contact aria-label="Remover contacto">Remover</button>
            </div>
        @endforeach
    </div>
    <button type="button" class="contact-add" data-add-contact>+ Adicionar contacto</button>
</fieldset>
<input type="hidden" name="is_active" value="0">
<label class="check customer-active"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $customer?->is_active ?? true))> Cliente activo</label>

<template id="customer-contact-template">
    <div class="customer-contact-row" data-contact-row>
        <label>Tipo<select name="contacts[__INDEX__][type]" data-contact-type required>
            <option value="email">Email</option><option value="phone">Telefone</option><option value="mobile" selected>Telemóvel</option>
        </select></label>
        <label>Contacto<input name="contacts[__INDEX__][value]" data-contact-value maxlength="255" required></label>
        <button type="button" class="contact-remove" data-remove-contact aria-label="Remover contacto">Remover</button>
    </div>
</template>
@push('scripts')
<script>
const contactList = document.querySelector('[data-contact-list]');
const contactTemplate = document.querySelector('#customer-contact-template');
function updateContactRows() {
    const rows = contactList.querySelectorAll('[data-contact-row]');
    rows.forEach(row => { row.querySelector('[data-remove-contact]').disabled = rows.length === 1; });
}
document.querySelector('[data-add-contact]').addEventListener('click', () => {
    const index = Number(contactList.dataset.nextIndex);
    contactList.dataset.nextIndex = String(index + 1);
    contactList.insertAdjacentHTML('beforeend', contactTemplate.innerHTML.replaceAll('__INDEX__', String(index)));
    updateContactRows();
    contactList.lastElementChild.querySelector('[data-contact-value]').focus();
});
contactList.addEventListener('click', event => {
    if (event.target.matches('[data-remove-contact]')) {
        event.target.closest('[data-contact-row]').remove();
        updateContactRows();
    }
});
contactList.addEventListener('change', event => {
    if (event.target.matches('[data-contact-type]')) {
        const value = event.target.closest('[data-contact-row]').querySelector('[data-contact-value]');
        value.type = event.target.value === 'email' ? 'email' : 'tel';
        value.placeholder = event.target.value === 'email' ? 'nome@exemplo.com' : '+258 ...';
    }
});
contactList.querySelectorAll('[data-contact-type]').forEach(select => select.dispatchEvent(new Event('change', { bubbles: true })));
updateContactRows();
</script>
@endpush
