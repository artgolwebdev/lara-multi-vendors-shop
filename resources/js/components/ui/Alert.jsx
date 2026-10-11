export default function Alert({ children, type = 'info', className = '', ...props }) {
  return (
    <div
      className={`gg-alert gg-alert--${type} ${className}`.trim()}
      role={type === 'danger' ? 'alert' : 'status'}
      {...props}
    >
      {children}
    </div>
  );
}
