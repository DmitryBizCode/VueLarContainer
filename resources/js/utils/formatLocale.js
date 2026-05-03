/** @param {string|number|Date|null|undefined} value */
export function formatDateGb(value) {
    if (!value) return '—';

    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(new Date(value));
}

/** @param {unknown} value @param {string} [currency] */
export function formatMoneyLocale(value, currency = 'USD') {
    const amount = Number(value ?? 0);
    const safeCurrency = String(currency || 'USD').toUpperCase();

    try {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: safeCurrency,
            maximumFractionDigits: 2,
        }).format(amount);
    } catch {
        return `${amount.toFixed(2)} ${safeCurrency}`;
    }
}
