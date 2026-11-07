<script setup>
import { computed } from 'vue';
import { paginationPages } from '../domain.js';

const props = defineProps({ pagination: { type: Object, required: true }, disabled: Boolean });
defineEmits(['change']);
const pages = computed(() =>
  paginationPages(props.pagination.current_page, props.pagination.last_page),
);
</script>

<template>
  <nav
    v-if="pagination.total > 0"
    id="results-pagination"
    class="results-pagination text-center"
    aria-label="Páginas dos resultados"
  >
    <p class="pagination-status">
      Página {{ pagination.current_page }} de {{ pagination.last_page }}
    </p>
    <ul
      class="pagination pagination-controls flex-wrap align-items-center justify-content-center gap-2 mb-0"
    >
      <li class="page-item" :class="{ disabled: disabled || pagination.current_page === 1 }">
        <button
          class="page-link"
          type="button"
          :disabled="disabled || pagination.current_page === 1"
          @click="$emit('change', pagination.current_page - 1)"
        >
          Anterior
        </button>
      </li>
      <li
        v-for="page in pages"
        :key="page"
        :class="
          typeof page === 'number'
            ? ['page-item', { active: page === pagination.current_page }]
            : 'pagination-ellipsis'
        "
      >
        <button
          v-if="typeof page === 'number'"
          class="page-link"
          type="button"
          :disabled="disabled"
          :aria-current="page === pagination.current_page ? 'page' : undefined"
          :aria-label="`Ir para a página ${page}`"
          @click="$emit('change', page)"
        >
          {{ page }}
        </button>
        <span v-else aria-hidden="true">…</span>
      </li>
      <li
        class="page-item"
        :class="{ disabled: disabled || pagination.current_page === pagination.last_page }"
      >
        <button
          class="page-link"
          type="button"
          :disabled="disabled || pagination.current_page === pagination.last_page"
          @click="$emit('change', pagination.current_page + 1)"
        >
          Próxima
        </button>
      </li>
    </ul>
  </nav>
</template>
