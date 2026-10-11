import { useEffect, useState } from 'react';
import { AdminPageHeader, ApiErrors, Pagination } from './shared';
import { formatMoney } from '../store/formatMoney';

export default function CustomerPages({ path }) {
    const parts = path.split('/').filter(Boolean);
    const customerId = parts[2] || null;

    if (customerId) {
        return <CustomerDetail customerId={customerId} />;
    }

    return <CustomerList />;
}

function CustomerList() {
    const [customers, setCustomers] = useState(null);
    const [links, setLinks] = useState(null);
    const [search, setSearch] = useState('');
    const [error, setError] = useState(null);

    async function load(url = '/api/admin/customers') {
        setError(null);

        try {
            const { data } = await window.axios.get(url);
            setCustomers(data.data);
            setLinks(data.links);
        } catch (requestError) {
            setError(requestError);
        }
    }

    useEffect(() => { load(); }, []);

    function submitSearch(event) {
        event.preventDefault();
        const query = search.trim();
        load(query ? `/api/admin/customers?search=${encodeURIComponent(query)}` : '/api/admin/customers');
    }

    return (
        <>
            <AdminPageHeader eyebrow="People" title="Customers" />
            {error && <ApiErrors error={error} />}
            <form className="admin-toolbar" onSubmit={submitSearch}>
                <label className="admin-field">
                    Search
                    <input type="search" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Name or email" />
                </label>
                <button className="admin-button admin-button-primary" type="submit">Search</button>
            </form>
            <section className="admin-table-wrap" aria-label="Customers">
                <table className="admin-table">
                    <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Orders</th><th>Registered</th><th>Actions</th></tr></thead>
                    <tbody>
                        {customers?.map((customer) => (
                            <tr key={customer.id}>
                                <td><span className="admin-row-title">{customer.name}</span></td>
                                <td>{customer.email}</td>
                                <td>{customer.phone || '—'}</td>
                                <td>{customer.orders_count}</td>
                                <td>{customer.created_at ? new Date(customer.created_at).toLocaleDateString() : '—'}</td>
                                <td className="admin-row-actions"><a href={`/admin/customers/${customer.id}`}>View</a></td>
                            </tr>
                        ))}
                        {customers?.length === 0 && <tr><td colSpan="6" className="admin-empty">No customers found.</td></tr>}
                        {!customers && <tr><td colSpan="6" className="admin-empty">Loading customers...</td></tr>}
                    </tbody>
                </table>
            </section>
            <Pagination links={links} onNavigate={load} />
        </>
    );
}

