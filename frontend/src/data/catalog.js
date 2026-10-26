// Catálogo local de demonstração. Os atributos alimentam os filtros da pesquisa.
export const filterDefinitions = {
  all: [],
  notebooks: [['brand', 'Marca']],
  pcs: [['brand', 'Marca']],
  monitors: [['brand', 'Marca']],
  peripherals: [['brand', 'Marca'], ['kind', 'Tipo']],
  processors: [['brand', 'Marca'], ['socket', 'Socket']],
  memory: [['brand', 'Marca'], ['speed', 'Velocidade'], ['capacity', 'Capacidade'], ['ddr', 'DDR']],
  graphics: [['brand', 'Marca'], ['vram', 'Memória de vídeo']],
  motherboards: [['brand', 'Marca'], ['formFactor', 'Formato'], ['ddr', 'DDR'], ['socket', 'Socket']],
  storage: [['kind', 'Tipo'], ['readSpeed', 'Leitura'], ['writeSpeed', 'Gravação'], ['capacity', 'Capacidade']],
  'power-supplies': [['brand', 'Marca'], ['efficiency', 'Certificação 80 Plus'], ['modular', 'Cabeamento']],
  cases: [['brand', 'Marca'], ['formFactor', 'Formato da placa-mãe']],
  cooling: [['brand', 'Marca'], ['kind', 'Tipo']]
};

