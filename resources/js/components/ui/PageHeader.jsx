export default function PageHeader({
  eyebrow,
  title,
  actions,
  className = '',
  ...props
}) {
  return (
    <header className={`gg-page-header ${className}`.trim()} {...props}>
      <div>
        {eyebrow && <p className="gg-page-header__eyebrow">{eyebrow}</p>}
        <h1 className="gg-page-header__title">{title}</h1>
      </div>
      {actions && <div className="gg-page-header__actions">{actions}</div>}
    </header>
  );
}
