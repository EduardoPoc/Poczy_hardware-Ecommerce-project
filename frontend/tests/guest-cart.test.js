import test from 'node:test';
import assert from 'node:assert/strict';
import {
  forgetGuestCartToken,
  guestCartToken,
  rememberGuestCartToken,
} from '../src/shared/session/guest-cart.js';
import { getCart } from '../src/features/cart/api.js';
import { submitSession } from '../src/features/auth/api.js';

function setup(context) {
  const values = new Map();
  globalThis.localStorage = {
    getItem: (key) => values.get(key) ?? null,
    setItem: (key, value) => values.set(key, value),
    removeItem: (key) => values.delete(key),
  };
  globalThis.document = { cookie: 'XSRF-TOKEN=guest-test' };
  context.after(() => {
    forgetGuestCartToken();
    delete globalThis.localStorage;
    delete globalThis.document;
  });
}

test('login envia o token persistido e o descarta somente após a fusão', async (context) => {
  setup(context);
  const token = 'a'.repeat(64);
  rememberGuestCartToken(token);
  context.mock.method(globalThis, 'fetch', async (path, options) => {
    if (path === '/sanctum/csrf-cookie') return new Response(null, { status: 204 });
    assert.equal(options.headers['X-Cart-Token'], token);
    assert.equal(options.credentials, 'include');
    return Response.json({ id: 7, cart_merge: { adjustments: [] } });
  });
  await submitSession('/api/login', {
    email: 'cliente@example.com',
    password: 'SenhaCliente!2026',
  });
  assert.equal(guestCartToken(), null);
});

test('sessão de visitante expirada permite carregar um novo carrinho uma única vez', async (context) => {
  setup(context);
  rememberGuestCartToken('b'.repeat(64));
  let calls = 0;
  context.mock.method(globalThis, 'fetch', async (path, options) => {
    if (path === '/sanctum/csrf-cookie') return new Response(null, { status: 204 });
    calls += 1;
    if (calls === 1) return Response.json({}, { status: 404 });
    assert.equal(options.headers['X-Cart-Token'], undefined);
    return Response.json({ data: { id: 8, items: [] } });
  });
  assert.equal((await getCart()).id, 8);
  assert.equal(calls, 2);
});

test('falha de login preserva o token para não perder o carrinho', async (context) => {
  setup(context);
  rememberGuestCartToken('c'.repeat(64));
  context.mock.method(globalThis, 'fetch', async () =>
    Response.json(
      { errors: { email: ['The provided credentials are invalid.'] } },
      { status: 422 },
    ),
  );
  await assert.rejects(submitSession('/api/login', {}), (error) => error.status === 422);
  assert.equal(guestCartToken(), 'c'.repeat(64));
});
