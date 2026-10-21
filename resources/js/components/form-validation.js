const controls = 'input:not([type="hidden"]), select, textarea';

const labelFor = (control) => {
    if (control.labels?.length) return control.labels[0];
    return control.id ? document.querySelector(`label[for="${CSS.escape(control.id)}"]`) : null;
};

const errorHostFor = (control) => {
    const label = labelFor(control);
    const wrapper = control.closest('.t-field, .field, .profile-field');
    if (wrapper?.querySelector('.error, .text-red-700, .text-red-800, [data-validation-error], .nt-client-error')) return wrapper;
    return label || wrapper || control.parentElement;
};

const clientError = (control) => {
    const error = errorHostFor(control)?.querySelector('.nt-client-error');
    return error || null;
};

const isControlValid = (control) => {
    const originalHistoricalValue = control.dataset.ntDateOriginalValue
        && control.value === control.dataset.ntDateOriginalValue
        && control.value < (control.dataset.ntDateTodayMin || '');
    const currentMinimum = control.min;
    if (originalHistoricalValue) control.min = '';
    const valid = control.checkValidity();
    if (originalHistoricalValue) control.min = currentMinimum;
    return valid;
};

const updateDateConstraints = (form) => {
    const pairs = [
        ['ends_at', 'starts_at'],
        ['expires_at', 'produced_at'],
        ['expires_at', 'issued_at'],
        ['expected_at', 'shipped_at'],
        ['delivered_at', 'shipped_at'],
    ];

    pairs.forEach(([targetName, sourceName]) => {
        const target = form.elements.namedItem(targetName);
        const source = form.elements.namedItem(sourceName);
        if (!target || !source || !('min' in target)) return;

        const todayMinimum = target.dataset.ntDateTodayMin || '';
        const minimum = [source.value || '', todayMinimum].filter(Boolean).sort((a, b) => a.localeCompare(b)).at(-1) || '';
        target.min = minimum;
        target._flatpickr?.set('minDate', minimum || null);
        const isRangeValid = !source.value || !target.value || target.value >= source.value;
        target.setCustomValidity(isRangeValid ? '' : `La date doit être postérieure ou égale à celle du champ « ${sourceName.replaceAll('_', ' ')} ».`);
        target.dataset.dateRangeInvalid = isRangeValid ? '0' : '1';
    });
};

const updateControl = (control) => {
    const whitespaceOnly = control.required
        && ['text', 'email', 'search', 'tel', 'url', 'password'].includes(control.type)
        && control.value.trim() === '';
    if (whitespaceOnly) {
        control.dataset.whitespaceRequired = '1';
        control.setCustomValidity('Ce champ est obligatoire.');
    } else if (control.dataset.whitespaceRequired === '1') {
        control.dataset.whitespaceRequired = '0';
        control.setCustomValidity('');
    }

    const valid = isControlValid(control);
    const serverError = control.dataset.serverInvalid === '1';
    control.classList.toggle('nt-invalid', !valid || serverError);
    control.setAttribute('aria-invalid', (!valid || serverError) ? 'true' : 'false');

    const error = clientError(control);
    if (!valid) {
        const host = errorHostFor(control);
        const message = control.validity.valueMissing || control.dataset.whitespaceRequired === '1'
            ? 'Ce champ est obligatoire.'
            : control.validity.typeMismatch && control.type === 'email'
                ? 'Veuillez saisir une adresse e-mail valide.'
                : control.validationMessage || 'Veuillez vérifier la valeur saisie.';
        if (host && !error) {
            const hint = document.createElement('small');
            hint.className = 'nt-client-error';
            hint.textContent = message;
            host.append(hint);
        } else if (error) {
            error.textContent = message;
        }
    } else if (error) {
        error.remove();
    }
};

const initializeForm = (form) => {
    // Render all errors inline instead of relying on browser-specific bubbles.
    form.noValidate = true;
    const fields = () => [...form.querySelectorAll(controls)].filter((field) => !field.disabled);

    fields().forEach((field) => {
        if (field.dataset.ntValidationBound === '1') return;
        field.dataset.ntValidationBound = '1';
        if (field.required) {
            const label = labelFor(field);
            if (label && !label.textContent.includes('*')) {
                const marker = document.createElement('span');
                marker.className = 'nt-required-marker';
                marker.setAttribute('aria-hidden', 'true');
                marker.textContent = ' *';
                label.append(marker);
            }
        }

        const name = field.name?.replace(/\[\]$/, '');
        const fieldHost = errorHostFor(field);
        const fieldErrors = [...(fieldHost?.querySelectorAll('.error, .text-red-700, .text-red-800, [data-validation-error]') || [])];
        const matchingError = fieldErrors.find((error) => error.dataset.validationError === name)
            || fieldErrors.find((error) => error.textContent.trim() !== '');
        if (matchingError) {
            const errorId = matchingError.id || `error-${name.replace(/[^a-z0-9_-]/gi, '-')}`;
            matchingError.id = errorId;
            matchingError.dataset.serverValidationError = '1';
            field.dataset.serverErrorId = errorId;
            field.dataset.serverInvalid = '1';
            field.classList.add('nt-invalid');
            field.setAttribute('aria-invalid', 'true');
            field.setAttribute('aria-describedby', [field.getAttribute('aria-describedby'), errorId].filter(Boolean).join(' '));
        }
    });

    const syncDates = () => {
        updateDateConstraints(form);
        fields().filter((field) => field.type === 'date' || field.type === 'datetime-local').forEach(updateControl);
    };

    fields().forEach((field) => {
        if (field.dataset.ntValidationListeners === '1') return;
        field.dataset.ntValidationListeners = '1';
        const clearServerErrorOnEdit = () => {
            field.dataset.serverInvalid = '0';
            const serverError = field.dataset.serverErrorId ? document.getElementById(field.dataset.serverErrorId) : null;
            if (serverError) serverError.hidden = true;
        };
        const refresh = () => {
            clearServerErrorOnEdit();
            updateDateConstraints(form);
            fields().filter((candidate) => candidate.type === 'date' || candidate.type === 'datetime-local').forEach(updateControl);
            updateControl(field);
        };
        field.addEventListener('input', refresh);
        field.addEventListener('change', refresh);

        // Keep native date and time controls so the browser opens its calendar picker.
    });

    if (form.dataset.ntValidationSubmitBound !== '1') {
        form.dataset.ntValidationSubmitBound = '1';
        form.addEventListener('submit', (event) => {
            syncDates();
            const currentFields = fields();
            currentFields.forEach(updateControl);
            const firstInvalid = currentFields.find((field) => !isControlValid(field));
            if (firstInvalid) {
                event.preventDefault();
                firstInvalid.focus({ preventScroll: false });
                firstInvalid.reportValidity();
            }
        });
    }

    syncDates();
};

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('form:not([data-validation="off"])').forEach(initializeForm);

    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            mutation.addedNodes.forEach((node) => {
                if (!(node instanceof Element)) return;
                if (node.matches('form:not([data-validation="off"])')) initializeForm(node);
                node.querySelectorAll?.('form:not([data-validation="off"])').forEach(initializeForm);
                const form = node.closest('form:not([data-validation="off"])');
                if (form) initializeForm(form);
            });
        });
    });
    observer.observe(document.body, { childList: true, subtree: true });

    const firstInvalid = document.querySelector('form [aria-invalid="true"]');
    if (firstInvalid) firstInvalid.focus({ preventScroll: true });
});
