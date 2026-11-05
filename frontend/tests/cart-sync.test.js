import test from 'node:test';
import assert from 'node:assert/strict';
import { useCart } from '../src/stores/cart.js';

test('atualização silenciosa remove itens indisponíveis e atualiza contador e totais', async (context) => {
  let data = {
    items: [
      { product_id: 1, quantity: 1 },
      { product_id: 2, quantity: 2 },
    ],
    items_quantity: 3,
    products_total: '39.80',
    total: '69.80',
  };
  context.mock.method(globalThis, 'fetch', async (path) =>
    path === '/sanctum/csrf-cookie' ? new Response(null, { status: 204 }) : Response.json({ data }),
  );
  const cart = useCart();
  assert.equal(await cart.load({ force: true }), true);
  assert.equal(cart.quantity.value, 3);
  data = {
    items: [{ product_id: 2, quantity: 2 }],
    items_quantity: 2,
    products_total: '19.90',
    total: '49.90',
  };
  const refresh = cart.load({ force: true, quiet: true });
  assert.equal(cart.state.loading, false);
  assert.equal(await refresh, true);
  assert.equal(cart.quantity.value, 2);
  assert.equal(cart.subtotal.value, '19.90');
  assert.equal(cart.state.cart.total, '49.90');
  assert.deepEqual(
    cart.items.value.map((item) => item.product_id),
    [2],
  );
});

test('falha na atualização em segundo plano preserva o carrinho confirmado', async (context) => {
  context.mock.method(globalThis, 'fetch', async () => {
    throw new TypeError('Sem conexão');
  });
  const cart = useCart();
  const before = JSON.stringify(cart.state.cart);
  assert.equal(await cart.load({ force: true, quiet: true }), false);
  assert.equal(JSON.stringify(cart.state.cart), before);
  assert.equal(cart.state.error, '');
  assert.equal(cart.state.loading, false);
});
