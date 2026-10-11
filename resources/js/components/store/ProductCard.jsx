import { useMemo, useState } from 'react';
import { useDispatch } from 'react-redux';
import { Link } from 'react-router-dom';
import { addLine } from '../../store/cartSlice';
import { formatMoney, hasDiscount } from './formatMoney';
import VendorSelect from './VendorSelect';

export default function ProductCard({ product }) {
    const dispatch = useDispatch();
    const offers = product.offers ?? [];
    const initialVendor = product.default_vendor?.id ?? offers[0]?.vendor_id ?? null;
    const [vendorId, setVendorId] = useState(initialVendor);
    const [justAdded, setJustAdded] = useState(false);

    const offer = useMemo(
        () => offers.find((candidate) => candidate.vendor_id === vendorId) ?? offers[0] ?? null,
        [offers, vendorId],
    );

    if (!offer) {
        return null;
    }

    const discounted = hasDiscount(offer);

    function addToCart() {
        dispatch(addLine({ product_id: product.id, vendor_id: vendorId, quantity: 1 }));
        setJustAdded(true);
        window.setTimeout(() => setJustAdded(false), 1500);
    }

    return (
        <article className="store-card">
            <Link className="store-card-media" to={`/products/${product.id}`}>
                {product.image
                    ? <img src={product.image.url} alt={product.name} loading="lazy" />
                    : <span className="store-card-placeholder" aria-hidden="true">{product.name.charAt(0)}</span>}
            </Link>

            <div className="store-card-body">
                <div className="store-card-heading">
                    <p className="store-card-category">{product.category?.name ?? 'Goods'}</p>
                    <Link className="store-card-title" to={`/products/${product.id}`}>{product.name}</Link>
                    <p className="store-card-sku">SKU {product.sku}</p>
                </div>

                <div className="store-card-price">
                    <span className="store-price">{formatMoney(offer.discounted_price)}</span>
                    {discounted && <span className="store-price-original">{formatMoney(offer.price)}</span>}
                </div>

                {offer.applied_rule && (
                    <p className="store-rule-badge">{offer.applied_rule.name}</p>
                )}
                {offer.discount_hint && (
                    <p className="store-hint">{offer.discount_hint.label}</p>
                )}

                <VendorSelect
                    id={`vendor-${product.id}`}
                    offers={offers}
                    value={vendorId}
                    onChange={setVendorId}
                />

                <div className="store-card-actions">
                    <span className={`store-stock ${product.in_stock ? 'is-in' : 'is-out'}`}>
                        {product.in_stock ? 'In stock' : 'Out of stock'}
                    </span>
                    <button
                        className="store-button store-button-small"
                        type="button"
                        onClick={addToCart}
                        disabled={!product.in_stock}
                    >
                        {justAdded ? 'Added' : 'Add to cart'}
                    </button>
                </div>
            </div>
        </article>
    );
}
