<script setup>
import { computed } from 'vue';
import { specificationLabel } from '../domain/products.js';

const props = defineProps({
  modelValue: { type: Object, required: true },
  options: { type: Object, default: () => ({ brands: [], attributes: {} }) },
  disabled: Boolean,
});
const emit = defineEmits(['change', 'clear']);
const hasFilters = computed(
  () =>
    props.modelValue.minPrice !== '' ||
    props.modelValue.maxPrice !== '' ||
    props.modelValue.brandIds.length ||
    Object.values(props.modelValue.specifications).some((values) => values.length),
);

function toggleBrand(id, checked) {
  const selected = props.modelValue.brandIds;
  emit('change', {
    brandIds: checked ? [...selected, id] : selected.filter((value) => value !== id),
  });
}

function toggleAttribute(key, value, checked) {
  const selected = props.modelValue.specifications[key] || [];
  emit('change', {
    specifications: {
      ...props.modelValue.specifications,
      [key]: checked ? [...selected, value] : selected.filter((item) => !Object.is(item, value)),
    },
  });
}
</script>

<template>
  <div class="filter-sidebar-heading d-flex align-items-center justify-content-between gap-2">
    <h2 id="filter-title">Filtrar produtos</h2>
    <button
      id="clear-filters"
      type="button"
      :disabled="disabled || !hasFilters"
      @click="$emit('clear')"
    >
      Limpar filtros
    </button>
  </div>
  <form id="product-filters" aria-label="Filtros de produtos" @submit.prevent>
    <fieldset class="filter-group" :disabled="disabled">
      <legend>Preço</legend>
      <div class="price-fields d-grid gap-2">
        <label for="price-min"
          >Mínimo (R$)<input
            id="price-min"
            class="form-control"
            name="min"
            type="number"
            min="0"
            step="0.01"
            inputmode="decimal"
            placeholder="0,00"
            :value="modelValue.minPrice"
            @input="$emit('change', { minPrice: $event.target.value })"
        /></label>
        <label for="price-max"
          >Máximo (R$)<input
            id="price-max"
            class="form-control"
            name="max"
            type="number"
            min="0"
            step="0.01"
            inputmode="decimal"
            placeholder="Sem limite"
            :value="modelValue.maxPrice"
            @input="$emit('change', { maxPrice: $event.target.value })"
        /></label>
      </div>
    </fieldset>
    <fieldset v-if="options.brands?.length" class="filter-group" :disabled="disabled">
      <legend>Marca</legend>
      <label
        v-for="brand in options.brands"
        :key="brand.id"
        class="filter-option d-flex align-items-center"
        :for="`brand-${brand.id}`"
      >
        <input
          :id="`brand-${brand.id}`"
          class="form-check-input"
          type="checkbox"
          name="brand_ids"
          :value="brand.id"
          :checked="modelValue.brandIds.includes(brand.id)"
          @change="toggleBrand(brand.id, $event.target.checked)"
        />{{ brand.name }}
      </label>
    </fieldset>
    <fieldset
      v-for="(definition, key) in options.attributes"
      :key="key"
      class="filter-group"
      :disabled="disabled"
    >
      <legend>{{ definition.name }}</legend>
      <label
        v-for="(value, index) in definition.values"
        :key="`${typeof value}:${value}`"
        class="filter-option d-flex align-items-center"
        :for="`filter-${key}-${index}`"
      >
        <input
          :id="`filter-${key}-${index}`"
          class="form-check-input"
          type="checkbox"
          :name="key"
          :checked="(modelValue.specifications[key] || []).includes(value)"
          @change="toggleAttribute(key, value, $event.target.checked)"
        />{{ specificationLabel(value, definition) }}
      </label>
    </fieldset>
  </form>
</template>
