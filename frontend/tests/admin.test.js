import test from 'node:test';
import assert from 'node:assert/strict';
import { readPayload } from '../src/features/admin/payload.js';
import { filteredRecords, paginateRecords } from '../src/features/admin/listing.js';
import { cents, moneyFromCents } from '../src/shared/lib/money.js';
import { listingResults } from '../src/features/admin/views.js';
import { orderForm } from '../src/features/admin/forms.js';
import { allowedOrderTransitions } from '../src/features/orders/domain.js';

// Only the form interface consumed by readPayload is needed for these business rules.
function formWith(values, prefixes = []) {
  const controls = Object.fromEntries(
    Object.entries(values).map(([name, value]) => [
      name,
      value instanceof File
        ? { files: [value] }
        : typeof value === 'boolean'
          ? { checked: value }
          : { value: String(value) },
    ]),
  );
  return {
    elements: { ...controls, namedItem: (name) => controls[name] },
    querySelectorAll: () => prefixes.map((prefix) => ({ dataset: { attributePrefix: prefix } })),
  };
}

const category = {
  id: 7,
  attributes: {
    wireless: { type: 'boolean' },
    capacity: { type: 'integer' },
    weight: { type: 'number' },
    optional: { type: 'text' },
    brand: { type: 'text' },
  },
};
const productValues = {
  name: '  Peça  ',
  category_id: '7',
  brand_id: '',
  description: '',
  price: '0.10',
  cost: '0.00',
  is_active: true,
  stock: '3',
  'specifications.wireless': 'false',
  'specifications.capacity': '0',
  'specifications.weight': '1.25',
  'specifications.optional': '',
};
const productPayload = (overrides = {}, record = null) =>
  readPayload(
    'products',
    formWith({ ...productValues, ...overrides }),
    { categories: [category] },
    record,
  );

test('formulário de produto preserva false, zero, decimais e valores monetários exatos', () => {
  const payload = productPayload();
  assert.equal(payload.name, 'Peça');
  assert.equal(payload.price, '0.10');
  assert.equal(payload.cost, '0.00');
  assert.equal(payload.brand_id, null);
  assert.equal(payload.stock, 3);
  assert.deepEqual(payload.specifications, { wireless: false, capacity: 0, weight: 1.25 });
});

test('edição do produto não altera estoque fora das movimentações', () => {
  assert.equal(Object.hasOwn(productPayload({}, { id: 1, stock: 3 }), 'stock'), false);
});

test('produtos ativos sem estoque permanecem no catálogo como indisponíveis', () => {
  assert.equal(productPayload({ stock: '0' }).stock, 0);
  assert.equal(productPayload({}, { id: 1, stock: 0 }).is_active, true);
  assert.equal(productPayload({ is_active: false }, { id: 1, stock: 0 }).is_active, false);
});

test('preços respeitam precisão e limites da API', () => {
  for (const price of ['0', '-1', '1.001', '100000000.00', 'Infinity', 'abc']) {
    assert.throws(() => productPayload({ price }), /monetários/);
  }
  assert.equal(productPayload({ price: '99999999.99' }).price, '99999999.99');
  assert.throws(() => productPayload({ cost: '-0.01' }), /monetários/);
});

test('características rejeitam booleanos ambíguos e inteiros inválidos', () => {
  assert.throws(() => productPayload({ 'specifications.wireless': '0' }), /true ou false/);
  assert.throws(() => productPayload({ 'specifications.capacity': '1.5' }), /numéricos/);
  assert.throws(() => productPayload({ 'specifications.weight': 'Infinity' }), /numéricos/);
});

