export function formatMoney(value) {
    if (value === null || value === undefined || value === '') {
        return '';
    }

    const amount = Number(value);

    if (Number.isNaN(amount)) {
        return String(value);
    }

    return `$${amount.toFixed(2)}`;
}

export function hasDiscount(offer) {
    return offer && Number(offer.discount_amount) > 0;
}
