export function formatDate(value, { dateStyle = 'medium', timeStyle = 'short' } = {}) {
  const date = new Date(value);
  if (!value || !Number.isFinite(date.getTime())) return '—';
  return new Intl.DateTimeFormat('pt-BR', { dateStyle, timeStyle }).format(date);
}
