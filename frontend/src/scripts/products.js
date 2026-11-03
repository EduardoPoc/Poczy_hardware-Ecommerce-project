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
  return `<article class="product-card"><a class="product-card-inner" href="${productUrl(product)}">
    <div class="product-image">${artwork(product)}</div>
    <p class="product-name">${product.name}</p>
    <p class="product-price"><span class="product-price-label">Por</span><span class="product-price-value">${money(product.price)}</span></p>
  </a></article>`;
}
