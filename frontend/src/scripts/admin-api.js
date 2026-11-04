import { apiRequest, ApiError } from './session.js';

export async function allRecords(path) {
  const records = [];
  let page = 1;
  let lastPage = 1;
  do {
    const response = await apiRequest(`${path}?per_page=100&page=${page}`);
    if (!Array.isArray(response?.data))
      throw new ApiError('A loja retornou uma resposta inesperada. Tente atualizar os dados.', 0);
    records.push(...response.data);
    lastPage = response.meta?.last_page ?? 1;
    if (!Number.isSafeInteger(lastPage) || lastPage < 1)
      throw new ApiError('Não foi possível confirmar a paginação dos dados.', 0);
    page += 1;
  } while (page <= lastPage);
  return records;
}

export async function loadAdminData() {
  const [products, categories, brands, orders] = await Promise.all([
    allRecords('/api/admin/products'),
    allRecords('/api/admin/categories'),
    allRecords('/api/admin/brands'),
    allRecords('/api/admin/orders'),
  ]);
  return { products, categories, brands, orders };
}
