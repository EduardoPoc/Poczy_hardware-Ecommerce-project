import { apiRequest, ApiError, resourceData } from './http.js';

export async function getProfile() {
  const user = resourceData(await apiRequest('/api/user'));
  if (!user?.id || typeof user.name !== 'string')
    throw new ApiError('Não foi possível carregar sua conta.');
  return user;
}

export async function updateProfile(data) {
  return resourceData(await apiRequest('/api/user', { method: 'PATCH', data }));
}

export function changePassword(data) {
  return apiRequest('/api/user/password', { method: 'PUT', data });
}

export async function listAddresses() {
  const addresses = resourceData(await apiRequest('/api/addresses'));
  if (!Array.isArray(addresses)) throw new ApiError('Não foi possível carregar seus endereços.');
  return addresses;
}

export async function saveAddress(address, id = null) {
  return resourceData(
    await apiRequest(id ? `/api/addresses/${encodeURIComponent(id)}` : '/api/addresses', {
      method: id ? 'PATCH' : 'POST',
      data: address,
    }),
  );
}

export function deleteAddress(id) {
  return apiRequest(`/api/addresses/${encodeURIComponent(id)}`, { method: 'DELETE' });
}

export async function listOrders(page = 1) {
  const response = await apiRequest(`/api/orders?page=${page}&per_page=10`);
  if (!Array.isArray(response?.data) || !response.meta)
    throw new ApiError('Não foi possível carregar seus pedidos.');
  return response;
}

export async function getOrder(id) {
  if (!/^\d+$/.test(String(id))) throw new ApiError('Pedido não encontrado.', 404);
  return resourceData(await apiRequest(`/api/orders/${encodeURIComponent(id)}`));
}

export async function completeCheckout(attempt) {
  return resourceData(await apiRequest('/api/checkout', { method: 'POST', data: attempt }));
}

export async function payOrder(id) {
  return resourceData(await apiRequest(`/api/orders/${encodeURIComponent(id)}/pay`, { method: 'POST' }));
}

export async function cancelOrder(id) {
  await apiRequest(`/api/orders/${encodeURIComponent(id)}`, { method: 'DELETE' });
  return getOrder(id);
}
