import { forwardRef } from 'react';

const Select = forwardRef(function Select(
  { className = '', error, children, ...props },
  ref,
) {
  const describedBy = [];
  if (props['aria-describedby']) {
    describedBy.push(props['aria-describedby']);
  }
  if (error && props.id) {
    describedBy.push(`${props.id}-error`);
  }
  if (error && props.name && !props.id) {
    describedBy.push(`${props.name}-error`);
  }

  return (
    <select
      ref={ref}
      className={`gg-select ${className}`.trim()}
      aria-invalid={error ? 'true' : undefined}
      aria-describedby={describedBy.length ? describedBy.join(' ') : undefined}
      {...props}
    >
      {children}
    </select>
  );
});

export default Select;
