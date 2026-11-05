<script setup>
import { computed, onMounted, ref } from 'vue';
import { currentUser } from '../api/auth.js';
import { useCart } from '../stores/cart.js';
import logo from '../../assets/logo1.svg';

const props = defineProps({ searchTerm: { type: String, default: '' } });
const search = ref(props.searchTerm);
const searchInput = ref(null);
const user = ref(null);
const { quantity, load } = useCart();
const accountLabel = computed(() =>
  user.value?.role === 'ADMIN' ? 'Administração' : user.value ? 'Minha conta' : 'Entrar',
);
const accountUrl = computed(() =>
  user.value?.role === 'ADMIN' ? '/admin' : user.value ? '/conta' : '/login',
);

function clearSearch() {
  search.value = '';
  searchInput.value?.focus();
}

onMounted(async () => {
  load();
  user.value = await currentUser();
});
</script>

<template>
  <header class="site-header position-relative align-items-center">
    <a class="brand-logo d-block" href="/" aria-label="Poczy Hardware — início">
      <img :src="logo" alt="Poczy Hardware" />
    </a>
    <form class="search-form d-flex overflow-hidden" role="search" action="/pesquisa" method="get">
      <label class="visually-hidden" for="product-search">Buscar produtos</label>
      <i class="bi bi-search search-field-icon align-self-center" aria-hidden="true"></i>
      <input
        id="product-search"
        ref="searchInput"
        v-model="search"
        class="form-control"
        name="q"
        type="search"
        placeholder="Buscar produtos..."
        maxlength="150"
      />
      <button
        class="search-clear"
        type="button"
        aria-label="Limpar busca"
        :hidden="!search"
        @click="clearSearch"
      >
        <i class="bi bi-x-lg" aria-hidden="true"></i>
      </button>
      <button class="search-submit" type="submit" aria-label="Buscar">
        <i class="bi bi-search" aria-hidden="true"></i>
      </button>
    </form>
    <a
      class="cart-link fw-semibold text-decoration-none text-nowrap ms-auto d-inline-flex align-items-center gap-2"
      href="/carrinho"
    >
      <i class="bi bi-cart3" aria-hidden="true"></i> Carrinho
      <span class="cart-count fw-bold badge" data-cart-count :hidden="!quantity">{{
        quantity
      }}</span>
    </a>
    <a
      class="login-link fw-semibold text-decoration-none text-nowrap d-inline-flex align-items-center gap-2"
      :href="accountUrl"
    >
      <i class="bi bi-person" aria-hidden="true"></i
      ><span data-account-label>{{ accountLabel }}</span>
    </a>
  </header>
</template>
