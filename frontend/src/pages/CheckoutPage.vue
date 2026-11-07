<script setup>
import { computed, onMounted, ref } from 'vue';
import SiteHeader from '../components/SiteHeader.vue';
import PageFooter from '../components/PageFooter.vue';
import AddressBook from '../components/AddressBook.vue';
import OrderDetails from '../components/OrderDetails.vue';
import RequestState from '../components/RequestState.vue';
import { completeCheckout, getOrder, getProfile } from '../api/customer.js';
import { errorMessage } from '../api/http.js';
import { money } from '../domain/products.js';
import { useCart } from '../stores/cart.js';

const cart = useCart();
const { items, quantity, subtotal, state } = cart;
const user = ref(null);
const loading = ref(true);
const error = ref('');
const selectedAddress = ref(null);
const addressesBusy = ref(true);
const submitting = ref(false);
const order = ref(null);
const attempt = ref(null);
const shipping = computed(() => state.cart?.shipping_total ?? '30.00');
const total = computed(() => state.cart?.total ?? '0.00');
const available = computed(
  () =>
    items.value.length &&
    items.value.every((item) => item.product.is_active && item.product.stock >= item.quantity),
);
const canSubmit = computed(
  () =>
    !submitting.value &&
    (attempt.value ||
      (selectedAddress.value &&
        !addressesBusy.value &&
        available.value &&
        !state.uncertain &&
        !state.loading &&
        !state.error)),
);
const storageKey = computed(() => `poczy-checkout:${user.value.id}`);

function login() {
  location.replace('/login?next=/checkout');
}

function persistAttempt(value) {
  attempt.value = value;
  try {
    if (value) sessionStorage.setItem(storageKey.value, JSON.stringify(value));
    else sessionStorage.removeItem(storageKey.value);
  } catch {
    /* A tentativa também permanece em memória quando o armazenamento está indisponível. */
  }
}

async function load() {
  loading.value = true;
  error.value = '';
  try {
    user.value = await getProfile();
    const orderId = new URLSearchParams(location.search).get('pedido');
    if (orderId) {
      order.value = await getOrder(orderId);
      document.title = `Pedido #${order.value.id} confirmado — Poczy Hardware`;
      return;
    }
    try {
      const saved = JSON.parse(sessionStorage.getItem(storageKey.value) || 'null');
      if (saved?.checkout_token && saved.payment_method === 'FAKE_PIX') {
        attempt.value = saved;
        selectedAddress.value = saved.address_id;
      }
    } catch {
      /* Uma tentativa inválida é ignorada; os dados do carrinho vêm da API. */
    }
    await cart.load({ force: true });
  } catch (failure) {
    if ([401, 419].includes(failure.status)) login();
    else error.value = errorMessage(failure);
  } finally {
    loading.value = false;
  }
}

async function placeOrder() {
  if (!canSubmit.value) return;
  submitting.value = true;
  error.value = '';
  if (!attempt.value)
    persistAttempt({
      address_id: selectedAddress.value,
      payment_method: 'FAKE_PIX',
      expected_total: total.value,
      checkout_token: crypto.randomUUID(),
    });
  try {
    order.value = await completeCheckout(attempt.value);
    persistAttempt(null);
    history.replaceState(null, '', `/checkout?pedido=${order.value.id}`);
    document.title = `Pedido #${order.value.id} confirmado — Poczy Hardware`;
    await cart.load({ force: true });
    window.scrollTo({ top: 0, behavior: 'smooth' });
  } catch (failure) {
    if ([401, 419].includes(failure.status)) {
      login();
      return;
    }
    if (failure.status >= 400 && failure.status < 500) {
      persistAttempt(null);
      await cart.load({ force: true });
    }
    error.value = attempt.value
      ? 'Não foi possível confirmar a criação do pedido. Clique em “Verificar pedido” para consultar a mesma tentativa, sem criar um pedido duplicado.'
      : errorMessage(failure);
  } finally {
    submitting.value = false;
  }
}

onMounted(load);
</script>

