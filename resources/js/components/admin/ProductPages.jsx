import { useEffect, useState } from 'react';
import { AdminPageHeader, ApiErrors, navigateWithFlash, Pagination, StatusBadge } from './shared';

const emptyProduct = { name: '', slug: '', sku: '', description: '', category_id: '', quantity: 0, is_active: true };
const emptyOffer = { vendor_id: '', price: '', vendor_sku: '', is_active: true };

export default function ProductPages({ path }) {
    const parts = path.split('/').filter(Boolean);
    const productId = parts[2] && parts[2] !== 'create' ? parts[2] : null;

    if (parts[2] === 'create' || parts[3] === 'edit') {
        return <ProductForm productId={productId} />;
    }

    return <ProductList />;
}

function ProductList() {
    const [products, setProducts] = useState(null);
    const [links, setLinks] = useState(null);
    const [error, setError] = useState(null);

    async function load(url = '/api/admin/products') {
        setError(null);

        try {
            const { data } = await window.axios.get(url);
            setProducts(data.data);
            setLinks(data.links);
        } catch (requestError) {
            setError(requestError);
        }
    }

    useEffect(() => { load(); }, []);

    async function removeProduct(product) {
        if (!window.confirm(`Delete ${product.name}?`)) {
            return;
        }

        try {
            await window.axios.delete(`/api/admin/products/${product.id}`);
            navigateWithFlash('/admin/products', 'Product deleted.');
        } catch (requestError) {
            setError(requestError);
        }
    }

    return (
        <>
            <AdminPageHeader
                eyebrow="Shared catalog"
                title="Products"
                action={<a className="admin-button admin-button-primary" href="/admin/products/create">Add product</a>}
            />
            {error && <ApiErrors error={error} />}
            <section className="admin-table-wrap" aria-label="Products">
                <table className="admin-table">
                    <thead><tr><th>Product</th><th>Category</th><th>Quantity</th><th>Lowest offer</th><th>Vendors</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                        {products?.map((product) => (
                            <tr key={product.id}>
                                <td><span className="admin-row-title">{product.name}</span><span className="admin-table-secondary">SKU {product.sku}</span></td>
                                <td>{product.category?.name || '—'}</td>
                                <td>{product.quantity}</td>
                                <td>{product.lowest_price ? `$${product.lowest_price}` : 'No active offer'}</td>
                                <td>{product.vendors_count ?? 0}</td>
                                <td><StatusBadge active={product.is_active} /></td>
                                <td className="admin-row-actions"><a href={`/admin/products/${product.id}/edit`}>Edit</a><button type="button" onClick={() => removeProduct(product)}>Delete</button></td>
                            </tr>
                        ))}
                        {products?.length === 0 && <tr><td colSpan="7" className="admin-empty">No products yet.</td></tr>}
                        {!products && <tr><td colSpan="7" className="admin-empty">Loading products...</td></tr>}
                    </tbody>
                </table>
            </section>
            <Pagination links={links} onNavigate={load} />
        </>
    );
}

