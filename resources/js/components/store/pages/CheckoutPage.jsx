import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useSelector } from 'react-redux';
import axios from 'axios';
import { selectCartLines, selectQuote } from '../../../store/cartSlice';
import { selectUser } from '../../../store/authSlice';
import { formatMoney } from '../formatMoney';

function generateIdempotencyKey() {
    if (typeof crypto !== 'undefined' && crypto.randomUUID) {
        return crypto.randomUUID();
    }

    return `${Date.now()}-${Math.random().toString(36).slice(2, 10)}`;
}

export default function CheckoutPage() {
    const navigate = useNavigate();
    const lines = useSelector(selectCartLines);
    const quote = useSelector(selectQuote);
    const user = useSelector(selectUser);

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
            }));
        }
    }, [user]);

    const handleChange = (event) => {
        const { name, value } = event.target;
        setForm((prev) => ({ ...prev, [name]: value }));
    };

    const handleSubmit = async (event) => {
        event.preventDefault();
        setSubmitting(true);
        setErrors(null);
        setGeneralError(null);

        try {
            const payload = {
                delivery: {
                    name: form.deliveryName || form.customerName,
                    email: form.deliveryEmail || form.customerEmail,
                    phone: form.deliveryPhone || form.customerPhone || null,
                    address: form.deliveryAddress || form.customerAddress,
                },
                lines: lines.map((line) => ({
                    product_id: line.product_id,
                    quantity: line.quantity,
                    ...(line.vendor_id ? { vendor_id: line.vendor_id } : {}),
                })),
                idempotency_key: key,
            };

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

            navigate(`/order-confirmation/${order.id}`);
        } catch (error) {
            const response = error.response;
            if (response?.status === 422) {
                setErrors(response.data.errors ?? null);
                setGeneralError(response.data.message ?? 'Please check your details.');
            } else if (response?.status === 409) {
                setGeneralError(response.data.message ?? 'Prices changed. Please review and confirm.');
            } else {
                setGeneralError('Something went wrong while placing your order.');
            }
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <div className="store-section">
            <div className="store-section-head">
                <h1>Checkout</h1>
            </div>

            {generalError && (
                <div className="store-alert" role="alert">
                    {generalError}
                </div>
            )}

            <form className="store-checkout" onSubmit={handleSubmit}>
                <div className="store-checkout-main">
                    <section className="store-card store-checkout-card">
                        <div className="store-card-head">
                            <h2>Contact & Delivery</h2>
                            <p className="store-text-muted">Enter your details for cash on delivery</p>
                        </div>

                        <div className="store-form-section">
                            <h3>Your contact</h3>
                            <div className="store-form-grid">
                                <label className="store-form-field">
                                    <span>Full name</span>
                                    <input
                                        type="text"
                                        name="customerName"
                                        value={form.customerName}
                                        onChange={handleChange}
                                        required={!user}
                                        autoComplete="name"
                                    />
                                </label>
                                <label className="store-form-field">
                                    <span>Email</span>
                                    <input
                                        type="email"
                                        name="customerEmail"
                                        value={form.customerEmail}
                                        onChange={handleChange}
                                        required={!user}
                                        autoComplete="email"
                                    />
                                </label>
                                <label className="store-form-field">
                                    <span>Phone</span>
                                    <input
                                        type="tel"
                                        name="customerPhone"
                                        value={form.customerPhone}
                                        onChange={handleChange}
                                        autoComplete="tel"
                                        placeholder="+1 555 0000"
                                    />
                                </label>
                                <label className="store-form-field store-form-field-full">
                                    <span>Address</span>
                                    <textarea
                                        name="customerAddress"
                                        rows={3}
                                        value={form.customerAddress}
                                        onChange={handleChange}
                                        autoComplete="address-line1"
                                        placeholder="Street, apartment, etc."
                                    />
                                </label>
                            </div>
                        </div>

                        <div className="store-form-section">
                            <h3>Delivery</h3>
                            <div className="store-form-grid">
                                <label className="store-form-field">
                                    <span>Recipient name</span>
                                    <input
                                        type="text"
                                        name="deliveryName"
                                        value={form.deliveryName}
                                        onChange={handleChange}
                                        required
                                        autoComplete="shipping name"
                                    />
                                </label>
                                <label className="store-form-field">
                                    <span>Email</span>
                                    <input
                                        type="email"
                                        name="deliveryEmail"
                                        value={form.deliveryEmail}
                                        onChange={handleChange}
                                        required
                                        autoComplete="shipping email"
                                    />
                                </label>
                                <label className="store-form-field">
                                    <span>Phone</span>
                                    <input
                                        type="tel"
                                        name="deliveryPhone"
                                        value={form.deliveryPhone}
                                        onChange={handleChange}
                                        autoComplete="shipping tel"
                                        placeholder="+1 555 0000"
                                    />
                                </label>
                                <label className="store-form-field store-form-field-full">
                                    <span>Delivery address</span>
                                    <textarea
                                        name="deliveryAddress"
                                        rows={3}
                                        value={form.deliveryAddress}
                                        onChange={handleChange}
                                        required
                                        autoComplete="shipping address-line1"
                                        placeholder="Full delivery address"
                                    />
                                </label>
                                <label className="store-form-field store-form-field-full">
                                    <span>Order notes (optional)</span>
                                    <textarea
                                        name="notes"
                                        rows={2}
                                        value={form.notes}
                                        onChange={handleChange}
                                        maxLength={2000}
                                        placeholder="Special instructions for delivery"
                                    />
                                </label>
                            </div>
                        </div>

                        <section className="store-checkout-payment">
                            <h3>Payment</h3>
                            <div className="store-payment-card">
                                <span className="store-payment-label">Method</span>
                                <div className="store-payment-method">
                                    <svg width="20" height="16" viewBox="0 0 20 16" aria-hidden="true">
                                        <rect x="1" y="1" width="18" height="14" rx="2" stroke="currentColor" strokeWidth="1.2" fill="none"/>
                                        <line x1="1" y1="5" x2="19" y2="5" stroke="currentColor" strokeWidth="1.2"/>
                                    </svg>
                                    <span>Cash on delivery</span>
                                </div>
                                <p className="store-text-muted">Pay with cash when your order is delivered to you.</p>
                            </div>
                        </section>
                    </section>
                </div>

                <aside className="store-card store-checkout-summary">
                    <h2>Order summary</h2>
                    {quote ? (
                        <>
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
                        </>
                    ) : (
                        <p className="store-text-muted">Updating your totals...</p>
                    )}

                    <button
                        type="submit"
                        className="store-button store-button-block"
                        disabled={submitting || lines.length === 0 || !quote}
                    >
                        {submitting ? 'Placing order...' : 'Place order (cash on delivery)'}
                    </button>

                    {errors && (
                        <div className="store-form-errors">
                            {Object.entries(errors).map(([field, messages]) => (
                                <p key={field}>{Array.isArray(messages) ? messages[0] : messages}</p>
                            ))}
                        </div>
                    )}
                </aside>
            </form>
        </div>
    );
}
