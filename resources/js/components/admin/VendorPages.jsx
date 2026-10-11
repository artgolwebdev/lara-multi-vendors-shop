import { useEffect, useState } from 'react';
import { AdminPageHeader, ApiErrors, navigateWithFlash, Pagination, StatusBadge } from './shared';
import { formatMoney } from '../store/formatMoney';

const emptyVendor = {
    name: '', email: '', phone: '', address: '', city: '', country: '', contact_person: '', notes: '', is_active: true,
};

export default function VendorPages({ path }) {
    const parts = path.split('/').filter(Boolean);
    const vendorId = parts[2] && parts[2] !== 'create' ? parts[2] : null;

    if (parts[2] === 'create' || parts[3] === 'edit') {
        return <VendorForm vendorId={vendorId} />;
    }

    if (vendorId) {
        return <VendorDetail vendorId={vendorId} />;
    }

    return <VendorList />;
}

function VendorList() {
    const [vendors, setVendors] = useState(null);
    const [links, setLinks] = useState(null);
    const [error, setError] = useState(null);

    async function load(url = '/api/admin/vendors') {
        setError(null);

        try {
            const { data } = await window.axios.get(url);
            setVendors(data.data);
            setLinks(data.links);
        } catch (requestError) {
            setError(requestError);
        }
    }

    useEffect(() => { load(); }, []);

    async function removeVendor(vendor) {
        if (!window.confirm(`Delete ${vendor.name}?`)) {
            return;
        }

        try {
            await window.axios.delete(`/api/admin/vendors/${vendor.id}`);
            navigateWithFlash('/admin/vendors', 'Vendor deleted.');
        } catch (requestError) {
            setError(requestError);
        }
    }

    return (
        <>
            <AdminPageHeader
                eyebrow="Catalog partners"
                title="Vendors"
                action={<a className="admin-button admin-button-primary" href="/admin/vendors/create">Add vendor</a>}
            />
            {error && <ApiErrors error={error} />}
            <section className="admin-table-wrap" aria-label="Vendors">
                <table className="admin-table">
                    <thead><tr><th>Vendor</th><th>Contact</th><th>Location</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                        {vendors?.map((vendor) => (
                            <tr key={vendor.id}>
                                <td><a className="admin-row-title" href={`/admin/vendors/${vendor.id}`}>{vendor.name}</a><span className="admin-table-secondary">{vendor.email}</span></td>
                                <td>{vendor.contact_person || vendor.phone || '—'}</td>
                                <td>{[vendor.city, vendor.country].filter(Boolean).join(', ') || '—'}</td>
                                <td><StatusBadge active={vendor.is_active} /></td>
                                <td className="admin-row-actions"><a href={`/admin/vendors/${vendor.id}/edit`}>Edit</a><button type="button" onClick={() => removeVendor(vendor)}>Delete</button></td>
                            </tr>
                        ))}
                        {vendors?.length === 0 && <tr><td colSpan="5" className="admin-empty">No vendors yet.</td></tr>}
                        {!vendors && <tr><td colSpan="5" className="admin-empty">Loading vendors...</td></tr>}
                    </tbody>
                </table>
            </section>
            <Pagination links={links} onNavigate={load} />
        </>
    );
}

function VendorForm({ vendorId }) {
    const [form, setForm] = useState(emptyVendor);
    const [error, setError] = useState(null);
    const [loading, setLoading] = useState(Boolean(vendorId));
    const [submitting, setSubmitting] = useState(false);

    useEffect(() => {
        if (!vendorId) {
            return;
        }

        window.axios.get(`/api/admin/vendors/${vendorId}`)
            .then(({ data }) => setForm({ ...emptyVendor, ...data.data }))
            .catch(setError)
            .finally(() => setLoading(false));
    }, [vendorId]);

    function updateField(event) {
        const { name, value, checked, type } = event.target;
        setForm((current) => ({ ...current, [name]: type === 'checkbox' ? checked : value }));
        setError(null);
    }

    async function submit(event) {
        event.preventDefault();
        setSubmitting(true);
        setError(null);

        try {
            if (vendorId) {
                await window.axios.patch(`/api/admin/vendors/${vendorId}`, form);
            } else {
                await window.axios.post('/api/admin/vendors', form);
            }

            navigateWithFlash('/admin/vendors', vendorId ? 'Vendor updated.' : 'Vendor created.');
        } catch (requestError) {
            setError(requestError);
        } finally {
            setSubmitting(false);
        }
    }

    if (loading) {
        return <p className="admin-muted" role="status">Loading vendor...</p>;
    }

    return (
        <>
            <AdminPageHeader eyebrow="Catalog partners" title={vendorId ? 'Edit vendor' : 'New vendor'} />
            {error && <ApiErrors error={error} />}
            <form className="admin-form" onSubmit={submit}>
                <div className="admin-form-grid">
                    <label className="admin-field">Vendor name<input name="name" value={form.name} onChange={updateField} required /></label>
                    <label className="admin-field">Email<input name="email" type="email" value={form.email} onChange={updateField} required /></label>
                    <label className="admin-field">Phone<input name="phone" value={form.phone || ''} onChange={updateField} /></label>
                    <label className="admin-field">Contact person<input name="contact_person" value={form.contact_person || ''} onChange={updateField} /></label>
                    <label className="admin-field">City<input name="city" value={form.city || ''} onChange={updateField} /></label>
                    <label className="admin-field">Country<input name="country" value={form.country || ''} onChange={updateField} /></label>
                    <label className="admin-field admin-field-wide">Address<textarea name="address" rows="2" value={form.address || ''} onChange={updateField} /></label>
                    <label className="admin-field admin-field-wide">Notes<textarea name="notes" rows="4" value={form.notes || ''} onChange={updateField} /></label>
                </div>
                <label className="admin-check"><input type="checkbox" name="is_active" checked={Boolean(form.is_active)} onChange={updateField} /> Active vendor</label>
                <div className="admin-form-actions">
                    <a className="admin-button" href="/admin/vendors">Cancel</a>
                    <button className="admin-button admin-button-primary" type="submit" disabled={submitting}>{submitting ? 'Saving...' : 'Save vendor'}</button>
                </div>
            </form>
        </>
    );
}

