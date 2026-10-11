import { useEffect, useState } from 'react';
import { AdminPageHeader, ApiErrors, Pagination } from './shared';

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
    const [error, setError] = useState(null);

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

    useEffect(() => { load(); }, [customerId]);

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
        </>
    );
}
