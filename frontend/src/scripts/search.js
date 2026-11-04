import './shared.js';
import { catalog, filterDefinitions, matchingProducts } from '../data/catalog.js';
import { productCard } from './products.js';

const searchParams = new URLSearchParams(window.location.search);
const searchTerm = searchParams.get('q') || '';
const categoryButtons = Array.from(document.querySelectorAll('[data-category]'));
const validCategories = categoryButtons.map(button => button.dataset.category);
let currentCategory = validCategories.includes(searchParams.get('categoria'))
  ? searchParams.get('categoria')
  : 'all';

const filterForm = document.getElementById('product-filters');
const resultsContainer = document.getElementById('search-products');
const summary = document.querySelector('.search-summary');
const emptyMessage = document.querySelector('.search-empty');
const clearButton = document.getElementById('clear-filters');
const categoryToggle = document.getElementById('category-toggle');
const filterToggle = document.getElementById('filter-toggle');
const categoryOptions = document.getElementById('category-options');
const filterOptions = document.getElementById('filter-options');
const pagination = document.getElementById('results-pagination');
const paginationControls = pagination.querySelector('.pagination-controls');
const priceFormatter = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
const rowsPerPage = 6;
function cardsPerPage() {
  const columns = Number.parseInt(getComputedStyle(resultsContainer).getPropertyValue('--search-columns'), 10);
  return rowsPerPage * (columns || 1);
}
let pageSize = cardsPerPage();
let currentPage = 1;
let filteredResults = [];

document.getElementById('product-search').value = searchTerm;

function availableOptions(category, key) {
  return [...new Set(catalog.filter(item => item.category === category).map(item => item[key]).filter(Boolean))]
    .sort((a, b) => a.localeCompare(b, 'pt-BR', { numeric: true }));
}

function buildFilters(category) {
  filterForm.replaceChildren();
  const priceGroup = document.createElement('fieldset');
  priceGroup.className = 'filter-group';
  priceGroup.innerHTML = `
    <legend>Preço</legend>
    <div class="price-fields">
      <label for="price-min">Mínimo (R$)<input id="price-min" name="min" type="number" min="0" step="0.01" inputmode="decimal" placeholder="0,00"></label>
      <label for="price-max">Máximo (R$)<input id="price-max" name="max" type="number" min="0" step="0.01" inputmode="decimal" placeholder="Sem limite"></label>
    </div>`;
  filterForm.append(priceGroup);

  for (const [key, title] of filterDefinitions[category]) {
    const options = availableOptions(category, key);
    if (!options.length) continue;
    const group = document.createElement('fieldset');
    group.className = 'filter-group';
    const legend = document.createElement('legend');
    legend.textContent = title;
    group.append(legend);
    for (const [index, option] of options.entries()) {
      const label = document.createElement('label');
      label.className = 'filter-option';
      const checkbox = document.createElement('input');
      checkbox.type = 'checkbox';
      checkbox.name = key;
      checkbox.value = option;
      checkbox.id = `filter-${key}-${index}`;
      label.htmlFor = checkbox.id;
      label.append(checkbox, document.createTextNode(option));
      group.append(label);
    }
    filterForm.append(group);
  }
}

function currentSelections() {
  const selected = {};
  filterForm.querySelectorAll('input[type="checkbox"]:checked').forEach(input => {
    (selected[input.name] ||= []).push(input.value);
  });
  return selected;
}

function priceValue(name) {
  const value = filterForm.elements[name].value;
  return value === '' ? null : Number(value);
}

function renderSearchCards(items) {
  resultsContainer.innerHTML = items.map(productCard).join('');
}

function renderPagination() {
  const totalPages = Math.ceil(filteredResults.length / pageSize);
  pagination.hidden = totalPages === 0;
  paginationControls.replaceChildren();
  if (totalPages === 0) return;

  pagination.querySelector('.pagination-status').textContent = `Página ${currentPage} de ${totalPages}`;
  const addPageButton = (label, page, current = false, disabled = false) => {
    const button = document.createElement('button');
    button.type = 'button';
    button.textContent = label;
    button.dataset.page = String(page);
    button.disabled = disabled;
    if (current) button.setAttribute('aria-current', 'page');
    else button.setAttribute('aria-label', typeof label === 'number' ? `Ir para a página ${page}` : label);
    paginationControls.append(button);
  };

  addPageButton('Anterior', currentPage - 1, false, currentPage === 1);
  const pages = [...new Set([1, totalPages, currentPage - 1, currentPage, currentPage + 1])]
    .filter(page => page >= 1 && page <= totalPages)
    .sort((a, b) => a - b);
  let previousPage = 0;
  for (const page of pages) {
    if (page - previousPage > 1) {
      const ellipsis = document.createElement('span');
      ellipsis.textContent = '…';
      ellipsis.className = 'pagination-ellipsis';
      ellipsis.setAttribute('aria-hidden', 'true');
      paginationControls.append(ellipsis);
    }
    addPageButton(page, page, page === currentPage);
    previousPage = page;
  }
  addPageButton('Próxima', currentPage + 1, false, currentPage === totalPages);
}

