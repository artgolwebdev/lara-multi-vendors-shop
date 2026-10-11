export default function Field({
  label,
  hint,
  error,
  htmlFor,
  id,
  children,
  className = '',
}) {
  const fieldId = id || htmlFor;
  const hintId = hint && fieldId ? `${fieldId}-hint` : undefined;
  const errorId = error && fieldId ? `${fieldId}-error` : undefined;

  return (
    <div className={`gg-field ${className}`.trim()}>
      {label && (
        <label htmlFor={htmlFor || fieldId} className="gg-label">
          {label}
        </label>
      )}
      {children}
      {hint && (
        <p id={hintId} className="gg-hint">
          {hint}
        </p>
      )}
      {error && (
        <p id={errorId} className="gg-error" role="alert">
          {error}
        </p>
      )}
    </div>
  );
}
