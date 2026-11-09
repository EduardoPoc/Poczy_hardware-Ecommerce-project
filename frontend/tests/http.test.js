import test from 'node:test';
import assert from 'node:assert/strict';
import { ApiError } from '../src/shared/api/errors.js';

let sequence = 0;
const freshClient = () => import(`../src/shared/api/http.js?test=${sequence++}`);

test('upload envia multipart com cookies e CSRF e usa POST com _method na edição', async (context) => {
  globalThis.document = { cookie: 'XSRF-TOKEN=token%3D' };
  context.after(() => {
    delete globalThis.document;
  });
  const sent = [];
  context.mock.method(globalThis, 'fetch', async (path, options) => {
    if (path === '/sanctum/csrf-cookie') return new Response(null, { status: 204 });
    sent.push(options);
    return Response.json({ data: { id: 1 } });
  });
  const { apiRequest } = await freshClient();
  for (const method of ['POST', 'PATCH']) {
    const body = new FormData();
    body.append('image', new File(['png'], 'imagem.png', { type: 'image/png' }));
    await apiRequest('/api/admin/products/1', { method, data: body });
  }
  for (const options of sent) {
    assert.equal(options.method, 'POST');
    assert.equal(options.credentials, 'include');
    assert.equal(options.headers['X-XSRF-TOKEN'], 'token=');
    assert.equal(options.headers['Content-Type'], undefined);
    assert.ok(options.body instanceof FormData);
  }
  assert.equal(sent[0].body.has('_method'), false);
  assert.equal(sent[1].body.get('_method'), 'PATCH');
});

test('requisições simultâneas compartilham a criação da sessão', async (context) => {
  const calls = [];
  context.mock.method(globalThis, 'fetch', async (path) => {
    calls.push(path);
    return path === '/sanctum/csrf-cookie'
      ? new Response(null, { status: 204 })
      : Response.json({ data: [] });
  });
  const { apiRequest } = await freshClient();
  await Promise.all([apiRequest('/api/products'), apiRequest('/api/categories')]);
  assert.equal(calls.filter((path) => path === '/sanctum/csrf-cookie').length, 1);
  assert.deepEqual(calls.slice(1).sort(), ['/api/categories', '/api/products']);
});

test('sessão expirada exige novo handshake antes da próxima tentativa', async (context) => {
  let handshakes = 0;
  let requests = 0;
  context.mock.method(globalThis, 'fetch', async (path) => {
    if (path === '/sanctum/csrf-cookie') {
      handshakes++;
      return new Response(null, { status: 204 });
    }
    requests++;
    return requests === 1 ? Response.json({}, { status: 419 }) : Response.json({ data: [] });
  });
  const { apiRequest } = await freshClient();
  await assert.rejects(apiRequest('/api/cart'), (error) => error.status === 419);
  await apiRequest('/api/cart');
  assert.equal(handshakes, 2);
});

test('erros de validação preservam os campos e traduzem mensagens da API', async (context) => {
  const errors = {
    quantity: ['Insufficient stock.'],
    product_id: ['The product is not available.'],
  };
  context.mock.method(globalThis, 'fetch', async (path) =>
    path === '/sanctum/csrf-cookie'
      ? new Response(null, { status: 204 })
      : Response.json({ errors }, { status: 422 }),
  );
  const { apiRequest } = await freshClient();
  await assert.rejects(apiRequest('/api/cart'), (error) => {
    assert.equal(error.status, 422);
    assert.deepEqual(error.errors, {
      quantity: ['Estoque insuficiente.'],
      product_id: ['O produto não está disponível.'],
    });
    assert.equal(error.message, 'Estoque insuficiente. O produto não está disponível.');
    return true;
  });
});

test('JSON inválido e resposta vazia não são tratados como sucesso', async (context) => {
  for (const response of [
    new Response('{invalid', { headers: { 'content-type': 'application/json' } }),
    new Response(''),
  ]) {
    context.mock.method(globalThis, 'fetch', async (path) =>
      path === '/sanctum/csrf-cookie' ? new Response(null, { status: 204 }) : response,
    );
    const { apiRequest } = await freshClient();
    await assert.rejects(apiRequest('/api/products'), ApiError);
  }
});

test('escrita envia JSON, cookies e o token CSRF da sessão atual', async (context) => {
  globalThis.document = { cookie: 'XSRF-TOKEN=token%3D; example=other' };
  context.after(() => {
    delete globalThis.document;
  });
  let sent;
  context.mock.method(globalThis, 'fetch', async (path, options) => {
    if (path === '/sanctum/csrf-cookie') return new Response(null, { status: 204 });
    sent = options;
    return new Response(null, { status: 204 });
  });
  const { apiRequest } = await freshClient();
  await apiRequest('/api/cart/items', { method: 'POST', data: { product_id: 7, quantity: 2 } });
  assert.equal(sent.credentials, 'include');
  assert.equal(sent.headers['X-XSRF-TOKEN'], 'token=');
  assert.equal(sent.headers['Content-Type'], 'application/json');
  assert.deepEqual(JSON.parse(sent.body), { product_id: 7, quantity: 2 });
});
