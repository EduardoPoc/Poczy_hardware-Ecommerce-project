import { apiRequest, resourceData } from './http.js';

export async function getCart() {
  return resourceData(await apiRequest('/api/cart'));
}

export async function addCartItem(productId, quantity) {
  return resourceData(
    await apiRequest('/api/cart/items', {
      method: 'POST',
      data: { product_id: Number(productId), quantity },
    }),
  );
}

export async function updateCartItem(productId, quantity) {
  return resourceData(
    await apiRequest(`/api/cart/items/${encodeURIComponent(productId)}`, {
      method: 'PATCH',
      data: { quantity },
    }),
  );
}

export async function removeCartItem(productId) {
  return resourceData(
    await apiRequest(`/api/cart/items/${encodeURIComponent(productId)}`, { method: 'DELETE' }),
  );
}
