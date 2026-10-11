import { useEffect, useState } from 'react';
import { AdminPageHeader, ApiErrors, navigateWithFlash, Pagination } from './shared';

const emptyRule = {
    name: '',
    description: '',
    type: 'quantity',
    discount_type: 'percentage',
    value: '',
    min_quantity: '',
    vendor_id: '',
    product_id: '',
    category_id: '',
    starts_at: '',
    ends_at: '',
    priority: 0,
    is_active: true,
};

const emptyTest = { product_id: '', vendor_id: '', quantity: 1 };

function money(value) {
    const amount = Number(value || 0);

    return `$${amount.toFixed(2)}`;
}

function formatValue(rule) {
    return rule.discount_type === 'percentage' ? `${Number(rule.value).toFixed(2)}%` : `${money(rule.value)} / unit`;
}

function appliesTo(rule) {
    if (rule.category_name) {
        return `Category: ${rule.category_name}`;
    }

    if (rule.product_name) {
        return `Product: ${rule.product_name}`;
    }

    if (rule.vendor_name) {
        return `Vendor: ${rule.vendor_name}`;
    }

    return 'All products';
}

function toDateTimeLocal(value) {
    if (!value) {
        return '';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    const pad = (number) => String(number).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

function RuleStatus({ status }) {
    return <span className={`admin-status admin-status-${status}`}>{status}</span>;
}

export default function DiscountRulePages({ path }) {
    const parts = path.split('/').filter(Boolean);
    const ruleId = parts[2] && parts[2] !== 'create' ? parts[2] : null;

    if (parts[2] === 'create' || parts[3] === 'edit') {
        return <DiscountRuleForm ruleId={ruleId} />;
    }

    return <DiscountRuleList />;
}

function DiscountRuleList() {
    const [rules, setRules] = useState(null);
    const [links, setLinks] = useState(null);
    const [error, setError] = useState(null);

    async function load(url = '/api/admin/discount-rules') {
        setError(null);

        try {
            const { data } = await window.axios.get(url);
            setRules(data.data);
            setLinks(data.links);
        } catch (requestError) {
            setError(requestError);
        }
    }

    useEffect(() => { load(); }, []);

    async function toggleStatus(rule) {
        try {
            await window.axios.patch(`/api/admin/discount-rules/${rule.id}/status`, { is_active: !rule.is_active });
            setRules((current) => current.map((item) => (
                item.id === rule.id ? { ...item, is_active: !item.is_active } : item
            )));
            load();
        } catch (requestError) {
            setError(requestError);
        }
    }

    async function removeRule(rule) {
        if (!window.confirm(`Delete discount rule ${rule.name}?`)) {
            return;
        }

        try {
            await window.axios.delete(`/api/admin/discount-rules/${rule.id}`);
            navigateWithFlash('/admin/discount-rules', 'Discount rule deleted.');
        } catch (requestError) {
            setError(requestError);
        }
    }

    return (
        <>
            <AdminPageHeader
                eyebrow="Promotions"
                title="Discount rules"
                action={<a className="admin-button admin-button-primary" href="/admin/discount-rules/create">Add discount rule</a>}
            />
            {error && <ApiErrors error={error} />}
            <section className="admin-table-wrap" aria-label="Discount rules">
                <table className="admin-table">
                    <thead><tr><th>Rule</th><th>Type</th><th>Value</th><th>Applies to</th><th>Status</th><th>Priority</th><th>Actions</th></tr></thead>
                    <tbody>
                        {rules?.map((rule) => (
                            <tr key={rule.id}>
                                <td><span className="admin-row-title">{rule.name}</span><span className="admin-table-secondary">{rule.description || '—'}</span></td>
                                <td>{rule.type}{rule.type === 'quantity' ? ` (min ${rule.min_quantity})` : ''}</td>
                                <td>{formatValue(rule)}</td>
                                <td>{appliesTo(rule)}</td>
                                <td><RuleStatus status={rule.status} /></td>
                                <td>{rule.priority}</td>
                                <td className="admin-row-actions">
                                    <button type="button" onClick={() => toggleStatus(rule)}>{rule.is_active ? 'Disable' : 'Enable'}</button>
                                    <a href={`/admin/discount-rules/${rule.id}/edit`}>Edit</a>
                                    <button type="button" onClick={() => removeRule(rule)}>Delete</button>
                                </td>
                            </tr>
                        ))}
                        {rules?.length === 0 && <tr><td colSpan="7" className="admin-empty">No discount rules yet.</td></tr>}
                        {!rules && <tr><td colSpan="7" className="admin-empty">Loading discount rules...</td></tr>}
                    </tbody>
                </table>
            </section>
            <Pagination links={links} onNavigate={load} />
            <DiscountTestPanel />
        </>
    );
}

function DiscountTestPanel() {
    const [options, setOptions] = useState(null);
    const [form, setForm] = useState(emptyTest);
    const [result, setResult] = useState(null);
    const [error, setError] = useState(null);
    const [submitting, setSubmitting] = useState(false);

    useEffect(() => {
        window.axios.get('/api/admin/discount-rules/options')
            .then(({ data }) => setOptions(data.data))
            .catch(setError);
    }, []);

    function updateField(event) {
        const { name, value } = event.target;
        setForm((current) => ({ ...current, [name]: value }));
        setError(null);
    }

    async function submit(event) {
        event.preventDefault();
        setSubmitting(true);
        setError(null);
        setResult(null);

        try {
            const { data } = await window.axios.post('/api/admin/discount-rules/test', form);
            setResult(data.data);
        } catch (requestError) {
            setError(requestError);
        } finally {
            setSubmitting(false);
        }
    }

    return (
        <section className="admin-form-section">
            <div className="admin-section-heading">
                <div><p className="admin-eyebrow">Sandbox</p><h2>Test a cart line</h2></div>
            </div>
            <p className="admin-help">Runs the live discount engine against the selected product's active offer. Nothing is saved.</p>
            {error && <ApiErrors error={error} />}
            <form className="admin-form-grid" onSubmit={submit}>
                <label className="admin-field">Product<select name="product_id" value={form.product_id} onChange={updateField} required>
                    <option value="">Choose product</option>
                    {options?.products.map((product) => <option key={product.id} value={product.id}>{product.name} ({product.sku})</option>)}
                </select></label>
                <label className="admin-field">Vendor<select name="vendor_id" value={form.vendor_id} onChange={updateField} required>
                    <option value="">Choose vendor</option>
                    {options?.vendors.map((vendor) => <option key={vendor.id} value={vendor.id}>{vendor.name}</option>)}
                </select></label>
                <label className="admin-field">Quantity<input name="quantity" type="number" min="1" value={form.quantity} onChange={updateField} required /></label>
                <div className="admin-form-actions">
                    <button className="admin-button admin-button-primary" type="submit" disabled={submitting}>{submitting ? 'Calculating...' : 'Run test'}</button>
                </div>
            </form>

            {result && (
                <div className="admin-test-result">
                    <table className="admin-table">
                        <thead><tr><th>Product</th><th>Qty</th><th>Unit</th><th>Subtotal</th><th>Discount</th><th>Total</th></tr></thead>
                        <tbody>
                            {result.lines.map((line) => (
                                <tr key={`${line.product_id}-${line.vendor_id}`}>
                                    <td>#{line.product_id}</td>
                                    <td>{line.quantity}</td>
                                    <td>{money(line.unit_price)}</td>
                                    <td>{money(line.line_subtotal)}</td>
                                    <td>{money(line.discount_amount)}</td>
                                    <td>{money(line.line_total)}</td>
                                </tr>
                            ))}
                        </tbody>
                        <tfoot>
                            <tr><th colSpan="3">Totals</th><th>{money(result.subtotal)}</th><th>{money(result.discount_total)}</th><th>{money(result.total)}</th></tr>
                        </tfoot>
                    </table>
                    {result.lines.some((line) => line.applied_rules.length > 0) && (
                        <ul className="admin-test-rules">
                            {result.lines.flatMap((line) => line.applied_rules.map((rule) => (
                                <li key={`${line.product_id}-${rule.rule_id}`}>Line #{line.product_id}: {rule.rule_name} − {money(rule.amount)}</li>
                            )))}
                        </ul>
                    )}
                </div>
            )}
        </section>
    );
}

function DiscountRuleForm({ ruleId }) {
    const [form, setForm] = useState(emptyRule);
    const [options, setOptions] = useState(null);
    const [error, setError] = useState(null);
    const [loading, setLoading] = useState(Boolean(ruleId));
    const [submitting, setSubmitting] = useState(false);

    useEffect(() => {
        const requests = [window.axios.get('/api/admin/discount-rules/options')];

        if (ruleId) {
            requests.push(window.axios.get(`/api/admin/discount-rules/${ruleId}`));
        }

        Promise.all(requests)
            .then(([optionResponse, ruleResponse]) => {
                setOptions(optionResponse.data.data);

                if (ruleResponse) {
                    const rule = ruleResponse.data.data;
                    setForm({
                        ...emptyRule,
                        ...rule,
                        value: String(rule.value),
                        min_quantity: rule.min_quantity ?? '',
                        vendor_id: rule.vendor_id ? String(rule.vendor_id) : '',
                        product_id: rule.product_id ? String(rule.product_id) : '',
                        category_id: rule.category_id ? String(rule.category_id) : '',
                        starts_at: toDateTimeLocal(rule.starts_at),
                        ends_at: toDateTimeLocal(rule.ends_at),
                    });
                }
            })
            .catch(setError)
            .finally(() => setLoading(false));
    }, [ruleId]);

    function updateField(event) {
        const { name, value, checked, type } = event.target;
        setForm((current) => ({ ...current, [name]: type === 'checkbox' ? checked : value }));
        setError(null);
    }

    async function submit(event) {
        event.preventDefault();
        setSubmitting(true);
        setError(null);

        const body = {
            name: form.name,
            description: form.description || null,
            type: form.type,
            discount_type: form.discount_type,
            value: form.value,
            min_quantity: form.type === 'quantity' ? form.min_quantity || null : null,
            vendor_id: form.vendor_id || null,
            product_id: form.type === 'quantity' ? form.product_id || null : null,
            category_id: form.type === 'category' ? form.category_id || null : null,
            starts_at: form.starts_at || null,
            ends_at: form.ends_at || null,
            priority: form.priority || 0,
            is_active: Boolean(form.is_active),
        };

        try {
            if (ruleId) {
                await window.axios.patch(`/api/admin/discount-rules/${ruleId}`, body);
            } else {
                await window.axios.post('/api/admin/discount-rules', body);
            }

            navigateWithFlash('/admin/discount-rules', ruleId ? 'Discount rule updated.' : 'Discount rule created.');
        } catch (requestError) {
            setError(requestError);
        } finally {
            setSubmitting(false);
        }
    }

    function summary() {
        const target = form.type === 'category'
            ? options?.categories.find((category) => String(category.id) === String(form.category_id))?.name
            : options?.products.find((product) => String(product.id) === String(form.product_id))?.name;
        const amount = form.discount_type === 'percentage' ? `${form.value || 0}%` : `${money(form.value)} per unit`;
        const qualifier = form.type === 'quantity' ? ` when buying ${form.min_quantity || 0}+ units` : '';
        const scope = target ? ` on ${target}` : ' across all products';

        return `${amount} off${qualifier}${scope}.`;
    }

    if (loading) {
        return <p className="admin-muted" role="status">Loading discount rule...</p>;
    }

    return (
        <>
            <AdminPageHeader eyebrow="Promotions" title={ruleId ? 'Edit discount rule' : 'New discount rule'} />
            {error && <ApiErrors error={error} />}
            <form className="admin-form" onSubmit={submit}>
                <section className="admin-form-section">
                    <h2>Rule details</h2>
                    <div className="admin-form-grid">
                        <label className="admin-field">Name<input name="name" value={form.name} onChange={updateField} required /></label>
                        <label className="admin-field">Type<select name="type" value={form.type} onChange={updateField} required>
                            {(options?.types || ['quantity', 'category']).map((type) => <option key={type} value={type}>{type}</option>)}
                        </select></label>
                        <label className="admin-field">Discount type<select name="discount_type" value={form.discount_type} onChange={updateField} required>
                            {(options?.discount_types || ['percentage', 'fixed']).map((type) => <option key={type} value={type}>{type}</option>)}
                        </select></label>
                        <label className="admin-field">{form.discount_type === 'percentage' ? 'Percentage (1-100)' : 'Amount per unit'}<input name="value" type="number" min="0.01" max={form.discount_type === 'percentage' ? '100' : undefined} step="0.01" value={form.value} onChange={updateField} required /></label>
                        {form.type === 'quantity' && (
                            <label className="admin-field">Minimum quantity<input name="min_quantity" type="number" min="1" value={form.min_quantity} onChange={updateField} required /></label>
                        )}
                        {form.type === 'quantity' && (
                            <label className="admin-field">Product (optional)<select name="product_id" value={form.product_id} onChange={updateField}>
                                <option value="">All products</option>
                                {options?.products.map((product) => <option key={product.id} value={product.id}>{product.name} ({product.sku})</option>)}
                            </select></label>
                        )}
                        {form.type === 'category' && (
                            <label className="admin-field">Category<select name="category_id" value={form.category_id} onChange={updateField} required>
                                <option value="">Choose category</option>
                                {options?.categories.map((category) => <option key={category.id} value={category.id}>{category.name}</option>)}
                            </select></label>
                        )}
                        <label className="admin-field">Vendor (optional)<select name="vendor_id" value={form.vendor_id} onChange={updateField}>
                            <option value="">All vendors</option>
                            {options?.vendors.map((vendor) => <option key={vendor.id} value={vendor.id}>{vendor.name}</option>)}
                        </select></label>
                        <label className="admin-field">Priority<input name="priority" type="number" min="0" value={form.priority} onChange={updateField} /></label>
                        <label className="admin-field">Starts at<input name="starts_at" type="datetime-local" value={form.starts_at} onChange={updateField} /></label>
                        <label className="admin-field">Ends at<input name="ends_at" type="datetime-local" value={form.ends_at} onChange={updateField} /></label>
                        <label className="admin-field admin-field-wide">Description<textarea name="description" rows="3" value={form.description || ''} onChange={updateField} /></label>
                    </div>
                    <label className="admin-check"><input type="checkbox" name="is_active" checked={Boolean(form.is_active)} onChange={updateField} /> Active rule</label>
                    <p className="admin-help">{summary()}</p>
                </section>

                <div className="admin-form-actions">
                    <a className="admin-button" href="/admin/discount-rules">Cancel</a>
                    <button className="admin-button admin-button-primary" type="submit" disabled={submitting}>{submitting ? 'Saving...' : 'Save discount rule'}</button>
                </div>
            </form>
        </>
    );
}