function ProductForm({ productId }) {
    const [form, setForm] = useState(emptyProduct);
    const [offers, setOffers] = useState([{ ...emptyOffer }]);
    const [vendors, setVendors] = useState([]);
    const [categories, setCategories] = useState([]);
    const [images, setImages] = useState([]);
    const [selectedImages, setSelectedImages] = useState([]);
    const [error, setError] = useState(null);
    const [loading, setLoading] = useState(true);
    const [submitting, setSubmitting] = useState(false);

    useEffect(() => {
        const requests = [
            window.axios.get('/api/admin/vendors?per_page=100'),
            window.axios.get('/api/admin/categories'),
        ];

        if (productId) {
            requests.push(window.axios.get(`/api/admin/products/${productId}`));
        }

        Promise.all(requests)
            .then(([vendorResponse, categoryResponse, productResponse]) => {
                setVendors(vendorResponse.data.data);
                setCategories(categoryResponse.data.data);

                if (productResponse) {
                    const product = productResponse.data.data;
                    setForm({ ...emptyProduct, ...product });
                    setOffers(product.offers.map((offer) => ({
                        vendor_id: String(offer.vendor_id),
                        price: offer.price,
                        vendor_sku: offer.vendor_sku || '',
                        is_active: offer.is_active,
                    })));
                    setImages(product.images);
                } else {
                    setLoading(false);
                }
            })
            .catch(setError)
            .finally(() => setLoading(false));
    }, [productId]);

    function updateField(event) {
        const { name, value, checked, type } = event.target;
        setForm((current) => ({ ...current, [name]: type === 'checkbox' ? checked : value }));
        setError(null);
    }

    function updateOffer(index, key, value) {
        setOffers((current) => current.map((offer, offerIndex) => (
            offerIndex === index ? { ...offer, [key]: value } : offer
        )));
        setError(null);
    }

    async function submit(event) {
        event.preventDefault();
        setSubmitting(true);
        setError(null);

        const body = new FormData();
        Object.entries(form).forEach(([key, value]) => {
            // Only submit editable fields; product payload extras such as images,
            // offers, and category are objects that must not be stringified here.
            if (!(key in emptyProduct)) {
                return;
            }
            body.append(key, key === 'is_active' ? (value ? '1' : '0') : value ?? '');
        });
        offers.forEach((offer, index) => {
            Object.entries(offer).forEach(([key, value]) => {
                body.append(`offers[${index}][${key}]`, key === 'is_active' ? (value ? '1' : '0') : value);
            });
        });
        selectedImages.forEach((image) => body.append('images[]', image));

        try {
            if (productId) {
                body.append('_method', 'PUT');
                await window.axios.post(`/api/admin/products/${productId}`, body);
            } else {
                await window.axios.post('/api/admin/products', body);
            }

            navigateWithFlash('/admin/products', productId ? 'Product updated.' : 'Product created.');
        } catch (requestError) {
            setError(requestError);
        } finally {
            setSubmitting(false);
        }
    }

    async function deleteImage(image) {
        try {
            await window.axios.delete(`/api/admin/products/${productId}/images/${image.id}`);
            setImages((current) => current.filter((item) => item.id !== image.id));
        } catch (requestError) {
            setError(requestError);
        }
    }

    if (loading) {
        return <p className="admin-muted" role="status">Loading product editor...</p>;
    }

    return (
        <>
            <AdminPageHeader eyebrow="Shared catalog" title={productId ? 'Edit product' : 'New product'} />
            {error && <ApiErrors error={error} />}
            <form className="admin-form" onSubmit={submit}>
                <section className="admin-form-section">
                    <h2>Product details</h2>
                    <div className="admin-form-grid">
                        <label className="admin-field">Name<input name="name" value={form.name} onChange={updateField} required /></label>
                        <label className="admin-field">Slug<input name="slug" value={form.slug} onChange={updateField} required /></label>
                        <label className="admin-field">SKU<input name="sku" value={form.sku} onChange={updateField} required /></label>
                        <label className="admin-field">Category<select name="category_id" value={form.category_id} onChange={updateField} required>
                            <option value="">Choose category</option>
                            {categories.map((category) => <option key={category.id} value={category.id}>{category.name}</option>)}
                        </select></label>
                        <label className="admin-field">Quantity<input name="quantity" type="number" min="0" value={form.quantity} onChange={updateField} required /></label>
                        <label className="admin-field admin-field-wide">Description<textarea name="description" rows="4" value={form.description || ''} onChange={updateField} /></label>
                    </div>
                    <label className="admin-check"><input type="checkbox" name="is_active" checked={Boolean(form.is_active)} onChange={updateField} /> Active product</label>
                </section>

                <section className="admin-form-section">
                    <div className="admin-section-heading"><div><p className="admin-eyebrow">Offer management</p><h2>Vendors and prices</h2></div><button className="admin-button" type="button" onClick={() => setOffers((current) => [...current, { ...emptyOffer }])}>Add offer</button></div>
                    {offers.map((offer, index) => (
                        <div className="admin-offer-row" key={`offer-${index}`}>
                            <label className="admin-field">Vendor<select value={offer.vendor_id} onChange={(event) => updateOffer(index, 'vendor_id', event.target.value)} required>
                                <option value="">Choose vendor</option>
                                {vendors.map((vendor) => <option key={vendor.id} value={vendor.id} disabled={offers.some((other, otherIndex) => otherIndex !== index && String(other.vendor_id) === String(vendor.id))}>{vendor.name}{vendor.is_active ? '' : ' (inactive)'}</option>)}
                            </select></label>
                            <label className="admin-field">Price<input type="number" min="0.01" step="0.01" value={offer.price} onChange={(event) => updateOffer(index, 'price', event.target.value)} required /></label>
                            <label className="admin-field">Vendor SKU<input value={offer.vendor_sku} onChange={(event) => updateOffer(index, 'vendor_sku', event.target.value)} /></label>
                            <label className="admin-check admin-offer-active"><input type="checkbox" checked={Boolean(offer.is_active)} onChange={(event) => updateOffer(index, 'is_active', event.target.checked)} /> Active</label>
                            <button className="admin-text-button" type="button" disabled={offers.length === 1} onClick={() => setOffers((current) => current.filter((_, offerIndex) => offerIndex !== index))}>Remove</button>
                        </div>
                    ))}
                </section>

                <section className="admin-form-section">
                    <h2>Product images</h2>
                    {images.length > 0 && <div className="admin-image-grid">{images.map((image) => <figure className="admin-image-item" key={image.id}><img src={image.url} alt="Product" /><figcaption>{image.is_primary ? 'Primary image' : 'Product image'}<button type="button" onClick={() => deleteImage(image)}>Remove</button></figcaption></figure>)}</div>}
                    {productId ? (
                        <p className="admin-muted">Adding images while editing is disabled. Images can be uploaded when creating the product.</p>
                    ) : (
                        <>
                            <label className="admin-field">Add images<input type="file" accept="image/jpeg,image/png,image/webp" multiple onChange={(event) => setSelectedImages(Array.from(event.target.files || []))} /></label>
                            {selectedImages.length > 0 && <p className="admin-muted">{selectedImages.map((image) => image.name).join(', ')}</p>}
                            <p className="admin-help">JPEG, PNG, or WebP. Maximum 2 MB per image, up to 10 per save.</p>
                        </>
                    )}
                </section>

                <div className="admin-form-actions">
                    <a className="admin-button" href="/admin/products">Cancel</a>
                    <button className="admin-button admin-button-primary" type="submit" disabled={submitting}>{submitting ? 'Saving...' : 'Save product'}</button>
                </div>
            </form>
        </>
    );
}