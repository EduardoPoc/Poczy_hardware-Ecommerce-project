<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue';
import SiteHeader from '../../../layouts/SiteHeader.vue';
import ProductArtwork from '../components/ProductArtwork.vue';
import ProductCard from '../components/ProductCard.vue';
import StoreFooter from '../../../layouts/StoreFooter.vue';
import RequestState from '../../../shared/components/RequestState.vue';
import { listCategories, listProducts } from '../api.js';

import { errorMessage } from '../../../shared/api/errors.js';
import { categoryUrl, productUrl } from '../domain.js';
import { money } from '../../../shared/lib/money.js';

const categories = ref([]);
const featured = ref([]);
const sections = ref([]);
const loading = ref(true);
const error = ref('');
const currentSlide = ref(0);
const activeProduct = computed(() => featured.value[currentSlide.value]);
let carouselTimer;

async function load() {
  loading.value = true;
  error.value = '';
  try {
    const [availableCategories, result] = await Promise.all([
      listCategories(),
      listProducts({ perPage: 5 }),
    ]);
    categories.value = availableCategories;
    featured.value = result.products;
    currentSlide.value = 0;
    sections.value = await Promise.all(
      availableCategories.slice(0, 2).map(async (category) => ({
        category,
        products: (await listProducts({ categoryId: category.id, perPage: 6 })).products,
      })),
    );
  } catch (failure) {
    error.value = errorMessage(failure);
  } finally {
    loading.value = false;
    if (window.location.hash === '#terms-panel') {
      await nextTick();
      const termsPanel = document.getElementById('terms-panel');
      termsPanel?.scrollIntoView({ block: 'start' });
      termsPanel?.focus({ preventScroll: true });
    }
  }
}

function restartCarousel() {
  clearInterval(carouselTimer);
  if (document.hidden || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  carouselTimer = setInterval(() => {
    if (featured.value.length)
      currentSlide.value = (currentSlide.value + 1) % featured.value.length;
  }, 4000);
}

function selectSlide(index) {
  currentSlide.value = index;
  restartCarousel();
}

onMounted(() => {
  load();
  restartCarousel();
  document.addEventListener('visibilitychange', restartCarousel);
});
onUnmounted(() => {
  clearInterval(carouselTimer);
  document.removeEventListener('visibilitychange', restartCarousel);
});
</script>

<template>
  <main class="page-shell">
    <div class="container-xl px-0 store-container">
      <SiteHeader />
      <RequestState :loading="loading" :error="error" @retry="load" />
      <template v-if="!loading && !error">
        <section
          v-if="activeProduct"
          class="hero-banner d-grid position-relative overflow-hidden align-items-center"
          aria-label="Produtos em destaque"
          aria-roledescription="carrossel"
        >
          <div class="hero-image-wrap align-items-center justify-content-center">
            <ProductArtwork :product="activeProduct" />
          </div>
          <div class="hero-copy" aria-live="polite" aria-atomic="true">
            <p class="eyebrow fw-semibold">{{ activeProduct.category?.name }}</p>
            <h1>{{ activeProduct.name }}</h1>
            <p class="hero-specs fw-semibold">{{ activeProduct.description }}</p>
            <p class="hero-old-price">
              {{ activeProduct.stock > 0 ? 'Disponível em estoque' : 'Produto indisponível' }}
            </p>
            <p class="hero-price m-0 fw-bold">
              <span class="hero-price-label fw-normal">Por</span>
              <span class="hero-price-value">{{ money(activeProduct.price) }}</span>
            </p>
            <a
              :href="productUrl(activeProduct)"
              class="hero-link d-inline-block fw-semibold text-decoration-none"
              >SAIBA MAIS →</a
            >
          </div>
          <div
            class="hero-dots position-absolute align-items-center gap-0"
            role="group"
            aria-label="Selecionar produto"
          >
            <button
              v-for="(product, index) in featured"
              :key="product.id"
              type="button"
              :class="{ active: index === currentSlide }"
              :aria-label="`Produto ${index + 1} de ${featured.length}: ${product.name}`"
              :aria-current="index === currentSlide ? 'true' : undefined"
              @click="selectSlide(index)"
            ></button>
          </div>
        </section>
        <section
          v-for="(section, index) in sections"
          :key="section.category.id"
          class="product-section"
          :class="{ 'video-section': index > 0 }"
        >
          <div class="section-heading d-flex justify-content-between">
            <h2>{{ section.category.name }}</h2>
            <a :href="categoryUrl(section.category)">Ver mais →</a>
          </div>
          <div v-if="section.products.length" class="row product-row catalog-row">
            <ProductCard v-for="product in section.products" :key="product.id" :product="product" />
          </div>
          <p v-else class="search-empty text-center mt-3">
            Nenhum produto disponível nesta categoria.
          </p>
        </section>
        <p v-if="!featured.length" class="search-empty text-center mt-3">
          Nenhum produto disponível no momento.
        </p>
      </template>
      <StoreFooter :categories="categories" />
    </div>
  </main>
</template>
