<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import SiteHeader from '../components/SiteHeader.vue';
import PageFooter from '../components/PageFooter.vue';
import ProductArtwork from '../components/ProductArtwork.vue';
import ProductCard from '../components/ProductCard.vue';
import RequestState from '../components/RequestState.vue';
import { getProduct, listProducts } from '../api/products.js';
import { listCategories } from '../api/categories.js';
import { errorMessage } from '../api/http.js';
import { categoryUrl, money, productSpecifications, SHIPPING_TOTAL } from '../domain/products.js';
import { useCart } from '../stores/cart.js';

const cart = useCart();
const product = ref(null);
const category = ref(null);
const related = ref([]);
const quantity = ref(1);
const loading = ref(true);
const error = ref('');
const notFound = ref(false);
const notice = ref('');
const imageDialog = ref(null);
const specifications = computed(() =>
  product.value ? productSpecifications(product.value, category.value) : [],
);
const canBuy = computed(
  () =>
    product.value?.is_active &&
    product.value.stock >= 1 &&
    !cart.state.saving &&
    !cart.state.uncertain,
);
let noticeTimer;

async function load() {
  loading.value = true;
  error.value = '';
  notFound.value = false;
  try {
    const [record, categories] = await Promise.all([
      getProduct(new URLSearchParams(location.search).get('id')),
      listCategories(),
    ]);
    product.value = record;
    category.value = categories.find((item) => item.id === record.category_id) || record.category;
    document.title = `${record.name} — Poczy Hardware`;
    document.querySelector('meta[name="description"]').content =
      `Confira ${record.name}, características e preço na Poczy Hardware.`;
    const response = await listProducts({ categoryId: record.category_id, perPage: 5 });
    related.value = response.products.filter((item) => item.id !== record.id).slice(0, 4);
  } catch (failure) {
    notFound.value = failure.status === 404;
    error.value = errorMessage(failure);
  } finally {
    loading.value = false;
  }
}

function notify(message) {
  notice.value = message;
  clearTimeout(noticeTimer);
  noticeTimer = setTimeout(() => {
    notice.value = '';
  }, 5000);
}

async function addToCart(openCart = false) {
  const amount = Number(quantity.value);
  if (!Number.isInteger(amount) || amount < 1 || amount > product.value.stock) {
    notify('Informe uma quantidade inteira dentro do estoque disponível.');
    return;
  }
  const saved = await cart.add(product.value.id, amount);
  if (!saved) {
    notify(cart.state.error);
    return;
  }
  if (openCart) location.assign('/carrinho');
  else notify(`${product.value.name} adicionado ao carrinho.`);
}

onMounted(load);
onUnmounted(() => clearTimeout(noticeTimer));
</script>

