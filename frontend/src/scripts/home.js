import './shared.js';
import { catalog } from '../data/catalog.js';
import { productCard, productUrl, money } from './products.js';
const notebookImage = new URL('../../assets/notebook.png', import.meta.url).href;
const cpuIcon = new URL('../../assets/cpu-icon.svg', import.meta.url).href;
const gpuIcon = new URL('../../assets/gpu-icon.svg', import.meta.url).href;

for (const category of ['processors', 'graphics']) {
  document.getElementById(category).innerHTML = catalog
    .filter(product => product.category === category).map(productCard).join('');
}

const featuredOffers = [
  {
    category: 'NOTEBOOK GAMER',
    name: 'ALIENWARE 15',
    specs: 'Core 5 • RTX 3050 • Tela 165 Hz',
    oldPrice: 'R$ 7.999,00',
    price: 'R$ 6.999,00',
    image: notebookImage,
    isIcon: false,
    alt: 'Notebook Alienware 15',
    href: productUrl(catalog.find(item => item.name === 'Notebook Gamer Alienware 15'))
  },
  {
    category: 'PLACA DE VÍDEO',
    name: 'GEFORCE RTX 4060',
    specs: '8 GB GDDR6 • DLSS 3 • Ray Tracing',
    oldPrice: 'R$ 2.299,90',
    price: 'R$ 1.999,90',
    image: gpuIcon,
    isIcon: true,
    alt: 'Ilustração de placa de vídeo GeForce RTX 4060',
    href: productUrl(catalog.find(item => item.name === 'GeForce RTX 4060 8 GB'))
  },
  {
    category: 'PROCESSADOR',
    name: 'RYZEN 5 5600',
    specs: '6 núcleos • 12 threads • Socket AM4',
    oldPrice: 'R$ 1.299,90',
    price: 'R$ 899,90',
    image: cpuIcon,
    isIcon: true,
    alt: 'Ilustração de processador Ryzen 5 5600',
    href: productUrl(catalog.find(item => item.name === 'Ryzen 5 5600'))
  },
  {
    category: 'PLACA DE VÍDEO',
    name: 'RADEON RX 7600',
    specs: '8 GB GDDR6 • RDNA 3 • Ray Tracing',
    oldPrice: 'R$ 2.199,90',
    price: 'R$ 1.799,90',
    image: gpuIcon,
    isIcon: true,
    alt: 'Ilustração de placa de vídeo Radeon RX 7600',
    href: productUrl(catalog.find(item => item.name === 'Radeon RX 7600 8 GB'))
  },
  {
    category: 'PROCESSADOR',
    name: 'CORE i5 12400F',
    specs: '6 núcleos • 12 threads • Socket LGA 1700',
    oldPrice: 'R$ 1.399,90',
    price: 'R$ 999,90',
    image: cpuIcon,
    isIcon: true,
    alt: 'Ilustração de processador Core i5 12400F',
    href: productUrl(catalog.find(item => item.name === 'Core i5 12400F'))
  }
];

const hero = document.querySelector('.hero-banner');
const offerButtons = hero ? hero.querySelectorAll('[data-slide]') : [];
let currentOfferIndex = 0;
let carouselTimer;
let offerTransitionTimer;

function showOffer(index) {
  window.clearTimeout(offerTransitionTimer);
  if (index === currentOfferIndex) {
    hero.classList.remove('is-changing');
    return;
  }
  hero.classList.add('is-changing');
  const transitionDelay = window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 220;
  offerTransitionTimer = window.setTimeout(() => {
    renderOffer(index);
    hero.classList.remove('is-changing');
  }, transitionDelay);
}

function renderOffer(index) {
  currentOfferIndex = index;
  const offer = featuredOffers[index];
  const image = hero.querySelector('.hero-image-wrap img');
  image.src = offer.image;
  image.alt = offer.alt;
  image.classList.toggle('hero-example-icon', offer.isIcon);
  hero.querySelector('.eyebrow').textContent = offer.category;
  hero.querySelector('h1').textContent = offer.name;
  hero.querySelector('.hero-specs').textContent = offer.specs;
  hero.querySelector('.hero-old-price del').textContent = offer.oldPrice;
  const productId = new URL(offer.href, location.href).searchParams.get('id');
  hero.querySelector('.hero-price-value').textContent = money(catalog.find(item => item.id === productId).price);
  hero.querySelector('.hero-link').href = offer.href;

  offerButtons.forEach((button, buttonIndex) => {
    const active = buttonIndex === index;
    button.classList.toggle('active', active);
    if (active) button.setAttribute('aria-current', 'true');
    else button.removeAttribute('aria-current');
  });
}

function restartCarouselTimer() {
  window.clearInterval(carouselTimer);
  if (!hero || document.hidden) return;
  carouselTimer = window.setInterval(() => {
    showOffer((currentOfferIndex + 1) % featuredOffers.length);
  }, 4000);
}

offerButtons.forEach(button => {
  button.addEventListener('click', () => {
    showOffer(Number(button.dataset.slide));
    restartCarouselTimer();
  });
});

if (hero) {
  featuredOffers.forEach(offer => {
    const image = new Image();
    image.src = offer.image;
  });
  renderOffer(0);
  restartCarouselTimer();
  document.addEventListener('visibilitychange', restartCarouselTimer);
}
