import './shared.js';
import { catalog, filterDefinitions, matchingProducts } from '../data/catalog.js';

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
const priceFormatter = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });

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
  resultsContainer.replaceChildren();
  for (const item of items) {
    const article = document.createElement('article');
    article.className = 'product-card';
    const card = document.createElement('div');
    card.className = 'product-card-inner';
    const artwork = document.createElement('div');
    artwork.className = 'product-image';
    if (item.image) {
      const image = document.createElement('img');
      image.src = `assets/${item.image}`;
      image.alt = '';
      artwork.append(image);
    } else {
      const icon = document.createElement('i');
      icon.className = `bi ${item.icon} catalog-icon`;
      icon.setAttribute('aria-hidden', 'true');
      artwork.append(icon);
    }
    const name = document.createElement('p');
    name.className = 'product-name';
    name.textContent = item.name;
    name.title = item.name;
    const price = document.createElement('p');
    price.className = 'product-price';
    const priceLabel = document.createElement('span');
    priceLabel.className = 'product-price-label';
    priceLabel.textContent = 'A partir de';
    const priceAmount = document.createElement('span');
    priceAmount.className = 'product-price-value';
    priceAmount.textContent = priceFormatter.format(item.price);
    price.append(priceLabel, priceAmount);
    card.append(artwork, name, price);
    article.append(card);
    resultsContainer.append(article);
  }
}

function applyFilters() {
  const minPrice = priceValue('min');
  const maxPrice = priceValue('max');
  const selected = currentSelections();
  const invalidRange = minPrice !== null && maxPrice !== null && minPrice > maxPrice;
  const results = invalidRange ? [] : matchingProducts(catalog, currentCategory, minPrice, maxPrice, selected);
  renderSearchCards(results);
  summary.textContent = `${results.length} ${results.length === 1 ? 'produto encontrado' : 'produtos encontrados'}`;
  emptyMessage.hidden = results.length > 0;
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

filterForm.addEventListener('input', applyFilters);
filterForm.addEventListener('change', applyFilters);
filterForm.addEventListener('submit', event => event.preventDefault());
clearButton.addEventListener('click', () => {
  filterForm.reset();
  applyFilters();
});
categoryButtons.forEach(button => {
  button.addEventListener('click', () => selectCategory(button.dataset.category));
});

selectCategory(currentCategory);
