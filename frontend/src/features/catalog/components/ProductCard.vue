<script setup>
import ProductArtwork from './ProductArtwork.vue';
import { money } from '../../../shared/lib/money.js';
import { productUrl } from '../domain.js';

defineProps({ product: { type: Object, required: true } });
</script>

<template>
  <article class="product-card">
    <a
      class="product-card-inner flex-column h-100 overflow-hidden card position-static"
      :href="productUrl(product)"
    >
      <div class="product-image flex-shrink-0 d-grid"><ProductArtwork :product="product" /></div>
      <p class="product-name overflow-hidden fw-semibold">{{ product.name }}</p>
      <p v-if="product.stock === 0" class="product-availability">Indisponível</p>
      <p class="product-price fw-bold">
        <span class="product-price-label d-block fw-normal">Por</span>
        <span class="product-price-value">{{ money(product.price) }}</span>
      </p>
    </a>
  </article>
</template>
