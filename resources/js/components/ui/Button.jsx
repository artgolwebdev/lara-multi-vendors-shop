export default function Button({
  children,
  variant = 'primary',
  size = 'md',
  type = 'button',
  disabled = false,
  loading = false,
  as,
  href,
  className = '',
  ...props
}) {
  const classes = ['gg-button', `gg-button--${variant}`]
    .concat(size === 'sm' ? ['gg-button--sm'] : [])
    .concat(className ? className.split(/\s+/) : [])
    .filter(Boolean)
    .join(' ');

  if (as === 'a' || href) {
    const Comp = as || 'a';
    return (
      <Comp
        href={href}
        className={classes}
        aria-disabled={disabled || loading}
        {...props}
      >
        {children}
      </Comp>
    );
  }

  return (
    <button
      type={type}
      className={classes}
      disabled={disabled || loading}
      {...props}
    >
      {children}
    </button>
  );
}
