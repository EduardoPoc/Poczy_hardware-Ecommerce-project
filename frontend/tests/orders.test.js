import test from 'node:test';
import assert from 'node:assert/strict';
import { cancelOrder, payOrder } from '../src/features/orders/api.js';

function session(context, handler) {
  globalThis.document = { cookie: 'XSRF-TOKEN=orders' };
  context.after(() => {
    delete globalThis.document;
  });
  context.mock.method(globalThis, 'fetch', async (path, options) => {
    if (path === '/sanctum/csrf-cookie') return new Response(null, { status: 204 });
    assert.equal(options.credentials, 'include');
    return handler(path, options);
  });
}

test('pagamento é uma ação explícita sem enviar dados financeiros', async (context) => {
  session(context, (path, options) => {
    assert.equal(path, '/api/orders/7/pay');
    assert.equal(options.method, 'POST');
    assert.equal(options.body, undefined);
    assert.equal(options.headers['X-XSRF-TOKEN'], 'orders');
    return Response.json({ data: { id: 7, status: 'PAID' } });
  });
  assert.equal((await payOrder(7)).status, 'PAID');
});

test('cancelamento confirma o estado persistido após a resposta sem corpo', async (context) => {
  const calls = [];
  session(context, (path, options) => {
    assert.equal(path, '/api/orders/7');
    calls.push(options.method || 'GET');
    return options.method === 'DELETE'
      ? new Response(null, { status: 204 })
      : Response.json({ data: { id: 7, status: 'CANCELLED' } });
  });
  assert.equal((await cancelOrder(7)).status, 'CANCELLED');
  assert.deepEqual(calls, ['DELETE', 'GET']);
});

test('transições rejeitadas não são apresentadas como sucesso', async (context) => {
  session(context, () =>
    Response.json({ message: 'Only orders awaiting payment can be paid.' }, { status: 409 }),
  );
  await assert.rejects(
    payOrder(7),
    (error) => error.status === 409 && error.message.includes('aguardando pagamento'),
  );
});
