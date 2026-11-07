import { apiRequest, resourceData } from './http.js';
import { cartAdjustmentMessage } from '../domain/api-messages.js';

export async function currentUser() {
  try {
    const user = resourceData(await apiRequest('/api/user'));
    return typeof user?.name === 'string' && typeof user?.email === 'string' ? user : null;
  } catch {
    return null;
  }
}

export async function submitSession(path, values) {
  const response = await apiRequest(path, { method: 'POST', data: values });
  if (Array.isArray(response?.cart_merge?.adjustments)) {
    response.cart_merge.adjustments = response.cart_merge.adjustments.map((adjustment) => ({
      ...adjustment,
      message: cartAdjustmentMessage(adjustment),
    }));
  }
  return response;
}
