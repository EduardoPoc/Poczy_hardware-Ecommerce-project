import { onScopeDispose, reactive, ref, watch } from 'vue';
import { listProducts } from '../api.js';
import { errorMessage } from '../../../shared/api/errors.js';

const SEARCH_DEBOUNCE_MS = 200;

/** Agrupa critérios e resultados da página e cancela buscas substituídas por novos filtros. */
export function useProductSearch(initial = {}) {
  const criteria = reactive({
    search: '',
    sort: 'name',
    categoryId: '',
    brandIds: [],
    minPrice: '',
    maxPrice: '',
    specifications: {},
    page: 1,
    perPage: 24,
    ...initial,
  });
  const products = ref([]);
  const pagination = ref({ current_page: 1, last_page: 1, total: 0 });
  const loading = ref(false);
  const error = ref('');
  let controller;
  let timer;
  let revision = 0;

  async function fetchProducts(version) {
    const request = new AbortController();
    controller = request;

    try {
      const response = await listProducts(criteria, { signal: request.signal });
      // O cancelamento pode chegar após a resposta; a revisão também impede resultados antigos.
      if (version !== revision) return;
      products.value = response.products;
      pagination.value = response.pagination;
    } catch (failure) {
      if (version !== revision || request.signal.aborted) return;
      products.value = [];
      error.value = errorMessage(failure);
    } finally {
      if (version === revision) loading.value = false;
    }
  }

  function reload() {
    clearTimeout(timer);
    controller?.abort();
    const version = ++revision;
    loading.value = true;
    error.value = '';
    timer = setTimeout(() => fetchProducts(version), SEARCH_DEBOUNCE_MS);
  }

  watch(criteria, reload, { deep: true, immediate: true });
  onScopeDispose(() => {
    clearTimeout(timer);
    controller?.abort();
  });

  return { criteria, products, pagination, loading, error, reload };
}
