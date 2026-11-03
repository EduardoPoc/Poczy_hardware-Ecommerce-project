import { resolve } from 'node:path';
import { defineConfig } from 'vite';

export default defineConfig({
  base: './',
  input: {
    home: resolve(import.meta.dirname, 'index.html'),
    search: resolve(import.meta.dirname, 'pesquisa.html'),
    product: resolve(import.meta.dirname, 'produto.html'),
    cart: resolve(import.meta.dirname, 'carrinho.html'),
    admin: resolve(import.meta.dirname, 'admin.html'),
    login: resolve(import.meta.dirname, 'login.html'),
    register: resolve(import.meta.dirname, 'cadastro.html')
  }
});
