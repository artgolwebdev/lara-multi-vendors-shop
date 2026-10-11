export function ApiErrors({ error }) {
    const errors = error?.response?.data?.errors;
    const messages = errors ? Object.values(errors).flat() : [error?.response?.data?.message || 'The request could not be completed.'];

    return (
        <div className="admin-alert admin-alert-error" role="alert">
            {messages.map((message, index) => <p key={`${message}-${index}`}>{message}</p>)}
        </div>
    );
}

export function AdminPageHeader({ eyebrow, title, action }) {
    return (
        <header className="admin-page-header">
            <div>
                <p className="admin-eyebrow">{eyebrow}</p>
                <h1>{title}</h1>
            </div>
            {action}
        </header>
    );
}

export function Pagination({ links, onNavigate }) {
    if (!links?.prev && !links?.next) {
        return null;
    }

    return (
        <nav className="admin-pagination" aria-label="Pagination">
            <button type="button" disabled={!links.prev} onClick={() => onNavigate(links.prev)}>Previous</button>
            <button type="button" disabled={!links.next} onClick={() => onNavigate(links.next)}>Next</button>
        </nav>
    );
}

export function navigateWithFlash(path, message) {
    window.sessionStorage.setItem('adminFlash', message);
    window.location.assign(path);
}

export function StatusBadge({ active }) {
    return <span className={`admin-status ${active ? 'is-active' : 'is-inactive'}`}>{active ? 'Active' : 'Inactive'}</span>;
}