import './shared.js';
import { catalog, filterDefinitions } from '../data/catalog.js';
import { artwork, money, productCard } from './products.js';
import { addCartItem } from './cart-store.js';

const categories = {
  notebooks: 'Notebooks', pcs: 'PCs montados', monitors: 'Monitores', peripherals: 'Periféricos',
  processors: 'Processadores', memory: 'Memórias RAM', graphics: 'Placas de vídeo',
  motherboards: 'Placas-mãe', storage: 'SSDs e HDs', 'power-supplies': 'Fontes', cases: 'Gabinetes', cooling: 'Refrigeração'
};
const product = catalog.find(item => item.id === new URLSearchParams(location.search).get('id'));
const detail = document.getElementById('product-detail');

if (!product) {
  document.title = 'Produto não encontrado — Poczy Hardware';
  detail.innerHTML = `<section class="product-missing"><p class="eyebrow">POCZY HARDWARE</p><h1>Produto não encontrado</h1><p>Este link não corresponde a um produto do catálogo.</p><a class="purchase-button" href="pesquisa.html">Explorar produtos</a></section>`;
} else {
  document.title = `${product.name} — Poczy Hardware`;
  document.querySelector('meta[name="description"]').content = `Confira ${product.name}, características e preço na Poczy Hardware.`;
  const attributes = [['brand', 'Marca'], ...filterDefinitions[product.category].filter(([key]) => key !== 'brand')].filter(([key]) => product[key]);
  const related = catalog.filter(item => item.category === product.category && item.id !== product.id).slice(0, 4);
  detail.innerHTML = `
    <nav class="search-breadcrumb product-breadcrumb" aria-label="Você está em"><a href="index.html">Início</a><span aria-hidden="true">/</span><a href="pesquisa.html?categoria=${product.category}">${categories[product.category]}</a><span aria-hidden="true">/</span><span aria-current="page">${product.name}</span></nav>
    <section class="product-overview" aria-labelledby="product-title">
      <div class="product-gallery">
        <span class="gallery-label">${categories[product.category]}</span>
        <button type="button" class="product-main-image" id="zoom-image" aria-label="Ampliar imagem de ${product.name}">${artwork(product)}</button>
        <p class="gallery-hint"><i class="bi bi-zoom-in" aria-hidden="true"></i> Clique na imagem para ampliar</p>
        <div class="gallery-brand"><span>POCZY<span class="brand-dot">.</span></span><span>HARDWARE PARA O SEU PRÓXIMO NÍVEL</span></div>
      </div>
      <div class="product-summary">
        <p class="eyebrow">${product.brand || categories[product.category]}</p>
        <h1 id="product-title">${product.name}</h1>
        <a class="detail-anchor" href="#characteristics">Ver características do produto <span aria-hidden="true">↗</span></a>
        <div class="detail-price"><span class="price-caption">Preço do produto</span><p>${money(product.price)}</p><span>em até 10x de ${money(product.price / 10)} sem juros</span></div>
        <p class="payment-note"><i class="bi bi-credit-card" aria-hidden="true"></i> Parcelamento ilustrativo</p>
        <hr />
        <h2>O que você precisa saber</h2>
        <ul class="product-highlights"><li>Categoria: ${categories[product.category]}</li>${attributes.map(([key, label]) => `<li>${label}: ${product[key]}</li>`).join('')}<li>Confira as características antes de escolher seu upgrade.</li></ul>
        <a class="detail-anchor" href="#description">Ler descrição completa <span aria-hidden="true">↓</span></a>
      </div>
      <aside class="purchase-panel" aria-label="Opções de compra">
        <div class="seller-heading"><i class="bi bi-shop" aria-hidden="true"></i><div><span>Vendido por</span><strong>Poczy Hardware</strong></div></div>
        <div class="delivery-info"><i class="bi bi-truck" aria-hidden="true"></i><div><strong>Entrega no seu endereço</strong><p>Frete e prazo serão informados quando a integração de entregas estiver disponível.</p></div></div>
        <label class="quantity-label" for="quantity">Quantidade</label>
        <select id="quantity" class="quantity-select">${Array.from({length: 10}, (_, index) => `<option value="${index + 1}">${index + 1} ${index ? 'unidades' : 'unidade'}</option>`).join('')}</select>
        <button type="button" class="purchase-button" id="buy-now">Comprar agora</button>
        <button type="button" class="purchase-button secondary" id="add-to-cart"><i class="bi bi-cart-plus" aria-hidden="true"></i> Adicionar ao carrinho</button>
        <p class="demo-notice"><i class="bi bi-info-circle" aria-hidden="true"></i> Produto de demonstração. Os pedidos ainda não são processados.</p>
      </aside>
    </section>
    <div class="product-information">
      <section class="detail-section" id="characteristics" aria-labelledby="characteristics-title"><p class="eyebrow">CADA DETALHE CONTA</p><h2 id="characteristics-title">Características do produto</h2><dl class="spec-table"><div><dt>Produto</dt><dd>${product.name}</dd></div><div><dt>Categoria</dt><dd>${categories[product.category]}</dd></div>${attributes.map(([key, label]) => `<div><dt>${label}</dt><dd>${product[key]}</dd></div>`).join('')}</dl></section>
      <section class="detail-section" id="description" aria-labelledby="description-title"><p class="eyebrow">CONHEÇA SEU PRÓXIMO UPGRADE</p><h2 id="description-title">Descrição</h2><p>Conheça o ${product.name}, disponível na categoria ${categories[product.category].toLowerCase()} da Poczy Hardware.</p><p>${attributes.map(([key, label]) => `${label}: ${product[key]}`).join(' · ') || 'Consulte as características do produto nesta página.'}.</p><p class="demo-notice">As informações deste catálogo são demonstrativas. Especificações completas, disponibilidade e condições comerciais serão confirmadas após a integração com a loja.</p></section>
    </div>
    ${related.length ? `<section class="product-section related-products"><div class="section-heading"><h2>Veja também</h2><a href="pesquisa.html?categoria=${product.category}">Ver todos →</a></div><div class="product-row">${related.map(productCard).join('')}</div></section>` : ''}`;
  document.getElementById('zoom-image').addEventListener('click', () => {
    document.getElementById('expanded-artwork').innerHTML = artwork(product);
    document.getElementById('image-dialog').showModal();
  });
  document.getElementById('add-to-cart').addEventListener('click', () => addToCart(false));
  document.getElementById('buy-now').addEventListener('click', () => addToCart(true));
}

let noticeTimer;
function notify(message) {
  const notice = document.getElementById('product-notice');
  notice.textContent = message;
  notice.hidden = false;
  clearTimeout(noticeTimer);
  noticeTimer = setTimeout(() => { notice.hidden = true; }, 5000);
}
function addToCart(open) {
  const quantity = Number(document.getElementById('quantity').value);
  const result = addCartItem(product.id, quantity);
  if (!result.ok) {
    notify('O limite do carrinho demonstrativo é de 10 unidades por produto.');
    return;
  }
  if (!result.persisted) {
    notify('O navegador não permitiu salvar o carrinho. Habilite o armazenamento para manter os itens ao mudar de página.');
    return;
  }
  if (open) window.location.assign('carrinho.html');
  else notify(`${product.name} adicionado ao carrinho.`);
}
document.querySelectorAll('[data-close]').forEach(button => button.addEventListener('click', () => button.closest('dialog').close()));
