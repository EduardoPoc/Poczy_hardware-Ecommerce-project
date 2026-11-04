# Frontend Poczy Hardware

A loja usa Vue 3, Vite e Bootstrap 5. A página inicial, a pesquisa, os detalhes de produto e o carrinho são componentes Vue. Login, cadastro e administração mantêm módulos JavaScript separados, com a mesma camada HTTP.

## Executar

```sh
cd frontend
npm ci
npm run dev
```

A API Laravel precisa estar disponível. O Vite encaminha `/api`, `/sanctum` e `/storage` para `VITE_API_PROXY_TARGET`, cujo padrão local é `http://127.0.0.1:8000`. No Docker Compose, acesse `http://localhost`: o serviço `proxy` encaminha essas rotas diretamente para a API e as páginas e WebSockets para o Vite. Para uma API em outra origem, configure `VITE_API_URL` e autorize cookies e CORS no backend.

As URLs existentes continuam disponíveis: `index.html`, `pesquisa.html`, `produto.html?id=<id-numérico>`, `carrinho.html`, `login.html`, `cadastro.html` e `admin.html`.

## Organização

- `src/api/`: cliente HTTP, autenticação, produtos, categorias e carrinho.
- `src/domain/`: critérios de busca, formatação e apresentação de características.
- `src/components/`: cabeçalho, cards, imagens, filtros, paginação e rodapés.
- `src/pages/`: componentes Vue das quatro páginas da loja.
- `src/composables/`: busca reativa com debounce e cancelamento de requisições anteriores.
- `src/stores/`: estado do carrinho confirmado pelo servidor.
- `src/scripts/`: autenticação e painel administrativo. `admin.js` coordena acesso e eventos; `admin-api.js` carrega dados; `admin-views.js` e `admin-forms.js` geram HTML; `admin-listing.js` filtra e pagina as listas; `admin-payload.js` lê e valida os formulários.
- `src/styles/`: tema Bootstrap, estilos compartilhados e ajustes visuais por página.
- `src/data/`: dados fictícios usados exclusivamente na demonstração administrativa explícita.

## Produtos e pesquisa

A vitrine usa os produtos, categorias, marcas, preços, imagens, descrições e estoque do backend. Não usa o catálogo de demonstração como alternativa quando a API falha. Produtos sem imagem exibem um ícone; falhas de rede exibem uma mensagem com nova tentativa.

A busca e a paginação acontecem no servidor por `GET /api/products/filter`, com `filter` em JSON e os parâmetros `page` e `per_page`. Os critérios incluem nome, categoria, marcas, faixa de preço e características. As opções de características vêm de `GET /api/categories/{id}/filters`, preservando números e valores booleanos, inclusive `false`.

Os links usam os identificadores reais da API. A página de produto consulta `GET /api/products/{id}`; produtos indisponíveis ou identificadores inválidos recebem um estado próprio. Categorias e links da loja são gerados a partir dos registros do banco.

## Carrinho e sessão

O carrinho usa `GET /api/cart`, `POST /api/cart/items`, `PATCH /api/cart/items/{id}` e `DELETE /api/cart/items/{id}`. Quantidades e totais vêm do servidor. O estoque define o limite de quantidade; o antigo limite fictício de dez unidades foi removido.

A sessão usa cookies Laravel e CSRF. Credenciais e tokens de carrinho não são gravados no armazenamento do navegador. Uma escrita sem resposta confirmada bloqueia novas alterações até atualizar o carrinho. A finalização da compra e o cálculo de frete ainda não possuem interface nesta página.

Login e cadastro usam os endpoints existentes de sessão. O painel exige `role=ADMIN` e continua protegido pela API. A prévia `admin.html?demo=1` contém somente dados fictícios de consulta, isolados da vitrine e das escritas reais.

## Verificar e gerar a build

```sh
npm test
npm run format:check
npm run build
```

`npm run format` aplica o Prettier ao JavaScript, aos componentes Vue, ao CSS, aos HTMLs e às configurações do frontend. O Bootstrap continua fornecendo componentes e utilitários; o CSS próprio preserva cores, tipografia e medidas específicas da Poczy.

Os testes do frontend cobrem critérios de busca, requisições HTTP, sessão e regras dos formulários administrativos, incluindo preços, estoque e atributos tipados.

A build gera `frontend/dist/` para hospedagem estática. Em produção, encaminhe `/api`, `/sanctum` e `/storage` para o backend, ou configure uma origem de API autorizada antes da build.
