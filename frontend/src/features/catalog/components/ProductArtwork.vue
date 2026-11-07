<script setup>
import { computed, ref, watch } from 'vue';
import { imageUrl } from '../../../shared/lib/images.js';

const props = defineProps({ product: { type: Object, required: true } });
const failed = ref(false);
const source = computed(() => imageUrl(props.product.image_url));
watch(source, () => {
  failed.value = false;
});
</script>

<template>
  <img
    v-if="source && !failed"
    class="catalog-product-photo"
    :src="source"
    :alt="product.name"
    @error="failed = true"
  />
  <i
    v-else
    class="bi bi-box-seam catalog-icon"
    role="img"
    :aria-label="`Imagem indisponível de ${product.name}`"
  ></i>
</template>
