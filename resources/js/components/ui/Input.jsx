import { forwardRef } from 'react';

const Input = forwardRef(function Input(
  { className = '', error, ...props },
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
    <input
      ref={ref}
      className={`gg-input ${className}`.trim()}
      aria-invalid={error ? 'true' : undefined}
      aria-describedby={describedBy.length ? describedBy.join(' ') : undefined}
      {...props}
    />
  );
});

export default Input;
