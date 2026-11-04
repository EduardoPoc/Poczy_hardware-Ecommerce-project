<script setup>
import { computed, ref } from 'vue';
import { categoryUrl } from '../domain/products.js';

const props = defineProps({ categories: { type: Array, default: () => [] } });
const groups = computed(() => {
  const middle = Math.ceil(props.categories.length / 2);
  return [props.categories.slice(0, middle), props.categories.slice(middle)];
});
const selectedTab = ref('terms');
const tabs = ref([]);

function moveTab(event, index) {
  const positions = { ArrowRight: (index + 1) % 2, ArrowLeft: (index + 1) % 2, Home: 0, End: 1 };
  if (!(event.key in positions)) return;
  event.preventDefault();
  const next = positions[event.key];
  selectedTab.value = next === 0 ? 'terms' : 'contact';
  tabs.value[next]?.focus();
}
</script>

<template>
  <footer class="store-footer" aria-labelledby="about-store-title">
    <div class="row g-4">
      <section class="col-12 col-lg-6">
        <p class="eyebrow fw-semibold">CONHEÇA A POCZY</p>
        <h2 id="about-store-title">Seu próximo setup começa aqui.</h2>
        <p class="store-description">
          A Poczy Hardware reúne notebooks, PCs e componentes para quem quer jogar, trabalhar ou dar
          um upgrade no setup. Explore as categorias e encontre as peças para montar um computador
          do seu jeito.
        </p>
        <a
          class="store-explore d-inline-flex align-items-center fw-semibold text-decoration-none"
          href="pesquisa.html"
          >Explorar produtos <i class="bi bi-arrow-right" aria-hidden="true"></i
        ></a>
      </section>
      <nav
        v-for="(group, index) in groups"
        :key="index"
        class="col-6 col-lg-3 store-footer-nav d-flex flex-column align-items-start"
        :aria-label="index ? 'Categorias da loja' : 'Explore a loja'"
      >
        <h3>{{ index ? 'Mais categorias' : 'Explore a loja' }}</h3>
        <a v-for="category in group" :key="category.id" :href="categoryUrl(category)">{{
          category.name
        }}</a>
      </nav>
    </div>
    <div class="store-info-tabs d-flex gap-2" role="tablist" aria-label="Informações da loja">
      <button
        id="terms-tab"
        :ref="(element) => (tabs[0] = element)"
        type="button"
        role="tab"
        :aria-selected="selectedTab === 'terms'"
        aria-controls="terms-panel"
        :tabindex="selectedTab === 'terms' ? 0 : -1"
        @click="selectedTab = 'terms'"
        @keydown="moveTab($event, 0)"
      >
        Termos de uso
      </button>
      <button
        id="contact-tab"
        :ref="(element) => (tabs[1] = element)"
        type="button"
        role="tab"
        :aria-selected="selectedTab === 'contact'"
        aria-controls="contact-panel"
        :tabindex="selectedTab === 'contact' ? 0 : -1"
        @click="selectedTab = 'contact'"
        @keydown="moveTab($event, 1)"
      >
        Contato
      </button>
    </div>
    <section
      id="terms-panel"
      class="store-info-panel"
      role="tabpanel"
      aria-labelledby="terms-tab"
      tabindex="0"
      :hidden="selectedTab !== 'terms'"
    >
      <h3>Termos de uso</h3>
      <p>Os termos de uso da Poczy Hardware serão disponibilizados nesta seção em breve.</p>
    </section>
    <section
      id="contact-panel"
      class="store-info-panel"
      role="tabpanel"
      aria-labelledby="contact-tab"
      tabindex="0"
      :hidden="selectedTab !== 'contact'"
    >
      <h3>Fale com a Poczy Hardware</h3>
      <p>
        Tem dúvidas sobre a loja ou nossos produtos? Em breve, você encontrará aqui nossos canais de
        atendimento e horários de contato.
      </p>
    </section>
    <div class="store-footer-bottom d-flex flex-wrap justify-content-between gap-3">
      <span>Poczy Hardware</span
      ><a href="#">Voltar ao topo <i class="bi bi-arrow-up" aria-hidden="true"></i></a>
    </div>
  </footer>
</template>
