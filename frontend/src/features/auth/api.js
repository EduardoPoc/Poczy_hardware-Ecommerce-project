import { apiRequest, resourceData } from '../../shared/api/http.js';
import { cartAdjustmentMessage } from '../../shared/api/messages.js';
import { forgetGuestCartToken, guestCartToken } from '../../shared/session/guest-cart.js';

export async function currentUser() {
  try {
    const user = resourceData(await apiRequest('/api/user'));
    return typeof user?.name === 'string' && typeof user?.email === 'string' ? user : null;
  } catch {
    return null;
  }
}

export async function submitSession(path, values) {
  let response;
  try {
    response = await apiRequest(path, { method: 'POST', data: values });
  } catch (failure) {
    if (failure.status !== 404 || !guestCartToken() || !['/api/login', '/api/user'].includes(path))
      throw failure;
    forgetGuestCartToken();
    response = await apiRequest(path, { method: 'POST', data: values });
  }
  if (response?.cart_merge) forgetGuestCartToken();
  if (Array.isArray(response?.cart_merge?.adjustments)) {
    response.cart_merge.adjustments = response.cart_merge.adjustments.map((adjustment) => ({
      ...adjustment,
      message: cartAdjustmentMessage(adjustment),
    }));
  }
  return response;
}

export function login(values) {
  return submitSession('/api/login', values);
}

export function register(values) {
  return submitSession('/api/user', values);
}

export function logout() {
  return submitSession('/api/logout', {});
}
