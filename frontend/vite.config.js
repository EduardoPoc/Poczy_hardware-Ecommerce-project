import { resolve } from 'node:path';
import { defineConfig } from 'vite';

export default defineConfig({
  base: './',
  input: {
    home: resolve(import.meta.dirname, 'index.html'),
    search: resolve(import.meta.dirname, 'pesquisa.html')
  }
});
