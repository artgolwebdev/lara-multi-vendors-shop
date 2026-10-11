export function ApiErrors({ error }) {
    const errors = error?.response?.data?.errors;
    const messages = errors ? Object.values(errors).flat() : [error?.response?.data?.message || 'The request could not be completed.'];

    return (
        <div className="admin-alert admin-alert-error gg-alert gg-alert--danger" role="alert">
            {messages.map((message, index) => (
                <p key={`${message}-${index}`}>{message}</p>
            ))}
        </div>
    );
}

export function AdminPageHeader({ eyebrow, title, action, actions }) {
    const rightActions = action || actions;
    return (
        <header className="admin-page-header gg-page-header">
            <div>
                {eyebrow && <p className="admin-eyebrow gg-page-header__eyebrow">{eyebrow}</p>}
                <h1>{title}</h1>
            </div>
            {rightActions && <div className="admin-page-header-actions gg-page-header__actions">{rightActions}</div>}
        </header>
    );
}

export function Pagination({ links, onNavigate }) {
    if (!links?.prev && !links?.next) {
        return null;
    }

    return (
        <nav className="admin-pagination" aria-label="Pagination">
            <button
                type="button"
                className="gg-button gg-button--secondary gg-button--sm"
                disabled={!links.prev}
                onClick={() => onNavigate(links.prev)}
            >
                Previous
            </button>
            <button
                type="button"
                className="gg-button gg-button--secondary gg-button--sm"
                disabled={!links.next}
                onClick={() => onNavigate(links.next)}
            >
                Next
            </button>
        </nav>
    );
}

export function navigateWithFlash(path, message) {
    window.sessionStorage.setItem('adminFlash', message);
    window.location.assign(path);
}

export function StatusBadge({ active, className = '' }) {
    return <span className={`admin-status ${active ? 'is-active' : 'is-inactive'} ${className}`.trim()}>{active ? 'Active' : 'Inactive'}</span>;
}

export function AdminAlert({ type = 'info', children }) {
    const typeClass =
        type === 'danger' || type === 'error'
            ? 'admin-alert-error'
            : type === 'success'
            ? 'admin-alert-success'
            : '';
    const ggType = type === 'error' ? 'danger' : type;
    return (
        <div
            className={`admin-alert ${typeClass} gg-alert gg-alert--${ggType}`}
            role={type === 'danger' || type === 'error' ? 'alert' : 'status'}
        >
            {children}
        </div>
    );
}
