import { Link } from 'react-router-dom';

export default function EmptyState({ title, description, actionLabel, actionTo }) {
    return (
        <div className="store-state store-state-empty" role="status">
            <p className="store-eyebrow">Nothing here yet</p>
            <h2>{title}</h2>
            {description && <p className="store-state-text">{description}</p>}
            {actionLabel && actionTo && <Link className="store-button" to={actionTo}>{actionLabel}</Link>}
        </div>
    );
}
