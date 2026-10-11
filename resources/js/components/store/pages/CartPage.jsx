import { useSelector } from 'react-redux';
import { Link } from 'react-router-dom';
import { selectUser } from '../../../store/authSlice';
import {
    selectCartLines,
    selectFieldErrors,
    selectQuote,
    selectQuoteError,
    selectQuoteStatus,
} from '../../../store/cartSlice';
import { formatMoney } from '../formatMoney';
import CartLine from '../CartLine';
import EmptyState from '../EmptyState';
import ErrorState from '../ErrorState';
import Steps from '../../ui/Steps';

export default function CartPage() {
    const lines = useSelector(selectCartLines);
    const quote = useSelector(selectQuote);
    const status = useSelector(selectQuoteStatus);
    const error = useSelector(selectQuoteError);
    const fieldErrors = useSelector(selectFieldErrors);
    const user = useSelector(selectUser);
    const promotions = user ? [] : (quote?.promotions ?? []);

    if (lines.length === 0) {
        return (
            <div className="store-section">
                <div className="store-section-head">
                    <h1>Your cart</h1>
                </div>
                <EmptyState
                    title="Your cart is empty"
                    description="Add some goods and they will show up here."
                    actionLabel="Browse the shop"
                    actionTo="/shop"
                />
            </div>
        );
    }

    return (
        <div className="store-section">
            <Steps
                steps={[
                    { key: 'cart', label: 'Cart' },
                    { key: 'checkout', label: 'Checkout' },
                    { key: 'confirmation', label: 'Confirmation' },
                ]}
                current={0}
            />

            <div className="store-section-head">
                <h1>Your cart</h1>
                {status === 'loading' && <span className="store-state-text" role="status">Updating prices...</span>}
            </div>

            {error && !quote && (
                <ErrorState
                    message={error?.message ?? 'We could not price your cart.'}
                    onRetry={() => window.location.reload()}
                />
            )}

            {promotions.length > 0 && (
                <div className="store-promo-banner" role="status">
                    {promotions.map((promotion) => (
                        <p key={promotion.id}>
                            {promotion.discount_text}
                            {' '}
                            <Link to="/register">Create an account</Link>
                            {' '}to claim it.
                        </p>
                    ))}
                </div>
            )}

            <div className="store-cart-layout">
                <div className="store-cart-lines">
                    {lines.map((line, index) => (
                        <CartLine
                            key={`${line.product_id}-${line.vendor_id ?? 'default'}`}
                            line={quote?.lines?.[index] ?? { ...line, product_name: 'Loading...', quantity: line.quantity }}
                            index={index}
                            error={fieldErrors[`lines.${index}.quantity`] ?? fieldErrors[`lines.${index}.vendor_id`]}
                        />
                    ))}
                </div>

                {quote && (
                    <aside className="store-cart-summary">
                        <h2>Summary</h2>

                        {quote.vendors.map((vendor) => (
                            <div className="store-vendor-summary" key={vendor.vendor_id}>
                                <span>{vendor.vendor_name}</span>
                                <span>{formatMoney(vendor.total)}</span>
                            </div>
                        ))}

                        <dl className="store-totals">
                            <div>
                                <dt>Subtotal</dt>
                                <dd>{formatMoney(quote.subtotal)}</dd>
                            </div>
                            <div>
                                <dt>Discounts</dt>
                                <dd className="store-discount">−{formatMoney(quote.discount_total)}</dd>
                            </div>
                            <div className="store-totals-grand">
                                <dt>Total</dt>
                                <dd>{formatMoney(quote.grand_total)}</dd>
                            </div>
                        </dl>

                        <Link to="/checkout" className="store-button store-button-block">
                            Proceed to checkout
                        </Link>
                    </aside>
                )}
            </div>

            <Link className="store-link-button" to="/shop">Continue shopping</Link>
        </div>
    );
}
