<script setup>
import { money } from '../../../shared/lib/money.js';
import { formatDate as dateLabel } from '../../../shared/lib/dates.js';
import { orderStatuses, paymentLabel } from '../domain.js';
import OrderActions from './OrderActions.vue';
defineProps({ order: { type: Object, required: true } });
const emit = defineEmits(['updated', 'unauthorized']);
</script>

<template>
  <div class="customer-order-details">
    <div class="customer-section-heading">
      <div>
        <p class="eyebrow">PEDIDO #{{ order.id }}</p>
        <h2>{{ orderStatuses[order.status] || order.status }}</h2>
        <p>{{ dateLabel(order.created_at) }}</p>
      </div>
      <span class="customer-status" :class="order.status.toLowerCase()">{{
        paymentLabel(order.payment_method)
      }}</span>
    </div>
    <ul class="customer-order-items">
      <li v-for="item in order.items" :key="item.id">
        <div>
          <strong>{{ item.product_name_snapshot }}</strong
          ><span>{{ item.quantity }} × {{ money(item.unit_price) }}</span>
        </div>
        <strong>{{ money(item.line_total) }}</strong>
      </li>
    </ul>
    <div class="customer-order-bottom">
      <div v-if="order.shipping_address">
        <h3>Endereço de entrega</h3>
        <p>
          {{ order.shipping_address.street }}, {{ order.shipping_address.number }}<br /><template
            v-if="order.shipping_address.complement"
            >{{ order.shipping_address.complement }}<br /></template
          >{{ order.shipping_address.city }} · {{ order.shipping_address.state }}<br />CEP
          {{ order.shipping_address.postal_code }}
        </p>
      </div>
      <dl class="customer-totals">
        <div>
          <dt>Produtos</dt>
          <dd>{{ money(order.products_total) }}</dd>
        </div>
        <div>
          <dt>Frete</dt>
          <dd>{{ money(order.shipping_total) }}</dd>
        </div>
        <div class="grand-total">
          <dt>Total</dt>
          <dd>{{ money(order.total) }}</dd>
        </div>
      </dl>
    </div>
    <p v-if="order.payment_method === 'FAKE_PIX'" class="customer-simulation-note">
      <i class="bi bi-info-circle" aria-hidden="true"></i> Pagamento simulado com Fake Pix, sem
      cobrança real.
    </p>
    <OrderActions
      :key="order.id"
      :order="order"
      @updated="emit('updated', $event)"
      @unauthorized="emit('unauthorized')"
    />
  </div>
</template>
