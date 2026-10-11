export default function Table({
  columns = [],
  data = [],
  renderRow,
  emptyText = 'No records found.',
  responsive = true,
  className = '',
  wrapperClassName = '',
  ...props
}) {
  const wrapperClasses = [
    'gg-table-wrapper',
    responsive ? 'gg-table--responsive' : '',
    wrapperClassName,
  ]
    .filter(Boolean)
    .join(' ');

  const tableClasses = ['gg-table', className].filter(Boolean).join(' ');

  return (
    <div className={wrapperClasses} {...props}>
      <table className={tableClasses}>
        {columns.length > 0 && (
          <thead>
            <tr>
              {columns.map((col) => (
                <th key={col.key} style={col.style}>{col.label}</th>
              ))}
            </tr>
          </thead>
        )}
        <tbody>
          {data.length === 0 && (
            <tr>
              <td colSpan={columns.length || 1} style={{ textAlign: 'center', color: 'var(--c-muted)' }}>
                {emptyText}
              </td>
            </tr>
          )}
          {data.length > 0 &&
            data.map((row, idx) =>
              renderRow ? (
                renderRow(row, idx)
              ) : (
                <tr key={row.id ?? idx}>
                  {columns.map((col) => (
                    <td key={col.key} data-label={col.label}>
                      {row[col.key]}
                    </td>
                  ))}
                </tr>
              ),
            )}
        </tbody>
      </table>
    </div>
  );
}
