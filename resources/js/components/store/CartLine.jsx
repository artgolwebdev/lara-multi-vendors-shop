import { useDispatch } from 'react-redux';
import { Link } from 'react-router-dom';
import { removeLine, setQuantity, setVendor } from '../../store/cartSlice';
import { formatMoney, hasDiscount } from './formatMoney';
import QuantityControl from './QuantityControl';
import VendorSelect from './VendorSelect';

export default function CartLine({ line, index, error }) {
    const dispatch = useDispatch();
    const cheaperElsewhere = line.lowest_vendor && line.selected_vendor
        && line.lowest_vendor.id !== line.selected_vendor.id;
    const discounted = hasDiscount({ discount_amount: line.discount_amount });

    return (
        <article className="store-cart-line">
            <Link className="store-cart-media" to={`/products/${line.product_id}`}>
                {line.image
                    ? <img src={line.image} alt={line.product_name} loading="lazy" />
                    : <span className="store-card-placeholder" aria-hidden="true">{line.product_name.charAt(0)}</span>}
            </Link>

            <div className="store-cart-info">
                <div className="store-cart-info-head">
                    <Link className="store-card-title" to={`/products/${line.product_id}`}>{line.product_name}</Link>
                    <button className="store-link-button" type="button" onClick={() => dispatch(removeLine(index))}>Remove</button>
                </div>

                <VendorSelect
                    id={`cart-vendor-${index}`}
                    offers={line.vendor_options}
                    value={line.selected_vendor?.id}
                    onChange={(vendorId) => dispatch(setVendor({ index, vendor_id: vendorId }))}
                />

                {cheaperElsewhere && (
                    <p className="store-hint store-hint-cheaper">
                        Cheaper at {line.lowest_vendor.name} — {formatMoney(line.lowest_price)}
                    </p>
                )}

                {line.is_loyalty && line.applied_rule && (
                    <p className="store-rule-badge store-rule-badge-loyalty">Welcome discount — {line.applied_rule.name}</p>
                )}
                {!line.is_loyalty && line.applied_rule && <p className="store-rule-badge">{line.applied_rule.name}</p>}
                {line.discount_hint && <p className="store-hint">{line.discount_hint.label}</p>}

                {error && <p className="store-line-error" role="alert">{error}</p>}
            </div>

            <div className="store-cart-qty">
                <QuantityControl
                    value={line.quantity}
                    max={line.stock}
                    onChange={(quantity) => dispatch(setQuantity({ index, quantity }))}
                />
                <span className="store-cart-unit">{formatMoney(line.unit_price)} each</span>
            </div>

            <div className="store-cart-total">
                {discounted && <span className="store-price-original">{formatMoney(line.line_subtotal)}</span>}
                <span className="store-price">{formatMoney(line.line_total)}</span>
            </div>
        </article>
    );
}
