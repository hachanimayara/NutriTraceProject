const dateInputTypes = new Set(['date', 'datetime-local', 'month', 'week']);

const pad = (value) => String(value).padStart(2, '0');

const minimumFor = (type, now = new Date()) => {
    const date = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;

    if (type === 'datetime-local') {
        return `${date}T${pad(now.getHours())}:${pad(now.getMinutes())}`;
    }
    if (type === 'month') return date.slice(0, 7);
    if (type === 'week') {
        const utcDate = new Date(Date.UTC(now.getFullYear(), now.getMonth(), now.getDate()));
        const weekday = utcDate.getUTCDay() || 7;
        utcDate.setUTCDate(utcDate.getUTCDate() + 4 - weekday);
        const yearStart = new Date(Date.UTC(utcDate.getUTCFullYear(), 0, 1));
        const week = Math.ceil((((utcDate - yearStart) / 86400000) + 1) / 7);
        return `${utcDate.getUTCFullYear()}-W${pad(week)}`;
    }
    return date;
};

const laterMinimum = (left, right) => !left ? right : !right ? left : (left > right ? left : right);

const initializeDateInput = (input) => {
    if (!(input instanceof HTMLInputElement) || !dateInputTypes.has(input.type)) return;
    if (input.dataset.noPastDates === 'false' || input.dataset.ntDateConstraintBound === '1') return;
    if (input.form?.method.toLowerCase() === 'get') return;

    input.dataset.ntDateConstraintBound = '1';
    input.dataset.ntDateOriginalMin = input.min;
    input.dataset.ntDateTodayMin = minimumFor(input.type);
    input.dataset.ntDateOriginalValue = input.value;
    input.min = laterMinimum(input.min, input.dataset.ntDateTodayMin);

    // Keep an existing historical value valid while requiring any changed value to be today or later.
    input.addEventListener('change', () => {
        input.min = laterMinimum(input.dataset.ntDateOriginalMin || '', input.dataset.ntDateTodayMin || '');
    });
};

const initialize = (root = document) => {
    root.querySelectorAll?.('input[type="date"], input[type="datetime-local"], input[type="month"], input[type="week"]')
        .forEach(initializeDateInput);
};

const start = () => {
    initialize();

    new MutationObserver((mutations) => {
        mutations.forEach(({ addedNodes }) => addedNodes.forEach((node) => {
            if (node instanceof Element) {
                if (node.matches('input[type="date"], input[type="datetime-local"], input[type="month"], input[type="week"]')) initializeDateInput(node);
                initialize(node);
            }
        }));
    }).observe(document.documentElement, { childList: true, subtree: true });
};

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, { once: true });
else start();
