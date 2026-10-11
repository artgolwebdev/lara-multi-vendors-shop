import { useEffect, useState } from 'react';
import { AdminPageHeader, ApiErrors, navigateWithFlash } from './shared';

export default function CategoryPages({ path }) {
    const parts = path.split('/').filter(Boolean);
    const categoryId = parts[2] && parts[2] !== 'create' ? parts[2] : null;

    if (parts[2] === 'create' || parts[3] === 'edit') {
        return <CategoryForm categoryId={categoryId} />;
    }

    return <CategoryList />;
}

function CategoryList() {
    const [categories, setCategories] = useState(null);
    const [error, setError] = useState(null);

    async function load() {
        setError(null);

        try {
            const { data } = await window.axios.get('/api/admin/categories');
            setCategories(data.data);
        } catch (requestError) {
            setError(requestError);
        }
    }

    useEffect(() => { load(); }, []);

    async function removeCategory(category) {
        if (!window.confirm(`Delete category ${category.name}?`)) {
            return;
        }

        try {
            await window.axios.delete(`/api/admin/categories/${category.id}`);
            navigateWithFlash('/admin/categories', 'Category deleted.');
        } catch (requestError) {
            setError(requestError);
        }
    }

    return (
        <>
            <AdminPageHeader
                eyebrow="Catalog structure"
                title="Categories"
                action={<a className="admin-button admin-button-primary" href="/admin/categories/create">Add category</a>}
            />
            {error && <ApiErrors error={error} />}
            <section className="admin-table-wrap" aria-label="Product categories">
                <table className="admin-table">
                    <thead><tr><th>Category</th><th>Products</th><th>Discount rules</th><th>Actions</th></tr></thead>
                    <tbody>
                        {categories?.map((category) => (
                            <tr key={category.id}>
                                <td><span className="admin-row-title">{category.name}</span></td>
                                <td>{category.products_count ?? 0}</td>
                                <td>{category.discount_rules_count ?? 0}</td>
                                <td className="admin-row-actions"><a href={`/admin/categories/${category.id}/edit`}>Edit</a><button type="button" onClick={() => removeCategory(category)}>Delete</button></td>
                            </tr>
                        ))}
                        {categories?.length === 0 && <tr><td colSpan="4" className="admin-empty">No categories yet.</td></tr>}
                        {!categories && <tr><td colSpan="4" className="admin-empty">Loading categories...</td></tr>}
                    </tbody>
                </table>
            </section>
        </>
    );
}

function CategoryForm({ categoryId }) {
    const [name, setName] = useState('');
    const [error, setError] = useState(null);
    const [loading, setLoading] = useState(Boolean(categoryId));
    const [submitting, setSubmitting] = useState(false);

    useEffect(() => {
        if (!categoryId) {
            return;
        }

        window.axios.get(`/api/admin/categories/${categoryId}`)
            .then(({ data }) => setName(data.data.name))
            .catch(setError)
            .finally(() => setLoading(false));
    }, [categoryId]);

    async function submit(event) {
        event.preventDefault();
        setSubmitting(true);
        setError(null);

        try {
            if (categoryId) {
                await window.axios.patch(`/api/admin/categories/${categoryId}`, { name });
            } else {
                await window.axios.post('/api/admin/categories', { name });
            }

            navigateWithFlash('/admin/categories', categoryId ? 'Category updated.' : 'Category created.');
        } catch (requestError) {
            setError(requestError);
        } finally {
            setSubmitting(false);
        }
    }

    if (loading) {
        return <p className="admin-muted" role="status">Loading category...</p>;
    }

    return (
        <>
            <AdminPageHeader eyebrow="Catalog structure" title={categoryId ? 'Edit category' : 'New category'} />
            {error && <ApiErrors error={error} />}
            <form className="admin-form" onSubmit={submit}>
                <section className="admin-form-section">
                    <label className="admin-field">Category name<input value={name} onChange={(event) => { setName(event.target.value); setError(null); }} required maxLength="255" /></label>
                </section>
                <div className="admin-form-actions">
                    <a className="admin-button" href="/admin/categories">Cancel</a>
                    <button className="admin-button admin-button-primary" type="submit" disabled={submitting}>{submitting ? 'Saving...' : 'Save category'}</button>
                </div>
            </form>
        </>
    );
}