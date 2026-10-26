import './shared.js';

const products = {
  processors: [
    ['Processador AMD Ryzen 5 5600, 6 núcleos', 'cpu-icon.svg'],
    ['Core i5 12400F', 'cpu-icon.svg'],
    ['Ryzen 7 5700X', 'cpu-icon.svg'],
    ['Core i7 12700F', 'cpu-icon.svg'],
    ['Ryzen 5 7600', 'cpu-icon.svg'],
    ['Core i5 13400F', 'cpu-icon.svg']
  ],
  graphics: [
    ['GeForce RTX 4060', 'gpu-icon.svg'],
    ['Radeon RX 7600', 'gpu-icon.svg'],
    ['GeForce RTX 3060', 'gpu-icon.svg'],
    ['Arc A750', 'gpu-icon.svg'],
    ['GeForce RTX 4070', 'gpu-icon.svg'],
    ['Radeon RX 7800 XT', 'gpu-icon.svg']
  ]
};

function renderProducts(targetId, items) {
  const target = document.getElementById(targetId);
  if (!target) return;
  target.innerHTML = items.map(([name, image]) => `
    <article class="product-card">
      <div class="product-card-inner">
        <div class="product-image"><img src="assets/${image}" alt="" /></div>
        <p class="product-name" title="${name}">${name}</p>
        <p class="product-price"><span class="product-price-label">A partir de</span><span class="product-price-value">R$ 1.299,90</span></p>
      </div>
    </article>
  `).join('');
}

renderProducts('processors', products.processors);
renderProducts('graphics', products.graphics);

const featuredOffers = [
  {
    category: 'NOTEBOOK GAMER',
    name: 'ALIENWARE 15',
    specs: 'Core 5 • RTX 3050 • Tela 165 Hz',
    oldPrice: 'R$ 7.999,00',
    price: 'R$ 6.999,00',
    image: 'assets/notebook.png',
    alt: 'Notebook Alienware 15',
    href: '#products'
  },
  {
    category: 'PLACA DE VÍDEO',
    name: 'GEFORCE RTX 4060',
    specs: '8 GB GDDR6 • DLSS 3 • Ray Tracing',
    oldPrice: 'R$ 2.299,90',
    price: 'R$ 1.999,90',
    image: 'assets/gpu-icon.svg',
    alt: 'Ilustração de placa de vídeo GeForce RTX 4060',
    href: '#graphics'
  },
  {
    category: 'PROCESSADOR',
    name: 'RYZEN 5 5600',
    specs: '6 núcleos • 12 threads • Socket AM4',
    oldPrice: 'R$ 1.299,90',
    price: 'R$ 899,90',
    image: 'assets/cpu-icon.svg',
    alt: 'Ilustração de processador Ryzen 5 5600',
    href: '#processors'
  },
  {
    category: 'PLACA DE VÍDEO',
    name: 'RADEON RX 7600',
    specs: '8 GB GDDR6 • RDNA 3 • Ray Tracing',
    oldPrice: 'R$ 2.199,90',
    price: 'R$ 1.799,90',
    image: 'assets/gpu-icon.svg',
    alt: 'Ilustração de placa de vídeo Radeon RX 7600',
    href: '#graphics'
  },
  {
    category: 'PROCESSADOR',
    name: 'CORE i5 12400F',
    specs: '6 núcleos • 12 threads • Socket LGA 1700',
    oldPrice: 'R$ 1.399,90',
    price: 'R$ 999,90',
    image: 'assets/cpu-icon.svg',
    alt: 'Ilustração de processador Core i5 12400F',
    href: '#processors'
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
  image.classList.toggle('hero-example-icon', offer.image.endsWith('.svg'));
  hero.querySelector('.eyebrow').textContent = offer.category;
  hero.querySelector('h1').textContent = offer.name;
  hero.querySelector('.hero-specs').textContent = offer.specs;
  hero.querySelector('.hero-old-price del').textContent = offer.oldPrice;
  hero.querySelector('.hero-price-value').textContent = offer.price;
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
  restartCarouselTimer();
  document.addEventListener('visibilitychange', restartCarouselTimer);
}
