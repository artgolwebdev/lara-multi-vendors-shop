import { forwardRef } from 'react';

const Textarea = forwardRef(function Textarea(
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
    <textarea
      ref={ref}
      className={`gg-textarea ${className}`.trim()}
      aria-invalid={error ? 'true' : undefined}
      aria-describedby={describedBy.length ? describedBy.join(' ') : undefined}
      {...props}
    />
  );
});

export default Textarea;
