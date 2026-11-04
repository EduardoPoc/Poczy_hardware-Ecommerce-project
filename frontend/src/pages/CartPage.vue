<script setup>
import { onMounted, ref } from 'vue';
import SiteHeader from '../components/SiteHeader.vue';
import PageFooter from '../components/PageFooter.vue';
import ProductArtwork from '../components/ProductArtwork.vue';
import ProductCard from '../components/ProductCard.vue';
import RequestState from '../components/RequestState.vue';
import { useCart } from '../stores/cart.js';
import { listProducts } from '../api/products.js';
import { errorMessage } from '../api/http.js';
import { money, productUrl } from '../domain/products.js';

const cart = useCart();
const { items, quantity, subtotal, state } = cart;
const recommendations = ref([]);
const recommendationError = ref('');
const feedback = ref('');
const announcement = ref('');

async function loadRecommendations() {
  recommendationError.value = '';
  try {
    recommendations.value = (await listProducts({ perPage: 4 })).products;
  } catch (failure) {
    recommendationError.value = errorMessage(failure);
  }
}

async function setQuantity(item, requested, input = null) {
  feedback.value = '';
  const amount = Number(requested);
  if (!Number.isInteger(amount) || amount < 1 || amount > item.product.stock) {
    feedback.value = 'Informe uma quantidade inteira dentro do estoque disponível.';
  } else if (amount !== item.quantity && (await cart.update(item.product_id, amount))) {
    announcement.value = `Quantidade atualizada. Subtotal: ${money(subtotal.value)}.`;
  }
  if (input)
    input.value = String(
      items.value.find((record) => record.product_id === item.product_id)?.quantity ||
        item.quantity,
    );
}

async function remove(item) {
  if (await cart.remove(item.product_id))
    announcement.value = `${item.product.name} removido do carrinho.`;
}

onMounted(() => {
  cart.load();
  loadRecommendations();
});
</script>

