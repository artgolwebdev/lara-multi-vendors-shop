import { formatMoney } from '../store/formatMoney';

export default function Money({ value, currency = 'USD', className = '', ...props }) {
  return (
    <span className={`gg-money ${className}`.trim()} {...props}>
      {formatMoney(value, currency)}
    </span>
  );
}
