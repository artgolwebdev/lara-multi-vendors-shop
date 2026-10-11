import { useEffect, useState } from 'react';

const navigation = [
    { href: '/admin', label: 'Overview', key: 'dashboard' },
    { href: '/admin/vendors', label: 'Vendors', key: 'vendors' },
    { href: '/admin/categories', label: 'Categories', key: 'categories' },
    { href: '/admin/discount-rules', label: 'Discounts', key: 'discount-rules' },
    { href: '/admin/products', label: 'Products', key: 'products' },
    { href: '/admin/customers', label: 'Customers', key: 'customers' },
];

export default function AdminLayout({ user, children }) {
    const [flash, setFlash] = useState('');
    const section = window.location.pathname.split('/')[2] || 'dashboard';

    useEffect(() => {
        const message = window.sessionStorage.getItem('adminFlash');

        if (message) {
            setFlash(message);
            window.sessionStorage.removeItem('adminFlash');
        }
    }, []);

    async function logout() {
        await window.axios.post('/api/logout');
        window.location.assign('/');
    }

    return (
        <div className="admin-shell">
            <aside className="admin-sidebar">
                <a className="admin-wordmark" href="/">goodgoods<span>.</span></a>
                <p className="admin-sidebar-label">Workspace</p>
                <nav className="admin-nav" aria-label="Admin navigation">
                    {navigation.map((item) => (
                        <a key={item.key} className={section === item.key ? 'is-current' : ''} href={item.href}>
                            <span aria-hidden="true" className="admin-nav-mark">{item.key === 'dashboard' ? 'O' : item.label[0]}</span>
                            {item.label}
                        </a>
                    ))}
                </nav>
                <div className="admin-sidebar-footer">
                    <span>Catalog control</span>
                    <span>GOODGOODS / 01</span>
                </div>
            </aside>
            <section className="admin-main">
                <header className="admin-topbar">
                    <div className="admin-breadcrumb">Goodgoods <span>/</span> Admin</div>
                    <div className="admin-account">
                        <span>{user.name}</span>
                        <button type="button" onClick={logout}>Sign out</button>
                    </div>
                </header>
                <main className="admin-content">
                    {flash && <div className="admin-alert admin-alert-success" role="status">{flash}</div>}
                    {children}
                </main>
            </section>
        </div>
    );
}