<template>
  <div class="page-shell cart-page min-vh-100">
    <div class="container-xl px-0 store-container">
      <SiteHeader />
      <main aria-labelledby="cart-title">
        <nav
          class="search-breadcrumb product-breadcrumb d-flex flex-wrap"
          aria-label="Você está em"
        >
          <a href="index.html">Início</a><span aria-hidden="true">/</span
          ><span aria-current="page">Carrinho</span>
        </nav>
        <div class="cart-page-heading d-flex justify-content-between">
          <div>
            <p class="eyebrow fw-semibold">SEU PRÓXIMO UPGRADE</p>
            <h1 id="cart-title">Seu carrinho<span class="brand-dot">.</span></h1>
            <p id="cart-description">
              {{
                quantity
                  ? `${quantity} itens escolhidos para o seu próximo setup.`
                  : 'Encontre as peças para montar um setup do seu jeito.'
              }}
            </p>
          </div>
          <a
            class="cart-continue-link d-inline-flex align-items-center gap-2 fw-semibold text-decoration-none text-nowrap"
            href="pesquisa.html"
            ><i class="bi bi-arrow-left" aria-hidden="true"></i> Continuar comprando</a
          >
        </div>
        <p
          id="cart-action-feedback"
          class="cart-action-feedback alert alert-danger"
          role="status"
          :hidden="!feedback"
        >
          {{ feedback }}
        </p>
        <RequestState
          :loading="state.loading"
          :error="state.error"
          @retry="cart.load({ force: true })"
        />
        <div
          v-if="items.length"
          id="cart-content"
          class="cart-layout d-grid"
          :aria-busy="state.saving"
        >
          <section class="cart-products overflow-hidden" aria-labelledby="cart-products-title">
            <div class="cart-seller d-flex align-items-center">
              <span class="cart-seller-icon d-grid"
                ><i class="bi bi-shop" aria-hidden="true"></i
              ></span>
              <div>
                <h2 id="cart-products-title">Vendido por Poczy Hardware</h2>
                <p id="cart-product-count">{{ items.length }} produtos · {{ quantity }} unidades</p>
              </div>
              <i class="bi bi-box-seam cart-seller-box ms-auto" aria-hidden="true"></i>
            </div>
            <div class="cart-column-headings" aria-hidden="true">
              <span>Produto</span><span>Quantidade</span><span>Subtotal</span>
            </div>
            <div id="cart-lines">
              <article
                v-for="item in items"
                :key="item.product_id"
                class="cart-line d-grid align-items-center"
                :data-cart-line="item.product_id"
                :aria-labelledby="`name-${item.product_id}`"
              >
                <div class="cart-line-product d-flex align-items-center">
                  <a
                    class="cart-line-art d-grid"
                    :href="productUrl(item.product)"
                    :aria-label="`Ver ${item.product.name}`"
                    ><ProductArtwork :product="item.product"
                  /></a>
                  <div class="cart-line-details">
                    <p v-if="item.product.brand" class="cart-line-brand fw-semibold">
                      {{ item.product.brand.name }}
                    </p>
                    <h3 :id="`name-${item.product_id}`">
                      <a :href="productUrl(item.product)">{{ item.product.name }}</a>
                    </h3>
                    <p class="cart-unit-price m-0">{{ money(item.unit_price) }} por unidade</p>
                  </div>
                </div>
                <div class="cart-quantity">
                  <div class="cart-stepper">
                    <button
                      type="button"
                      :aria-label="`Diminuir quantidade de ${item.product.name}`"
                      :disabled="state.saving || state.uncertain || item.quantity <= 1"
                      @click="setQuantity(item, item.quantity - 1)"
                    >
                      <i class="bi bi-dash-lg" aria-hidden="true"></i>
                    </button>
                    <input
                      class="form-control"
                      type="number"
                      min="1"
                      :max="item.product.stock"
                      step="1"
                      inputmode="numeric"
                      :value="item.quantity"
                      :disabled="state.saving || state.uncertain"
                      :aria-label="`Quantidade de ${item.product.name}`"
                      :aria-describedby="`limit-${item.product_id}`"
                      @change="setQuantity(item, $event.target.value, $event.target)"
                      @keydown.enter.prevent="setQuantity(item, $event.target.value, $event.target)"
                    />
                    <button
                      type="button"
                      :aria-label="`Aumentar quantidade de ${item.product.name}`"
                      :disabled="
                        state.saving || state.uncertain || item.quantity >= item.product.stock
                      "
                      @click="setQuantity(item, item.quantity + 1)"
                    >
                      <i class="bi bi-plus-lg" aria-hidden="true"></i>
                    </button>
                  </div>
                  <p :id="`limit-${item.product_id}`" class="cart-line-limit">
                    {{ item.product.stock }} unidades disponíveis
                  </p>
                </div>
                <div class="cart-line-cost d-flex flex-column align-items-end">
                  <strong>{{ money(item.line_total) }}</strong
                  ><button
                    class="cart-remove d-inline-flex align-items-center border-0"
                    type="button"
                    :disabled="state.saving || state.uncertain"
                    :aria-label="`Remover ${item.product.name}`"
                    @click="remove(item)"
                  >
                    <i class="bi bi-trash3" aria-hidden="true"></i> Remover
                  </button>
                </div>
              </article>
            </div>
            <div class="cart-list-footer d-flex align-items-center">
              <i class="bi bi-bag-check" aria-hidden="true"></i
              ><span>Preços e disponibilidade são atualizados pela loja.</span>
            </div>
          </section>
          <aside class="cart-summary" aria-labelledby="cart-summary-title">
            <h2 id="cart-summary-title">
              <i class="bi bi-receipt" aria-hidden="true"></i> Resumo do pedido
            </h2>
            <dl class="cart-summary-values m-0">
              <div>
                <dt>Produtos ({{ quantity }})</dt>
                <dd>{{ money(subtotal) }}</dd>
              </div>
              <div>
                <dt>Frete</dt>
                <dd class="cart-shipping-pending">A calcular</dd>
              </div>
            </dl>
            <div class="cart-subtotal d-grid align-items-center gap-2">
              <span>Subtotal</span><strong id="cart-subtotal">{{ money(subtotal) }}</strong
              ><small>Frete não incluído</small>
            </div>
            <button
              class="purchase-button cart-checkout d-inline-flex align-items-center justify-content-center gap-2 btn btn-primary"
              type="button"
              disabled
              aria-describedby="cart-checkout-notice"
            >
              Finalizar compra <i class="bi bi-arrow-right" aria-hidden="true"></i>
            </button>
            <p id="cart-checkout-notice" class="cart-checkout-notice d-flex gap-2">
              <i class="bi bi-info-circle" aria-hidden="true"></i
              ><span
                >A finalização de pedidos e o cálculo de frete ainda não estão disponíveis nesta
                tela.</span
              >
            </p>
            <a
              class="cart-summary-continue d-flex justify-content-center align-items-center fw-semibold text-decoration-none"
              href="pesquisa.html"
              >Escolher mais produtos <i class="bi bi-arrow-up-right" aria-hidden="true"></i
            ></a>
          </aside>
        </div>
        <section
          v-else-if="state.cart && !state.loading && !state.error"
          id="cart-empty"
          class="cart-empty text-center"
          aria-labelledby="cart-empty-title"
        >
          <div class="cart-empty-icon d-grid">
            <i class="bi bi-cart3" aria-hidden="true"></i><span>0</span>
          </div>
          <p class="eyebrow fw-semibold">ESPAÇO PARA UM NOVO SETUP</p>
          <h2 id="cart-empty-title">Seu carrinho está vazio</h2>
          <p>
            O próximo upgrade começa com uma boa escolha.<br />Explore a loja e encontre as peças
            para o seu setup.
          </p>
          <a
            class="purchase-button d-inline-flex align-items-center justify-content-center gap-2 btn btn-primary"
            href="pesquisa.html"
            >Explorar produtos <i class="bi bi-arrow-right" aria-hidden="true"></i
          ></a>
        </section>
        <section
          v-if="recommendations.length"
          class="cart-recommendations product-section"
          aria-labelledby="cart-recommendations-title"
        >
          <div class="section-heading d-flex justify-content-between">
            <h2 id="cart-recommendations-title">Mais para o seu setup</h2>
            <a href="pesquisa.html"
              >Ver produtos <i class="bi bi-arrow-right" aria-hidden="true"></i
            ></a>
          </div>
          <div class="product-row row">
            <ProductCard v-for="product in recommendations" :key="product.id" :product="product" />
          </div>
        </section>
        <RequestState :error="recommendationError" @retry="loadRecommendations" />
        <p class="visually-hidden" role="status" aria-live="polite" aria-atomic="true">
          {{ announcement }}
        </p>
      </main>
      <PageFooter />
    </div>
  </div>
</template>
