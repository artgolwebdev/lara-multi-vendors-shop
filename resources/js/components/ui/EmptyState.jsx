import Button from './Button';

export default function EmptyState({
  title = 'Nothing here yet',
  text,
  actionLabel,
  onAction,
  className = '',
}) {
  return (
    <div className={`gg-empty ${className}`.trim()}>
      {title && <h2 className="gg-empty__title">{title}</h2>}
      {text && <p className="gg-empty__text">{text}</p>}
      {actionLabel && onAction && (
        <Button type="button" onClick={onAction}>
          {actionLabel}
        </Button>
      )}
    </div>
  );
}
