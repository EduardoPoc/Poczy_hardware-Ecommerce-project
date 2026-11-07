import { escapeHtml } from '../../shared/lib/html.js';
import { formatDate } from '../../shared/lib/dates.js';
import { orderStatuses } from '../orders/domain.js';

export function dateLabel(value) {
  return formatDate(value, { dateStyle: 'short' });
}

export function badge(status) {
  return /* HTML */ `<span
    class="admin-badge ${escapeHtml(String(status).toLowerCase())} d-inline-flex align-items-center fw-semibold text-nowrap badge"
    ><span></span>${escapeHtml(orderStatuses[status] || status)}</span
  >`;
}
