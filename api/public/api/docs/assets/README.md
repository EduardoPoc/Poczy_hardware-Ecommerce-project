# Assets do Swagger UI

Distribuição oficial `swagger-ui-dist` **5.34.1**, sem build e sem CDN em tempo de execução.

Origem: https://registry.npmjs.org/swagger-ui-dist/-/swagger-ui-dist-5.34.1.tgz

Integridade do arquivo de origem (SHA-512, formato npm):
`sha512-VSr8fXfI93obYaQicLJbLUKGRxDojj/SfRJDDLCioGTUBj4BK/Nj4eE9jCeCAnJxVLi7iS+08PbiZLyrQKBkJQ==`

`swagger-ui-bundle.js`, `swagger-ui.css`, `LICENSE`, `NOTICE` e
`swagger-ui-bundle.js.LICENSE.txt` são copiados da distribuição sem alteração.
Os source maps opcionais não são necessários para executar a UI.

`docs.css` e `swagger-initializer.js` são a integração local. O inicializador
carrega `/api/docs/openapi.yaml`, mantém cookies no mesmo host e, antes de cada
escrita, obtém `/sanctum/csrf-cookie` e envia o cookie decodificado em
`X-XSRF-TOKEN`. A validação remota do Swagger está desativada (`validatorUrl: null`).

Para atualizar, baixe uma versão fixa do pacote oficial, confira sua integridade,
substitua os cinco arquivos da distribuição e atualize a versão e o hash acima.
Não é necessário instalar dependências no projeto nem executar build do frontend.

Testes da integração de sessão/CSRF, a partir da raiz do repositório:
`node --test api/tests/Documentation/swagger-ui.test.cjs`.