<template>
  <div class="page-shell product-page min-vh-100">
    <div class="container-xl px-0 store-container">
      <SiteHeader />
      <main id="product-detail">
        <section v-if="notFound" class="product-missing text-center">
          <p class="eyebrow fw-semibold">POCZY HARDWARE</p>
          <h1>Produto não encontrado</h1>
          <p>Este produto não está disponível no catálogo da loja.</p>
          <a
            class="purchase-button d-inline-flex align-items-center justify-content-center gap-2 btn btn-primary"
            href="/pesquisa"
            >Explorar produtos</a
          >
        </section>
        <RequestState v-else :loading="loading" :error="error" @retry="load" />
        <template v-if="product && !loading && !error">
          <nav
            class="search-breadcrumb product-breadcrumb d-flex flex-wrap"
            aria-label="Você está em"
          >
            <a href="/">Início</a><span aria-hidden="true">/</span
            ><a :href="categoryUrl(category)">{{ category.name }}</a
            ><span aria-hidden="true">/</span><span aria-current="page">{{ product.name }}</span>
          </nav>
          <section class="product-overview d-grid" aria-labelledby="product-title">
            <div class="product-gallery d-flex flex-column">
              <span class="gallery-label align-self-start">{{ category.name }}</span>
              <button
                id="zoom-image"
                type="button"
                class="product-main-image d-grid border-0"
                :aria-label="`Ampliar imagem de ${product.name}`"
                @click="imageDialog.showModal()"
              >
                <ProductArtwork :product="product" />
              </button>
              <p class="gallery-hint text-center">
                <i class="bi bi-zoom-in" aria-hidden="true"></i> Clique na imagem para ampliar
              </p>
              <div class="gallery-brand justify-content-between align-items-center gap-3">
                <span>POCZY<span class="brand-dot">.</span></span
                ><span>HARDWARE PARA O SEU PRÓXIMO NÍVEL</span>
              </div>
            </div>
            <div class="product-summary">
              <p class="eyebrow fw-semibold">{{ product.brand?.name || category.name }}</p>
              <h1 id="product-title">{{ product.name }}</h1>
              <a class="detail-anchor" href="#characteristics"
                >Ver características do produto <span aria-hidden="true">↗</span></a
              >
              <div class="detail-price">
                <span class="price-caption">Preço do produto</span>
                <p>{{ money(product.price) }}</p>
                <span>{{
                  product.stock > 0
                    ? `${product.stock} unidades em estoque`
                    : 'Produto indisponível'
                }}</span>
              </div>
              <hr />
              <h2>O que você precisa saber</h2>
              <ul class="product-highlights">
                <li>Categoria: {{ category.name }}</li>
                <li v-for="attribute in specifications" :key="attribute.key">
                  {{ attribute.label }}: {{ attribute.value }}
                </li>
              </ul>
              <a class="detail-anchor" href="#description"
                >Ler descrição completa <span aria-hidden="true">↓</span></a
              >
            </div>
            <aside class="purchase-panel" aria-label="Opções de compra">
              <div class="seller-heading">
                <i class="bi bi-shop" aria-hidden="true"></i>
                <div><span>Vendido por</span><strong>Poczy Hardware</strong></div>
              </div>
              <div class="delivery-info">
                <i class="bi bi-truck" aria-hidden="true"></i>
                <div>
                  <strong>Entrega no seu endereço</strong>
                  <p>Frete fixo de {{ money(SHIPPING_TOTAL) }} por pedido.</p>
                </div>
              </div>
              <label class="quantity-label d-block form-label" for="quantity">Quantidade</label>
              <input
                id="quantity"
                v-model.number="quantity"
                class="quantity-select w-100 form-control"
                type="number"
                min="1"
                :max="product.stock"
                step="1"
                :disabled="!canBuy"
              />
              <button
                id="buy-now"
                type="button"
                class="purchase-button d-inline-flex align-items-center justify-content-center gap-2 btn btn-primary"
                :disabled="!canBuy"
                @click="addToCart(true)"
              >
                {{ cart.state.saving ? 'Adicionando…' : 'Comprar agora' }}
              </button>
              <button
                id="add-to-cart"
                type="button"
                class="purchase-button secondary d-inline-flex align-items-center justify-content-center gap-2 btn btn-outline-primary"
                :disabled="!canBuy"
                @click="addToCart()"
              >
                <i class="bi bi-cart-plus" aria-hidden="true"></i> Adicionar ao carrinho
              </button>
              <RequestState
                v-if="cart.state.uncertain"
                :error="cart.state.error"
                @retry="cart.load({ force: true })"
              />
            </aside>
          </section>
          <div class="product-information d-grid gap-4">
            <section
              id="characteristics"
              class="detail-section"
              aria-labelledby="characteristics-title"
            >
              <p class="eyebrow fw-semibold">CADA DETALHE CONTA</p>
              <h2 id="characteristics-title">Características do produto</h2>
              <dl class="spec-table m-0 overflow-hidden">
                <div>
                  <dt>Produto</dt>
                  <dd>{{ product.name }}</dd>
                </div>
                <div>
                  <dt>Categoria</dt>
                  <dd>{{ category.name }}</dd>
                </div>
                <div v-if="product.brand">
                  <dt>Marca</dt>
                  <dd>{{ product.brand.name }}</dd>
                </div>
                <div v-for="attribute in specifications" :key="attribute.key">
                  <dt>{{ attribute.label }}</dt>
                  <dd>{{ attribute.value }}</dd>
                </div>
              </dl>
            </section>
            <section id="description" class="detail-section" aria-labelledby="description-title">
              <p class="eyebrow fw-semibold">CONHEÇA SEU PRÓXIMO UPGRADE</p>
              <h2 id="description-title">Descrição</h2>
              <p class="text-break product-description">
                {{ product.description || 'Descrição não informada pela loja.' }}
              </p>
            </section>
          </div>
          <section v-if="related.length" class="product-section related-products">
            <div class="section-heading d-flex justify-content-between">
              <h2>Veja também</h2>
              <a :href="categoryUrl(category)">Ver todos →</a>
            </div>
            <div class="product-row row">
              <ProductCard v-for="item in related" :key="item.id" :product="item" />
            </div>
          </section>
        </template>
      </main>
      <PageFooter />
    </div>
  </div>
  <dialog
    id="image-dialog"
    ref="imageDialog"
    class="product-dialog image-dialog"
    aria-label="Imagem ampliada do produto"
  >
    <button
      class="dialog-close position-absolute border-0"
      type="button"
      aria-label="Fechar imagem"
      @click="imageDialog.close()"
    >
      ×
    </button>
    <div id="expanded-artwork"><ProductArtwork v-if="product" :product="product" /></div>
  </dialog>
  <p id="product-notice" class="product-notice position-fixed m-0" role="status" :hidden="!notice">
    {{ notice }}
  </p>
</template>
