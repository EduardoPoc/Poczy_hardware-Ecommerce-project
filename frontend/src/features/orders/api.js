import { apiRequest, ApiError, resourceData } from '../../shared/api/http.js';

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

export async function payOrder(id) {
  return resourceData(
    await apiRequest(`/api/orders/${encodeURIComponent(id)}/pay`, { method: 'POST' }),
  );
}

export async function cancelOrder(id) {
  await apiRequest(`/api/orders/${encodeURIComponent(id)}`, { method: 'DELETE' });
  return getOrder(id);
}
