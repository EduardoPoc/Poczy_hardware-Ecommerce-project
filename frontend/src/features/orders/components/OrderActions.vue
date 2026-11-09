<script setup>
import { ref } from 'vue';
import { cancelOrder, getOrder, payOrder } from '../api.js';
import { errorMessage, isSessionFailure, isUncertainFailure } from '../../../shared/api/errors.js';

const props = defineProps({ order: { type: Object, required: true } });
const emit = defineEmits(['updated', 'unauthorized']);
const saving = ref(false);
const confirmingCancellation = ref(false);
const error = ref('');
const uncertain = ref(false);

function showFailure(failure) {
  if (isSessionFailure(failure)) emit('unauthorized');
  else error.value = errorMessage(failure);
}

async function refresh() {
  saving.value = true;
  error.value = '';
  try {
    emit('updated', await getOrder(props.order.id));
    uncertain.value = false;
  } catch (failure) {
    showFailure(failure);
  } finally {
    saving.value = false;
  }
}

async function perform(action) {
  if (saving.value || uncertain.value || props.order.status !== 'PENDING') return;
  saving.value = true;
  error.value = '';
  try {
    emit('updated', await action(props.order.id));
    confirmingCancellation.value = false;
  } catch (failure) {
    uncertain.value = isUncertainFailure(failure) || failure.status === 409;
    showFailure(failure);
  } finally {
    saving.value = false;
  }
}
</script>

<template>
  <div :aria-busy="saving">
    <p v-if="error" class="alert alert-danger" role="alert">{{ error }}</p>
    <div v-if="uncertain" class="customer-actions">
      <p role="status">Atualize o pedido para confirmar o resultado antes de tentar novamente.</p>
      <button class="admin-secondary btn btn-outline-secondary" :disabled="saving" @click="refresh">
        {{ saving ? 'Consultando…' : 'Verificar pedido' }}
      </button>
    </div>
    <div v-else-if="order.status === 'PENDING'" class="customer-actions">
      <template v-if="confirmingCancellation">
        <p>Cancelar este pedido e devolver os itens ao estoque?</p>
        <button
          class="admin-secondary btn btn-outline-secondary"
          :disabled="saving"
          @click="perform(cancelOrder)"
        >
          {{ saving ? 'Cancelando…' : 'Sim, cancelar pedido' }}
        </button>
        <button
          class="customer-text-button"
          :disabled="saving"
          @click="confirmingCancellation = false"
        >
          Manter pedido
        </button>
      </template>
      <template v-else>
        <button class="admin-primary btn btn-primary" :disabled="saving" @click="perform(payOrder)">
          {{ saving ? 'Confirmando…' : 'Confirmar pagamento simulado' }}
        </button>
        <button
          class="customer-text-button danger"
          :disabled="saving"
          @click="confirmingCancellation = true"
        >
          Cancelar pedido
        </button>
      </template>
    </div>
  </div>
</template>