function renderCurrentPage() {
  const start = (currentPage - 1) * pageSize;
  renderSearchCards(filteredResults.slice(start, start + pageSize));
  renderPagination();
}

function goToPage(page) {
  const totalPages = Math.ceil(filteredResults.length / pageSize);
  if (!Number.isInteger(page) || page < 1 || page > totalPages || page === currentPage) return;
  currentPage = page;
  renderCurrentPage();
  resultsContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
  paginationControls.querySelector('[aria-current="page"]')?.focus({ preventScroll: true });
}

function applyFilters() {
  const minPrice = priceValue('min');
  const maxPrice = priceValue('max');
  const selected = currentSelections();
  const invalidRange = minPrice !== null && maxPrice !== null && minPrice > maxPrice;
  filteredResults = invalidRange ? [] : matchingProducts(catalog, currentCategory, minPrice, maxPrice, selected, searchTerm);
  currentPage = 1;
  renderCurrentPage();
  summary.textContent = `${filteredResults.length} ${filteredResults.length === 1 ? 'produto encontrado' : 'produtos encontrados'}`;
  emptyMessage.hidden = filteredResults.length > 0;
  emptyMessage.textContent = invalidRange
    ? 'O preço mínimo não pode ser maior que o preço máximo.'
    : 'Nenhum produto corresponde aos filtros selecionados.';
  clearButton.disabled = !(minPrice !== null || maxPrice !== null || Object.keys(selected).length);
}

function selectCategory(category) {
  currentCategory = category;
  buildFilters(category);
  const selectedButton = categoryButtons.find(button => button.dataset.category === category);
  document.getElementById('search-title').textContent = searchTerm
    ? `Resultados para “${searchTerm}”`
    : category === 'all' ? 'Todos os produtos' : selectedButton.textContent;
  categoryButtons.forEach(button => {
    const active = button === selectedButton;
    button.classList.toggle('active', active);
    button.setAttribute('aria-pressed', String(active));
  });
  applyFilters();
}

function setPanelOpen(button, panel, open) {
  button.setAttribute('aria-expanded', String(open));
  panel.classList.toggle('is-open', open);
}

categoryToggle.addEventListener('click', () => {
  const open = categoryToggle.getAttribute('aria-expanded') !== 'true';
  setPanelOpen(categoryToggle, categoryOptions, open);
  if (open) setPanelOpen(filterToggle, filterOptions, false);
});
filterToggle.addEventListener('click', () => {
  const open = filterToggle.getAttribute('aria-expanded') !== 'true';
  setPanelOpen(filterToggle, filterOptions, open);
  if (open) setPanelOpen(categoryToggle, categoryOptions, false);
});
document.addEventListener('keydown', event => {
  if (event.key !== 'Escape') return;
  setPanelOpen(categoryToggle, categoryOptions, false);
  setPanelOpen(filterToggle, filterOptions, false);
});

filterForm.addEventListener('input', applyFilters);
filterForm.addEventListener('change', applyFilters);
filterForm.addEventListener('submit', event => event.preventDefault());
paginationControls.addEventListener('click', event => {
  const button = event.target.closest('button[data-page]');
  if (button) goToPage(Number(button.dataset.page));
});
window.addEventListener('resize', () => {
  const nextPageSize = cardsPerPage();
  if (nextPageSize === pageSize) return;
  const firstVisibleProduct = (currentPage - 1) * pageSize;
  pageSize = nextPageSize;
  currentPage = Math.floor(firstVisibleProduct / pageSize) + 1;
  renderCurrentPage();
});
clearButton.addEventListener('click', () => {
  filterForm.reset();
  applyFilters();
});
categoryButtons.forEach(button => {
  button.addEventListener('click', () => {
    selectCategory(button.dataset.category);
    setPanelOpen(categoryToggle, categoryOptions, false);
    if (window.matchMedia('(max-width: 1199.98px)').matches) categoryToggle.focus();
  });
});

selectCategory(currentCategory);
