import test from 'node:test';
import assert from 'node:assert/strict';
import { changePassword, updateProfile } from '../src/features/account/api.js';
import { profilePayload } from '../src/features/account/domain.js';

const user = { id: 7, name: 'Mariana Costa', email: 'mariana@example.com' };

function mockSession(context, handler) {
  globalThis.document = { cookie: 'XSRF-TOKEN=account%3Dtoken' };
  context.after(() => {
    delete globalThis.document;
  });
  context.mock.method(globalThis, 'fetch', async (path, options) => {
    if (path === '/sanctum/csrf-cookie') return new Response(null, { status: 204 });
    assert.equal(options.credentials, 'include');
    assert.equal(options.headers['X-XSRF-TOKEN'], 'account=token');
    assert.equal(options.headers['Content-Type'], 'application/json');
    return handler(path, options);
  });
}

test('nome alterado mantém o e-mail e dispensa senha atual', () => {
  assert.deepEqual(
    profilePayload(
      {
        ...user,
        name: ' Mariana Silva ',
        email: ' MARIANA@example.com ',
        current_password: 'secret',
        role: 'ADMIN',
      },
      user,
    ),
    { name: 'Mariana Silva' },
  );
  assert.deepEqual(profilePayload({ ...user, email: ' MARIANA@EXAMPLE.COM ' }, user), {});
});

test('novo e-mail é normalizado e exige enviar a senha atual sem aparar espaços', () => {
  assert.deepEqual(
    profilePayload(
      { ...user, email: ' NOVO@example.com ', current_password: ' Senha atual! ' },
      user,
    ),
    { email: 'novo@example.com', current_password: ' Senha atual! ' },
  );
});

test('perfil é atualizado na própria conta com sessão, CSRF e retorno do servidor', async (context) => {
  const data = profilePayload({ ...user, name: 'Mariana Silva' }, user);
  const updated = { ...user, ...data };
  mockSession(context, (path, options) => {
    assert.equal(path, '/api/user');
    assert.equal(options.method, 'PATCH');
    assert.deepEqual(JSON.parse(options.body), data);
    return Response.json({ data: updated });
  });
  assert.deepEqual(await updateProfile(data), updated);
});

test('e-mail ocupado e senha atual incorreta preservam os campos e traduzem os erros', async (context) => {
  const errors = {
    email: ['The email has already been taken.'],
    current_password: ['The current password is incorrect.'],
  };
  mockSession(context, () => Response.json({ errors }, { status: 422 }));
  await assert.rejects(
    updateProfile({ email: 'ocupado@example.com', current_password: 'errada' }),
    (error) => {
      assert.equal(error.status, 422);
      assert.deepEqual(error.errors, {
        email: ['E-mail: já está em uso.'],
        current_password: ['A senha atual está incorreta.'],
      });
      return true;
    },
  );
});

test('troca de senha envia os três campos sem alterar a senha e aceita sucesso sem corpo', async (context) => {
  const data = {
    current_password: ' SenhaAtual!2026 ',
    password: ' NovaSenha!2026 ',
    password_confirmation: ' NovaSenha!2026 ',
  };
  mockSession(context, (path, options) => {
    assert.equal(path, '/api/user/password');
    assert.equal(options.method, 'PUT');
    assert.deepEqual(JSON.parse(options.body), data);
    return new Response(null, { status: 204 });
  });
  assert.equal(await changePassword(data), null);
});

test('senha rejeitada mantém os erros de validação para nova tentativa', async (context) => {
  mockSession(context, () =>
    Response.json(
      { errors: { password: ['The password field confirmation does not match.'] } },
      { status: 422 },
    ),
  );
  await assert.rejects(
    changePassword({
      current_password: 'atual',
      password: 'NovaSenha!2026',
      password_confirmation: 'outra',
    }),
    (error) => {
      assert.equal(error.status, 422);
      assert.deepEqual(error.errors.password, ['Senha: não corresponde à confirmação.']);
      return true;
    },
  );
});

test('sessão encerrada e CSRF expirado continuam identificáveis no formulário', async (context) => {
  let status = 401;
  mockSession(context, () => Response.json({}, { status }));
  await assert.rejects(updateProfile({ name: 'Mariana Silva' }), (error) => error.status === 401);
  status = 419;
  await assert.rejects(changePassword({}), (error) => error.status === 419);
});
