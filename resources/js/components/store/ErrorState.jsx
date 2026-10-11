export default function ErrorState({ message = 'We could not load this page.', onRetry }) {
    return (
        <div className="store-state store-state-error" role="alert">
            <p className="store-eyebrow">Something went wrong</p>
            <h2>{message}</h2>
            {onRetry && (
                <button className="store-button" type="button" onClick={onRetry}>Try again</button>
            )}
        </div>
    );
}
