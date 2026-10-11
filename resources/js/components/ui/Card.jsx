export default function Card({ children, className = '', bodyClassName = '', ...props }) {
  return (
    <section className={`gg-card ${className}`.trim()} {...props}>
      <div className={`gg-card__body ${bodyClassName}`.trim()}>{children}</div>
    </section>
  );
}
