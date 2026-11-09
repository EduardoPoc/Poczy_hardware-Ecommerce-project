import test from 'node:test';
import assert from 'node:assert/strict';
import {
  allRecords,
  getAdminRecord,
  saveAdminRecord,
  deleteAdminRecord,
} from '../src/features/admin/api.js';

function mockSession(context, handler) {
  globalThis.document = { cookie: 'XSRF-TOKEN=admin-token' };
  context.after(() => {
    delete globalThis.document;
  });
  context.mock.method(globalThis, 'fetch', async (path, options) =>
    path === '/sanctum/csrf-cookie' ? new Response(null, { status: 204 }) : handler(path, options),
  );
}

test('administrative lists follow server pagination without dropping records', async (context) => {
  const requested = [];
  mockSession(context, (path) => {
    requested.push(path);
    const page = Number(new URL(path, 'http://localhost').searchParams.get('page'));
    return Response.json({ data: [{ id: page }], meta: { last_page: 2 } });
  });
  assert.deepEqual(await allRecords('/api/admin/orders'), [{ id: 1 }, { id: 2 }]);
  assert.equal(requested.length, 2);
  assert.match(requested[1], /page=2$/);
});

test('stock movements, updates and deletion use their own endpoints and methods', async (context) => {
  const requested = [];
  mockSession(context, (path, options) => {
    requested.push({
      path,
      method: options.method,
      body: options.body && JSON.parse(options.body),
    });
    assert.equal(options.headers['X-XSRF-TOKEN'], 'admin-token');
    return new Response(null, { status: 204 });
  });
  await saveAdminRecord(
    { kind: 'stock', resource: 'products', record: { id: 7 } },
    { type: 'INBOUND', quantity: 2 },
  );
  await saveAdminRecord(
    { kind: 'order', resource: 'orders', record: { id: 8 } },
    { status: 'SHIPPED' },
  );
  await deleteAdminRecord('brands', 9);
  assert.deepEqual(requested, [
    {
      path: '/api/admin/products/7/stock-movements',
      method: 'POST',
      body: { type: 'INBOUND', quantity: 2 },
    },
    { path: '/api/admin/orders/8', method: 'PATCH', body: { status: 'SHIPPED' } },
    { path: '/api/admin/brands/9', method: 'DELETE', body: undefined },
  ]);
});

test('unknown resources cannot produce arbitrary administrative requests', async (context) => {
  const fetch = context.mock.method(globalThis, 'fetch');
  await assert.rejects(getAdminRecord('../user', 1), TypeError);
  assert.equal(fetch.mock.calls.length, 0);
});

test('invalid list payloads or pagination are reported instead of looping', async (context) => {
  for (const payload of [{ data: null }, { data: [], meta: { last_page: 'invalid' } }]) {
    mockSession(context, () => Response.json(payload));
    await assert.rejects(allRecords('/api/admin/products'));
  }
});
