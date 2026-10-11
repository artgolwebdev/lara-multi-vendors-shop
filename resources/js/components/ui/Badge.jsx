export default function Badge({ children, className = '', ...props }) {
  return (
    <span className={`gg-badge ${className}`.trim()} {...props}>
      {children}
    </span>
  );
}
