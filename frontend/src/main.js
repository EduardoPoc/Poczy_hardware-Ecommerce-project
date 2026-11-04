import { createApp } from 'vue';

const pages = {
  home: () => import('./pages/HomePage.vue'),
  search: () => import('./pages/SearchPage.vue'),
  product: () => import('./pages/ProductPage.vue'),
  cart: () => import('./pages/CartPage.vue'),
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
