import { apiRequest, ApiError, resourceData } from '../../shared/api/http.js';

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
