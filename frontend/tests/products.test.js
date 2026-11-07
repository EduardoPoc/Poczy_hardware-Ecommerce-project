import test from 'node:test';
import assert from 'node:assert/strict';
import {
  imageUrl,
  paginationPages,
  productFilter,
  productQuery,
  productSpecifications,
} from '../src/domain/products.js';
import { listProducts, getProduct } from '../src/api/products.js';
import { ApiError } from '../src/api/http.js';

test('os filtros enviados ao backend preservam false, zero e números', () => {
  const query = new URLSearchParams(
    productQuery({
      search: '  Notebook  ',
      categoryId: '31',
      brandIds: [7],
      minPrice: 0,
      specifications: { dedicated_gpu: [false], capacity: [8, 16], empty: [] },
      page: 2,
      perPage: 12,
    }),
  );

  assert.deepEqual(JSON.parse(query.get('filter')), {
    q: 'Notebook',
    category_id: 31,
    brand_ids: [7],
    min_price: 0,
    specifications: { dedicated_gpu: [false], capacity: [8, 16] },
  });
  assert.equal(query.get('page'), '2');
  assert.equal(query.get('per_page'), '12');
});

test('ordenação é enviada à API junto da paginação e dos filtros', () => {
  for (const sort of ['price_asc', 'price_desc', 'newest']) {
    const query = new URLSearchParams(productQuery({ sort, categoryId: 7, page: 2 }));
    assert.equal(JSON.parse(query.get('filter')).sort, sort);
    assert.equal(JSON.parse(query.get('filter')).category_id, 7);
    assert.equal(query.get('page'), '2');
  }
});

test('limpar filtros não envia critérios vazios ou categoria fictícia', () => {
  assert.deepEqual(
    productFilter({
      search: ' ',
      categoryId: '',
      brandIds: [],
      minPrice: '',
      maxPrice: '',
      specifications: { type: [] },
    }),
    {},
  );
});

test('características usam os nomes e unidades do backend sem perder false', () => {
  const result = productSpecifications(
    { specifications: { ram: 16, gpu: false } },
    { attributes: { ram: { name: 'Memória', unit: 'GB' }, gpu: { name: 'GPU dedicada' } } },
  );
  assert.deepEqual(result, [
    { key: 'ram', label: 'Memória', value: '16 GB' },
    { key: 'gpu', label: 'GPU dedicada', value: 'Não' },
  ]);
});

test('imagens rejeitam protocolos executáveis vindos do servidor', () => {
  assert.equal(imageUrl('javascript:alert(1)'), '');
  assert.equal(imageUrl('data:text/html,<script>alert(1)</script>'), '');
  assert.equal(imageUrl('https://example.com/produto.jpg'), 'https://example.com/produto.jpg');
});

test('paginação representa os intervalos sem repetir páginas', () => {
  assert.deepEqual(paginationPages(5, 10), [1, 'ellipsis-4', 4, 5, 6, 'ellipsis-10', 10]);
  assert.deepEqual(paginationPages(1, 1), [1]);
});

test('listagem usa a pesquisa paginada da API e os metadados do servidor', async (context) => {
  let requested;
  context.mock.method(globalThis, 'fetch', async (url) => {
    if (url === '/sanctum/csrf-cookie') return new Response(null, { status: 204 });
    requested = url;
    return Response.json({
      data: [{ id: 52, name: 'Produto cadastrado no banco' }],
      meta: { current_page: 2, last_page: 3, total: 27 },
    });
  });

  const result = await listProducts({ search: 'Produto', page: 2 });
  assert.match(requested, /^\/api\/products\/filter\?/);
  assert.equal(
    JSON.parse(new URL(requested, 'https://loja.example').searchParams.get('filter')).q,
    'Produto',
  );
  assert.equal(result.products[0].id, 52);
  assert.equal(result.pagination.total, 27);
});

test('resposta inválida não é substituída por catálogo fictício', async (context) => {
  context.mock.method(globalThis, 'fetch', async () => Response.json({ data: [] }));
  await assert.rejects(listProducts(), ApiError);
});

test('identificador antigo ou inválido não consulta produto fictício', async (context) => {
  const fetch = context.mock.method(globalThis, 'fetch');
  await assert.rejects(getProduct('notebook-demonstrativo'), (error) => error.status === 404);
  assert.equal(fetch.mock.calls.length, 0);
});

test('cancelamento de pesquisa não vira erro de conexão', async (context) => {
  const controller = new AbortController();
  controller.abort();
  context.mock.method(globalThis, 'fetch', async () => {
    throw new DOMException('Cancelado', 'AbortError');
  });
  await assert.rejects(
    listProducts({}, { signal: controller.signal }),
    (error) => error.name === 'AbortError',
  );
});
