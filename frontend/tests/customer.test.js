import test from 'node:test';
import assert from 'node:assert/strict';
import { accountDestination } from '../src/features/auth/domain.js';
import { addressPayload } from '../src/features/account/domain.js';
import { paymentLabel } from '../src/features/orders/domain.js';
import { completeCheckout } from '../src/features/checkout/api.js';
import { getProfile, listAddresses, saveAddress } from '../src/features/account/api.js';

test('endereços enviam somente campos próprios, sem permitir escolher o dono', () => {
  assert.deepEqual(
    addressPayload({
      id: 99,
      user_id: 88,
      postal_code: ' 01001-000 ',
      street: ' Rua de Entrega ',
      number: ' S/N ',
      city: ' São Paulo ',
      state: ' sp ',
      complement: ' ',
    }),
    {
      postal_code: '01001-000',
      street: 'Rua de Entrega',
      number: 'S/N',
      city: 'São Paulo',
      state: 'SP',
      complement: null,
    },
  );
});

test('retorno após login aceita somente os destinos da compra e da conta', () => {
  for (const path of ['/checkout', '/conta', '/carrinho'])
    assert.equal(accountDestination(path), path);
  for (const path of [
    'https://example.com',
    '//example.com',
    '/admin',
    '/checkout?next=https://example.com',
    null,
  ]) {
    assert.equal(accountDestination(path), '/conta');
  }
  assert.equal(paymentLabel('FAKE_PIX'), 'Fake Pix');
});

test('cadastro de endereço usa a sessão e mantém erros de validação', async (context) => {
  globalThis.document = { cookie: 'XSRF-TOKEN=csrf-token' };
  context.after(() => {
    delete globalThis.document;
  });
  const address = addressPayload({
    postal_code: '01001-000',
    street: 'Rua Um',
    number: '1',
    city: 'São Paulo',
    state: 'SP',
  });
  context.mock.method(globalThis, 'fetch', async (path, options) => {
    if (path === '/sanctum/csrf-cookie') return new Response(null, { status: 204 });
    assert.equal(path, '/api/addresses');
    assert.equal(options.method, 'POST');
    assert.equal(options.credentials, 'include');
    assert.equal(options.headers['X-XSRF-TOKEN'], 'csrf-token');
    assert.deepEqual(JSON.parse(options.body), address);
    return Response.json(
      { errors: { postal_code: ['The postal code field format is invalid.'] } },
      { status: 422 },
    );
  });
  await assert.rejects(
    saveAddress(address),
    (error) =>
      error.status === 422 && error.errors.postal_code[0] === 'CEP: tem um formato inválido.',
  );
});

test('uma resposta perdida permite reenviar a mesma tentativa de Fake Pix', async (context) => {
  globalThis.document = { cookie: 'XSRF-TOKEN=csrf-token' };
  context.after(() => {
    delete globalThis.document;
  });
  const attempt = {
    address_id: 7,
    payment_method: 'FAKE_PIX',
    expected_total: '69.80',
    checkout_token: '123e4567-e89b-42d3-a456-426614174000',
  };
  const attempts = [];
  context.mock.method(globalThis, 'fetch', async (path, options) => {
    if (path === '/sanctum/csrf-cookie') return new Response(null, { status: 204 });
    assert.equal(path, '/api/checkout');
    attempts.push(JSON.parse(options.body));
    if (attempts.length === 1) throw new TypeError('Conexão perdida');
    return Response.json(
      { data: { id: 42, status: 'PAID', payment_method: 'FAKE_PIX' } },
      { status: 201 },
    );
  });
  await assert.rejects(completeCheckout(attempt), (error) => error.status === 0);
  assert.equal((await completeCheckout(attempt)).id, 42);
  assert.deepEqual(attempts, [attempt, attempt]);
});

test('conta mantém erros de autenticação e endereços não usam dados fictícios', async (context) => {
  context.mock.method(globalThis, 'fetch', async (path) =>
    path === '/sanctum/csrf-cookie'
      ? new Response(null, { status: 204 })
      : Response.json({}, { status: 401 }),
  );
  await assert.rejects(getProfile(), (error) => error.status === 401);
  context.mock.method(globalThis, 'fetch', async () => Response.json({ data: null }));
  await assert.rejects(listAddresses(), /endereços/);
});
