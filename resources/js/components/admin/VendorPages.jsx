import { useEffect, useState } from 'react';
import { AdminPageHeader, ApiErrors, navigateWithFlash, Pagination, StatusBadge } from './shared';

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

    useEffect(() => {
        window.axios.get(`/api/admin/vendors/${vendorId}`)
            .then(({ data }) => setVendor(data.data))
            .catch(setError);
    }, [vendorId]);

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
            </dl>
            <a className="admin-back-link" href="/admin/vendors">Back to vendors</a>
        </>
    );
}