export const catalog = [
  { category: 'notebooks', name: 'Notebook Gamer Alienware 15', price: 6999, brand: 'Alienware', image: 'notebook.png' },
  { category: 'notebooks', name: 'Notebook Acer Nitro 5', price: 5299, brand: 'Acer', icon: 'bi-laptop' },
  { category: 'pcs', name: 'PC montado Poczy Start', price: 3499, brand: 'Poczy', icon: 'bi-pc-display' },
  { category: 'pcs', name: 'PC montado Gamer Acer', price: 5799, brand: 'Acer', icon: 'bi-pc-display' },
  { category: 'monitors', name: 'Monitor LG 24 polegadas', price: 899, brand: 'LG', icon: 'bi-display' },
  { category: 'monitors', name: 'Monitor Samsung 27 polegadas', price: 1299, brand: 'Samsung', icon: 'bi-display' },
  { category: 'peripherals', name: 'Mouse Logitech', price: 149, brand: 'Logitech', kind: 'Mouse', icon: 'bi-mouse' },
  { category: 'peripherals', name: 'Teclado Redragon', price: 249, brand: 'Redragon', kind: 'Teclado', icon: 'bi-keyboard' },
  { category: 'peripherals', name: 'Headset Logitech', price: 329, brand: 'Logitech', kind: 'Headset', icon: 'bi-headset' },
  { category: 'peripherals', name: 'Fone comum JBL', price: 99, brand: 'JBL', kind: 'Fone comum', icon: 'bi-earbuds' },
  { category: 'processors', name: 'Ryzen 5 5600', price: 899, brand: 'AMD', socket: 'AM4', image: 'cpu-icon.svg' },
  { category: 'processors', name: 'Core i5 12400F', price: 999, brand: 'Intel', socket: 'LGA 1700', image: 'cpu-icon.svg' },
  { category: 'processors', name: 'Ryzen 7 5700X', price: 1399, brand: 'AMD', socket: 'AM4', image: 'cpu-icon.svg' },
  { category: 'processors', name: 'Core i7 12700F', price: 1799, brand: 'Intel', socket: 'LGA 1700', image: 'cpu-icon.svg' },
  { category: 'processors', name: 'Ryzen 5 7600', price: 1499, brand: 'AMD', socket: 'AM5', image: 'cpu-icon.svg' },
  { category: 'memory', name: 'Memória Kingston 16 GB DDR4', price: 299, brand: 'Kingston', speed: '3200 MHz', capacity: '16 GB', ddr: 'DDR4', icon: 'bi-memory' },
  { category: 'memory', name: 'Memória Corsair 32 GB DDR5', price: 699, brand: 'Corsair', speed: '5600 MHz', capacity: '32 GB', ddr: 'DDR5', icon: 'bi-memory' },
  { category: 'memory', name: 'Memória Kingston 8 GB DDR4', price: 159, brand: 'Kingston', speed: '2666 MHz', capacity: '8 GB', ddr: 'DDR4', icon: 'bi-memory' },
  { category: 'graphics', name: 'GeForce RTX 4060 8 GB', price: 1999, brand: 'NVIDIA', vram: '8 GB', image: 'gpu-icon.svg' },
  { category: 'graphics', name: 'Radeon RX 7600 8 GB', price: 1799, brand: 'AMD', vram: '8 GB', image: 'gpu-icon.svg' },
  { category: 'graphics', name: 'GeForce RTX 4070 12 GB', price: 3799, brand: 'NVIDIA', vram: '12 GB', image: 'gpu-icon.svg' },
  { category: 'graphics', name: 'Radeon RX 7800 XT 16 GB', price: 3299, brand: 'AMD', vram: '16 GB', image: 'gpu-icon.svg' },
  { category: 'motherboards', name: 'Placa-mãe ASUS B550M', price: 799, brand: 'ASUS', formFactor: 'Micro ATX', ddr: 'DDR4', socket: 'AM4', icon: 'bi-motherboard' },
  { category: 'motherboards', name: 'Placa-mãe MSI B650', price: 1399, brand: 'MSI', formFactor: 'ATX', ddr: 'DDR5', socket: 'AM5', icon: 'bi-motherboard' },
  { category: 'storage', name: 'SSD Kingston SATA 480 GB', price: 249, kind: 'SATA 3', readSpeed: '500 MB/s', writeSpeed: '450 MB/s', capacity: '480 GB', icon: 'bi-device-ssd' },
  { category: 'storage', name: 'SSD WD NVMe 1 TB', price: 499, kind: 'NVMe', readSpeed: '3500 MB/s', writeSpeed: '3000 MB/s', capacity: '1 TB', icon: 'bi-device-ssd' },
  { category: 'storage', name: 'HD Seagate SATA 2 TB', price: 399, kind: 'HD SATA 3', readSpeed: '190 MB/s', writeSpeed: '180 MB/s', capacity: '2 TB', icon: 'bi-device-hdd' },
  { category: 'power-supplies', name: 'Fonte Corsair 650 W Bronze', price: 549, brand: 'Corsair', efficiency: 'Bronze', modular: 'Não modular', icon: 'bi-lightning-charge' },
  { category: 'power-supplies', name: 'Fonte Cooler Master 750 W Gold', price: 799, brand: 'Cooler Master', efficiency: 'Gold', modular: 'Modular', icon: 'bi-lightning-charge' },
  { category: 'power-supplies', name: 'Fonte Corsair 700 W Silver', price: 649, brand: 'Corsair', efficiency: 'Prata', modular: 'Não modular', icon: 'bi-lightning-charge' },
  { category: 'power-supplies', name: 'Fonte EVGA 600 W', price: 379, brand: 'EVGA', efficiency: 'Sem certificação', modular: 'Semi-modular', icon: 'bi-lightning-charge' },
  { category: 'cases', name: 'Gabinete NZXT ATX', price: 699, brand: 'NZXT', formFactor: 'ATX', icon: 'bi-pc' },
  { category: 'cases', name: 'Gabinete Cooler Master Micro ATX', price: 399, brand: 'Cooler Master', formFactor: 'Micro ATX', icon: 'bi-pc' },
  { category: 'cooling', name: 'Water cooler Corsair 240 mm', price: 599, brand: 'Corsair', kind: 'Water cooler', icon: 'bi-fan' },
  { category: 'cooling', name: 'Air cooler Cooler Master', price: 199, brand: 'Cooler Master', kind: 'Air cooler', icon: 'bi-fan' },
  { category: 'cooling', name: 'Ventoinha NZXT 120 mm', price: 89, brand: 'NZXT', kind: 'Ventoinha', icon: 'bi-fan' }
];

export function matchingProducts(items, category, minPrice, maxPrice, selected) {
  return items.filter(item => {
    if (category !== 'all' && item.category !== category) return false;
    if (minPrice !== null && item.price < minPrice) return false;
    if (maxPrice !== null && item.price > maxPrice) return false;
    return Object.entries(selected).every(([key, values]) => values.length === 0 || values.includes(item[key]));
  });
}
