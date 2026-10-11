export default function QuantityControl({ value, max, onChange }) {
    const atMax = Boolean(max) && value >= max;

    return (
        <div className="store-quantity">
            <button type="button" onClick={() => onChange(value - 1)} disabled={value <= 1} aria-label="Decrease quantity">&minus;</button>
            <input
                type="number"
                min="1"
                max={max || undefined}
                value={value}
                onChange={(event) => onChange(Number(event.target.value))}
                aria-label="Quantity"
            />
            <button type="button" onClick={() => onChange(value + 1)} disabled={atMax} aria-label="Increase quantity">+</button>
        </div>
    );
}
