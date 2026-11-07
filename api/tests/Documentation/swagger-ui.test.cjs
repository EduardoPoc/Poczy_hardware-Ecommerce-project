const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { test } = require('node:test');
const { runInNewContext } = require('node:vm');
const { resolve } = require('node:path');

const source = readFileSync(resolve(__dirname, '../../public/api/docs/assets/swagger-initializer.js'), 'utf8');

function setup({ cookie = 'XSRF-TOKEN=first%3D', status = 204 } = {}) {
  let config;
  const calls = [];
  const document = { cookie };
  const window = {
    location: { origin: 'http://localhost' },
    addEventListener: (event, callback) => callback(),
  };
  runInNewContext(source, {
    window, document, URL,
    SwaggerUIBundle: (options) => { config = options; },
    fetch: async (url, options) => {
      calls.push({ url, options });
      return { ok: status >= 200 && status < 300, status };
    },
  });
  return { config, document, calls };
}

test('loads the exact static spec with cookies and without a remote validator', () => {
  const { config } = setup();
  assert.equal(config.url, '/api/docs/openapi.yaml');
  assert.equal(config.withCredentials, true);
  assert.equal(config.validatorUrl, null);
  assert.equal(config.queryConfigEnabled, false);
});

test('reads preserve cookies and select JSON without duplicated Accept headers', async () => {
  const { config, calls } = setup();
  const request = await config.requestInterceptor({ url: '/api/user', method: 'GET', headers: { accept: '*/*' } });
  assert.equal(request.credentials, 'include');
  assert.equal(request.headers.Accept, 'application/json');
  assert.equal(Object.keys(request.headers).filter((key) => key.toLowerCase() === 'accept').length, 1);
  assert.equal(calls.length, 0);
});

for (const method of ['POST', 'PUT', 'PATCH', 'DELETE']) {
  test(`${method} initializes CSRF at the root and uses the decoded cookie`, async () => {
    const { config, calls } = setup();
    const request = await config.requestInterceptor({ url: 'http://localhost/api/user', method, headers: { 'x-xsrf-token': 'old' } });
    assert.equal(calls.length, 1);
    assert.equal(calls[0].url, '/sanctum/csrf-cookie');
    assert.equal(calls[0].options.credentials, 'include');
    assert.equal(calls[0].options.cache, 'no-store');
    assert.equal(request.headers['X-XSRF-TOKEN'], 'first=');
    assert.equal(Object.keys(request.headers).filter((key) => key.toLowerCase() === 'x-xsrf-token').length, 1);
    assert.equal(request.credentials, 'include');
  });
}

test('reads a fresh CSRF cookie for the next write after session rotation', async () => {
  const { config, document, calls } = setup();
  await config.requestInterceptor({ url: '/api/login', method: 'POST' });
  document.cookie = 'session=value; XSRF-TOKEN=rotated%2Bvalue%3D';
  const request = await config.requestInterceptor({ url: '/api/logout', method: 'POST' });
  assert.equal(calls.length, 2);
  assert.equal(request.headers['X-XSRF-TOKEN'], 'rotated+value=');
});

test('stops writes when CSRF initialization fails or its cookie is missing', async () => {
  await assert.rejects(setup({ status: 500 }).config.requestInterceptor({ url: '/api/login', method: 'POST' }), /HTTP 500/);
  await assert.rejects(setup({ cookie: '' }).config.requestInterceptor({ url: '/api/login', method: 'POST' }), /XSRF-TOKEN indisponível/);
});

test('rejects other origins before fetching CSRF or forwarding credentials', async () => {
  const { config, calls } = setup();
  await assert.rejects(config.requestInterceptor({ url: 'https://example.com/api/user', method: 'POST' }), /mesmo host/);
  assert.equal(calls.length, 0);
});