test('imagem com formato inválido ou acima de 5 MB e descrição longa são rejeitadas', () => {
  assert.throws(
    () => productPayload({ image: new File(['svg'], 'imagem.svg', { type: 'image/svg+xml' }) }),
    /PNG, JPG, JPEG ou WebP/,
  );
  assert.throws(
    () => productPayload({ image: new File(['png'], 'imagem.php', { type: 'image/png' }) }),
    /PNG, JPG, JPEG ou WebP/,
  );
  assert.throws(
    () =>
      productPayload({
        image: new File([new Uint8Array(5 * 1024 * 1024 + 1)], 'imagem.png', { type: 'image/png' }),
      }),
    /5 MB/,
  );
  assert.throws(() => productPayload({ description: 'á'.repeat(32768) }), /65.535 bytes/);
});

test('upload mantém os dados tipados em multipart e preserva a imagem ao editar sem arquivo', () => {
  const image = new File(['png'], 'imagem.png', { type: 'image/png' });
  const body = productPayload({ image, is_active: false });
  assert.ok(body instanceof FormData);
  assert.equal(body.get('image'), image);
  assert.deepEqual(JSON.parse(body.get('specifications')), {
    wireless: false,
    capacity: 0,
    weight: 1.25,
  });
  assert.equal(body.get('is_active'), '0');
  assert.equal(body.get('brand_id'), '');
  assert.equal(body.get('price'), '0.10');
  assert.equal(Object.hasOwn(productPayload({}, { id: 1, stock: 3 }), 'image'), false);
  assert.equal(Object.hasOwn(productPayload(), 'image_url'), false);
});

test('ajuste de estoque aceita saldo negativo; entrada e saída exigem quantidade positiva', () => {
  const payload = (type, quantity) =>
    readPayload('stock', formWith({ type, quantity, reason: 'Conferência' }));
  assert.equal(payload('ADJUSTMENT', -3).quantity, -3);
  for (const [type, quantity] of [
    ['INBOUND', -1],
    ['OUTBOUND', 0],
    ['ADJUSTMENT', 0],
    ['INBOUND', 1.5],
    ['INBOUND', 2147483648],
  ]) {
    assert.throws(() => payload(type, quantity), /quantidade inteira/);
  }
});

test('status do pedido permite somente a próxima transição', () => {
  const payload = (status, current) =>
    readPayload('order', formWith({ status }), {}, { status: current });
  assert.deepEqual(payload('CANCELLED', 'PENDING'), { status: 'CANCELLED' });
  assert.throws(() => payload('DELIVERED', 'PENDING'), /próxima etapa/);
  assert.throws(() => payload('PAID', 'DELIVERED'), /próxima etapa/);
});

test('admin respeita a sequência pago, enviado e entregue e só cancela pendentes', () => {
  const record = { id: 31, status: 'PAID', payment_method: 'FAKE_PIX', total: '49.90' };
  const html = orderForm(record);
  assert.match(html, /value="SHIPPED"/);
  assert.doesNotMatch(html, /value="CANCELLED"/);
  assert.deepEqual(allowedOrderTransitions(record), ['SHIPPED']);
  assert.deepEqual(allowedOrderTransitions({ status: 'SHIPPED' }), ['DELIVERED']);
  for (const status of ['DELIVERED', 'CANCELLED']) {
    assert.deepEqual(allowedOrderTransitions({ status }), []);
    assert.throws(() => readPayload('order', formWith({ status }), {}, record), /próxima etapa/);
  }
});

test('ações administrativas respeitam as transições retornadas pela API', () => {
  const record = { status: 'PAID', payment_method: 'FAKE_PIX', allowed_statuses: ['CANCELLED'] };
  assert.deepEqual(allowedOrderTransitions(record), ['CANCELLED']);
  assert.throws(
    () => readPayload('order', formWith({ status: 'DELIVERED' }), {}, record),
    /próxima etapa/,
  );
  assert.deepEqual(allowedOrderTransitions({ status: 'PAID', payment_method: 'PIX' }), ['SHIPPED']);
});

