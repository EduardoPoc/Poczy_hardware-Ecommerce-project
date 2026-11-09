<script setup>
import { onMounted, ref } from 'vue';
import { deleteAddress, listAddresses, saveAddress } from '../api.js';
import { errorMessage, isSessionFailure } from '../../../shared/api/errors.js';
import AddressForm from './AddressForm.vue';
import RequestState from '../../../shared/components/RequestState.vue';

const props = defineProps({
  selectable: Boolean,
  modelValue: { type: Number, default: null },
  disabled: Boolean,
});
const emit = defineEmits(['update:modelValue', 'loaded', 'unauthorized', 'busy']);
const addresses = ref([]);
const loading = ref(true);
const saving = ref(false);
const error = ref('');
const feedback = ref('');
const fieldErrors = ref({});
const editing = ref(false);
const current = ref(null);
const deleting = ref(null);

function failure(errorValue) {
  if (isSessionFailure(errorValue)) emit('unauthorized');
  error.value = errorMessage(errorValue);
}

function synchronize(preferred = props.modelValue) {
  if (props.selectable && !addresses.value.some((address) => address.id === preferred))
    preferred = addresses.value[0]?.id || null;
  if (props.selectable) emit('update:modelValue', preferred);
  emit('loaded', addresses.value);
}

async function load() {
  loading.value = true;
  error.value = '';
  emit('busy', true);
  try {
    addresses.value = await listAddresses();
    synchronize();
  } catch (failureValue) {
    failure(failureValue);
  } finally {
    loading.value = false;
    emit('busy', false);
  }
}

function edit(address = null) {
  current.value = address;
  fieldErrors.value = {};
  error.value = '';
  feedback.value = '';
  editing.value = true;
  emit('busy', true);
  deleting.value = null;
}

async function save(data) {
  if (saving.value || props.disabled) return;
  saving.value = true;
  emit('busy', true);
  error.value = '';
  fieldErrors.value = {};
  try {
    const address = await saveAddress(data, current.value?.id);
    addresses.value = current.value
      ? addresses.value.map((item) => (item.id === address.id ? address : item))
      : [...addresses.value, address];
    editing.value = false;
    synchronize(props.selectable ? address.id : props.modelValue);
    feedback.value = 'Endereço salvo com sucesso.';
  } catch (failureValue) {
    fieldErrors.value = failureValue.errors || {};
    failure(failureValue);
  } finally {
    saving.value = false;
    emit('busy', editing.value);
  }
}

async function remove(id) {
  if (saving.value || props.disabled) return;
  saving.value = true;
  emit('busy', true);
  error.value = '';
  try {
    await deleteAddress(id);
    addresses.value = addresses.value.filter((address) => address.id !== id);
    deleting.value = null;
    synchronize();
    feedback.value = 'Endereço removido.';
  } catch (failureValue) {
    failure(failureValue);
  } finally {
    saving.value = false;
    emit('busy', false);
  }
}

onMounted(load);
</script>

<template>
  <div class="customer-address-book" :aria-busy="loading || saving">
    <div class="customer-section-heading">
      <div>
        <h2>{{ selectable ? 'Endereço de entrega' : 'Meus endereços' }}</h2>
        <p>
          {{
            selectable
              ? 'Escolha onde você quer receber seu pedido.'
              : 'Seus endereços ficam disponíveis na finalização da compra.'
          }}
        </p>
      </div>
      <button
        type="button"
        class="admin-primary btn btn-primary"
        :disabled="disabled || saving || loading"
        @click="edit()"
      >
        <i class="bi bi-plus-lg" aria-hidden="true"></i> Novo endereço
      </button>
    </div>
    <RequestState
      v-if="loading || (error && !editing)"
      :loading="loading"
      :error="error"
      @retry="load"
    />
    <p v-if="feedback" class="alert alert-info" role="status">{{ feedback }}</p>
    <div v-if="editing" class="customer-panel">
      <p v-if="error" class="alert alert-danger" role="alert">{{ error }}</p>
      <AddressForm
        :key="current?.id || 'new'"
        :address="current"
        :saving="saving || disabled"
        :errors="fieldErrors"
        @save="save"
        @cancel="
          editing = false;
          emit('busy', false);
        "
      />
    </div>
    <div
      v-if="!loading && addresses.length"
      class="customer-address-grid"
      :class="{ 'is-selectable': selectable }"
    >
      <article
        v-for="(address, index) in addresses"
        :key="address.id"
        class="customer-address-card"
        :class="{ selected: selectable && modelValue === address.id }"
      >
        <label v-if="selectable" class="customer-address-choice">
          <input
            type="radio"
            name="shipping_address"
            :value="address.id"
            :checked="modelValue === address.id"
            :disabled="disabled || saving"
            @change="emit('update:modelValue', address.id)"
          />
          <span
            >Entregar neste endereço <strong>{{ address.street }}, {{ address.number }}</strong
            ><span v-if="address.complement">{{ address.complement }}</span
            ><span
              >{{ address.city }} · {{ address.state }} · CEP {{ address.postal_code }}</span
            ></span
          >
        </label>
        <div v-else>
          <span class="customer-card-label"
            ><i class="bi bi-geo-alt" aria-hidden="true"></i> Endereço {{ index + 1 }}</span
          >
          <h3>{{ address.street }}, {{ address.number }}</h3>
          <p v-if="address.complement">{{ address.complement }}</p>
          <p>{{ address.city }} · {{ address.state }}</p>
          <p>CEP {{ address.postal_code }}</p>
        </div>
        <div
          v-if="deleting === address.id"
          class="customer-delete-confirm"
          role="group"
          aria-label="Confirmar remoção do endereço"
        >
          <p>Remover este endereço da sua conta?</p>
          <button
            type="button"
            class="customer-text-button danger"
            :disabled="saving || disabled"
            @click="remove(address.id)"
          >
            Sim, remover</button
          ><button
            type="button"
            class="customer-text-button"
            :disabled="saving"
            @click="deleting = null"
          >
            Cancelar
          </button>
        </div>
        <div v-else class="customer-card-actions">
          <button
            type="button"
            class="customer-text-button"
            :disabled="saving || disabled"
            @click="edit(address)"
          >
            <i class="bi bi-pencil" aria-hidden="true"></i> Editar</button
          ><button
            type="button"
            class="customer-text-button danger"
            :disabled="saving || disabled"
            @click="deleting = address.id"
          >
            Remover
          </button>
        </div>
      </article>
    </div>
    <div v-else-if="!loading && !error && !editing" class="customer-empty">
      <i class="bi bi-geo-alt" aria-hidden="true"></i>
      <h3>Seu primeiro endereço começa aqui</h3>
      <p>Cadastre um endereço para receber seu próximo upgrade.</p>
      <button
        type="button"
        class="admin-secondary btn btn-outline-secondary"
        :disabled="disabled"
        @click="edit()"
      >
        Adicionar endereço
      </button>
    </div>
  </div>
</template>
