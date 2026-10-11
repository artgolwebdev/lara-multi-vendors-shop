import { useEffect, useState } from 'react';

const emptyProfile = { name: '', email: '', phone: '', address: '' };

function formatDiscount(discount) {
    const value = discount.discount_type === 'percentage'
        ? `${Number(discount.value)}%`
        : `$${Number(discount.value).toFixed(2)}`;

    return `${value} off your first order`;
}

export default function AccountPanel({ user, onUserChange, onLogout }) {
    const [profile, setProfile] = useState({ ...emptyProfile, name: user.name, email: user.email });
    const [password, setPassword] = useState({ current_password: '', password: '', password_confirmation: '' });
    const [discounts, setDiscounts] = useState(null);
    const [orders, setOrders] = useState(null);
    const [message, setMessage] = useState('');
    const [error, setError] = useState('');
    const [submitting, setSubmitting] = useState(false);

    useEffect(() => {
        window.axios.get('/api/account/profile')
            .then(({ data }) => {
                const account = data.data;
                setProfile({
                    name: account.name ?? '',
                    email: account.email ?? '',
                    phone: account.phone ?? '',
                    address: account.address ?? '',
                });
            })
            .catch(() => setError('We could not load your profile.'));

        window.axios.get('/api/account/discounts')
            .then(({ data }) => setDiscounts(data.data))
            .catch(() => setDiscounts([]));

        window.axios.get('/api/orders')
            .then(({ data }) => setOrders(data.data))
            .catch(() => setOrders([]));
    }, []);

    function updateProfileField(event) {
        setProfile((current) => ({ ...current, [event.target.name]: event.target.value }));
        setMessage('');
        setError('');
    }

    function updatePasswordField(event) {
        setPassword((current) => ({ ...current, [event.target.name]: event.target.value }));
        setMessage('');
        setError('');
    }

    async function saveProfile(event) {
        event.preventDefault();
        setSubmitting(true);
        setMessage('');
        setError('');

        try {
            const { data } = await window.axios.put('/api/account/profile', profile);
            setMessage('Profile updated.');
            onUserChange?.(data.data);
        } catch (requestError) {
            const errors = requestError.response?.data?.errors;
            setError(errors ? Object.values(errors).flat()[0] : 'We could not save your profile.');
        } finally {
            setSubmitting(false);
        }
    }

    async function savePassword(event) {
        event.preventDefault();
        setSubmitting(true);
        setMessage('');
        setError('');

        try {
            await window.axios.put('/api/account/password', password);
            setPassword({ current_password: '', password: '', password_confirmation: '' });
            setMessage('Password changed.');
        } catch (requestError) {
            const errors = requestError.response?.data?.errors;
            setError(errors ? Object.values(errors).flat()[0] : 'We could not change your password.');
        } finally {
            setSubmitting(false);
        }
    }

    return (
        <div className="account-panel">
            <div className="auth-panel-top">
                <span>YOUR ACCOUNT</span>
                <button className="account-logout" type="button" onClick={onLogout}>Sign out</button>
            </div>

            <div className="account-head">
                <p className="eyebrow">Signed in as</p>
                <h2>{user.name}</h2>
            </div>

            {message && <p className="form-success" role="status">{message}</p>}
            {error && <p className="form-error" role="alert">{error}</p>}

            <section className="account-section">
                <h3>My profile</h3>
                <form onSubmit={saveProfile} noValidate>
                    <label className="field-label">Name
                        <input name="name" value={profile.name} onChange={updateProfileField} required />
                    </label>
                    <label className="field-label">Email
                        <input name="email" type="email" value={profile.email} onChange={updateProfileField} required />
                    </label>
                    <label className="field-label">Phone
                        <input name="phone" value={profile.phone} onChange={updateProfileField} />
                    </label>
                    <label className="field-label">Address
                        <textarea name="address" rows="2" value={profile.address} onChange={updateProfileField} />
                    </label>
                    <button className="primary-button" type="submit" disabled={submitting}>Save profile</button>
                </form>
            </section>

            <section className="account-section">
                <h3>Change password</h3>
                <form onSubmit={savePassword} noValidate>
                    <label className="field-label">Current password
                        <input name="current_password" type="password" autoComplete="current-password" value={password.current_password} onChange={updatePasswordField} required />
                    </label>
                    <label className="field-label">New password
                        <input name="password" type="password" autoComplete="new-password" value={password.password} onChange={updatePasswordField} required />
                    </label>
                    <label className="field-label">Confirm new password
                        <input name="password_confirmation" type="password" autoComplete="new-password" value={password.password_confirmation} onChange={updatePasswordField} required />
                    </label>
                    <button className="primary-button" type="submit" disabled={submitting}>Change password</button>
                </form>
            </section>

            <section className="account-section">
                <h3>My discounts</h3>
                {discounts === null && <p className="account-empty">Loading your discounts...</p>}
                {discounts?.length === 0 && <p className="account-empty">You have no discounts right now.</p>}
                {discounts?.length > 0 && (
                    <ul className="account-discounts">
                        {discounts.map((discount) => (
                            <li key={discount.id}>
                                <strong>{discount.name}</strong>
                                <span>{discount.description || formatDiscount(discount)}</span>
                            </li>
                        ))}
                    </ul>
                )}
            </section>

            <section className="account-section">
                <h3>My orders</h3>
                {orders === null && <p className="account-empty">Loading your orders...</p>}
                {orders !== null && orders.length === 0 && (
                    <p className="account-empty">You haven’t placed any orders yet.</p>
                )}
                {orders?.length > 0 && (
                    <ul className="account-orders">
                        {orders.map((order) => (
                            <li key={order.id} className="account-order-item">
                                <div className="account-order-main">
                                    <strong>{order.order_number}</strong>
                                    <span>{order.placed_at ? new Date(order.placed_at).toLocaleDateString() : '—'} • {order.sub_orders_count ?? order.sub_orders?.length ?? 1} vendor{(order.sub_orders_count ?? order.sub_orders?.length ?? 1) === 1 ? '' : 's'}</span>
                                </div>
                                <span className={`store-status-pill status-${order.status}`}>{order.status}</span>
                                <strong>${order.total}</strong>
                                <a href={`/order-confirmation/${order.id}`} className="account-order-view gg-button gg-button--secondary gg-button--sm">
                                    View
                                </a>
                            </li>
                        ))}
                    </ul>
                )}
            </section>
        </div>
    );
}