test('atributos da categoria mantêm opções e limites tipados', () => {
  const form = formWith(
    {
      name: 'Categoria',
      description: '',
      is_active: true,
      'a.key': 'wireless',
      'a.name': 'Sem fio',
      'a.type': 'boolean',
      'a.required': false,
      'a.is_filterable': true,
      'a.options': 'true\nfalse',
      'b.key': 'capacity',
      'b.name': 'Capacidade',
      'b.type': 'integer',
      'b.required': true,
      'b.is_filterable': false,
      'b.minimum': '0',
      'b.options': '0\n8',
    },
    ['a', 'b'],
  );
  const { attributes } = readPayload('categories', form);
  assert.deepEqual(attributes.wireless.options, [true, false]);
  assert.deepEqual(attributes.capacity.options, [0, 8]);
  assert.equal(attributes.capacity.minimum, 0);
  assert.equal(attributes.capacity.is_filterable, false);
});

test('atributos duplicados são rejeitados antes de sobrescrever a definição', () => {
  const form = formWith(
    {
      'a.key': 'capacity',
      'a.name': 'RAM',
      'a.type': 'integer',
      'a.required': true,
      'a.is_filterable': true,
      'b.key': 'capacity',
    },
    ['a', 'b'],
  );
  assert.throws(() => readPayload('categories', form), /identificador único/);
});

const products = [
  { id: 1, name: 'Mouse', stock: 0, is_active: true, category_id: 7, brand: { name: 'Poczy' } },
  { id: 2, name: 'Memória', stock: 1, is_active: false, category_id: 7 },
  { id: 3, name: 'Monitor', stock: 5, is_active: true, category_id: 8 },
  { id: 4, name: 'SSD', stock: 6, is_active: true, category_id: 7 },
];
const filters = (overrides = {}) => ({ q: '', status: '', category: '', ...overrides });

test('filtros administrativos combinam texto, categoria e situação sem alterar os dados', () => {
  const original = structuredClone(products);
  const result = filteredRecords(
    'products',
    { products },
    filters({ q: 'POCZY', category: '7', status: 'active' }),
  );
  assert.deepEqual(
    result.map(({ id }) => id),
    [1],
  );
  assert.deepEqual(products, original);
});

test('faixas de estoque distinguem zero, uma a cinco unidades e disponível', () => {
  for (const [status, expected] of [
    ['out', [1]],
    ['low', [2, 3]],
    ['available', [4]],
  ]) {
    assert.deepEqual(
      filteredRecords('stock', { products }, filters({ status })).map(({ id }) => id),
      expected,
    );
  }
});

test('pedidos são ordenados por data e desempate por ID', () => {
  const orders = [
    { id: 1, created_at: '2026-11-01' },
    { id: 2, created_at: '2026-11-02' },
    { id: 3, created_at: '2026-11-02' },
  ];
  assert.deepEqual(
    filteredRecords('orders', { orders }, filters()).map(({ id }) => id),
    [3, 2, 1],
  );
});

test('paginação retorna à última página válida quando os filtros reduzem o resultado', () => {
  const records = Array.from({ length: 11 }, (_, id) => ({ id }));
  assert.deepEqual(paginateRecords(records, 9), {
    current: 2,
    pages: 2,
    visible: [{ id: 10 }],
    label: '11–11 de 11 registros',
  });
  assert.deepEqual(paginateRecords([], 9), {
    current: 1,
    pages: 1,
    visible: [],
    label: '0 registros',
  });
});

test('nomes vindos da API são escapados ao gerar tabelas administrativas', () => {
  const { html } = listingResults(
    'brands',
    { brands: [{ id: 1, name: '<script>alert(1)</script>' }], products: [] },
    filters(),
  );
  assert.equal(html.includes('<script>'), false);
  assert.match(html, /&lt;script&gt;/);
});

test('totais administrativos somam centavos sem erro de ponto flutuante', () => {
  assert.equal(moneyFromCents(cents('0.10') + cents('0.20')), 'R$ 0,30');
  assert.equal(moneyFromCents(cents('-1234.50')), '-R$ 1.234,50');
});
