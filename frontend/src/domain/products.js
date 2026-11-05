const currency = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });

export const SHIPPING_TOTAL = 30;

export function money(value) {
  return currency.format(Number(value ?? 0));
}

export function productUrl(product) {
  return `/produto?id=${encodeURIComponent(product.id)}`;
}

export function categoryUrl(category) {
  return `/pesquisa?categoria=${encodeURIComponent(category.id)}`;
}

export function productFilter(criteria = {}) {
  const filter = {};
  if (criteria.search?.trim()) filter.q = criteria.search.trim();
  if (criteria.categoryId) filter.category_id = Number(criteria.categoryId);
  if (criteria.brandIds?.length) filter.brand_ids = criteria.brandIds.map(Number);
  if (criteria.minPrice !== '' && criteria.minPrice != null) filter.min_price = criteria.minPrice;
  if (criteria.maxPrice !== '' && criteria.maxPrice != null) filter.max_price = criteria.maxPrice;

  const specifications = Object.fromEntries(
    Object.entries(criteria.specifications || {}).filter(([, values]) => values.length > 0),
  );

  if (Object.keys(specifications).length) filter.specifications = specifications;
  return filter;
}

export function productQuery(criteria = {}) {
  const params = new URLSearchParams({
    page: String(criteria.page || 1),
    per_page: String(criteria.perPage || 24),
    filter: JSON.stringify(productFilter(criteria)),
  });

  return params.toString();
}

export function imageUrl(value) {
  if (!value || typeof value !== 'string') return '';

  try {
    const base = import.meta.env?.VITE_API_URL || globalThis.location?.origin;
    const url = new URL(value, base);
    return ['http:', 'https:'].includes(url.protocol) ? url.href : '';
  } catch {
    return '';
  }
}

export function specificationLabel(value, definition = {}) {
  if (typeof value === 'boolean') return value ? 'Sim' : 'Não';
  return `${value}${definition.unit ? ` ${definition.unit}` : ''}`;
}

export function productSpecifications(product, category) {
  const definitions = category?.attributes || {};

  return Object.entries(product.specifications || {}).map(([key, value]) => ({
    key,
    label: definitions[key]?.name || key.replaceAll('_', ' '),
    value: specificationLabel(value, definitions[key]),
  }));
}

export function paginationPages(current, last) {
  return [...new Set([1, last, current - 1, current, current + 1])]
    .filter((page) => page >= 1 && page <= last)
    .sort((first, second) => first - second)
    .flatMap((page, index, pages) =>
      index > 0 && page - pages[index - 1] > 1 ? ['ellipsis-' + page, page] : [page],
    );
}
