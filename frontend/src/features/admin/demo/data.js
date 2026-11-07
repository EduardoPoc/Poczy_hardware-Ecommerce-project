// Dados públicos e fictícios, exclusivos da prévia de consulta. Nenhuma escrita é enviada.
import { catalog, filterDefinitions } from './catalog.js';

const categoryNames = {
  notebooks: 'Notebooks',
  pcs: 'PCs montados',
  monitors: 'Monitores',
  peripherals: 'Periféricos',
  processors: 'Processadores',
  memory: 'Memórias RAM',
  graphics: 'Placas de vídeo',
  motherboards: 'Placas-mãe',
  storage: 'SSDs e HDs',
  'power-supplies': 'Fontes',
  cases: 'Gabinetes',
  cooling: 'Refrigeração',
};
const brands = [...new Set(catalog.map((product) => product.brand).filter(Boolean))]
  .sort()
  .map((name, index) => ({ id: index + 1, name }));
const categories = Object.entries(categoryNames).map(([key, name], index) => ({
  id: index + 1,
  name,
  is_active: true,
  description: `Seleção de ${name.toLowerCase()} para o seu setup.`,
  attributes: Object.fromEntries(
    filterDefinitions[key]
      .filter(([field]) => field !== 'brand')
      .map(([field, label]) => [
        field,
        { name: label, type: 'text', required: false, is_filterable: true },
      ]),
  ),
  key,
}));
const products = catalog.map((product, index) => {
  const category = categories.find((item) => item.key === product.category);
  const brand = brands.find((item) => item.name === product.brand) || null;
  return {
    id: index + 1,
    name: product.name,
    category_id: category.id,
    brand_id: brand?.id ?? null,
    category,
    brand,
    price: product.price.toFixed(2),
    cost: null,
    stock: [12, 4, 0, 23, 3, 18, 7, 32][index % 8],
    is_active: index !== 16,
    description: 'Produto ilustrativo da demonstração.',
    image_url: null,
    specifications: Object.fromEntries(
      filterDefinitions[product.category]
        .filter(([field]) => field !== 'brand' && product[field] !== undefined)
        .map(([field]) => [field, product[field]]),
    ),
    created_at: '2026-11-01T12:00:00Z',
  };
});
const states = ['PENDING', 'PAID', 'SHIPPED', 'DELIVERED', 'PENDING', 'CANCELLED'];
const orders = states.map((status, index) => {
  const product = products[index];
  return {
    id: 1053 - index,
    user_id: 101 + index,
    status,
    payment_method: ['PIX', 'CREDIT_CARD', 'BOLETO'][index % 3],
    products_total: product.price,
    shipping_total: '0.00',
    total: product.price,
    items_count: 1,
    items_quantity: 1,
    created_at: `2026-11-0${3 - Math.floor(index / 2)}T${String(15 - index).padStart(2, '0')}:30:00Z`,
    items: [
      {
        id: index + 1,
        product_id: product.id,
        product_name_snapshot: product.name,
        category_name_snapshot: product.category.name,
        quantity: 1,
        unit_price: product.price,
        line_total: product.price,
      },
    ],
    shipping_address: null,
    status_history: [{ id: index + 1, status, changed_at: `2026-11-03T12:00:00Z` }],
  };
});
export const demoData = { products, categories, brands, orders };
export const demoMovements = (productId) => [
  {
    id: 2,
    product_id: productId,
    type: 'OUTBOUND',
    quantity: 2,
    reason: 'Saída ilustrativa',
    created_at: '2026-11-03T12:30:00Z',
  },
  {
    id: 1,
    product_id: productId,
    type: 'INBOUND',
    quantity: 10,
    reason: 'Reposição ilustrativa',
    created_at: '2026-11-02T14:00:00Z',
  },
];
