import { useEffect, useState } from 'react';
import { AdminPageHeader, ApiErrors } from './shared';

const countLabels = [
    ['orders', 'Orders', 'All placed orders'],
    ['vendors', 'Vendors', 'Total vendor records'],
    ['active_vendors', 'Active vendors', 'Available to receive offers'],
    ['products', 'Products', 'Shared catalog items'],
    ['active_products', 'Active products', 'Visible to future storefronts'],
    ['active_offers', 'Active offers', 'Offers from active vendors'],
];

export default function AdminDashboard() {
    const [counts, setCounts] = useState(null);
    const [error, setError] = useState(null);

    useEffect(() => {
        window.axios.get('/api/admin/dashboard')
            .then(({ data }) => setCounts(data.data))
            .catch(setError);
    }, []);

    return (
        <>
            <AdminPageHeader eyebrow="Administration" title="Overview" />
            {error && <ApiErrors error={error} />}
            {!counts && !error ? <p className="admin-muted" role="status">Loading counts...</p> : null}
            {counts && (
                <section className="admin-count-grid" aria-label="Catalog counts">
                    {countLabels.map(([key, label, description]) => (
                        <article className="admin-count" key={key}>
                            <p>{label}</p>
                            <strong>{counts[key]}</strong>
                            <span>{description}</span>
                        </article>
                    ))}
                </section>
            )}
        </>
    );
}