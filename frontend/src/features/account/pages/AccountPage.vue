<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import CustomerLayout from '../../../layouts/CustomerLayout.vue';
import AddressBook from '../components/AddressBook.vue';
import AccountSettings from '../components/AccountSettings.vue';
import OrderDetails from '../../orders/components/OrderDetails.vue';
import RequestState from '../../../shared/components/RequestState.vue';
import { getOrder, listOrders } from '../../orders/api.js';
import { getProfile, listAddresses } from '../api.js';
import { errorMessage } from '../../../shared/api/http.js';
import { formatDate as dateLabel } from '../../../shared/lib/dates.js';
import { orderStatuses, paymentLabel } from '../../orders/domain.js';
import { money } from '../../../shared/lib/money.js';

const user = ref(null);
const passwordChanged = ref(false);
const loading = ref(true);
const error = ref('');
const section = ref('overview');
const addressesCount = ref(0);
const orders = ref([]);
const meta = ref({ total: 0, current_page: 1, last_page: 1 });
const ordersLoading = ref(false);
const ordersError = ref('');
const order = ref(null);
const selectedOrderId = ref(null);
const orderLoading = ref(false);
const orderError = ref('');
const orderDialog = ref(null);
const titles = { overview: 'Visão geral', addresses: 'Meus endereços', orders: 'Meus pedidos' };
const title = computed(() => titles[section.value]);

function unauthorized(failure) {
  if (!failure || [401, 419].includes(failure.status)) {
    location.replace('/login?next=/conta');
    return true;
  }
  return false;
}

function finishPasswordChange() {
  passwordChanged.value = true;
  user.value = null;
}

function updateOrder(record) {
  order.value = record;
  orders.value = orders.value.map((item) =>
    item.id === record.id ? { ...item, ...record } : item,
  );
}

function changeSection() {
  const next = location.hash.slice(1);
  section.value = Object.hasOwn(titles, next) ? next : 'overview';
  document.title = `${titles[section.value]} — Poczy Hardware`;
}

async function loadOrders(page = meta.value.current_page) {
  if (ordersLoading.value) return;
  ordersLoading.value = true;
  ordersError.value = '';
  try {
    const response = await listOrders(page);
    orders.value = response.data;
    meta.value = response.meta;
  } catch (failure) {
    if (!unauthorized(failure)) ordersError.value = errorMessage(failure);
  } finally {
    ordersLoading.value = false;
  }
}

async function load() {
  loading.value = true;
  error.value = '';
  try {
    user.value = await getProfile();
    const [addresses] = await Promise.all([listAddresses(), loadOrders(1)]);
    addressesCount.value = addresses.length;
  } catch (failure) {
    if (!unauthorized(failure)) error.value = errorMessage(failure);
  } finally {
    loading.value = false;
  }
}

async function showOrder(id) {
  selectedOrderId.value = id;
  order.value = null;
  orderLoading.value = true;
  orderError.value = '';
  orderDialog.value.showModal();
  try {
    order.value = await getOrder(id);
  } catch (failure) {
    if (!unauthorized(failure)) orderError.value = errorMessage(failure);
  } finally {
    orderLoading.value = false;
  }
}

onMounted(() => {
  changeSection();
  window.addEventListener('hashchange', changeSection);
  load();
});
onUnmounted(() => window.removeEventListener('hashchange', changeSection));
</script>

