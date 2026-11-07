import { apiRequest, resourceData } from '../../shared/api/http.js';

export async function completeCheckout(attempt) {
  return resourceData(await apiRequest('/api/checkout', { method: 'POST', data: attempt }));
}
