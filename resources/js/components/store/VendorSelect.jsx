import { formatMoney } from './formatMoney';

export default function VendorSelect({ offers, value, onChange, id, disabled = false }) {
    if (!offers || offers.length === 0) {
        return null;
    }

    return (
        <label className="store-vendor-select" htmlFor={id}>
            <span className="store-vendor-label">Vendor</span>
            <select
                id={id}
                value={value ?? ''}
                onChange={(event) => onChange(Number(event.target.value))}
                disabled={disabled}
            >
                {offers.map((offer) => (
                    <option key={offer.vendor_id} value={offer.vendor_id}>
                        {offer.vendor_name} — {formatMoney(offer.discounted_price)}
                        {offer.is_lowest ? ' (Best price)' : ''}
                    </option>
                ))}
            </select>
        </label>
    );
}
