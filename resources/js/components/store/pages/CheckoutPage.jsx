import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useSelector, useDispatch } from 'react-redux';
import axios from 'axios';
import { selectCartLines, selectQuote, clearCart } from '../../../store/cartSlice';
import { selectUser } from '../../../store/authSlice';
import { formatMoney } from '../formatMoney';
import Steps from '../../ui/Steps';
import Skeleton from '../../ui/Skeleton';

function generateIdempotencyKey() {
    if (typeof crypto !== 'undefined' && crypto.randomUUID) {
        return crypto.randomUUID();
    }

    return `${Date.now()}-${Math.random().toString(36).slice(2, 10)}`;
}

export default function CheckoutPage() {
    const navigate = useNavigate();
    const dispatch = useDispatch();
    const lines = useSelector(selectCartLines);
    const reduxQuote = useSelector(selectQuote);
    const user = useSelector(selectUser);

    const [displayedQuote, setDisplayedQuote] = useState(reduxQuote);
    const [priceChanged, setPriceChanged] = useState(false);

    useEffect(() => {
        if (!priceChanged) {
            setDisplayedQuote(reduxQuote);
        }
    }, [reduxQuote, priceChanged]);

    const quote = displayedQuote;

    const [form, setForm] = useState({
        customerName: user?.name ?? '',
        customerEmail: user?.email ?? '',
        customerPhone: user?.phone ?? '',
        customerAddress: user?.address ?? '',
        deliveryName: user?.name ?? '',
        deliveryEmail: user?.email ?? '',
        deliveryPhone: user?.phone ?? '',
        deliveryAddress: user?.address ?? '',
        notes: '',
    });

    const [sameAsContact, setSameAsContact] = useState(true);
    const [errors, setErrors] = useState(null);
    const [generalError, setGeneralError] = useState(null);
    const [submitting, setSubmitting] = useState(false);
    const [key] = useState(generateIdempotencyKey);

    useEffect(() => {
        if (lines.length === 0) {
            navigate('/cart', { replace: true });
        }
    }, [lines, navigate]);

    useEffect(() => {
        if (user) {
            setForm((prev) => ({
                ...prev,
                customerName: prev.customerName || user.name || '',
                customerEmail: prev.customerEmail || user.email || '',
                customerPhone: prev.customerPhone || user.phone || '',
                customerAddress: prev.customerAddress || user.address || '',
                deliveryName: prev.deliveryName || user.name || '',
                deliveryEmail: prev.deliveryEmail || user.email || '',
                deliveryPhone: prev.deliveryPhone || user.phone || '',
                deliveryAddress: prev.deliveryAddress || user.address || '',
            }));
        }
    }, [user]);

    const handleChange = (event) => {
        const { name, value } = event.target;
        setForm((prev) => {
            const next = { ...prev, [name]: value };
            if (sameAsContact) {
                if (name === 'customerName') next.deliveryName = value;
                if (name === 'customerEmail') next.deliveryEmail = value;
                if (name === 'customerPhone') next.deliveryPhone = value;
                if (name === 'customerAddress') next.deliveryAddress = value;
            }
            return next;
        });

        if (errors) {
            setErrors(null);
        }
    };

    const handleSameAsContactToggle = (e) => {
        const checked = e.target.checked;
        setSameAsContact(checked);
        if (checked) {
            setForm((prev) => ({
                ...prev,
                deliveryName: prev.customerName,
                deliveryEmail: prev.customerEmail,
                deliveryPhone: prev.customerPhone,
                deliveryAddress: prev.customerAddress,
            }));
        }
    };

    const handleSubmit = async (event) => {
        if (event) {
            event.preventDefault();
        }
        setSubmitting(true);
        setErrors(null);
        setGeneralError(null);

        try {
            const deliveryPayload = {
                name: sameAsContact ? form.customerName : (form.deliveryName || form.customerName),
                email: sameAsContact ? form.customerEmail : (form.deliveryEmail || form.customerEmail),
                phone: sameAsContact ? form.customerPhone : (form.deliveryPhone || form.customerPhone || null),
                address: sameAsContact ? form.customerAddress : (form.deliveryAddress || form.customerAddress),
            };

            const payload = {
                delivery: deliveryPayload,
                lines: lines.map((line) => ({
                    product_id: line.product_id,
                    quantity: line.quantity,
                    ...(line.vendor_id ? { vendor_id: line.vendor_id } : {}),
                })),
                idempotency_key: key,
            };

            if (quote?.grand_total) {
                payload.expected_total = quote.grand_total;
            }

            if (form.notes) {
                payload.notes = form.notes;
            }

            const hasCustomer = form.customerName || form.customerEmail;
            if (hasCustomer || !user) {
                payload.customer = {
                    name: form.customerName,
                    email: form.customerEmail,
                    phone: form.customerPhone || null,
                    address: form.customerAddress || null,
                };
            }

            const response = await axios.post('/api/orders', payload);
            const order = response.data.data;

            if (order?.id && order?.guest_token) {
                sessionStorage.setItem(`order_token:${order.id}`, order.guest_token);
            }

            dispatch(clearCart());
            navigate(`/order-confirmation/${order.id}`, { state: { fromCheckout: true } });
        } catch (error) {
            const response = error.response;
            if (response?.status === 422) {
                setErrors(response.data.errors ?? null);
                setGeneralError(response.data.message ?? 'Please check the entered information.');
            } else if (response?.status === 409) {
                const latestQuote = response.data.data;
                if (latestQuote) {
                    setDisplayedQuote(latestQuote);
                    setPriceChanged(true);
                }
                setGeneralError(response.data.message ?? 'Prices or discounts have changed. Please review the updated total and confirm.');
            } else {
                setGeneralError('Something went wrong while placing your order. Please try again.');
            }
        } finally {
            setSubmitting(false);
        }
    };

    const getFieldError = (fieldName) => {
        if (!errors) return null;
        const msg = errors[fieldName];
        if (!msg) return null;
        return Array.isArray(msg) ? msg[0] : msg;
    };

    if (lines.length === 0) {
        return (
            <div className="store-section">
                <p className="store-text-muted">Your cart is empty. Redirecting to cart...</p>
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
                current={1}
            />

            <div className="store-section-head">
                <h1>Checkout</h1>
            </div>

            {!user && (
                <div className="store-promo-banner" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '12px' }}>
                    <p>
                        <strong>Already have an account?</strong> Sign in to apply your saved details and loyalty discounts.
                    </p>
                    <a href="/account?redirect=/checkout" className="gg-button gg-button--secondary gg-button--sm">
                        Sign in or register
                    </a>
                </div>
            )}

            {generalError && (
                <div className={`store-alert ${priceChanged ? 'store-alert-warning' : ''}`} role="alert">
                    <p style={{ margin: 0, fontWeight: 600 }}>{generalError}</p>
                    {priceChanged && quote && (
                        <p style={{ margin: '8px 0 0', fontSize: '13px' }}>
                            Updated total: <strong>{formatMoney(quote.grand_total)}</strong>. Click the button below to confirm and place your order.
                        </p>
                    )}
                </div>
            )}

            <form className="store-checkout" onSubmit={handleSubmit} noValidate>
                <div className="store-checkout-main">
                    <section className="store-card store-checkout-card">
                        <div className="store-card-head">
                            <div>
                                <h2>Contact Details</h2>
                                <p className="store-text-muted" style={{ margin: 0, fontSize: '13px' }}>
                                    We will contact you to confirm delivery.
                                </p>
                            </div>
                        </div>

                        <div className="store-form-section">
                            <div className="store-form-grid">
                                <label className={`store-form-field ${getFieldError('customer.name') ? 'has-error' : ''}`}>
                                    <span>Full name *</span>
                                    <input
                                        type="text"
                                        name="customerName"
                                        value={form.customerName}
                                        onChange={handleChange}
                                        required={!user}
                                        autoComplete="name"
                                    />
                                    {getFieldError('customer.name') && (
                                        <span className="store-field-error">{getFieldError('customer.name')}</span>
                                    )}
                                </label>

                                <label className={`store-form-field ${getFieldError('customer.email') ? 'has-error' : ''}`}>
                                    <span>Email address *</span>
                                    <input
                                        type="email"
                                        name="customerEmail"
                                        value={form.customerEmail}
                                        onChange={handleChange}
                                        required={!user}
                                        autoComplete="email"
                                    />
                                    {getFieldError('customer.email') && (
                                        <span className="store-field-error">{getFieldError('customer.email')}</span>
                                    )}
                                </label>

                                <label className={`store-form-field ${getFieldError('customer.phone') ? 'has-error' : ''}`}>
                                    <span>Phone number</span>
                                    <input
                                        type="tel"
                                        name="customerPhone"
                                        value={form.customerPhone}
                                        onChange={handleChange}
                                        autoComplete="tel"
                                        placeholder="+1 555 0100"
                                    />
                                    {getFieldError('customer.phone') && (
                                        <span className="store-field-error">{getFieldError('customer.phone')}</span>
                                    )}
                                </label>

                                <label className={`store-form-field store-form-field-full ${getFieldError('customer.address') ? 'has-error' : ''}`}>
                                    <span>Billing / Contact address</span>
                                    <textarea
                                        name="customerAddress"
                                        rows={2}
                                        value={form.customerAddress}
                                        onChange={handleChange}
                                        autoComplete="street-address"
                                        placeholder="Street, suite, city"
                                    />
                                    {getFieldError('customer.address') && (
                                        <span className="store-field-error">{getFieldError('customer.address')}</span>
                                    )}
                                </label>
                            </div>
                        </div>

                        <div className="store-form-section">
                            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '14px' }}>
                                <h3 style={{ margin: 0 }}>Delivery Address</h3>
                                <label style={{ display: 'inline-flex', alignItems: 'center', gap: '8px', fontSize: '13px', cursor: 'pointer' }}>
                                    <input
                                        type="checkbox"
                                        checked={sameAsContact}
                                        onChange={handleSameAsContactToggle}
                                    />
                                    Same as contact details
                                </label>
                            </div>

                            {!sameAsContact && (
                                <div className="store-form-grid" style={{ marginBottom: '16px' }}>
                                    <label className={`store-form-field ${getFieldError('delivery.name') ? 'has-error' : ''}`}>
                                        <span>Recipient name *</span>
                                        <input
                                            type="text"
                                            name="deliveryName"
                                            value={form.deliveryName}
                                            onChange={handleChange}
                                            required
                                            autoComplete="shipping name"
                                        />
                                        {getFieldError('delivery.name') && (
                                            <span className="store-field-error">{getFieldError('delivery.name')}</span>
                                        )}
                                    </label>

                                    <label className={`store-form-field ${getFieldError('delivery.email') ? 'has-error' : ''}`}>
                                        <span>Recipient email *</span>
                                        <input
                                            type="email"
                                            name="deliveryEmail"
                                            value={form.deliveryEmail}
                                            onChange={handleChange}
                                            required
                                            autoComplete="shipping email"
                                        />
                                        {getFieldError('delivery.email') && (
                                            <span className="store-field-error">{getFieldError('delivery.email')}</span>
                                        )}
                                    </label>

                                    <label className={`store-form-field ${getFieldError('delivery.phone') ? 'has-error' : ''}`}>
                                        <span>Recipient phone</span>
                                        <input
                                            type="tel"
                                            name="deliveryPhone"
                                            value={form.deliveryPhone}
                                            onChange={handleChange}
                                            autoComplete="shipping tel"
                                            placeholder="+1 555 0100"
                                        />
                                        {getFieldError('delivery.phone') && (
                                            <span className="store-field-error">{getFieldError('delivery.phone')}</span>
                                        )}
                                    </label>

                                    <label className={`store-form-field store-form-field-full ${getFieldError('delivery.address') ? 'has-error' : ''}`}>
                                        <span>Delivery address *</span>
                                        <textarea
                                            name="deliveryAddress"
                                            rows={2}
                                            value={form.deliveryAddress}
                                            onChange={handleChange}
                                            required
                                            autoComplete="shipping street-address"
                                            placeholder="Street address, apartment, city"
                                        />
                                        {getFieldError('delivery.address') && (
                                            <span className="store-field-error">{getFieldError('delivery.address')}</span>
                                        )}
                                    </label>
                                </div>
                            )}

                            <label className="store-form-field store-form-field-full" style={{ marginTop: '12px' }}>
                                <span>Order notes (optional)</span>
                                <textarea
                                    name="notes"
                                    rows={2}
                                    value={form.notes}
                                    onChange={handleChange}
                                    maxLength={2000}
                                    placeholder="Special instructions for delivery or packaging"
                                />
                            </label>
                        </div>

                        <section className="store-checkout-payment">
                            <h3>Payment Method</h3>
                            <div className="store-payment-card">
                                <span className="store-payment-label">Selected method</span>
                                <div className="store-payment-method">
                                    <svg width="20" height="16" viewBox="0 0 20 16" aria-hidden="true">
                                        <rect x="1" y="1" width="18" height="14" rx="2" stroke="currentColor" strokeWidth="1.2" fill="none"/>
                                        <line x1="1" y1="5" x2="19" y2="5" stroke="currentColor" strokeWidth="1.2"/>
                                    </svg>
                                    <span>Cash on delivery (COD)</span>
                                </div>
                                <p className="store-text-muted" style={{ margin: 0, fontSize: '13px' }}>
                                    Pay in cash when your items arrive at your address.
                                </p>
                            </div>
                        </section>
                    </section>
                </div>

                <aside className="store-card store-checkout-summary">
                    <h2>Order Summary</h2>
                    {quote ? (
                        <>
                            <div className="store-checkout-vendors" style={{ display: 'flex', flexDirection: 'column', gap: '16px', marginBottom: '20px' }}>
                                {quote.vendors.map((vendor) => (
                                    <div key={vendor.vendor_id} style={{ borderBottom: '1px solid var(--c-border, #e4e2da)', paddingBottom: '12px' }}>
                                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '8px' }}>
                                            <strong style={{ fontSize: '14px', color: 'var(--c-primary, #315b4b)' }}>{vendor.vendor_name}</strong>
                                            <span style={{ fontWeight: 600, fontSize: '14px' }}>{formatMoney(vendor.total)}</span>
                                        </div>
                                        <div style={{ display: 'flex', flexDirection: 'column', gap: '6px' }}>
                                            {vendor.line_indexes?.map((lineIndex) => {
                                                const line = quote.lines?.[lineIndex];
                                                if (!line) return null;
                                                return (
                                                    <div key={`${line.product_id}-${lineIndex}`} style={{ fontSize: '13px', display: 'flex', justifyContent: 'space-between' }}>
                                                        <span style={{ color: 'var(--c-muted, #6f6e66)' }}>
                                                            {line.product_name} × {line.quantity}
                                                            {line.applied_rule && (
                                                                <span className="store-item-rule" style={{ marginLeft: '6px', fontSize: '10px' }}>
                                                                    {line.applied_rule.name}
                                                                </span>
                                                            )}
                                                        </span>
                                                        <span style={{ fontVariantNumeric: 'tabular-nums' }}>
                                                            {formatMoney(line.line_total)}
                                                        </span>
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    </div>
                                ))}
                            </div>

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
                                    <dt>Total to pay</dt>
                                    <dd>{formatMoney(quote.grand_total)}</dd>
                                </div>
                            </dl>
                        </>
                    ) : (
                        <div style={{ padding: '20px 0' }}>
                            <Skeleton style={{ height: 24, marginBottom: 12 }} />
                            <Skeleton style={{ height: 40, marginBottom: 12 }} />
                            <Skeleton style={{ height: 24 }} />
                        </div>
                    )}

                    <button
                        type="submit"
                        className="store-button store-button-block"
                        disabled={submitting || lines.length === 0 || !quote}
                    >
                        {submitting ? 'Placing order...' : priceChanged ? `Confirm & place order (${formatMoney(quote.grand_total)})` : 'Place order (cash on delivery)'}
                    </button>

                    {errors && Object.keys(errors).some(k => k.startsWith('lines.')) && (
                        <div className="store-form-errors">
                            {Object.entries(errors)
                                .filter(([k]) => k.startsWith('lines.'))
                                .map(([k, msgs]) => (
                                    <p key={k}>{Array.isArray(msgs) ? msgs[0] : msgs}</p>
                                ))}
                        </div>
                    )}
                </aside>
            </form>
        </div>
    );
}

