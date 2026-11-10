import { createApp } from 'vue';
import { showLearningNotice } from '../shared/ui/learning-notice.js';
import '../styles/learning-notice.css';

showLearningNotice();

const pages = {
  home: () => import('../features/catalog/pages/HomePage.vue'),
  search: () => import('../features/catalog/pages/SearchPage.vue'),
  product: () => import('../features/catalog/pages/ProductPage.vue'),
  cart: () => import('../features/cart/pages/CartPage.vue'),
  account: () => import('../features/account/pages/AccountPage.vue'),
  checkout: () => import('../features/checkout/pages/CheckoutPage.vue'),
};

async function start() {
  const root = document.getElementById('app');
  try {
    const { default: page } = await pages[document.body.dataset.page]();
    createApp(page).mount(root);
  } catch {
    root.textContent = 'Não foi possível abrir a loja. Atualize a página e tente novamente.';
    root.setAttribute('role', 'alert');
  }
}

start();
