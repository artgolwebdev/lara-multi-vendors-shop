export default function Steps({ steps = [], current = 0, className = '' }) {
  return (
    <nav className={`gg-steps ${className}`.trim()} aria-label="Checkout steps">
      {steps.map((step, idx) => {
        const isActive = idx === current;
        const isComplete = idx < current;
        const classes = [
          'gg-step',
          isActive ? 'is-active' : '',
          isComplete ? 'is-complete' : '',
        ]
          .filter(Boolean)
          .join(' ');

        return (
          <div key={step.key || idx} className={classes}>
            <span className="gg-step__dot">{isComplete ? '✓' : idx + 1}</span>
            <span>{step.label}</span>
            {idx < steps.length - 1 && <span className="gg-step__sep">→</span>}
          </div>
        );
      })}
    </nav>
  );
}
