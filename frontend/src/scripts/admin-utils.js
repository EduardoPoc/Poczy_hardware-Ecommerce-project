export const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character]);
export const statusLabels = { PENDING: 'Aguardando pagamento', PAID: 'Pago', SHIPPED: 'Enviado', DELIVERED: 'Entregue', CANCELLED: 'Cancelado' };
export const orderTransitions = { PENDING: ['PAID', 'CANCELLED'], PAID: ['SHIPPED'], SHIPPED: ['DELIVERED'], DELIVERED: [], CANCELLED: [] };
export const movementLabels = { INBOUND: 'Entrada', OUTBOUND: 'Saída', ADJUSTMENT: 'Ajuste' };
export function dateLabel(value) {
  const date = new Date(value);
  return value && Number.isFinite(date.getTime()) ? new Intl.DateTimeFormat('pt-BR', { dateStyle: 'short', timeStyle: 'short' }).format(date) : '—';
}
export function cents(value = '0') {
  const match = String(value ?? '0').match(/^(-?)(\d+)(?:\.(\d{1,2}))?$/);
  if (!match) throw new Error('Valor monetário inválido.');
  return (BigInt(match[2]) * 100n + BigInt((match[3] || '').padEnd(2, '0'))) * (match[1] ? -1n : 1n);
}
export function moneyFromCents(value) {
  const absolute = value < 0n ? -value : value;
  return `${value < 0n ? '-' : ''}R$ ${(absolute / 100n).toLocaleString('pt-BR')},${String(absolute % 100n).padStart(2, '0')}`;
}
export const money = value => moneyFromCents(cents(value));
export const badge = status => `<span class="admin-badge ${escapeHtml(String(status).toLowerCase())} d-inline-flex align-items-center fw-semibold text-nowrap badge"><span></span>${escapeHtml(statusLabels[status] || status)}</span>`;
export function imageUrl(value) {
  try { const url = new URL(value); return ['http:', 'https:'].includes(url.protocol) ? url.href : ''; }
  catch { return ''; }
}
export const objectValues = value => value && typeof value === 'object' && !Array.isArray(value) ? value : {};