function CustomerDetail({ customerId }) {
    const [customer, setCustomer] = useState(null);
    const [rules, setRules] = useState(null);
    const [orders, setOrders] = useState([]);
    const [lifetimeTotal, setLifetimeTotal] = useState('0.00');
    const [ordersMeta, setOrdersMeta] = useState(null);
    const [ordersLoading, setOrdersLoading] = useState(true);
    const [error, setError] = useState(null);

    const loadOrders = (url) => {
        setOrdersLoading(true);
        window.axios.get(url)
            .then(({ data }) => {
                setOrders(data.data || []);
                setOrdersMeta(data.meta || null);
                if (data.lifetime_total !== undefined) {
                    setLifetimeTotal(data.lifetime_total);
                }
            })
            .catch(() => {})
            .finally(() => setOrdersLoading(false));
    };

    async function load() {
        setError(null);

        try {
            const [customerResponse, rulesResponse] = await Promise.all([
                window.axios.get(`/api/admin/customers/${customerId}`),
                window.axios.get(`/api/admin/customers/${customerId}/loyalty-rules`),
            ]);
            setCustomer(customerResponse.data.data);
            setRules(rulesResponse.data.data);
        } catch (requestError) {
            setError(requestError);
        }
    }

    useEffect(() => {
        load();
        loadOrders(`/api/admin/customers/${customerId}/orders`);
    }, [customerId]);

    async function toggle(rule) {
        setError(null);

        try {
            if (rule.attached) {
                await window.axios.delete(`/api/admin/customers/${customerId}/loyalty-rules/${rule.id}`);
            } else {
                await window.axios.post(`/api/admin/customers/${customerId}/loyalty-rules/${rule.id}`);
            }
            await load();
        } catch (requestError) {
            setError(requestError);
        }
    }

    if (!customer) {
        return (
            <>
                <AdminPageHeader eyebrow="People" title="Customer" />
                {error && <ApiErrors error={error} />}
                {!error && <p className="admin-muted" role="status">Loading customer...</p>}
            </>
        );
    }

    return (
        <>
            <AdminPageHeader
                eyebrow="People"
                title={customer.name}
                action={<a className="admin-button" href="/admin/customers">Back to customers</a>}
            />
            {error && <ApiErrors error={error} />}

            <section className="admin-form-section">
                <h2>Profile</h2>
                <dl className="admin-detail-list">
                    <div><dt>Name</dt><dd>{customer.name}</dd></div>
                    <div><dt>Email</dt><dd>{customer.email}</dd></div>
                    <div><dt>Phone</dt><dd>{customer.phone || '—'}</dd></div>
                    <div><dt>Address</dt><dd>{customer.address || '—'}</dd></div>
                    <div><dt>Orders</dt><dd>{customer.orders_count}</dd></div>
                    <div><dt>Registered</dt><dd>{customer.created_at ? new Date(customer.created_at).toLocaleString() : '—'}</dd></div>
                </dl>
            </section>

            <section className="admin-form-section">
                <h2>Loyalty discounts</h2>
                <p className="admin-help">All-customer rules apply to everyone and are managed from the Discounts page. Selected-customer rules can be granted or revoked here.</p>
                <table className="admin-table">
                    <thead><tr><th>Rule</th><th>Applies to</th><th>Granted</th><th>Action</th></tr></thead>
                    <tbody>
                        {rules?.map((rule) => (
                            <tr key={rule.id}>
                                <td>{rule.name}</td>
                                <td>{rule.applies_to_everyone ? 'Everyone' : 'Selected customers'}</td>
                                <td>{rule.applies_to_everyone ? '—' : (rule.attached ? 'Yes' : 'No')}</td>
                                <td className="admin-row-actions">
                                    {rule.applies_to_everyone
                                        ? <a href="/admin/discount-rules">Manage</a>
                                        : (
                                            <button type="button" onClick={() => toggle(rule)}>
                                                {rule.attached ? 'Revoke' : 'Grant'}
                                            </button>
                                        )}
                                </td>
                            </tr>
                        ))}
                        {rules?.length === 0 && <tr><td colSpan="4" className="admin-empty">No active loyalty rules.</td></tr>}
                        {!rules && <tr><td colSpan="4" className="admin-empty">Loading loyalty rules...</td></tr>}
                    </tbody>
                </table>
            </section>

            <section className="admin-form-section">
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '16px' }}>
                    <h2 style={{ margin: 0 }}>Customer Order History</h2>
                    <span style={{ fontSize: '13px', color: 'var(--c-muted)' }}>
                        Lifetime spend: <strong>{formatMoney(lifetimeTotal)}</strong>
                    </span>
                </div>

                <div className="admin-table-wrapper">
                    <table className="admin-table gg-table">
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Placed</th>
                                <th>Vendors</th>
                                <th>Items</th>
                                <th>Status</th>
                                <th style={{ textAlign: 'right' }}>Total</th>
                                <th style={{ textAlign: 'right' }}>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            {ordersLoading ? (
                                <tr><td colSpan="7" style={{ padding: '20px', textAlign: 'center' }}>Loading orders...</td></tr>
                            ) : orders.length === 0 ? (
                                <tr><td colSpan="7" style={{ padding: '20px', textAlign: 'center', color: 'var(--c-muted)' }}>No orders placed by this customer yet.</td></tr>
                            ) : (
                                orders.map((order) => (
                                    <tr key={order.id}>
                                        <td>
                                            <a href={`/admin/orders/${order.id}`} className="admin-link" style={{ fontWeight: 700 }}>
                                                {order.order_number}
                                            </a>
                                        </td>
                                        <td>{order.placed_at ? new Date(order.placed_at).toLocaleDateString() : '—'}</td>
                                        <td>{order.sub_orders_count ?? 1}</td>
                                        <td>{order.items_count ?? '—'}</td>
                                        <td>
                                            <span className={`store-status-pill status-${order.status}`}>
                                                {order.status}
                                            </span>
                                        </td>
                                        <td style={{ textAlign: 'right', fontWeight: 700 }}>
                                            {formatMoney(order.total)}
                                        </td>
                                        <td style={{ textAlign: 'right' }}>
                                            <a href={`/admin/orders/${order.id}`} className="gg-button gg-button--secondary gg-button--sm">
                                                View
                                            </a>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                {ordersMeta && ordersMeta.last_page > 1 && (
                    <div className="admin-pagination" style={{ display: 'flex', justifyContent: 'flex-end', gap: '8px' }}>
                        <button
                            type="button"
                            className="gg-button gg-button--secondary gg-button--sm"
                            disabled={!ordersMeta.prev_page_url}
                            onClick={() => loadOrders(ordersMeta.prev_page_url)}
                        >
                            Previous
                        </button>
                        <button
                            type="button"
                            className="gg-button gg-button--secondary gg-button--sm"
                            disabled={!ordersMeta.next_page_url}
                            onClick={() => loadOrders(ordersMeta.next_page_url)}
                        >
                            Next
                        </button>
                    </div>
                )}
            </section>
        </>
    );
}
