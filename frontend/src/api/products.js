import { apiRequest, ApiError, resourceData } from './http.js';
import { productQuery } from '../domain/products.js';

export async function listProducts(criteria = {}, { signal } = {}) {
  const response = await apiRequest(`/api/products/filter?${productQuery(criteria)}`, { signal });

  if (!Array.isArray(response?.data) || !Number.isInteger(response.meta?.total)) {
    throw new ApiError('Não foi possível confirmar a lista de produtos.');
  }

  return { products: response.data, pagination: response.meta };
}

export async function getProduct(id, { signal } = {}) {
  if (!/^\d+$/.test(String(id))) {
    throw new ApiError('Produto não encontrado.', 404);
  }

  return resourceData(await apiRequest(`/api/products/${encodeURIComponent(id)}`, { signal }));
}