<template>
  <div class="page-shell customer-checkout-page min-vh-100">
    <div class="container-xl px-0 store-container">
      <SiteHeader />
      <main class="customer-checkout" aria-labelledby="checkout-title">
        <nav class="customer-breadcrumb" aria-label="Você está em">
          <a href="/">Início</a><span aria-hidden="true">/</span><a href="/carrinho">Carrinho</a
          ><span aria-hidden="true">/</span><span aria-current="page">Finalizar compra</span>
        </nav>
        <div class="customer-section-heading checkout-heading">
          <div>
            <p class="eyebrow fw-semibold">SEU PRÓXIMO UPGRADE</p>
            <h1 id="checkout-title">
              {{ order ? 'Pedido confirmado' : 'Finalizar compra' }}<span class="brand-dot">.</span>
            </h1>
            <p>
              {{
                order
                  ? 'Confira os detalhes da sua compra.'
                  : 'Escolha seu endereço e confirme o pedido.'
              }}
            </p>
          </div>
          <a class="customer-text-button" href="/conta"
            ><i class="bi bi-person" aria-hidden="true"></i> Minha conta</a
          >
        </div>
        <RequestState
          v-if="loading || (!user && error)"
          :loading="loading"
          :error="error"
          @retry="load"
        />
        <template v-else-if="order">
          <section class="customer-success">
            <span class="customer-success-icon"
              ><i class="bi bi-check2" aria-hidden="true"></i
            ></span>
            <div>
              <p class="eyebrow">
                {{
                  order.status === 'PENDING' ? 'AGUARDANDO PAGAMENTO' : 'PEDIDO REGISTRADO'
                }}
              </p>
              <h2>Seu pedido #{{ order.id }} foi registrado!</h2>
              <p>
                {{
                  order.status === 'PENDING'
                    ? 'Seu estoque foi reservado. Confirme o pagamento simulado abaixo ou cancele o pedido.'
                    : 'Acompanhe o status e os detalhes na sua conta.'
                }}
              </p>
            </div>
          </section>
          <section class="customer-panel"><OrderDetails :order="order" @updated="order = $event" @unauthorized="login" /></section>
          <div class="customer-actions">
            <a class="admin-primary btn btn-primary" href="/conta#orders"
              >Ver meus pedidos <i class="bi bi-arrow-right" aria-hidden="true"></i></a
            ><a class="admin-secondary btn btn-outline-secondary" href="/pesquisa"
              >Continuar comprando</a
            >
          </div>
        </template>
        <template v-else-if="user && !loading">
          <p v-if="error" class="alert alert-danger" role="alert">{{ error }}</p>
          <RequestState
            :loading="state.loading"
            :error="state.error"
            @retry="cart.load({ force: true })"
          />
          <div v-if="items.length || attempt" class="customer-checkout-grid">
            <div class="customer-checkout-steps">
              <ol class="customer-steps" aria-label="Etapas da compra">
                <li><span>1</span> Endereço</li>
                <li><span>2</span> Revisão</li>
                <li><span>3</span> Confirmação</li>
              </ol>
              <AddressBook
                v-if="items.length"
                v-model="selectedAddress"
                selectable
                :disabled="submitting || Boolean(attempt)"
                @busy="addressesBusy = $event"
                @unauthorized="login"
              />
              <section class="customer-panel customer-payment" aria-labelledby="payment-title">
                <div class="customer-section-heading">
                  <div>
                    <h2 id="payment-title">Método de pagamento</h2>
                    <p>Uma forma simples de testar sua compra.</p>
                  </div>
                  <i class="bi bi-qr-code" aria-hidden="true"></i>
                </div>
                <label class="customer-payment-choice"
                  ><input
                    type="radio"
                    name="payment_method"
                    value="FAKE_PIX"
                    checked
                    :disabled="submitting || Boolean(attempt)"
                  /><span
                    ><strong>Fake Pix <span class="customer-status">Simulação</span></strong
                    ><span>Confirmação no pedido, sem cobrança real.</span></span
                  ></label
                >
                <p class="customer-simulation-note">
                  <i class="bi bi-info-circle" aria-hidden="true"></i> Ao confirmar, seu pedido será criado aguardando pagamento.
                  A simulação do pagamento acontece na próxima etapa.
                </p>
              </section>
              <p v-if="attempt" class="alert alert-info" role="status">
                Uma tentativa de criação do pedido está em andamento. Verifique o resultado antes de alterar
                a compra.
              </p>
            </div>
            <aside
              class="customer-panel customer-checkout-summary"
              aria-labelledby="checkout-summary-title"
            >
              <h2 id="checkout-summary-title">
                <i class="bi bi-receipt" aria-hidden="true"></i> Resumo do pedido
              </h2>
              <ul v-if="items.length" class="customer-checkout-items">
                <li v-for="item in items" :key="item.product_id">
                  <div>
                    <strong>{{ item.product.name }}</strong
                    ><span>{{ item.quantity }} × {{ money(item.unit_price) }}</span>
                  </div>
                  <strong>{{ money(item.line_total) }}</strong>
                </li>
              </ul>
              <dl class="customer-totals">
                <div>
                  <dt>Produtos ({{ quantity }})</dt>
                  <dd>{{ money(subtotal) }}</dd>
                </div>
                <div>
                  <dt>Frete fixo</dt>
                  <dd>{{ money(shipping) }}</dd>
                </div>
                <div class="grand-total">
                  <dt>Total</dt>
                  <dd>{{ money(attempt?.expected_total ?? total) }}</dd>
                </div>
              </dl>
              <button
                class="admin-primary btn btn-primary customer-pay-button"
                type="button"
                :disabled="!canSubmit"
                @click="placeOrder"
              >
                <i
                  class="bi"
                  :class="submitting ? 'bi-hourglass-split' : 'bi-qr-code'"
                  aria-hidden="true"
                ></i
                >{{
                  submitting
                    ? 'Criando pedido…'
                    : attempt
                      ? 'Verificar pedido'
                      : 'Confirmar pedido'
                }}
              </button>
              <p v-if="!selectedAddress && !attempt" class="customer-checkout-help">
                Cadastre e selecione um endereço para continuar.
              </p>
              <p v-else-if="!available && !attempt" class="customer-field-error">
                Há produtos sem estoque suficiente. Ajuste seu carrinho.
              </p>
              <a class="customer-summary-link" href="/carrinho"
                >Revisar carrinho <i class="bi bi-arrow-up-right" aria-hidden="true"></i
              ></a>
            </aside>
          </div>
          <div
            v-else-if="state.cart && !state.loading && !state.error"
            class="customer-empty customer-panel"
          >
            <i class="bi bi-cart3" aria-hidden="true"></i>
            <h2>Seu carrinho está vazio</h2>
            <p>Escolha seus produtos para finalizar uma compra.</p>
            <a class="admin-primary btn btn-primary" href="/pesquisa">Explorar produtos</a>
          </div>
        </template>
      </main>
      <PageFooter />
    </div>
  </div>
</template>
