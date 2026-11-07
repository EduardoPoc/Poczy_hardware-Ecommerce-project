import { apiRequest, ApiError, resourceData } from '../../shared/api/http.js';
import { productQuery } from './domain.js';

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

export async function listCategories() {
  const categories = resourceData(await apiRequest('/api/categories'));
  if (!Array.isArray(categories)) throw new ApiError('Não foi possível carregar as categorias.');
  return categories;
}

export async function getCategoryFilters(id, { signal } = {}) {
  return resourceData(
    await apiRequest(`/api/categories/${encodeURIComponent(id)}/filters`, { signal }),
  );
}

export async function listBrands() {
  const brands = resourceData(await apiRequest('/api/brands'));
  if (!Array.isArray(brands)) throw new ApiError('Não foi possível carregar as marcas.');
  return brands;
}
