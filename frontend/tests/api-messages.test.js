import test from 'node:test';
import assert from 'node:assert/strict';
import {
  translateApiMessage,
  translateValidationErrors,
  cartAdjustmentMessage,
} from '../src/domain/api-messages.js';
import { submitSession } from '../src/api/auth.js';

test('validações do Laravel são apresentadas em português com limites e campos preservados', () => {
  const cases = [
    ['email', 'The email field is required.', 'E-mail: é obrigatório.'],
    ['email', 'The email has already been taken.', 'E-mail: já está em uso.'],
    [
      'password',
      'The password field confirmation does not match.',
      'Senha: não corresponde à confirmação.',
    ],
    [
      'password',
      'The password field must contain at least one uppercase and one lowercase letter.',
      'Senha: deve conter letras maiúsculas e minúsculas.',
    ],
    [
      'password',
      'The password field must contain at least one symbol.',
      'Senha: deve conter pelo menos um símbolo.',
    ],
    [
      'items.0.quantity',
      'The items.0.quantity field must be between 1 and 10000.',
      'Quantidade: deve estar entre 1 e 10000.',
    ],
    [
      'name',
      'The name field must not be greater than 150 characters.',
      'Nome: deve ter no máximo 150 caracteres.',
    ],
    [
      'attributes',
      'The attributes field must not have more than 100 items.',
      'Atributos: deve ter no máximo 100 itens.',
    ],
    [
      'price',
      'The price field must have 0-2 decimal places.',
      'Preço: deve ter 0 a 2 casas decimais.',
    ],
    [
      'filter.max_price',
      'The filter.max price field must be greater than or equal to filter.min price.',
      'Preço máximo: deve ser maior ou igual a Preço mínimo.',
    ],
    ['state', 'The selected state is invalid.', 'Estado: selecione uma opção válida.'],
    ['role', 'The role field is prohibited.', 'Permissão: não pode ser enviado.'],
  ];
  for (const [field, message, expected] of cases)
    assert.equal(translateApiMessage(message, { field }), expected);
});

test('mensagens de negócio mantêm instruções de estoque, login, upload e checkout', () => {
  for (const [message, expected] of [
    ['Insufficient stock.', 'Estoque insuficiente.'],
    ['The provided credentials are invalid.', 'As credenciais informadas são inválidas.'],
    ['The image must not exceed 5 MB.', 'A imagem deve ter no máximo 5 MB.'],
    [
      'The cart amount has changed. Review the new total before confirming Fake Pix.',
      'O valor do carrinho foi atualizado. Confira o novo total antes de confirmar o Fake Pix.',
    ],
  ])
    assert.equal(translateApiMessage(message), expected);
});

test('mensagens desconhecidas e estruturas inválidas usam retorno seguro em português', () => {
  assert.equal(
    translateApiMessage('SQLSTATE secret details'),
    'Não foi possível concluir. Tente novamente em instantes.',
  );
  assert.equal(
    translateApiMessage('Unknown error', { field: 'email' }),
    'E-mail: confira o valor informado.',
  );
  assert.equal(translateApiMessage(null, { fallback: 'Atualize os dados.' }), 'Atualize os dados.');
  for (const invalid of [null, [], 'invalid'])
    assert.deepEqual(translateValidationErrors(invalid), {});
  assert.deepEqual(translateValidationErrors({ email: 'The email field is required.' }), {
    email: ['E-mail: é obrigatório.'],
  });
});

test('respostas HTTP traduzem conflitos conhecidos e ocultam conflitos desconhecidos', async (context) => {
  let message = 'A record with the provided unique values already exists.';
  context.mock.method(globalThis, 'fetch', async (path) =>
    path === '/sanctum/csrf-cookie'
      ? new Response(null, { status: 204 })
      : Response.json({ message }, { status: 409 }),
  );
  const { apiRequest } = await import('../src/api/http.js?translations');
  await assert.rejects(apiRequest('/api/admin/products'), {
    message: 'Já existe um registro com os dados únicos informados.',
  });
  message = 'Internal English details';
  await assert.rejects(apiRequest('/api/admin/products'), {
    message: 'Os dados foram alterados. Atualize e confira o registro.',
  });
});

test('login traduz avisos de fusão pelo motivo e preserva os dados do ajuste', async (context) => {
  globalThis.document = { cookie: 'XSRF-TOKEN=test' };
  context.after(() => {
    delete globalThis.document;
  });
  const adjustment = {
    product_id: 7,
    requested_quantity: 8,
    quantity: 3,
    reason: 'stock_limit',
    message: 'The quantity was adjusted from 8 to 3 according to available stock.',
  };
  context.mock.method(globalThis, 'fetch', async (path) =>
    path === '/sanctum/csrf-cookie'
      ? new Response(null, { status: 204 })
      : Response.json({ id: 1, cart_merge: { adjustments: [adjustment] } }),
  );
  const response = await submitSession('/api/login', {});
  assert.deepEqual(response.cart_merge.adjustments[0], {
    ...adjustment,
    message: 'A quantidade foi ajustada de 8 para 3 conforme o estoque disponível.',
  });
  assert.match(cartAdjustmentMessage({ reason: 'unavailable' }), /foi removido do carrinho/);
  assert.equal(
    cartAdjustmentMessage({ reason: 'unknown', message: 'Unknown warning' }),
    'O carrinho foi atualizado. Confira os itens antes de continuar.',
  );
});
