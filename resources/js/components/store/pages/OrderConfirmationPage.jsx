import { useEffect, useState } from 'react';
import { useParams, useSearchParams, useLocation, Link } from 'react-router-dom';
import { useSelector, useDispatch } from 'react-redux';
import axios from 'axios';
import { selectUser } from '../../../store/authSlice';
import { clearCart } from '../../../store/cartSlice';
import { formatMoney } from '../formatMoney';
import Skeleton from '../../ui/Skeleton';

export default function OrderConfirmationPage() {
    const { orderId } = useParams();
    const [searchParams] = useSearchParams();
    const location = useLocation();
    const dispatch = useDispatch();
    const user = useSelector(selectUser);

    const [order, setOrder] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {
        let isMounted = true;

        const fetchOrder = async () => {
            try {
                let url = `/api/orders/${orderId}`;
                const guestToken = searchParams.get('guest_token') || sessionStorage.getItem(`order_token:${orderId}`);
                if (guestToken) {
                    url += `?guest_token=${encodeURIComponent(guestToken)}`;
                }
                const response = await axios.get(url);
                if (isMounted) {
                    setOrder(response.data.data);
                    // Clear cart if arriving fresh from checkout or with a new token
                    if (location.state?.fromCheckout || sessionStorage.getItem(`order_token:${orderId}`)) {
                        dispatch(clearCart());
                    }
                }
            } catch (err) {
                if (isMounted) {
                    setError('We could not load this order. It may not exist, or you may need to sign in.');
                }
            } finally {
                if (isMounted) {
                    setLoading(false);
                }
            }
        };

        fetchOrder();

        return () => {
            isMounted = false;
        };
    }, [orderId, searchParams, location.state, dispatch]);

    if (loading) {
        return (
            <div className="store-section store-confirmation">
                <div className="store-confirmation-hero">
                    <div style={{ width: 140, height: 26, margin: '0 auto 16px' }}><Skeleton /></div>
                    <div style={{ width: 280, height: 34, margin: '0 auto 12px' }}><Skeleton /></div>
                    <div style={{ width: 360, height: 18, margin: '0 auto 20px' }}><Skeleton /></div>
                </div>
                <div className="store-confirmation-grid-two">
                    <div className="store-card"><Skeleton style={{ height: 160 }} /></div>
                    <div className="store-card"><Skeleton style={{ height: 160 }} /></div>
                </div>
            </div>
        );
    }

    if (error || !order) {
        return (
            <div className="store-section store-confirmation">
                <div className="store-card" style={{ textAlign: 'center', padding: '48px 24px' }}>
                    <h2 style={{ marginBottom: '12px' }}>Order not found or unavailable</h2>
                    <p className="store-text-muted" style={{ maxWidth: '500px', margin: '0 auto 24px' }}>
                        {error || 'We could not load this order.'}
                    </p>
                    <div className="store-confirmation-actions">
                        <Link to="/shop" className="store-button">Continue shopping</Link>
                        {!user && (
                            <Link
                                to={`/account?redirect=${encodeURIComponent(`/order-confirmation/${orderId}`)}`}
                                className="store-link-button"
                            >
                                Sign in to your account
                            </Link>
                        )}
                    </div>
                </div>
            </div>
        );
    }

    return (
        <div className="store-section store-confirmation">
            <div className="store-confirmation-hero">
                <div className="store-confirmation-badge">
                    <svg width="18" height="18" viewBox="0 0 18 18" aria-hidden="true">
                        <circle cx="9" cy="9" r="8" fill="currentColor" opacity="0.15" />
                        <path d="M6.25 9.25L8 11L11.75 7.25" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" />
                    </svg>
                    Order confirmed
                </div>
                <h1>Thank you for your order</h1>
                <p>
                    Your order <strong>{order.order_number}</strong> has been received. We’ll contact you shortly to arrange cash on delivery.
                </p>
                <div className="store-confirmation-actions">
                    <Link to="/shop" className="store-button">Continue shopping</Link>
                    {user ? (
                        <Link to="/account" className="store-link-button">View your orders</Link>
                    ) : (
                        <span className="store-text-muted" style={{ fontSize: '13px' }}>
                            Save your confirmation link or keep this page open
                        </span>
                    )}
                </div>
            </div>

            <div className="store-confirmation-grid-two">
                <div className="store-card">
                    <div className="store-card-head">
                        <h2>Order details</h2>
                        <span className={`store-status-pill status-${order.status}`}>{order.status}</span>
                    </div>
                    <dl className="store-meta-list">
                        <div>
                            <dt>Order number</dt>
                            <dd className="store-meta-strong">{order.order_number}</dd>
                        </div>
                        <div>
                            <dt>Placed at</dt>
                            <dd>{order.placed_at ? new Date(order.placed_at).toLocaleString() : '—'}</dd>
                        </div>
                        <div>
                            <dt>Payment method</dt>
                            <dd>Cash on delivery</dd>
                        </div>
                        <div>
                            <dt>Sub-orders</dt>
                            <dd>{order.sub_orders?.length ?? 0} vendor{(order.sub_orders?.length ?? 0) === 1 ? '' : 's'}</dd>
                        </div>
                    </dl>
                </div>

                <div className="store-card">
                    <div className="store-card-head">
                        <h2>Totals</h2>
                    </div>
                    <dl className="store-totals">
                        <div>
                            <dt>Subtotal</dt>
                            <dd>{formatMoney(order.subtotal)}</dd>
                        </div>
                        <div>
                            <dt>Discounts</dt>
                            <dd className="store-discount">−{formatMoney(order.discount_total)}</dd>
                        </div>
                        <div className="store-totals-grand">
                            <dt>Total to pay on delivery (COD)</dt>
                            <dd>{formatMoney(order.total)}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            <div className="store-confirmation-grid-two">
                <div className="store-card">
                    <div className="store-card-head">
                        <h2>Customer details</h2>
                    </div>
                    <address className="store-address">
                        <p><strong>{order.customer.name}</strong></p>
                        <p><a href={`mailto:${order.customer.email}`}>{order.customer.email}</a></p>
                        {order.customer.phone && <p><a href={`tel:${order.customer.phone}`}>{order.customer.phone}</a></p>}
                        {order.customer.address && <p className="store-text-muted">{order.customer.address}</p>}
                    </address>
                </div>
                <div className="store-card">
                    <div className="store-card-head">
                        <h2>Delivery address</h2>
                    </div>
                    <address className="store-address">
                        <p><strong>{order.delivery.name}</strong></p>
                        <p><a href={`mailto:${order.delivery.email}`}>{order.delivery.email}</a></p>
                        {order.delivery.phone && <p><a href={`tel:${order.delivery.phone}`}>{order.delivery.phone}</a></p>}
                        <p className="store-text-muted">{order.delivery.address}</p>
                    </address>
                </div>
            </div>

            <div className="store-card">
                <div className="store-card-head">
                    <h2>Items by vendor</h2>
                </div>
                <div className="store-suborders">
                    {order.sub_orders?.map((subOrder) => (
                        <div key={subOrder.id} className="store-suborder-block">
                            <div className="store-suborder-header">
                                <div>
                                    <h3>{subOrder.vendor.name}</h3>
                                    <p className="store-text-muted" style={{ margin: 0, fontSize: '13px' }}>
                                        Status: <span className={`store-status-pill status-${subOrder.status}`}>{subOrder.status}</span>
                                    </p>
                                </div>
                                <span className="store-suborder-total">{formatMoney(subOrder.total)}</span>
                            </div>
                            <div className="store-table-wrapper">
                                <table className="store-table">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th>SKU</th>
                                            <th>Qty</th>
                                            <th>Unit price</th>
                                            <th>Discount</th>
                                            <th>Line total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {subOrder.items?.map((item) => {
                                            const appliedRules = item.applied_rules && item.applied_rules.length > 0
                                                ? item.applied_rules
                                                : (item.applied_rule ? [item.applied_rule] : []);

                                            return (
                                                <tr key={item.id}>
                                                    <td>
                                                        <div className="store-item-name">{item.product_name}</div>
                                                        {item.product_category && (
                                                            <div className="store-item-meta">{item.product_category}</div>
                                                        )}
                                                    </td>
                                                    <td>{item.product_sku || '—'}</td>
                                                    <td>{item.quantity}</td>
                                                    <td>{formatMoney(item.unit_price)}</td>
                                                    <td>
                                                        {item.discount_amount > 0 ? (
                                                            <div>
                                                                <span className="store-discount">−{formatMoney(item.discount_amount)}</span>
                                                                {appliedRules.map((rule) => (
                                                                    <div key={rule.id || rule.name} className="store-item-rule">
                                                                        {rule.name}
                                                                    </div>
                                                                ))}
                                                            </div>
                                                        ) : (
                                                            formatMoney(0)
                                                        )}
                                                    </td>
                                                    <td className="store-table-strong">{formatMoney(item.line_total)}</td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                            <div className="store-suborder-sum">
                                <div>
                                    <span>Subtotal</span>
                                    <span>{formatMoney(subOrder.subtotal)}</span>
                                </div>
                                <div>
                                    <span>Discounts</span>
                                    <span className="store-discount">−{formatMoney(subOrder.discount_total)}</span>
                                </div>
                                <div className="store-suborder-sum-grand">
                                    <span>Total for {subOrder.vendor.name}</span>
                                    <span>{formatMoney(subOrder.total)}</span>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
}
