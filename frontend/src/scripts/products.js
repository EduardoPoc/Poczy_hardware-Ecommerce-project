export const money = value => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value);
export const productUrl = product => `produto.html?id=${encodeURIComponent(product.id)}`;
const images = {
  'notebook.png': new URL('../../assets/notebook.png', import.meta.url).href,
  'cpu-icon.svg': new URL('../../assets/cpu-icon.svg', import.meta.url).href,
  'gpu-icon.svg': new URL('../../assets/gpu-icon.svg', import.meta.url).href
};
export function artwork(product) {
  return product.image
    ? `<img src="${images[product.image]}" alt="${product.name}" class="${product.image === 'notebook.png' ? 'catalog-product-photo' : ''}" />`
    : `<i class="bi ${product.icon} catalog-icon" aria-hidden="true"></i>`;
}
export function productCard(product) {
  return `<article class="product-card"><a class="product-card-inner flex-column h-100 overflow-hidden card position-static" href="${productUrl(product)}">
    <div class="product-image flex-shrink-0 d-grid">${artwork(product)}</div>
    <p class="product-name overflow-hidden fw-semibold">${product.name}</p>
    <p class="product-price fw-bold"><span class="product-price-label d-block fw-normal">Por</span><span class="product-price-value">${money(product.price)}</span></p>
  </a></article>`;
}