<template>
  <div v-if="passwordChanged" class="customer-access-gate">
    <a href="/">Poczy Hardware</a>
    <h1>Senha alterada com sucesso</h1>
    <p role="status">Sua sessão foi encerrada. Entre novamente com a nova senha.</p>
    <a class="customer-login-link admin-primary btn btn-primary" href="/login?next=/conta">
      Entrar na minha conta
    </a>
  </div>
  <div v-else-if="!user" class="customer-access-gate">
    <a href="/">Poczy Hardware</a>
    <h1>Minha conta</h1>
    <RequestState :loading="loading" :error="error" @retry="load" />
  </div>
  <CustomerLayout v-else :user="user" :section="section" :title="title">
    <div class="admin-page-heading d-flex justify-content-between">
      <div>
        <p class="eyebrow fw-semibold">SEU ESPAÇO POCZY</p>
        <h1 id="customer-title">{{ title }}<span class="brand-dot">.</span></h1>
        <p>
          {{
            section === 'overview'
              ? `Olá, ${user.name.split(' ')[0]}! Cuide de cada próximo upgrade por aqui.`
              : section === 'addresses'
                ? 'Organize seus endereços de entrega.'
                : 'Acompanhe os pedidos realizados na sua conta.'
          }}
        </p>
      </div>
      <a class="admin-secondary btn btn-outline-secondary" href="/carrinho"
        ><i class="bi bi-cart3" aria-hidden="true"></i> Meu carrinho</a
      >
    </div>
    <RequestState :loading="loading" :error="error" @retry="load" />
    <template v-if="!loading && !error">
      <template v-if="section === 'overview'">
        <div class="customer-stats">
          <a href="#addresses" class="customer-stat"
            ><i class="bi bi-geo-alt" aria-hidden="true"></i><strong>{{ addressesCount }}</strong
            ><span>Endereços cadastrados</span></a
          ><a href="#orders" class="customer-stat"
            ><i class="bi bi-bag-check" aria-hidden="true"></i><strong>{{ meta.total }}</strong
            ><span>Pedidos realizados</span></a
          ><a href="/checkout" class="customer-stat"
            ><i class="bi bi-qr-code" aria-hidden="true"></i><strong>Fake Pix</strong
            ><span>Pagamento simulado no checkout</span></a
          >
        </div>
        <section class="customer-welcome customer-panel">
          <div>
            <p class="eyebrow">PRONTO PARA O PRÓXIMO NÍVEL?</p>
            <h2>Seu setup, do seu jeito.</h2>
            <p>Escolha seus produtos, selecione um endereço e finalize com Fake Pix.</p>
            <a class="admin-primary btn btn-primary" href="/pesquisa"
              >Explorar produtos <i class="bi bi-arrow-right" aria-hidden="true"></i
            ></a>
          </div>
          <i class="bi bi-cpu" aria-hidden="true"></i>
        </section>
        <AccountSettings
          :user="user"
          @updated="user = $event"
          @password-changed="finishPasswordChange"
          @unauthorized="unauthorized()"
        />
      </template>
      <AddressBook
        v-else-if="section === 'addresses'"
        @loaded="addressesCount = $event.length"
        @unauthorized="unauthorized()"
      />
      <section v-else class="customer-panel">
        <div class="customer-section-heading">
          <div>
            <h2>Histórico de compras</h2>
            <p>{{ meta.total }} pedido(s) na sua conta</p>
          </div>
          <button
            class="admin-secondary btn btn-outline-secondary"
            type="button"
            :disabled="ordersLoading"
            @click="loadOrders()"
          >
            <i class="bi bi-arrow-clockwise" aria-hidden="true"></i> Atualizar
          </button>
        </div>
        <RequestState :loading="ordersLoading" :error="ordersError" @retry="loadOrders()" />
        <div v-if="!ordersLoading && orders.length" class="customer-orders-list">
          <article v-for="record in orders" :key="record.id" class="customer-order-row">
            <div>
              <strong>Pedido #{{ record.id }}</strong
              ><span>{{ dateLabel(record.created_at) }}</span>
            </div>
            <div>
              <span class="customer-status" :class="record.status.toLowerCase()">{{
                orderStatuses[record.status] || record.status
              }}</span
              ><span>{{ paymentLabel(record.payment_method) }}</span>
            </div>
            <div>
              <strong>{{ money(record.total) }}</strong
              ><span>{{ record.items_quantity }} unidade(s)</span>
            </div>
            <button
              class="admin-secondary btn btn-outline-secondary"
              type="button"
              :aria-label="`Ver detalhes do pedido ${record.id}`"
              @click="showOrder(record.id)"
            >
              Ver pedido <i class="bi bi-arrow-up-right" aria-hidden="true"></i>
            </button>
          </article>
          <nav
            v-if="meta.last_page > 1"
            class="customer-pagination"
            aria-label="Páginas de pedidos"
          >
            <button
              type="button"
              class="admin-secondary btn btn-outline-secondary"
              :disabled="meta.current_page <= 1"
              @click="loadOrders(meta.current_page - 1)"
            >
              Anterior</button
            ><span>Página {{ meta.current_page }} de {{ meta.last_page }}</span
            ><button
              type="button"
              class="admin-secondary btn btn-outline-secondary"
              :disabled="meta.current_page >= meta.last_page"
              @click="loadOrders(meta.current_page + 1)"
            >
              Próxima
            </button>
          </nav>
        </div>
        <div v-else-if="!ordersLoading && !ordersError" class="customer-empty">
          <i class="bi bi-bag" aria-hidden="true"></i>
          <h3>Seu próximo upgrade está por vir</h3>
          <p>Quando você finalizar uma compra, ela aparecerá aqui.</p>
          <a href="/pesquisa" class="admin-primary btn btn-primary">Explorar produtos</a>
        </div>
      </section>
    </template>
    <footer class="admin-footer">
      <span>Poczy Hardware · Seu próximo setup começa aqui.</span>
    </footer>
    <dialog
      ref="orderDialog"
      class="admin-dialog customer-order-dialog p-0"
      aria-labelledby="order-dialog-title"
    >
      <header class="admin-dialog-header d-flex align-items-center">
        <h2 id="order-dialog-title">Detalhes do pedido</h2>
        <button
          type="button"
          class="admin-icon-button btn btn-outline-secondary"
          aria-label="Fechar detalhes do pedido"
          @click="orderDialog.close()"
        >
          <i class="bi bi-x-lg" aria-hidden="true"></i>
        </button>
      </header>
      <div class="customer-dialog-content">
        <RequestState
          :loading="orderLoading"
          :error="orderError"
          @retry="showOrder(selectedOrderId)"
        /><OrderDetails
          v-if="order"
          :order="order"
          @updated="updateOrder"
          @unauthorized="unauthorized()"
        />
      </div>
    </dialog>
  </CustomerLayout>
</template>