function VendorDetail({ vendorId }) {
    const [vendor, setVendor] = useState(null);
    const [error, setError] = useState(null);
    const [orders, setOrders] = useState([]);
    const [totalSales, setTotalSales] = useState('0.00');
    const [ordersMeta, setOrdersMeta] = useState(null);
    const [ordersLoading, setOrdersLoading] = useState(true);

    useEffect(() => {
        window.axios.get(`/api/admin/vendors/${vendorId}`)
            .then(({ data }) => setVendor(data.data))
            .catch(setError);

        loadOrders(`/api/admin/vendors/${vendorId}/orders`);
    }, [vendorId]);

    const loadOrders = (url) => {
        setOrdersLoading(true);
        window.axios.get(url)
            .then(({ data }) => {
                setOrders(data.data || []);
                setOrdersMeta(data.meta || null);
                if (data.total_sales !== undefined) {
                    setTotalSales(data.total_sales);
                }
            })
            .catch(() => {})
            .finally(() => setOrdersLoading(false));
    };

    if (error) {
        return <ApiErrors error={error} />;
    }

    if (!vendor) {
        return <p className="admin-muted" role="status">Loading vendor...</p>;
    }

    return (
        <>
            <AdminPageHeader
                eyebrow="Vendor record"
                title={vendor.name}
                action={<a className="admin-button admin-button-primary" href={`/admin/vendors/${vendor.id}/edit`}>Edit vendor</a>}
            />
            <dl className="admin-detail-list">
                <div><dt>Email</dt><dd>{vendor.email}</dd></div>
                <div><dt>Phone</dt><dd>{vendor.phone || '—'}</dd></div>
                <div><dt>Contact person</dt><dd>{vendor.contact_person || '—'}</dd></div>
                <div><dt>Location</dt><dd>{[vendor.city, vendor.country].filter(Boolean).join(', ') || '—'}</dd></div>
                <div><dt>Address</dt><dd>{vendor.address || '—'}</dd></div>
                <div><dt>Notes</dt><dd>{vendor.notes || '—'}</dd></div>
                <div><dt>Status</dt><dd><StatusBadge active={vendor.is_active} /></dd></div>
                <div><dt>Total Sales</dt><dd><strong>{formatMoney(totalSales)}</strong></dd></div>
            </dl>

            <section className="admin-form-section" style={{ marginTop: '24px' }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '16px' }}>
                    <h2 style={{ margin: 0 }}>Vendor Orders & Fulfillment</h2>
                    <span style={{ fontSize: '13px', color: 'var(--c-muted)' }}>
                        Fulfilled sales: <strong>{formatMoney(totalSales)}</strong>
                    </span>
                </div>

                <div className="admin-table-wrapper">
                    <table className="admin-table gg-table">
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Customer</th>
                                <th>Placed</th>
                                <th>Status</th>
                                <th style={{ textAlign: 'right' }}>Vendor Total</th>
                                <th style={{ textAlign: 'right' }}>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            {ordersLoading ? (
                                <tr><td colSpan="6" style={{ padding: '20px', textAlign: 'center' }}>Loading orders...</td></tr>
                            ) : orders.length === 0 ? (
                                <tr><td colSpan="6" style={{ padding: '20px', textAlign: 'center', color: 'var(--c-muted)' }}>No orders for this vendor yet.</td></tr>
                            ) : (
                                orders.map((order) => {
                                    const subOrder = order.sub_orders?.[0];
                                    return (
                                        <tr key={order.id}>
                                            <td>
                                                <a href={`/admin/orders/${order.id}`} className="admin-link" style={{ fontWeight: 700 }}>
                                                    {order.order_number}
                                                </a>
                                            </td>
                                            <td>{order.customer?.name || 'Guest'}</td>
                                            <td>{order.placed_at ? new Date(order.placed_at).toLocaleDateString() : '—'}</td>
                                            <td>
                                                <span className={`store-status-pill status-${subOrder?.status || order.status}`}>
                                                    {subOrder?.status || order.status}
                                                </span>
                                            </td>
                                            <td style={{ textAlign: 'right', fontWeight: 700 }}>
                                                {formatMoney(subOrder?.total || order.total)}
                                            </td>
                                            <td style={{ textAlign: 'right' }}>
                                                <a href={`/admin/orders/${order.id}`} className="gg-button gg-button--secondary gg-button--sm">
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

            <p style={{ marginTop: '20px' }}>
                <a className="admin-back-link" href="/admin/vendors">← Back to vendors</a>
            </p>
        </>
    );
}