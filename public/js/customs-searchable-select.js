document.querySelectorAll('[data-searchable-picker]').forEach(picker => {
    const native = picker.querySelector('[data-searchable-native]');
    const combobox = picker.querySelector('[data-searchable-combobox]');
    const search = picker.querySelector('[data-searchable-search]');
    const selectedId = picker.querySelector('[data-searchable-id]');
    const options = picker.querySelector('[data-searchable-options]');
    const choices = [...native.options]
        .filter(option => option.value)
        .map(option => ({ id: option.value, label: option.textContent.trim() }));
    const normalize = value => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
    let activeIndex = -1;

    function closeOptions() {
        options.hidden = true;
        search.setAttribute('aria-expanded', 'false');
        search.removeAttribute('aria-activedescendant');
        activeIndex = -1;
    }

    function markActive(index) {
        const buttons = [...options.querySelectorAll('[role="option"]')];
        if (!buttons.length) return;
        activeIndex = (index + buttons.length) % buttons.length;
        buttons.forEach((button, position) => button.classList.toggle('active', position === activeIndex));
        search.setAttribute('aria-activedescendant', buttons[activeIndex].id);
        buttons[activeIndex].scrollIntoView({ block: 'nearest' });
    }

    function select(choice) {
        selectedId.value = choice.id;
        search.value = choice.label;
        search.setCustomValidity('');
        search.focus();
        closeOptions();
    }

    function showOptions() {
        const query = normalize(search.value.trim());
        const matchingChoices = choices.filter(choice => normalize(choice.label).includes(query));
        const matches = picker.hasAttribute('data-show-all-options') ? matchingChoices : matchingChoices.slice(0, 30);
        options.replaceChildren();
        activeIndex = -1;
        search.removeAttribute('aria-activedescendant');

        if (matches.length) {
            matches.forEach((choice, index) => {
                const option = document.createElement('button');
                option.type = 'button';
                option.id = `${options.id}-option-${index}`;
                option.className = 'importer-option';
                option.setAttribute('role', 'option');
                option.setAttribute('aria-selected', selectedId.value === choice.id ? 'true' : 'false');
                option.textContent = choice.label;
                option.addEventListener('click', () => select(choice));
                options.appendChild(option);
            });
        } else {
            const empty = document.createElement('div');
            empty.className = 'importer-empty';
            empty.textContent = 'Nenhum resultado encontrado';
            options.appendChild(empty);
        }
        options.hidden = false;
        search.setAttribute('aria-expanded', 'true');
    }

    picker.querySelector('label').htmlFor = search.id;
    selectedId.value = native.value;
    search.value = native.selectedOptions[0]?.value ? native.selectedOptions[0].textContent.trim() : '';
    native.disabled = true;
    native.hidden = true;
    combobox.hidden = false;
    selectedId.disabled = false;
    search.required = true;

    search.addEventListener('focus', () => {
        if (selectedId.value) search.select();
        showOptions();
    });
    search.addEventListener('input', () => {
        selectedId.value = '';
        search.setCustomValidity('');
        showOptions();
    });
    search.addEventListener('keydown', event => {
        if (event.key === 'Escape' || event.key === 'Tab') { closeOptions(); return; }
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            if (options.hidden) showOptions();
            const direction = event.key === 'ArrowDown' ? 1 : -1;
            const buttons = options.querySelectorAll('[role="option"]');
            markActive(activeIndex < 0 ? (direction === 1 ? 0 : buttons.length - 1) : activeIndex + direction);
        }
        if (event.key === 'Enter' && !options.hidden) {
            const buttons = options.querySelectorAll('[role="option"]');
            const index = activeIndex >= 0 ? activeIndex : (buttons.length === 1 ? 0 : -1);
            if (index >= 0) { event.preventDefault(); buttons[index].click(); }
        }
    });
    document.addEventListener('click', event => {
        if (!picker.contains(event.target)) closeOptions();
    });
    picker.closest('form').addEventListener('submit', event => {
        if (!selectedId.value) {
            event.preventDefault();
            search.setCustomValidity('Selecione uma opção da lista.');
            search.reportValidity();
            search.focus();
        }
    });
});
