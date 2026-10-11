import { useEffect, useState } from 'react';
import AccountPanel from './account/AccountPanel';

const emptyForm = { name: '', email: '', password: '', password_confirmation: '', phone: '', address: '' };

function initialMode() {
    const params = new URLSearchParams(window.location.search);

    return params.get('mode') === 'register' ? 'register' : 'login';
}

export default function AuthApp() {
    const [user, setUser] = useState(null);
    const [mode, setMode] = useState(initialMode);
    const [form, setForm] = useState(emptyForm);
    const [loading, setLoading] = useState(true);
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState('');

    useEffect(() => {
        window.axios.get('/api/user')
            .then(({ data }) => setUser(data.data))
            .catch(() => setUser(null))
            .finally(() => setLoading(false));
    }, []);

    function updateField(event) {
        setForm((current) => ({ ...current, [event.target.name]: event.target.value }));
        setError('');
    }

    async function submit(event) {
        event.preventDefault();
        setSubmitting(true);
        setError('');

        try {
            await window.axios.get('/sanctum/csrf-cookie');
            const endpoint = mode === 'register' ? '/api/register' : '/api/login';
            const payload = mode === 'register'
                ? form
                : { email: form.email, password: form.password };
            const { data } = await window.axios.post(endpoint, payload);

            setForm(emptyForm);

            if (redirectAfterAuth()) {
                return;
            }

            setUser(data.data);
        } catch (requestError) {
            const errors = requestError.response?.data?.errors;
            setError(errors ? Object.values(errors).flat()[0] : requestError.response?.data?.message || 'Something went wrong. Please try again.');
        } finally {
            setSubmitting(false);
        }
    }

    async function logout() {
        setSubmitting(true);
        setError('');

        try {
            await window.axios.post('/api/logout');
            setUser(null);
            setMode('login');
        } catch {
            setError('We could not sign you out. Please try again.');
        } finally {
            setSubmitting(false);
        }
    }

    function redirectAfterAuth() {
        const redirect = new URLSearchParams(window.location.search).get('redirect');

        if (redirect && redirect.startsWith('/')) {
            window.location.assign(redirect);

            return true;
        }

        return false;
    }

    return (
        <main className="auth-shell">
            <section className="auth-story" aria-label="Marketplace introduction">
                <a className="wordmark" href="/">goodgoods<span>.</span></a>
                <div className="story-copy">
                    <p className="eyebrow">A marketplace for makers</p>
                    <h1>Small shops.<br />Good things.</h1>
                    <p className="story-description">Find thoughtful goods from independent sellers, all in one place.</p>
                </div>
                <div className="story-footer">
                    <span>Made for the curious</span>
                    <span className="footer-mark">GG / 01</span>
                </div>
                <div className="story-shape" aria-hidden="true"><span>G</span></div>
            </section>

            <section className="auth-panel">
                <div className="auth-panel-top">
                    <span>YOUR NEXT FAVORITE THING</span>
                    <span>01 — 03</span>
                </div>

                {loading ? (
                    <div className="auth-status" role="status">Checking your account<span className="loading-dots">...</span></div>
                ) : user ? (
                    <AccountPanel user={user} onUserChange={setUser} onLogout={logout} />
                ) : (
                    <div className="auth-form-wrap">
                        <p className="eyebrow">{mode === 'register' ? 'Join the marketplace' : 'Good to see you again'}</p>
                        <h2>{mode === 'register' ? <>Make room<br />for good.</> : <>Welcome<br />back.</>}</h2>
                        <p className="form-intro">{mode === 'register' ? 'Create an account to discover independent shops.' : 'Sign in to pick up where you left off.'}</p>

                        <div className="auth-tabs" role="tablist" aria-label="Account access">
                            <button type="button" role="tab" aria-selected={mode === 'login'} onClick={() => { setMode('login'); setError(''); }}>Sign in</button>
                            <button type="button" role="tab" aria-selected={mode === 'register'} onClick={() => { setMode('register'); setError(''); }}>Create account</button>
                        </div>

                        <form onSubmit={submit} noValidate>
                            {mode === 'register' && (
                                <label className="field-label">
                                    Your name
                                    <input name="name" autoComplete="name" value={form.name} onChange={updateField} required />
                                </label>
                            )}
                            <label className="field-label">
                                Email address
                                <input name="email" type="email" autoComplete="email" value={form.email} onChange={updateField} required />
                            </label>
                            <label className="field-label">
                                Password
                                <input name="password" type="password" autoComplete={mode === 'register' ? 'new-password' : 'current-password'} value={form.password} onChange={updateField} required />
                            </label>
                            {mode === 'register' && (
                                <label className="field-label">
                                    Confirm password
                                    <input name="password_confirmation" type="password" autoComplete="new-password" value={form.password_confirmation} onChange={updateField} required />
                                </label>
                            )}
                            {mode === 'register' && (
                                <label className="field-label">
                                    Phone (optional)
                                    <input name="phone" autoComplete="tel" value={form.phone} onChange={updateField} />
                                </label>
                            )}
                            {mode === 'register' && (
                                <label className="field-label">
                                    Address (optional)
                                    <textarea name="address" rows="2" autoComplete="street-address" value={form.address} onChange={updateField} />
                                </label>
                            )}
                            {error && <p className="form-error" role="alert">{error}</p>}
                            <button className="primary-button" type="submit" disabled={submitting}>
                                {submitting ? 'Please wait...' : mode === 'register' ? 'Create your account' : 'Sign in'}
                                <span aria-hidden="true">↗</span>
                            </button>
                        </form>
                        <p className="terms-note">By continuing, you agree to shop thoughtfully.</p>
                    </div>
                )}

                <div className="panel-bottom"><span>Independent by nature.</span><span>© GOODGOODS 2026</span></div>
            </section>
        </main>
    );
}