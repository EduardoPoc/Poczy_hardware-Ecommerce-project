/* Swagger UI uses the application's web session; credentials are managed by the browser. */
window.addEventListener('load', () => {
  function xsrfCookie() {
    const cookie = document.cookie.split('; ').find((value) => value.startsWith('XSRF-TOKEN='));
    return cookie ? decodeURIComponent(cookie.slice('XSRF-TOKEN='.length)) : null;
  }

  window.ui = SwaggerUIBundle({
    url: '/api/docs/openapi.yaml',
    dom_id: '#swagger-ui',
    deepLinking: true,
    withCredentials: true,
    validatorUrl: null,
    queryConfigEnabled: false,
    persistAuthorization: false,
    displayRequestDuration: true,
    requestInterceptor: async (request) => {
      const url = new URL(request.url, window.location.origin);
      if (url.origin !== window.location.origin) {
        throw new Error('As chamadas desta documentação devem usar o mesmo host da API.');
      }

      request.credentials = 'include';
      request.headers = request.headers || {};
      function setHeader(name, value) {
        for (const key of Object.keys(request.headers)) {
          if (key.toLowerCase() === name.toLowerCase()) {
            delete request.headers[key];
          }
        }
        request.headers[name] = value;
      }
      if (url.pathname.startsWith('/api/') && !url.pathname.startsWith('/api/docs/')) {
        setHeader('Accept', 'application/json');
      }

      if (!['GET', 'HEAD', 'OPTIONS'].includes((request.method || 'GET').toUpperCase())) {
        // Refresh before each write: login/logout/password changes can rotate the session or CSRF.
        const csrf = await fetch('/sanctum/csrf-cookie', {
          credentials: 'include',
          headers: { Accept: 'application/json' },
          cache: 'no-store',
        });
        if (!csrf.ok) {
          throw new Error(`Não foi possível inicializar o CSRF (HTTP ${csrf.status}).`);
        }
        const token = xsrfCookie();
        if (!token) {
          throw new Error('Cookie XSRF-TOKEN indisponível. Verifique os cookies da sessão neste host.');
        }
        setHeader('X-XSRF-TOKEN', token);
      }
      return request;
    },
  });
});
