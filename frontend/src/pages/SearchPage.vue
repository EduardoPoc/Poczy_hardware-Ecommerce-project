<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import SiteHeader from '../components/SiteHeader.vue';
import ProductCard from '../components/ProductCard.vue';
import ProductFilters from '../components/ProductFilters.vue';
import ProductPagination from '../components/ProductPagination.vue';
import RequestState from '../components/RequestState.vue';
import { listCategories, listBrands, getCategoryFilters } from '../api/categories.js';
import { errorMessage } from '../api/http.js';
import { productSortOptions } from '../domain/products.js';
import { useProductSearch } from '../composables/useProductSearch.js';

const params = new URLSearchParams(location.search);
const initialCategory = Number(params.get('categoria')) || '';
const { criteria, products, pagination, loading, error, reload } = useProductSearch({
  search: params.get('q') || '',
  sort: params.get('sort') || 'name',
  categoryId: initialCategory,
  page: Math.max(1, Number(params.get('page')) || 1),
});
const categories = ref([]);
const categoryError = ref('');
const options = ref({ brands: [], attributes: {} });
const filterLoading = ref(false);
const filterError = ref('');
const categoryOpen = ref(false);
const filterOpen = ref(false);
const results = ref(null);
const categoryToggle = ref(null);
const title = computed(() =>
  criteria.search
    ? `Resultados para “${criteria.search}”`
    : categories.value.find((category) => category.id === Number(criteria.categoryId))?.name ||
      'Resultados da pesquisa',
);
let filterController;

async function loadCategories() {
  categoryError.value = '';
  try {
    categories.value = await listCategories();
  } catch (failure) {
    categoryError.value = errorMessage(failure);
  }
}

async function loadFilters() {
  filterController?.abort();
  const request = new AbortController();
  filterController = request;
  filterLoading.value = true;
  filterError.value = '';
  options.value = { brands: [], attributes: {} };
  try {
    const response = criteria.categoryId
      ? await getCategoryFilters(criteria.categoryId, { signal: request.signal })
      : { brands: await listBrands(), attributes: {} };
    if (!request.signal.aborted) options.value = response;
  } catch (failure) {
    if (!request.signal.aborted) filterError.value = errorMessage(failure);
  } finally {
    if (!request.signal.aborted) filterLoading.value = false;
  }
}

function clearFilters() {
  Object.assign(criteria, {
    minPrice: '',
    maxPrice: '',
    brandIds: [],
    specifications: {},
    page: 1,
  });
}

function selectCategory(id) {
  clearFilters();
  criteria.categoryId = id;
  categoryOpen.value = false;
  if (window.matchMedia('(max-width: 1199.98px)').matches) categoryToggle.value?.focus();
}

function changeFilters(values) {
  Object.assign(criteria, values, { page: 1 });
}

