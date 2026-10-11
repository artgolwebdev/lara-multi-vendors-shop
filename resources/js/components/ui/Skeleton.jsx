export default function Skeleton({ className = '', size = 'base', circle = false }) {
  const sizeClass = size === 'lg' ? 'gg-skeleton--lg' : '';
  const circleClass = circle ? 'gg-skeleton--circle' : '';
  const classes = ['gg-skeleton', sizeClass, circleClass, className]
    .filter(Boolean)
    .join(' ');
  return <span className={classes} aria-hidden="true" />;
}
