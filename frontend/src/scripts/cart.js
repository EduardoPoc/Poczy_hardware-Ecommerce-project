import './shared.js';
import { catalog } from '../data/catalog.js';
import { artwork, money, productCard, productUrl } from './products.js';
import { getCartItems, getCartCount, getCartSubtotal, MAX_CART_QUANTITY, setCartQuantity, removeCartItem, subscribeCart } from './cart-store.js';

const lines = document.getElementById('cart-lines');
const announcement = document.getElementById('cart-announcement');
let renderedIds = null;

function lineMarkup({ id, product, quantity }) {
  return `<article class="cart-line" data-cart-line="${id}" aria-labelledby="name-${id}">
    <div class="cart-line-product">
      <a class="cart-line-art" href="${productUrl(product)}" aria-label="Ver ${product.name}">${artwork(product)}</a>
      <div class="cart-line-details"><p class="cart-line-brand">${product.brand || 'Poczy Hardware'}</p><h3 id="name-${id}"><a href="${productUrl(product)}">${product.name}</a></h3><p class="cart-unit-price">${money(product.price)} por unidade</p></div>
    </div>
    <div class="cart-quantity"><div class="cart-stepper">
      <button type="button" data-cart-action="decrease" data-cart-id="${id}" aria-label="Diminuir quantidade de ${product.name}" ${quantity === 1 ? 'disabled' : ''}><i class="bi bi-dash-lg" aria-hidden="true"></i></button>
      <input type="number" min="1" max="${MAX_CART_QUANTITY}" step="1" inputmode="numeric" value="${quantity}" data-cart-quantity="${id}" aria-label="Quantidade de ${product.name}" aria-describedby="limit-${id}" />
      <button type="button" data-cart-action="increase" data-cart-id="${id}" aria-label="Aumentar quantidade de ${product.name}" ${quantity === MAX_CART_QUANTITY ? 'disabled' : ''}><i class="bi bi-plus-lg" aria-hidden="true"></i></button>
    </div><p class="cart-line-limit" id="limit-${id}">Máximo de ${MAX_CART_QUANTITY} unidades</p></div>
    <div class="cart-line-cost"><strong data-line-subtotal></strong><button class="cart-remove" type="button" data-cart-action="remove" data-cart-id="${id}" aria-label="Remover ${product.name}"><i class="bi bi-trash3" aria-hidden="true"></i> Remover</button></div>
  </article>`;
}

function renderCart() {
  const items = getCartItems();
  const count = getCartCount();
  const subtotal = money(getCartSubtotal());
  const ids = items.map(item => item.id).join('|');
  document.getElementById('cart-content').hidden = count === 0;
  document.getElementById('cart-empty').hidden = count !== 0;
  document.getElementById('cart-description').textContent = count
    ? `${count} ${count === 1 ? 'item escolhido' : 'itens escolhidos'} para o seu próximo setup.`
    : 'Encontre as peças para montar um setup do seu jeito.';
  document.getElementById('cart-product-count').textContent = `${items.length} ${items.length === 1 ? 'produto' : 'produtos'} · ${count} ${count === 1 ? 'unidade' : 'unidades'}`;
  document.getElementById('cart-summary-count').textContent = `Produtos (${count})`;
  document.getElementById('cart-products-total').textContent = subtotal;
  document.getElementById('cart-subtotal').textContent = subtotal;

  // Preserva os controles e o foco quando apenas a quantidade muda.
  if (ids !== renderedIds) {
    const active = document.activeElement;
    const focusedLine = active.closest('[data-cart-line]');
    const oldIndex = focusedLine ? [...lines.children].indexOf(focusedLine) : -1;
    lines.innerHTML = items.map(lineMarkup).join('');
    renderedIds = ids;
    if (oldIndex !== -1) {
      const nextLine = lines.children[Math.min(oldIndex, items.length - 1)];
      if (nextLine) nextLine.querySelector('[data-cart-action="remove"]').focus();
      else document.getElementById('cart-empty-link').focus();
    }

    const selected = new Set(items.map(item => item.id));
    const recommendations = ['notebooks', 'processors', 'graphics', 'memory']
      .map(category => catalog.find(product => product.category === category && !selected.has(product.id)))
      .filter(Boolean);
    document.getElementById('cart-recommendations').innerHTML = recommendations.map(productCard).join('');
  }

  items.forEach(({ id, product, quantity }) => {
    const row = [...lines.children].find(element => element.dataset.cartLine === id);
    row.querySelector('[data-cart-quantity]').value = quantity;
    row.querySelector('[data-line-subtotal]').textContent = money(Math.round(product.price * 100) * quantity / 100);
    const decrease = row.querySelector('[data-cart-action="decrease"]');
    const increase = row.querySelector('[data-cart-action="increase"]');
    const focusedControl = document.activeElement;
    decrease.disabled = quantity === 1;
    increase.disabled = quantity === MAX_CART_QUANTITY;
    if (focusedControl.disabled && row.contains(focusedControl)) row.querySelector('[data-cart-quantity]').focus();
  });
}

function reportChange(result, message) {
  const feedback = document.getElementById('cart-action-feedback');
  feedback.hidden = result.ok;
  if (!result.ok) {
    feedback.textContent = `Informe uma quantidade inteira de 1 a ${MAX_CART_QUANTITY}.`;
    renderCart();
    return;
  }
  if (!result.persisted) document.getElementById('cart-storage-notice').hidden = false;
  announcement.textContent = `${message} Subtotal: ${money(getCartSubtotal())}.`;
}

lines.addEventListener('click', event => {
  const button = event.target.closest('[data-cart-action]');
  if (!button || button.disabled) return;
  const item = getCartItems().find(entry => entry.id === button.dataset.cartId);
  if (!item) return;
  if (button.dataset.cartAction === 'remove') {
    reportChange(removeCartItem(item.id), `${item.product.name} removido do carrinho.`);
  } else {
    const quantity = item.quantity + (button.dataset.cartAction === 'increase' ? 1 : -1);
    reportChange(setCartQuantity(item.id, quantity), `Quantidade de ${item.product.name}: ${quantity}.`);
  }
});

function commitQuantity(input) {
  const item = getCartItems().find(entry => entry.id === input.dataset.cartQuantity);
  if (!item) return;
  const quantity = Number(input.value);
  if (quantity === item.quantity) return;
  reportChange(setCartQuantity(item.id, quantity), `Quantidade de ${item.product.name}: ${quantity}.`);
}

lines.addEventListener('change', event => {
  const input = event.target.closest('[data-cart-quantity]');
  if (input) commitQuantity(input);
});

lines.addEventListener('keydown', event => {
  const input = event.target.closest('[data-cart-quantity]');
  if (!input || !['Enter', 'Tab'].includes(event.key)) return;
  if (event.key === 'Enter') event.preventDefault();
  // Aplica os limites antes de o Tab escolher o próximo controle disponível.
  commitQuantity(input);
});

subscribeCart(renderCart);
renderCart();
