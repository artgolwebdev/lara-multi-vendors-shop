import { useEffect, useState } from 'react';
import AccountPanel from './account/AccountPanel';
import { Alert, Button, Card, Field, Input, Textarea } from './ui';

const emptyForm = { name: '', email: '', password: '', password_confirmation: '', phone: '', address: '', remember: false };

function initialMode() {
    const path = window.location.pathname;
    const requestedMode = new URLSearchParams(window.location.search).get('mode');

    if (path === '/register') return 'register';
    if (path === '/login') return 'login';

    return requestedMode === 'register' ? 'register' : 'login';
}

function safeRedirect(target) {
    if (!target) return null;

    try {
        const destination = new URL(target, window.location.origin);

        return destination.origin === window.location.origin ? destination.href : null;
    } catch {
        return null;
    }
}

export default function AuthApp() {
    const [user, setUser] = useState(null);
    const [mode, setMode] = useState(initialMode);
    const [form, setForm] = useState(emptyForm);
    const [loading, setLoading] = useState(true);
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState('');
    const [fieldErrors, setFieldErrors] = useState({});
    const [showPassword, setShowPassword] = useState(false);

    useEffect(() => {
        window.axios.get('/api/user')
            .then(({ data }) => setUser(data.data))
            .catch(() => setUser(null))
            .finally(() => setLoading(false));
    }, []);

    function updateField(event) {
        const { name, type, checked, value } = event.target;
        setForm((current) => ({ ...current, [name]: type === 'checkbox' ? checked : value }));
        setFieldErrors((current) => ({ ...current, [name]: undefined }));
        setError('');
    }

    function changeMode(nextMode) {
        setMode(nextMode);
        setError('');
        setFieldErrors({});

        if (window.location.pathname === '/login' || window.location.pathname === '/register') {
            const url = new URL(window.location.href);
            url.pathname = nextMode === 'register' ? '/register' : '/login';
            window.history.replaceState({}, '', url);
        } else {
            const url = new URL(window.location.href);
            url.searchParams.set('mode', nextMode);
            window.history.replaceState({}, '', url);
        }
    }

    async function submit(event) {
        event.preventDefault();
        setSubmitting(true);
        setError('');
        setFieldErrors({});

        try {
            await window.axios.get('/sanctum/csrf-cookie');
            const endpoint = mode === 'register' ? '/api/register' : '/api/login';
            const payload = mode === 'register'
                ? form
                : { email: form.email, password: form.password, remember: form.remember };
            const { data } = await window.axios.post(endpoint, payload);

            setForm(emptyForm);

            if (redirectAfterAuth()) return;

            setUser(data.data);
        } catch (requestError) {
            const errors = requestError.response?.data?.errors;
            setFieldErrors(errors || {});
            setError(errors ? '' : requestError.response?.data?.message || 'Something went wrong. Please try again.');
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
        const params = new URLSearchParams(window.location.search);
        const root = document.getElementById('auth-app');
        const destination = safeRedirect(params.get('redirect'))
            || safeRedirect(root?.dataset.intendedUrl);

        if (destination) {
            window.location.assign(destination);
            return true;
        }

        return false;
    }

    const isRegistering = mode === 'register';

    return (
        <main className="auth-shell">
            <section className="auth-story" aria-label="Marketplace introduction">
                <a className="wordmark" href="/">goodgoods<span>.</span></a>
                <div className="story-copy">
                    <p className="eyebrow">A marketplace for makers</p>
                    <h1>Small shops.<br />Good things.</h1>
                    <p className="story-description">Meet independent makers, compare transparent prices from their shops, and find your next favorite thing.</p>
                    <ul className="story-highlights">
                        <li>Thoughtful finds from independent shops</li>
                        <li>Clear pricing from every seller</li>
                        <li>First-order offers on selected finds</li>
                    </ul>
                </div>
                <div className="story-footer"><span>Made for the curious</span><span className="footer-mark">GG / 01</span></div>
                <div className="story-shape" aria-hidden="true"><span>G</span></div>
            </section>

            <section className="auth-panel">
                <div className="auth-panel-top"><a href="/" className="auth-back-link">← Back to store</a><span>YOUR NEXT FAVORITE THING</span></div>

                {loading ? (
                    <div className="auth-status" role="status">Checking your account<span className="loading-dots">...</span></div>
                ) : user ? (
                    <AccountPanel user={user} onUserChange={setUser} onLogout={logout} />
                ) : (
                    <Card className="auth-card" bodyClassName="auth-card__body">
                        <div className="auth-form-wrap">
                            <p className="eyebrow">{isRegistering ? 'Join the marketplace' : 'Good to see you again'}</p>
                            <h2>{isRegistering ? <>Make room<br />for good.</> : <>Welcome<br />back.</>}</h2>
                            <p className="form-intro">{isRegistering ? 'Create an account to discover independent shops.' : 'Sign in to pick up where you left off.'}</p>

                            <div className="auth-tabs" role="tablist" aria-label="Account access">
                                <button type="button" role="tab" aria-selected={!isRegistering} onClick={() => changeMode('login')}>Sign in</button>
                                <button type="button" role="tab" aria-selected={isRegistering} onClick={() => changeMode('register')}>Create account</button>
                            </div>

                            {error && <Alert type="danger" className="auth-alert">{error}</Alert>}
                            <form onSubmit={submit} noValidate className="auth-form">
                                {isRegistering && <Field label="Your name" htmlFor="auth-name" error={fieldErrors.name?.[0]}><Input id="auth-name" name="name" autoComplete="name" value={form.name} onChange={updateField} error={fieldErrors.name?.[0]} required /></Field>}
                                <Field label="Email address" htmlFor="auth-email" error={fieldErrors.email?.[0]}><Input id="auth-email" name="email" type="email" autoComplete="email" value={form.email} onChange={updateField} error={fieldErrors.email?.[0]} required /></Field>
                                <Field label="Password" htmlFor="auth-password" error={fieldErrors.password?.[0]}>
                                    <div className="auth-password-control"><Input id="auth-password" name="password" type={showPassword ? 'text' : 'password'} autoComplete={isRegistering ? 'new-password' : 'current-password'} value={form.password} onChange={updateField} error={fieldErrors.password?.[0]} required /><button className="password-toggle" type="button" onClick={() => setShowPassword((visible) => !visible)} aria-label={showPassword ? 'Hide password' : 'Show password'}>{showPassword ? 'Hide' : 'Show'}</button></div>
                                </Field>
                                {isRegistering && <Field label="Confirm password" htmlFor="auth-password-confirmation" error={fieldErrors.password_confirmation?.[0]}><Input id="auth-password-confirmation" name="password_confirmation" type={showPassword ? 'text' : 'password'} autoComplete="new-password" value={form.password_confirmation} onChange={updateField} error={fieldErrors.password_confirmation?.[0]} required /></Field>}
                                {isRegistering && <Field label="Phone (optional)" htmlFor="auth-phone" error={fieldErrors.phone?.[0]}><Input id="auth-phone" name="phone" autoComplete="tel" value={form.phone} onChange={updateField} error={fieldErrors.phone?.[0]} /></Field>}
                                {isRegistering && <Field label="Address (optional)" htmlFor="auth-address" error={fieldErrors.address?.[0]}><Textarea id="auth-address" name="address" rows="2" autoComplete="street-address" value={form.address} onChange={updateField} error={fieldErrors.address?.[0]} /></Field>}
                                {!isRegistering && <div className="auth-options"><label className="remember-option"><input name="remember" type="checkbox" checked={form.remember} onChange={updateField} /> Remember me</label><a href="/password/reset">Forgot your password?</a></div>}
                                <Button type="submit" loading={submitting} className="auth-submit">{submitting ? 'Please wait...' : isRegistering ? 'Create your account' : 'Sign in'}<span aria-hidden="true">↗</span></Button>
                            </form>
                            <p className="terms-note">By continuing, you agree to shop thoughtfully.</p>
                        </div>
                    </Card>
                )}

                <div className="panel-bottom"><span>Independent by nature.</span><span>© GOODGOODS 2026</span></div>
            </section>
        </main>
    );
}
