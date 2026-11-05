<script setup>
import { reactive, useId, watch } from 'vue';
import { addressPayload, states } from '../domain/customer.js';

const props = defineProps({
  address: { type: Object, default: null },
  saving: Boolean,
  errors: { type: Object, default: () => ({}) },
});
const emit = defineEmits(['save', 'cancel']);
const prefix = useId();
const fields = reactive({});
const inputs = [
  {
    key: 'postal_code',
    label: 'CEP',
    max: 9,
    autocomplete: 'postal-code',
    pattern: '[0-9]{5}-?[0-9]{3}',
    placeholder: '00000-000',
  },
  { key: 'street', label: 'Rua ou avenida', max: 150, autocomplete: 'address-line1', wide: true },
  { key: 'number', label: 'Número', max: 20, autocomplete: 'off' },
  {
    key: 'complement',
    label: 'Complemento · opcional',
    max: 100,
    autocomplete: 'address-line2',
    optional: true,
  },
  { key: 'city', label: 'Cidade', max: 100, autocomplete: 'address-level2' },
];
watch(
  () => props.address,
  (address) => {
    for (const key of ['postal_code', 'street', 'number', 'complement', 'city', 'state'])
      fields[key] = address?.[key] || '';
  },
  { immediate: true },
);
</script>

<template>
  <form class="customer-address-form" @submit.prevent="emit('save', addressPayload(fields))">
    <h3>{{ address ? 'Editar endereço' : 'Novo endereço' }}</h3>
    <fieldset :disabled="saving" class="border-0 p-0 m-0">
      <div class="admin-form-grid d-grid">
        <div
          v-for="input in inputs"
          :key="input.key"
          class="admin-field d-flex flex-column gap-2"
          :class="{ wide: input.wide }"
        >
          <label :for="`${prefix}-${input.key}`">{{ input.label }}</label>
          <input
            :id="`${prefix}-${input.key}`"
            v-model="fields[input.key]"
            class="form-control"
            :name="input.key"
            :maxlength="input.max"
            :required="!input.optional"
            :autocomplete="input.autocomplete"
            :pattern="input.pattern"
            :placeholder="input.placeholder"
            :inputmode="input.key === 'postal_code' ? 'numeric' : undefined"
            :aria-invalid="Boolean(errors[input.key])"
            :aria-describedby="errors[input.key] ? `${prefix}-${input.key}-error` : undefined"
          />
          <small
            v-if="errors[input.key]"
            :id="`${prefix}-${input.key}-error`"
            class="customer-field-error"
            >{{ errors[input.key].join(' ') }}</small
          >
        </div>
        <div class="admin-field d-flex flex-column gap-2">
          <label :for="`${prefix}-state`">Estado</label>
          <select
            :id="`${prefix}-state`"
            v-model="fields.state"
            name="state"
            class="form-select"
            autocomplete="address-level1"
            required
            :aria-invalid="Boolean(errors.state)"
          >
            <option value="" disabled>Selecione o estado</option>
            <option v-for="state in states" :key="state" :value="state">{{ state }}</option>
          </select>
          <small v-if="errors.state" class="customer-field-error">{{
            errors.state.join(' ')
          }}</small>
        </div>
      </div>
      <div class="customer-actions">
        <button type="submit" class="admin-primary btn btn-primary">
          {{ saving ? 'Salvando…' : 'Salvar endereço' }}
        </button>
        <button
          type="button"
          class="admin-secondary btn btn-outline-secondary"
          @click="emit('cancel')"
        >
          Cancelar
        </button>
      </div>
    </fieldset>
  </form>
</template>
