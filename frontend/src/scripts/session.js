// Laravel autentica por cookie de sessão; credenciais nunca vão para o storage.
const configuredOrigin = import.meta.env.VITE_API_URL;
const apiOrigin = (configuredOrigin ?? (import.meta.env.DEV
  ? `${window.location.protocol}//${window.location.hostname}:8000`
  : '')).replace(/\/$/, '');

export class ApiError extends Error {
  constructor(message, status, errors = {}) {
    super(message);
    this.status = status;
    this.errors = errors;
  }
}

async function request(path, options = {}) {
  let response;
  try {
    response = await fetch(`${apiOrigin}${path}`, {
      credentials: 'include',
      signal: AbortSignal.timeout(12000),
      ...options,
      headers: { Accept: 'application/json', ...options.headers }
    });
  } catch {
    throw new ApiError('Não foi possível conectar à loja. Tente novamente em instantes.', 0);
  }

  let payload = null;
  try {
    if (response.headers.get('content-type')?.includes('application/json')) payload = await response.json();
  } catch {
    throw new ApiError('Não foi possível confirmar a resposta da loja. Atualize os dados antes de tentar novamente.', 0);
  }
  if (!response.ok) {
    const messages = {
      401: 'Entre na sua conta para continuar.',
      403: 'Sua conta não tem permissão para acessar este recurso.',
      404: 'Este registro não foi encontrado. Atualize a lista e tente novamente.',
      409: payload?.message || 'Esta alteração conflita com os dados atuais. Atualize a lista e confira o registro.',
      419: 'Sua sessão expirou. Tente novamente.',
      422: 'Confira os dados informados e tente novamente.',
      429: 'Muitas tentativas. Aguarde alguns instantes antes de tentar novamente.'
    };
    throw new ApiError(messages[response.status] || 'Não foi possível concluir. Tente novamente em instantes.', response.status, payload?.errors);
  }
  if (response.status !== 204 && !payload) {
    throw new ApiError('Não foi possível conectar à loja. Tente novamente em instantes.', 0);
  }
  return payload;
}

export async function apiRequest(path, { method = 'GET', data } = {}) {
  const headers = {};
  if (!['GET', 'HEAD'].includes(method)) {
    await request('/sanctum/csrf-cookie');
    const cookie = document.cookie.split('; ').find(value => value.startsWith('XSRF-TOKEN='));
    if (!cookie) throw new ApiError('Não foi possível iniciar uma sessão segura. Atualize a página e tente novamente.', 419);
    headers['X-XSRF-TOKEN'] = decodeURIComponent(cookie.slice('XSRF-TOKEN='.length));
  }
  if (data !== undefined) headers['Content-Type'] = 'application/json';
  return request(path, { method, headers, ...(data !== undefined ? { body: JSON.stringify(data) } : {}) });
}

export const submitSession = (path, values) => apiRequest(path, { method: 'POST', data: values });

export async function currentUser() {
  try {
    const response = await request('/api/user');
    const user = response?.data ?? response;
    return typeof user?.name === 'string' && typeof user?.email === 'string' ? user : null;
  } catch {
    return null;
  }
}
