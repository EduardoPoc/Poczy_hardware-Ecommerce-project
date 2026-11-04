import { apiRequest, ApiError, resourceData } from './http.js';

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
