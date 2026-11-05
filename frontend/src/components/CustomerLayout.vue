<script setup>
import { computed, ref } from 'vue';
import { apiRequest, errorMessage } from '../api/http.js';
import logo from '../../assets/logo1.svg';

const props = defineProps({
  user: { type: Object, required: true },
  section: { type: String, default: 'overview' },
  title: { type: String, required: true },
});
const menuOpen = ref(false);
const loggingOut = ref(false);
const error = ref('');
const initials = computed(() =>
  props.user.name
    .trim()
    .split(/\s+/)
    .slice(0, 2)
    .map((part) => part[0])
    .join('')
    .toUpperCase(),
);
const navigation = [
  { id: 'overview', label: 'Visão geral', icon: 'bi-grid-1x2' },
  { id: 'addresses', label: 'Meus endereços', icon: 'bi-geo-alt' },
  { id: 'orders', label: 'Meus pedidos', icon: 'bi-bag-check' },
];

async function logout() {
  if (loggingOut.value) return;
  loggingOut.value = true;
  error.value = '';
  try {
    await apiRequest('/api/logout', { method: 'POST', data: {} });
    location.replace('/login');
  } catch (failure) {
    error.value = errorMessage(failure);
    loggingOut.value = false;
  }
}
</script>

<template>
  <a class="admin-skip position-fixed" href="#customer-title">Pular para o conteúdo</a>
  <div class="admin-shell d-grid min-vh-100">
    <button
      class="admin-menu-overlay"
      :hidden="!menuOpen"
      type="button"
      aria-label="Fechar menu de navegação"
      @click="menuOpen = false"
    ></button>
    <aside
      id="customer-sidebar"
      class="admin-sidebar d-flex flex-column overflow-y-auto"
      :class="{ 'is-open': menuOpen }"
      aria-label="Navegação da minha conta"
      @keydown.esc="menuOpen = false"
    >
      <a class="admin-brand d-flex align-items-center gap-2" href="/"
        ><img :src="logo" alt="Poczy Hardware — início" /><span>CONTA</span></a
      >
      <p class="admin-nav-label d-block fw-semibold">SEU ESPAÇO POCZY</p>
      <nav class="admin-nav" aria-label="Áreas da conta">
        <a
          v-for="item in navigation"
          :key="item.id"
          :href="`/conta#${item.id}`"
          :aria-current="section === item.id ? 'page' : undefined"
          @click="menuOpen = false"
          ><i class="bi" :class="item.icon" aria-hidden="true"></i>{{ item.label }}</a
        >
      </nav>
      <div class="admin-sidebar-bottom mt-auto">
        <div class="admin-shop-card d-grid">
          <i class="bi bi-cpu" aria-hidden="true"></i
          ><strong>Seu próximo setup<br />começa aqui.</strong
          ><a href="/pesquisa"
            >Explorar a loja <i class="bi bi-arrow-up-right" aria-hidden="true"></i
          ></a>
        </div>
        <a class="customer-sidebar-cart" href="/carrinho"
          ><i class="bi bi-cart3" aria-hidden="true"></i> Meu carrinho</a
        >
        <a v-if="user.role === 'ADMIN'" class="customer-sidebar-cart" href="/admin"
          ><i class="bi bi-shield-check" aria-hidden="true"></i> Administração</a
        >
        <button id="admin-logout" type="button" :disabled="loggingOut" @click="logout">
          <i class="bi bi-box-arrow-left" aria-hidden="true"></i
          >{{ loggingOut ? 'Saindo…' : 'Sair da conta' }}
        </button>
        <p v-if="error" class="customer-field-error" role="alert">{{ error }}</p>
        <span class="admin-sidebar-footnote d-block">Poczy Hardware · Minha conta</span>
      </div>
    </aside>
    <div class="admin-main">
      <header class="admin-topbar d-flex align-items-center">
        <button
          type="button"
          class="admin-icon-button admin-menu-toggle p-0 btn btn-outline-secondary"
          :aria-expanded="menuOpen"
          aria-controls="customer-sidebar"
          aria-label="Abrir menu"
          @click="menuOpen = !menuOpen"
        >
          <i class="bi bi-list" aria-hidden="true"></i>
        </button>
        <div class="admin-location align-items-center">
          <span>Minha conta</span><i class="bi bi-chevron-right" aria-hidden="true"></i
          ><strong>{{ title }}</strong>
        </div>
        <a
          class="admin-view-store d-inline-flex gap-2 align-items-center ms-auto text-nowrap"
          href="/"
          >Voltar à loja <i class="bi bi-arrow-up-right" aria-hidden="true"></i
        ></a>
        <div class="admin-profile align-items-center">
          <span class="admin-avatar d-grid fw-bold">{{ initials }}</span>
          <div>
            <strong>{{ user.name }}</strong
            ><span>Cliente Poczy</span>
          </div>
        </div>
      </header>
      <main class="admin-workspace customer-workspace" aria-labelledby="customer-title">
        <slot />
      </main>
    </div>
  </div>
</template>
