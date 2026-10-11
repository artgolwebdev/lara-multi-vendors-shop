import { useEffect, useState, useMemo } from 'react';
import { AdminPageHeader, ApiErrors } from './shared';
import { formatMoney } from '../store/formatMoney';
import Card from '../ui/Card';
import Badge from '../ui/Badge';
import Skeleton from '../ui/Skeleton';

const STATUS_OPTIONS = [
    { value: '', label: 'All statuses' },
    { value: 'pending', label: 'Pending' },
    { value: 'confirmed', label: 'Confirmed' },
    { value: 'shipped', label: 'Shipped' },
    { value: 'delivered', label: 'Delivered' },
    { value: 'cancelled', label: 'Cancelled' },
];

export default function OrderPages({ path }) {
    const orderIdMatch = path.match(/\/admin\/orders\/(\d+)/);
    const orderId = orderIdMatch ? orderIdMatch[1] : null;

    if (orderId) {
        return <OrderDetail orderId={orderId} />;
    }

    return <OrderList />;
}

function OrderList() {
    const [orders, setOrders] = useState([]);
    const [meta, setMeta] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [vendors, setVendors] = useState([]);
    const [customers, setCustomers] = useState([]);

    // Initialize filter form from URL params
    const getInitialFilters = () => {
        const params = new URLSearchParams(window.location.search);
        return {
            search: params.get('search') || '',
            status: params.get('status') || '',
            vendor_id: params.get('vendor_id') || '',
            customer_id: params.get('customer_id') || '',
            date_from: params.get('date_from') || '',
            date_to: params.get('date_to') || '',
            page: Number(params.get('page')) || 1,
        };
    };

    const [formFilters, setFormFilters] = useState(getInitialFilters);
    const [appliedFilters, setAppliedFilters] = useState(getInitialFilters);

    useEffect(() => {
        window.axios.get('/api/admin/vendors?per_page=100')
            .then(({ data }) => setVendors(data.data || []))
            .catch(() => {});

        window.axios.get('/api/admin/customers?per_page=100')
            .then(({ data }) => setCustomers(data.data || []))
            .catch(() => {});
    }, []);

    useEffect(() => {
        setLoading(true);
        setError('');

        const params = new URLSearchParams();
        Object.entries(appliedFilters).forEach(([key, value]) => {
            if (value) params.set(key, value);
        });

        // Persist to browser URL without reload
        const newUrl = `${window.location.pathname}${params.toString() ? '?' + params.toString() : ''}`;
        window.history.replaceState({}, '', newUrl);

        window.axios.get(`/api/admin/orders?${params.toString()}`)
            .then(({ data }) => {
                setOrders(data.data || []);
                setMeta(data.meta || null);
            })
            .catch(() => setError('Failed to load orders. Please try again.'))
            .finally(() => setLoading(false));
    }, [appliedFilters]);

    const handleFormChange = (event) => {
        const { name, value } = event.target;
        setFormFilters((prev) => ({ ...prev, [name]: value }));
    };

    const handleApplyFilters = (e) => {
        if (e) e.preventDefault();
        setAppliedFilters({ ...formFilters, page: 1 });
    };

    const handleResetFilters = () => {
        const empty = { search: '', status: '', vendor_id: '', customer_id: '', date_from: '', date_to: '', page: 1 };
        setFormFilters(empty);
        setAppliedFilters(empty);
    };

    const removeFilter = (key) => {
        const updated = { ...appliedFilters, [key]: '', page: 1 };
        setFormFilters((prev) => ({ ...prev, [key]: '' }));
        setAppliedFilters(updated);
    };

    const handlePageChange = (page) => {
        setFormFilters((prev) => ({ ...prev, page }));
        setAppliedFilters((prev) => ({ ...prev, page }));
    };

    const activeChips = useMemo(() => {
        const chips = [];
        if (appliedFilters.search) {
            chips.push({ key: 'search', label: `Search: "${appliedFilters.search}"` });
        }
        if (appliedFilters.status) {
            chips.push({ key: 'status', label: `Status: ${appliedFilters.status}` });
        }
        if (appliedFilters.vendor_id) {
            const v = vendors.find((x) => String(x.id) === String(appliedFilters.vendor_id));
            chips.push({ key: 'vendor_id', label: `Vendor: ${v ? v.name : appliedFilters.vendor_id}` });
        }
        if (appliedFilters.customer_id) {
            const c = customers.find((x) => String(x.id) === String(appliedFilters.customer_id));
            chips.push({ key: 'customer_id', label: `Customer: ${c ? c.name : appliedFilters.customer_id}` });
        }
        if (appliedFilters.date_from) {
            chips.push({ key: 'date_from', label: `From: ${appliedFilters.date_from}` });
        }
        if (appliedFilters.date_to) {
            chips.push({ key: 'date_to', label: `To: ${appliedFilters.date_to}` });
        }
        return chips;
    }, [appliedFilters, vendors, customers]);

    return (
        <div className="admin-section">
            <AdminPageHeader
                eyebrow="Order management"
                title="Orders"
            />

            {error && <div className="admin-alert admin-alert-danger">{error}</div>}

            <Card className="admin-card" style={{ marginBottom: '24px' }}>
                <form onSubmit={handleApplyFilters} className="admin-filters-form">
                    <div className="admin-filters" style={{ borderBottom: 'none', marginBottom: '12px', paddingBottom: 0 }}>
                        <input
                            type="search"
                            name="search"
                            placeholder="Search order #, customer name or email"
                            value={formFilters.search}
                            onChange={handleFormChange}
                        />

                        <select name="status" value={formFilters.status} onChange={handleFormChange}>
                            {STATUS_OPTIONS.map((opt) => (
                                <option key={opt.value} value={opt.value}>{opt.label}</option>
                            ))}
                        </select>

                        <select name="vendor_id" value={formFilters.vendor_id} onChange={handleFormChange}>
                            <option value="">All vendors</option>
                            {vendors.map((vendor) => (
                                <option key={vendor.id} value={vendor.id}>{vendor.name}</option>
                            ))}
                        </select>

                        <select name="customer_id" value={formFilters.customer_id} onChange={handleFormChange}>
                            <option value="">All customers</option>
                            {customers.map((customer) => (
                                <option key={customer.id} value={customer.id}>{customer.name} ({customer.email})</option>
                            ))}
                        </select>

                        <input
                            type="date"
                            name="date_from"
                            value={formFilters.date_from}
                            onChange={handleFormChange}
                            title="From date"
                        />
                        <input
                            type="date"
                            name="date_to"
                            value={formFilters.date_to}
                            onChange={handleFormChange}
                            title="To date"
                        />

                        <button type="submit" className="gg-button gg-button--primary gg-button--sm">
                            Apply
                        </button>
                        <button type="button" onClick={handleResetFilters} className="gg-button gg-button--secondary gg-button--sm">
                            Reset
                        </button>
                    </div>

                    {activeChips.length > 0 && (
                        <div style={{ display: 'flex', flexWrap: 'wrap', gap: '8px', paddingTop: '10px', borderTop: '1px solid var(--c-border, #e4e2da)' }}>
                            <span style={{ fontSize: '12px', color: 'var(--c-muted)', alignSelf: 'center' }}>Active filters:</span>
                            {activeChips.map((chip) => (
                                <button
                                    key={chip.key}
                                    type="button"
                                    onClick={() => removeFilter(chip.key)}
                                    className="gg-badge"
                                    style={{ cursor: 'pointer', background: '#eaf1ec', borderColor: '#b8d5c3', color: 'var(--c-primary)' }}
                                    title="Click to remove filter"
                                >
                                    {chip.label} ✕
                                </button>
                            ))}
                        </div>
                    )}
                </form>
            </Card>

            <div className="admin-table-wrapper">
                <table className="admin-table gg-table">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Customer</th>
                            <th>Vendors</th>
                            <th>Items</th>
                            <th style={{ textAlign: 'right' }}>Total</th>
                            <th>Status</th>
                            <th>Placed At</th>
                            <th style={{ textAlign: 'right' }}>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        {loading ? (
                            <tr>
                                <td colSpan="8" style={{ padding: '24px' }}>
                                    <Skeleton style={{ height: 24, marginBottom: 8 }} />
                                    <Skeleton style={{ height: 24, marginBottom: 8 }} />
                                    <Skeleton style={{ height: 24 }} />
                                </td>
                            </tr>
                        ) : orders.length === 0 ? (
                            <tr>
                                <td colSpan="8" style={{ textAlign: 'center', padding: '36px', color: 'var(--c-muted)' }}>
                                    No orders found matching the selected filters.
                                </td>
                            </tr>
                        ) : (
                            orders.map((order) => {
                                const subOrders = order.sub_orders || [];
                                return (
                                    <tr key={order.id}>
                                        <td>
                                            <a href={`/admin/orders/${order.id}`} className="admin-link" style={{ fontWeight: 700 }}>
                                                {order.order_number}
                                            </a>
                                        </td>
                                        <td>
                                            <div style={{ fontWeight: 600 }}>{order.customer?.name || 'Guest'}</div>
                                            <div className="admin-subtext">{order.customer?.email}</div>
                                        </td>
                                        <td>
                                            {subOrders.length > 0 ? (
                                                <div style={{ display: 'flex', flexWrap: 'wrap', gap: '4px' }}>
                                                    {subOrders.map((so) => (
                                                        <a
                                                            key={so.id}
                                                            href={`/admin/vendors/${so.vendor?.id}`}
                                                            className="gg-badge"
                                                            style={{ textDecoration: 'none', fontSize: '11px' }}
                                                        >
                                                            {so.vendor?.name}
                                                        </a>
                                                    ))}
                                                </div>
                                            ) : (
                                                <span>{order.sub_orders_count ?? 0}</span>
                                            )}
                                        </td>
                                        <td>{order.items_count ?? '—'}</td>
                                        <td style={{ textAlign: 'right', fontVariantNumeric: 'tabular-nums', fontWeight: 700 }}>
                                            {formatMoney(order.total)}
                                        </td>
                                        <td>
                                            <span className={`store-status-pill status-${order.status}`}>
                                                {order.status}
                                            </span>
                                        </td>
                                        <td style={{ fontSize: '12px', color: 'var(--c-muted)' }}>
                                            {order.placed_at ? new Date(order.placed_at).toLocaleDateString() : '—'}
                                        </td>
                                        <td style={{ textAlign: 'right' }}>
                                            <a className="gg-button gg-button--secondary gg-button--sm" href={`/admin/orders/${order.id}`}>
                                                View
                                            </a>
                                        </td>
                                    </tr>
                                );
                            })
                        )}
                    </tbody>
                </table>
            </div>

            {meta && meta.last_page > 1 && (
                <div className="admin-pagination" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginTop: '16px' }}>
                    <span style={{ fontSize: '13px', color: 'var(--c-muted)' }}>
                        Page {meta.current_page} of {meta.last_page} ({meta.total} total orders)
                    </span>
                    <div style={{ display: 'flex', gap: '8px' }}>
                        <button
                            type="button"
                            className="gg-button gg-button--secondary gg-button--sm"
                            disabled={appliedFilters.page <= 1}
                            onClick={() => handlePageChange(appliedFilters.page - 1)}
                        >
                            Previous
                        </button>
                        <button
                            type="button"
                            className="gg-button gg-button--secondary gg-button--sm"
                            disabled={appliedFilters.page >= meta.last_page}
                            onClick={() => handlePageChange(appliedFilters.page + 1)}
                        >
                            Next
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}

function OrderDetail({ orderId }) {
    const [order, setOrder] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [message, setMessage] = useState('');
    const [updating, setUpdating] = useState(false);

    const fetchOrder = () => {
        window.axios.get(`/api/admin/orders/${orderId}`)
            .then(({ data }) => setOrder(data.data))
            .catch(() => setError('Failed to load order details.'))
            .finally(() => setLoading(false));
    };

    useEffect(() => {
        fetchOrder();
    }, [orderId]);

    async function updateStatus(subOrderId, status) {
        setUpdating(true);
        setMessage('');
        setError('');
        try {
            await window.axios.patch(`/api/admin/sub-orders/${subOrderId}/status`, { status });
            setMessage('Sub-order status updated successfully.');
            fetchOrder();
        } catch {
            setError('Failed to update sub-order status.');
        } finally {
            setUpdating(false);
        }
    }

    async function notifyVendor(subOrderId) {
        setUpdating(true);
        setMessage('');
        setError('');
        try {
            await window.axios.post(`/api/admin/sub-orders/${subOrderId}/notify`);
            setMessage('Vendor notification queued.');
            fetchOrder();
        } catch {
            setError('Failed to dispatch vendor notification.');
        } finally {
            setUpdating(false);
        }
    }

    if (loading) {
        return (
            <div className="admin-section">
                <Skeleton style={{ height: 36, width: 200, marginBottom: 16 }} />
                <Skeleton style={{ height: 180, marginBottom: 16 }} />
            </div>
        );
    }

    if (error && !order) {
        return (
            <div className="admin-section">
                <div className="admin-alert admin-alert-danger">{error}</div>
                <a href="/admin/orders" className="admin-link">← Back to orders</a>
            </div>
        );
    }

    if (!order) return null;

    return (
        <div className="admin-section">
            <header className="admin-section-head gg-page-header">
                <div>
                    <a href="/admin/orders" className="admin-link" style={{ fontSize: '13px', display: 'inline-block', marginBottom: '8px' }}>
                        ← Back to orders
                    </a>
                    <h1>Order {order.order_number}</h1>
                    <p className="admin-subtitle">
                        Placed {order.placed_at ? new Date(order.placed_at).toLocaleString() : '—'}
                    </p>
                </div>
                <div style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
                    <span className={`store-status-pill status-${order.status}`} style={{ fontSize: '13px', padding: '4px 12px' }}>
                        {order.status}
                    </span>
                </div>
            </header>

            {message && <div className="admin-alert admin-alert-success">{message}</div>}
            {error && <div className="admin-alert admin-alert-danger">{error}</div>}

            <div className="admin-grid" style={{ gridTemplateColumns: 'repeat(3, 1fr)', gap: '20px', marginBottom: '24px' }}>
                <div className="admin-card" style={{ margin: 0 }}>
                    <h2>Totals Overview</h2>
                    <dl className="admin-definition">
                        <div>
                            <dt>Payment method</dt>
                            <dd>Cash on delivery</dd>
                        </div>
                        <div>
                            <dt>Subtotal</dt>
                            <dd>{formatMoney(order.subtotal)}</dd>
                        </div>
                        <div>
                            <dt>Discounts</dt>
                            <dd className="admin-discount">−{formatMoney(order.discount_total)}</dd>
                        </div>
                        <div style={{ borderTop: '1px solid var(--c-border, #e4e2da)', paddingTop: '8px', marginTop: '4px' }}>
                            <dt>Grand Total</dt>
                            <dd><strong style={{ fontSize: '16px' }}>{formatMoney(order.total)}</strong></dd>
                        </div>
                    </dl>
                </div>

                <div className="admin-card" style={{ margin: 0 }}>
                    <h2>Customer</h2>
                    <div className="admin-stack">
                        <div><strong>{order.customer?.name}</strong></div>
                        <div><a href={`mailto:${order.customer?.email}`} className="admin-link">{order.customer?.email}</a></div>
                        {order.customer?.phone && <div>{order.customer.phone}</div>}
                        {order.customer?.address && <div style={{ color: 'var(--c-muted)' }}>{order.customer.address}</div>}
                        {order.user_id && (
                            <div style={{ marginTop: '8px' }}>
                                <a href={`/admin/customers/${order.user_id}`} className="gg-button gg-button--secondary gg-button--sm">
                                    View Customer Profile
                                </a>
                            </div>
                        )}
                    </div>
                </div>

                <div className="admin-card" style={{ margin: 0 }}>
                    <h2>Delivery Details</h2>
                    <div className="admin-stack">
                        <div><strong>{order.delivery?.name}</strong></div>
                        <div><a href={`mailto:${order.delivery?.email}`} className="admin-link">{order.delivery?.email}</a></div>
                        {order.delivery?.phone && <div>{order.delivery.phone}</div>}
                        <div style={{ color: 'var(--c-muted)' }}>{order.delivery?.address}</div>
                        {order.notes && (
                            <div style={{ marginTop: '8px', padding: '8px', background: '#f8faf7', borderRadius: '3px', fontSize: '12px' }}>
                                <strong>Notes:</strong> {order.notes}
                            </div>
                        )}
                    </div>
                </div>
            </div>

            <div className="admin-card">
                <h2>Sub-orders & Items by Vendor</h2>
                {order.sub_orders?.map((subOrder) => {
                    const canResendNotification = subOrder.notification_status === 'pending' || subOrder.notification_status === 'failed';

                    return (
                        <div key={subOrder.id} className="admin-suborder" style={{ borderBottom: '1px solid var(--c-border, #e4e2da)', paddingBottom: '24px', marginBottom: '24px' }}>
                            <div className="admin-suborder-head" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', flexWrap: 'wrap', gap: '16px', marginBottom: '16px' }}>
                                <div>
                                    <h3 style={{ margin: '0 0 4px', fontSize: '16px' }}>
                                        <a href={`/admin/vendors/${subOrder.vendor?.id}`} className="admin-link">
                                            {subOrder.vendor?.name}
                                        </a>
                                    </h3>
                                    <p className="admin-subtext" style={{ margin: 0 }}>
                                        {subOrder.vendor?.email} {subOrder.vendor?.phone && `• ${subOrder.vendor?.phone}`}
                                    </p>
                                </div>

                                <div className="admin-suborder-actions" style={{ display: 'flex', alignItems: 'center', gap: '12px', flexWrap: 'wrap' }}>
                                    <label style={{ fontSize: '12px', display: 'flex', alignItems: 'center', gap: '6px' }}>
                                        Status:
                                        <select
                                            value={subOrder.status}
                                            onChange={(e) => updateStatus(subOrder.id, e.target.value)}
                                            disabled={updating}
                                            style={{ height: '34px', padding: '0 8px', borderRadius: '3px', border: '1px solid #ccd5ce' }}
                                        >
                                            <option value="pending">Pending</option>
                                            <option value="confirmed">Confirmed</option>
                                            <option value="shipped">Shipped</option>
                                            <option value="delivered">Delivered</option>
                                            <option value="cancelled">Cancelled</option>
                                        </select>
                                    </label>

                                    <span className={`store-status-pill status-${subOrder.status}`}>
                                        {subOrder.status}
                                    </span>

                                    <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                                        <span className={`gg-badge status-${subOrder.notification_status === 'sent' ? 'delivered' : subOrder.notification_status === 'failed' ? 'cancelled' : 'pending'}`}>
                                            Notify: {subOrder.notification_status}
                                        </span>
                                        {subOrder.notified_at && (
                                            <span style={{ fontSize: '11px', color: 'var(--c-muted)' }}>
                                                {new Date(subOrder.notified_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                                            </span>
                                        )}

                                        {canResendNotification && (
                                            <button
                                                type="button"
                                                className="gg-button gg-button--secondary gg-button--sm"
                                                onClick={() => notifyVendor(subOrder.id)}
                                                disabled={updating}
                                            >
                                                Send notification
                                            </button>
                                        )}
                                    </div>
                                </div>
                            </div>

                            <div className="admin-table-wrapper" style={{ marginBottom: '14px' }}>
                                <table className="admin-table gg-table">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th>SKU</th>
                                            <th>Qty</th>
                                            <th>Unit price</th>
                                            <th>Discount</th>
                                            <th style={{ textAlign: 'right' }}>Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {subOrder.items?.map((item) => {
                                            const appliedRule = item.applied_rule?.name || (item.applied_rules?.[0]?.name);

                                            return (
                                                <tr key={item.id}>
                                                    <td>
                                                        <div style={{ fontWeight: 600 }}>{item.product_name}</div>
                                                        {item.product_category && (
                                                            <div className="admin-subtext">{item.product_category}</div>
                                                        )}
                                                    </td>
                                                    <td>{item.product_sku || '—'}</td>
                                                    <td>{item.quantity}</td>
                                                    <td>{formatMoney(item.unit_price)}</td>
                                                    <td>
                                                        {item.discount_amount > 0 ? (
                                                            <div>
                                                                <span className="admin-discount">−{formatMoney(item.discount_amount)}</span>
                                                                {appliedRule && (
                                                                    <div className="store-item-rule" style={{ fontSize: '10px', marginLeft: 0 }}>
                                                                        {appliedRule}
                                                                    </div>
                                                                )}
                                                            </div>
                                                        ) : (
                                                            formatMoney(0)
                                                        )}
                                                    </td>
                                                    <td style={{ textAlign: 'right', fontWeight: 700 }}>
                                                        {formatMoney(item.line_total)}
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>

                            <div className="admin-suborder-sum" style={{ display: 'flex', flexDirection: 'column', alignItems: 'flex-end', gap: '4px', fontSize: '13px' }}>
                                <div style={{ display: 'flex', justifyContent: 'space-between', minWidth: '220px' }}>
                                    <span>Subtotal:</span>
                                    <span>{formatMoney(subOrder.subtotal)}</span>
                                </div>
                                <div style={{ display: 'flex', justifyContent: 'space-between', minWidth: '220px' }}>
                                    <span>Discounts:</span>
                                    <span className="admin-discount">−{formatMoney(subOrder.discount_total)}</span>
                                </div>
                                <div style={{ display: 'flex', justifyContent: 'space-between', minWidth: '220px', fontWeight: 700, borderTop: '1px solid var(--c-border, #e4e2da)', paddingTop: '4px', marginTop: '4px' }}>
                                    <span>Total for {subOrder.vendor?.name}:</span>
                                    <span>{formatMoney(subOrder.total)}</span>
                                </div>
                            </div>
                        </div>
                    );
                })}
            </div>
        </div>
    );
}

