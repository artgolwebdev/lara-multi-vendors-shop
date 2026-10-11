import { useEffect, useState } from 'react';
import { formatMoney } from '../store/formatMoney';

const STATUS_COLORS = {
    pending: 'status-pending',
    confirmed: 'status-confirmed',
    shipped: 'status-shipped',
    delivered: 'status-delivered',
    cancelled: 'status-cancelled',
};

export default function OrderPages({ path }) {
    const isIndex = path === '/admin/orders';
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
    const [filters, setFilters] = useState({ search: '', status: '', vendor_id: '', customer_id: '', date_from: '', date_to: '', page: 1 });
    const [vendors, setVendors] = useState([]);
    const [customers, setCustomers] = useState([]);

    useEffect(() => {
        window.axios.get('/api/admin/vendors?per_page=100').then(({ data }) => setVendors(data.data || []));
        window.axios.get('/api/admin/customers?per_page=100').then(({ data }) => setCustomers(data.data || []));
    }, []);

    useEffect(() => {
        setLoading(true);
        const params = new URLSearchParams();
        Object.entries(filters).forEach(([key, value]) => {
            if (value) params.set(key, value);
        });
        window.axios.get(`/api/admin/orders?${params.toString()}`)
            .then(({ data }) => {
                setOrders(data.data || []);
                setMeta(data.meta || null);
            })
            .catch(() => setError('Failed to load orders'))
            .finally(() => setLoading(false));
    }, [filters]);

    function handleFilterChange(event) {
        const { name, value } = event.target;
        setFilters((prev) => ({ ...prev, [name]: value, page: 1 }));
    }

    function handlePageChange(page) {
        setFilters((prev) => ({ ...prev, page }));
    }

    return (
        <div className="admin-section">
            <header className="admin-section-head">
                <div>
                    <h1>Orders</h1>
                    <p className="admin-subtitle">All orders grouped by vendors with status tracking</p>
                </div>
            </header>

            {error && <div className="admin-alert admin-alert-danger">{error}</div>}

            <div className="admin-card">
                <div className="admin-filters">
                    <input
                        type="search"
                        name="search"
                        placeholder="Search order #, customer name or email"
                        value={filters.search}
                        onChange={handleFilterChange}
                    />
                    <select name="status" value={filters.status} onChange={handleFilterChange}>
                        <option value="">All statuses</option>
                        <option value="pending">Pending</option>
                        <option value="confirmed">Confirmed</option>
                        <option value="shipped">Shipped</option>
                        <option value="delivered">Delivered</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                    <select name="vendor_id" value={filters.vendor_id} onChange={handleFilterChange}>
                        <option value="">All vendors</option>
                        {vendors.map((vendor) => (
                            <option key={vendor.id} value={vendor.id}>{vendor.name}</option>
                        ))}
                    </select>
                    <select name="customer_id" value={filters.customer_id} onChange={handleFilterChange}>
                        <option value="">All customers</option>
                        {customers.map((customer) => (
                            <option key={customer.id} value={customer.id}>{customer.name} - {customer.email}</option>
                        ))}
                    </select>
                    <input type="date" name="date_from" value={filters.date_from} onChange={handleFilterChange} />
                    <input type="date" name="date_to" value={filters.date_to} onChange={handleFilterChange} />
                </div>

                <div className="admin-table-wrapper">
                    <table className="admin-table">
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Customer</th>
                                <th>Vendors</th>
                                <th>Items</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Placed</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            {loading ? (
                                <tr><td colSpan="8">Loading...</td></tr>
                            ) : orders.length === 0 ? (
                                <tr><td colSpan="8">No orders found</td></tr>
                            ) : (
                                orders.map((order) => (
                                    <tr key={order.id}>
                                        <td><strong>{order.order_number}</strong></td>
                                        <td>
                                            <div>{order.customer?.name || order.customer_name}</div>
                                            <div className="admin-subtext">{order.customer?.email || order.customer_email}</div>
                                        </td>
                                        <td>{order.sub_orders?.length ?? order.sub_orders_count ?? 0}</td>
                                        <td>{order.items_count ?? '-'}</td>
                                        <td><strong>{formatMoney(order.total)}</strong></td>
                                        <td><span className={`admin-status ${STATUS_COLORS[order.status]}`}>{order.status}</span></td>
                                        <td>{order.placed_at ? new Date(order.placed_at).toLocaleString() : '-'}</td>
                                        <td><a className="admin-link" href={`/admin/orders/${order.id}`}>View</a></td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                {meta && meta.last_page > 1 && (
                    <div className="admin-pagination">
                        <button type="button" disabled={filters.page <= 1} onClick={() => handlePageChange(filters.page - 1)}>Previous</button>
                        <span>Page {meta.current_page} of {meta.last_page}</span>
                        <button type="button" disabled={filters.page >= meta.last_page} onClick={() => handlePageChange(filters.page + 1)}>Next</button>
                    </div>
                )}
            </div>
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
            .catch(() => setError('Failed to load order'))
            .finally(() => setLoading(false));
    };

    useEffect(() => {
        fetchOrder();
    }, [orderId]);

    async function updateStatus(subOrderId, status) {
        setUpdating(true);
        setMessage('');
        try {
            await window.axios.patch(`/api/admin/sub-orders/${subOrderId}/status`, { status });
            setMessage('Status updated');
            fetchOrder();
        } catch {
            setError('Failed to update status');
        } finally {
            setUpdating(false);
        }
    }

    async function notifyVendor(subOrderId) {
        setUpdating(true);
        setMessage('');
        try {
            await window.axios.post(`/api/admin/sub-orders/${subOrderId}/notify`);
            setMessage('Notification queued');
            fetchOrder();
        } catch {
            setError('Failed to notify vendor');
        } finally {
            setUpdating(false);
        }
    }

    if (loading) return <div className="admin-section">Loading...</div>;
    if (error && !order) return <div className="admin-alert admin-alert-danger">{error}</div>;
    if (!order) return null;

    return (
        <div className="admin-section">
            <header className="admin-section-head">
                <div>
                    <a href="/admin/orders" className="admin-link">← Back to orders</a>
                    <h1>Order {order.order_number}</h1>
                    <p className="admin-subtitle">Placed {order.placed_at ? new Date(order.placed_at).toLocaleString() : '-'}</p>
                </div>
                <span className={`admin-status ${STATUS_COLORS[order.status]}`}>{order.status}</span>
            </header>

            {message && <div className="admin-alert admin-alert-success">{message}</div>}
            {error && <div className="admin-alert admin-alert-danger">{error}</div>}

            <div className="admin-grid">
                <div className="admin-card">
                    <h2>Overview</h2>
                    <dl className="admin-definition">
                        <div><dt>Payment</dt><dd>Cash on delivery</dd></div>
                        <div><dt>Subtotal</dt><dd>{formatMoney(order.subtotal)}</dd></div>
                        <div><dt>Discounts</dt><dd className="admin-discount">−{formatMoney(order.discount_total)}</dd></div>
                        <div><dt>Total</dt><dd><strong>{formatMoney(order.total)}</strong></dd></div>
                    </dl>
                </div>
                <div className="admin-card">
                    <h2>Customer</h2>
                    <div className="admin-stack">
                        <div><strong>{order.customer?.name || order.customer_name}</strong></div>
                        <div>{order.customer?.email || order.customer_email}</div>
                        {order.customer?.phone && <div>{order.customer.phone}</div>}
                        {order.customer?.address && <div>{order.customer.address}</div>}
                    </div>
                </div>
                <div className="admin-card">
                    <h2>Delivery</h2>
                    <div className="admin-stack">
                        <div><strong>{order.delivery?.name || order.delivery_name}</strong></div>
                        <div>{order.delivery?.email || order.delivery_email}</div>
                        {order.delivery?.phone && <div>{order.delivery.phone}</div>}
                        <div>{order.delivery?.address || order.delivery_address}</div>
                    </div>
                </div>
            </div>

            <div className="admin-card">
                <h2>Sub-orders & Vendors</h2>
                {order.sub_orders?.map((subOrder) => (
                    <div key={subOrder.id} className="admin-suborder">
                        <div className="admin-suborder-head">
                            <div>
                                <h3>{subOrder.vendor.name}</h3>
                                <p className="admin-subtext">{subOrder.vendor.email} {subOrder.vendor.phone && `• ${subOrder.vendor.phone}`}</p>
                            </div>
                            <div className="admin-suborder-actions">
                                <select value={subOrder.status} onChange={(e) => updateStatus(subOrder.id, e.target.value)} disabled={updating}>
                                    <option value="pending">Pending</option>
                                    <option value="confirmed">Confirmed</option>
                                    <option value="shipped">Shipped</option>
                                    <option value="delivered">Delivered</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                                <button type="button" className="admin-button" onClick={() => notifyVendor(subOrder.id)} disabled={updating}>
                                    {subOrder.notification_status === 'sent' ? 'Re-notify' : 'Notify vendor'}
                                </button>
                                <span className={`admin-status ${STATUS_COLORS[subOrder.status]}`}>{subOrder.status}</span>
                            </div>
                        </div>
                        <div className="admin-table-wrapper">
                            <table className="admin-table">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>SKU</th>
                                        <th>Qty</th>
                                        <th>Unit</th>
                                        <th>Discount</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {subOrder.items?.map((item) => (
                                        <tr key={item.id}>
                                            <td>
                                                <div>{item.product_name}</div>
                                                {item.product_category && <div className="admin-subtext">{item.product_category}</div>}
                                            </td>
                                            <td>{item.product_sku || '-'}</td>
                                            <td>{item.quantity}</td>
                                            <td>{formatMoney(item.unit_price)}</td>
                                            <td>{item.discount_amount > 0 ? `−${formatMoney(item.discount_amount)}` : formatMoney(0)}</td>
                                            <td><strong>{formatMoney(item.line_total)}</strong></td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        <div className="admin-suborder-sum">
                            <div><span>Subtotal</span><span>{formatMoney(subOrder.subtotal)}</span></div>
                            <div><span>Discounts</span><span className="admin-discount">−{formatMoney(subOrder.discount_total)}</span></div>
                            <div className="admin-suborder-sum-grand"><span>Total</span><span>{formatMoney(subOrder.total)}</span></div>
                            <div><span>Notification</span><span>{subOrder.notification_status}{subOrder.notified_at && ` • ${new Date(subOrder.notified_at).toLocaleString()}`}</span></div>
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}
