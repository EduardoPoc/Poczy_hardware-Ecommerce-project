export class ApiError extends Error {
  constructor(message, status = 0, errors = {}) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
    this.errors = errors;
  }
}

export function errorMessage(error) {
  return error instanceof ApiError
    ? error.message
    : 'Não foi possível carregar os dados da loja. Tente novamente.';
}

export function isSessionFailure(error) {
  return [401, 419].includes(error?.status);
}

export function isUncertainFailure(error) {
  return error?.status == null || error.status === 0 || error.status >= 500;
}
