<script setup>
defineProps({ loading: Boolean, error: { type: String, default: '' } });
defineEmits(['retry']);
</script>

<template>
  <p v-if="loading" class="py-4 text-center" role="status">
    <span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Carregando dados
    da loja…
  </p>
  <div v-else-if="error" class="alert alert-danger my-3" role="alert">
    <p class="mb-2">{{ error }}</p>
    <button class="btn btn-outline-secondary" type="button" @click="$emit('retry')">
      Tentar novamente
    </button>
  </div>
</template>
