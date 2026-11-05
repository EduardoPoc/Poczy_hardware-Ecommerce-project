const apiOrigin = (import.meta.env?.VITE_API_URL ?? '').replace(/\/$/, '');
const REQUEST_TIMEOUT_MS = 12000;
let csrfReady = false;
let csrfPromise;

export class ApiError extends Error {
  constructor(message, status = 0, errors = {}) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
    this.errors = errors;
  }
}

export function resourceData(response) {
  return response?.data ?? response;
}

export function errorMessage(error) {
  return error?.message || 'Não foi possível carregar os dados da loja. Tente novamente.';
}

async function request(path, options = {}) {
  let response;

  try {
    response = await fetch(`${apiOrigin}${path}`, {
      credentials: 'include',
      ...options,
      signal: options.signal
        ? AbortSignal.any([options.signal, AbortSignal.timeout(REQUEST_TIMEOUT_MS)])
        : AbortSignal.timeout(REQUEST_TIMEOUT_MS),
      headers: { Accept: 'application/json', ...options.headers },
    });
  } catch (error) {
    if (options.signal?.aborted) throw error;
    throw new ApiError('Não foi possível conectar à loja. Tente novamente em instantes.');
  }

  const payload = await readJsonResponse(response);

  if (!response.ok) {
    if (response.status === 419) csrfReady = false;
    throw responseError(response.status, payload);
  }

  if (response.status !== 204 && !payload) {
    throw new ApiError('Não foi possível confirmar a resposta da loja.');
  }

  return payload;
}

async function readJsonResponse(response) {
  try {
    if (response.headers.get('content-type')?.includes('application/json')) {
      return await response.json();
    }
  } catch {
    throw new ApiError('A loja retornou uma resposta inválida. Atualize os dados.');
  }
  return null;
}

function responseError(status, payload) {
  const validationMessages = Object.values(payload?.errors || {})
    .flat()
    .filter(Boolean);
  const messages = {
    401: 'Entre na sua conta para continuar.',
    403: 'Sua conta não tem permissão para acessar este recurso.',
    404: 'Este registro não foi encontrado.',
    409: payload?.message || 'Os dados foram alterados. Atualize e confira o registro.',
    413: 'O arquivo ou a requisição ultrapassa o tamanho permitido.',
    419: 'Sua sessão expirou. Atualize a página e tente novamente.',
    422: validationMessages.join(' ') || 'Confira os dados informados e tente novamente.',
    429: 'Muitas tentativas. Aguarde alguns instantes antes de tentar novamente.',
  };

  return new ApiError(
    messages[status] || 'Não foi possível concluir. Tente novamente em instantes.',
    status,
    payload?.errors,
  );
}

// Compartilha a primeira sessão para evitar cookies concorrentes em requisições simultâneas.
async function ensureSession() {
  if (csrfReady) return;
  if (!csrfPromise) {
    csrfPromise = request('/sanctum/csrf-cookie')
      .then(() => {
        csrfReady = true;
      })
      .finally(() => {
        csrfPromise = null;
      });
  }
  await csrfPromise;
}

export async function apiRequest(path, { method = 'GET', data, signal } = {}) {
  const headers = {};
  const multipart = data instanceof FormData;
  await ensureSession();

  if (!['GET', 'HEAD'].includes(method)) {
    const cookie = document.cookie.split('; ').find((value) => value.startsWith('XSRF-TOKEN='));

    if (!cookie) {
      throw new ApiError('Não foi possível iniciar uma sessão segura. Atualize a página.', 419);
    }

    headers['X-XSRF-TOKEN'] = decodeURIComponent(cookie.slice('XSRF-TOKEN='.length));
  }

  if (multipart && ['PUT', 'PATCH'].includes(method)) {
    data.set('_method', method);
    method = 'POST';
  }
  if (data !== undefined && !multipart) headers['Content-Type'] = 'application/json';

  return request(path, {
    method,
    headers,
    signal,
    ...(data !== undefined ? { body: multipart ? data : JSON.stringify(data) } : {}),
  });
}