function changePage(page) {
  if (page === criteria.page || loading.value) return;
  criteria.page = page;
  document.getElementById('search-title')?.focus({ preventScroll: true });
  results.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function resizePage() {
  if (!results.value) return;
  const columns =
    Number.parseInt(getComputedStyle(results.value).getPropertyValue('--search-columns'), 10) || 1;
  const perPage = columns * 6;
  if (criteria.perPage === perPage) return;
  const firstResult = (criteria.page - 1) * criteria.perPage;
  criteria.perPage = perPage;
  criteria.page = Math.floor(firstResult / perPage) + 1;
}

function closePanels(event) {
  if (event.key === 'Escape') {
    categoryOpen.value = false;
    filterOpen.value = false;
  }
}

watch(() => criteria.categoryId, loadFilters, { immediate: true });
watch(
  () => [criteria.categoryId, criteria.page, criteria.sort],
  () => {
    const url = new URL(location.href);
    if (criteria.sort !== 'name') url.searchParams.set('sort', criteria.sort);
    else url.searchParams.delete('sort');
    if (criteria.categoryId) url.searchParams.set('categoria', criteria.categoryId);
    else url.searchParams.delete('categoria');
    if (criteria.page > 1) url.searchParams.set('page', criteria.page);
    else url.searchParams.delete('page');
    history.replaceState(null, '', url);
  },
);
onMounted(() => {
  loadCategories();
  resizePage();
  window.addEventListener('resize', resizePage);
  document.addEventListener('keydown', closePanels);
});
onUnmounted(() => {
  filterController?.abort();
  window.removeEventListener('resize', resizePage);
  document.removeEventListener('keydown', closePanels);
});
</script>

<template>
  <main class="page-shell search-page">
    <div class="search-page-layout">
      <div class="search-page-main">
        <div class="search-header">
          <SiteHeader :search-term="criteria.search" />
          <div class="mobile-search-controls">
            <button
              id="category-toggle"
              ref="categoryToggle"
              type="button"
              :aria-expanded="categoryOpen"
              aria-controls="category-options"
              @click="categoryOpen = !categoryOpen"
            >
              <i class="bi bi-grid" aria-hidden="true"></i> Categorias
              <i class="bi bi-chevron-down toggle-chevron" aria-hidden="true"></i>
            </button>
            <button
              id="filter-toggle"
              type="button"
              :aria-expanded="filterOpen"
              aria-controls="filter-options"
              @click="filterOpen = !filterOpen"
            >
              <i class="bi bi-sliders" aria-hidden="true"></i> Filtros
              <i class="bi bi-chevron-down toggle-chevron" aria-hidden="true"></i>
            </button>
          </div>
          <nav
            id="category-options"
            class="category-filters flex-wrap"
            :class="{ 'is-open': categoryOpen }"
            aria-label="Filtrar por categoria"
          >
            <button
              type="button"
              :class="{ active: !criteria.categoryId }"
              :aria-pressed="!criteria.categoryId"
              @click="selectCategory('')"
            >
              Todos
            </button>
            <button
              v-for="category in categories"
              :key="category.id"
              type="button"
              :class="{ active: category.id === Number(criteria.categoryId) }"
              :aria-pressed="category.id === Number(criteria.categoryId)"
              @click="selectCategory(category.id)"
            >
              {{ category.name }}
            </button>
          </nav>
          <RequestState :error="categoryError" @retry="loadCategories" />
        </div>
        <section id="results" class="search-results" aria-labelledby="search-title">
          <nav class="search-breadcrumb d-flex" aria-label="Você está em">
            <a href="/">Início</a><span aria-hidden="true">/</span><span>Pesquisa</span>
          </nav>
          <h1 id="search-title" tabindex="-1">{{ title }}</h1>
          <label class="search-sort">
            Ordenar por
            <select
              class="form-select"
              :value="criteria.sort"
              @change="changeFilters({ sort: $event.target.value })"
            >
              <option
                v-for="option in productSortOptions"
                :key="option.value"
                :value="option.value"
              >
                {{ option.label }}
              </option>
            </select>
          </label>
          <p class="search-summary" role="status" aria-live="polite">
            {{
              loading
                ? 'Buscando produtos…'
                : error
                  ? 'Não foi possível concluir a busca.'
                  : `${pagination.total} ${pagination.total === 1 ? 'produto encontrado' : 'produtos encontrados'}`
            }}
          </p>
          <div class="search-layout d-grid">
            <aside
              id="filter-options"
              class="filter-sidebar"
              :class="{ 'is-open': filterOpen }"
              aria-labelledby="filter-title"
            >
              <ProductFilters
                :model-value="criteria"
                :options="options"
                :disabled="filterLoading"
                @change="changeFilters"
                @clear="clearFilters"
              />
              <RequestState :loading="filterLoading" :error="filterError" @retry="loadFilters" />
            </aside>
            <div class="search-results-main" :aria-busy="loading">
              <RequestState :loading="loading" :error="error" @retry="reload" />
              <div id="search-products" ref="results" class="row product-row catalog-row">
                <ProductCard v-for="product in products" :key="product.id" :product="product" />
              </div>
              <ProductPagination
                v-if="!error"
                :pagination="pagination"
                :disabled="loading"
                @change="changePage"
              />
              <p v-if="!loading && !error && !products.length" class="search-empty text-center">
                Nenhum produto corresponde aos filtros selecionados.
              </p>
            </div>
          </div>
        </section>
      </div>
    </div>
  </main>
</template>
