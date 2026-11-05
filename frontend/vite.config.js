import { resolve } from 'node:path';
import { defineConfig, loadEnv } from 'vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '');
  const target =
    process.env.VITE_API_PROXY_TARGET || env.VITE_API_PROXY_TARGET || 'http://127.0.0.1:8000';

  return {
    plugins: [vue()],
    base: './',
    server: {
      proxy: Object.fromEntries(
        ['/api', '/sanctum', '/storage'].map((path) => [path, { target, changeOrigin: true }]),
      ),
    },
    input: {
      home: resolve(import.meta.dirname, 'index.html'),
      search: resolve(import.meta.dirname, 'pesquisa.html'),
      product: resolve(import.meta.dirname, 'produto.html'),
      cart: resolve(import.meta.dirname, 'carrinho.html'),
      account: resolve(import.meta.dirname, 'conta.html'),
      checkout: resolve(import.meta.dirname, 'checkout.html'),
      admin: resolve(import.meta.dirname, 'admin.html'),
      login: resolve(import.meta.dirname, 'login.html'),
      register: resolve(import.meta.dirname, 'cadastro.html'),
    },
  };
});
