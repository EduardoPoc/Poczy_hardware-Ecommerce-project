# Frontend Poczy Hardware

A loja usa Vue 3, Vite e Bootstrap 5. Catálogo, carrinho, conta e checkout usam componentes Vue. Login, cadastro e administração usam módulos JavaScript, com a mesma camada HTTP.

## Executar

```sh
cd frontend
npm ci
npm run dev
```

A API Laravel precisa estar disponível. O Vite encaminha `/api`, `/sanctum` e `/storage` para `VITE_API_PROXY_TARGET`, cujo padrão local é `http://127.0.0.1:8000`. No Docker Compose, acesse `http://localhost`: o serviço `proxy` encaminha essas rotas diretamente para a API e as páginas e WebSockets para o Vite. Para uma API em outra origem, configure `VITE_API_URL` e autorize cookies e CORS no backend.

As páginas usam URLs sem extensão: `/`, `/pesquisa`, `/produto?id=<id-numérico>`, `/carrinho`, `/login`, `/cadastro`, `/conta`, `/checkout` e `/admin`. Os arquivos HTML continuam sendo as entradas do Vite. Pelo proxy Nginx, os endereços antigos com `.html` redirecionam permanentemente para as URLs novas, preservando os parâmetros.

## Organização

- `src/entrypoints/`: inicialização das páginas da loja, autenticação e administração. Os HTMLs apontam somente para essas entradas.
- `src/features/catalog/`: API pública, critérios de busca, componentes de produto, composable de pesquisa e páginas do catálogo.
- `src/features/cart/`: API, estado reativo confirmado pelo servidor e página do carrinho.
- `src/features/account/`: API de perfil e endereços, montagem dos payloads, componentes e página da conta.
- `src/features/orders/`: API, estados e transições dos pedidos, detalhes e ações de pagamento/cancelamento.
- `src/features/checkout/`: API de finalização e página do checkout.
- `src/features/auth/`: sessão, destinos de retorno e formulários de login/cadastro.
- `src/features/admin/`: coordenação do painel, API, regras de formulários, listagem e apresentação. `demo/` concentra os dados fictícios carregados explicitamente por `?demo=1`.
- `src/shared/`: transporte HTTP, tradução de erros, credencial do carrinho visitante, estado visual de requisições e helpers de dinheiro, datas e imagens.
- `src/layouts/`: composição de cabeçalhos, rodapés e navegação das páginas. Pode integrar recursos de várias funcionalidades.
- `src/assets/`: imagens e ícones processados pelo Vite, organizados em `brand/`, `icons/` e `images/`. Arquivos servidos sem processamento, como favicon e página 404, ficam em `public/`.
- `src/styles/`: tema Bootstrap, estilos compartilhados e ajustes visuais por página.

Cada funcionalidade concentra seus componentes, regras e acesso aos endpoints em `api.js`. Módulos `domain.js` contêm funções sem DOM ou Vue; `shared/` não depende das funcionalidades. Imports relativos explícitos funcionam tanto no Vite quanto nos testes Node. A estrutura é multipágina: cada URL pública continua sendo uma entrada HTML.

## Produtos e pesquisa

A vitrine usa os produtos, categorias, marcas, preços, imagens, descrições e estoque do backend. Não usa o catálogo de demonstração como alternativa quando a API falha. Produtos sem imagem exibem um ícone; falhas de rede exibem uma mensagem com nova tentativa.

A busca e a paginação acontecem no servidor por `GET /api/products/filter`, com `filter` em JSON e os parâmetros `page` e `per_page`. Os critérios incluem nome, categoria, marcas, faixa de preço, características e ordenação por nome, preço ou mais recentes. As opções de características vêm de `GET /api/categories/{id}/filters`, preservando números e valores booleanos, inclusive `false`.

Os links usam os identificadores reais da API. A página de produto consulta `GET /api/products/{id}`; produtos indisponíveis ou identificadores inválidos recebem um estado próprio. Categorias e links da loja são gerados a partir dos registros do banco.

## Carrinho e sessão

O carrinho usa `GET /api/cart`, `POST /api/cart/items`, `PATCH /api/cart/items/{id}` e `DELETE /api/cart/items/{id}`. Quantidades e totais vêm do servidor. O estoque define o limite de quantidade; o antigo limite fictício de dez unidades foi removido.

A sessão usa cookies Laravel e CSRF. O token do carrinho visitante fica no localStorage e também depende do vínculo com a sessão; ele é removido após a fusão. Credenciais de autenticação não são gravadas no estado visual. Uma escrita sem resposta confirmada bloqueia novas alterações até atualizar o carrinho. O checkout cria um pedido pendente com endereço e frete calculado pelo servidor. Meus pedidos permite pagar de forma simulada ou cancelar pedidos pendentes. Minha conta também permite editar nome, e-mail e senha.

Login e cadastro usam os endpoints existentes de sessão. O painel exige `role=ADMIN` e continua protegido pela API. A prévia `/admin?demo=1` contém somente dados fictícios de consulta, isolados da vitrine e das escritas reais.

## Verificar e gerar a build

```sh
npm test
npm run format:check
npm run build
```

`npm run format` aplica o Prettier ao JavaScript, aos componentes Vue, ao CSS, aos HTMLs e às configurações do frontend. O Bootstrap continua fornecendo componentes e utilitários; o CSS próprio preserva cores, tipografia e medidas específicas da Poczy.

Os testes do frontend cobrem critérios de busca, requisições HTTP, sessão e regras dos formulários administrativos, incluindo preços, estoque e atributos tipados.

A build gera `frontend/dist/` para hospedagem estática. Em produção, encaminhe `/api`, `/sanctum` e `/storage` para o backend, ou configure uma origem de API autorizada antes da build.

O servidor da hospedagem precisa resolver os caminhos sem extensão para os arquivos HTML correspondentes. Em um Nginx que serve a pasta `dist`, configure as regras abaixo, mantendo o encaminhamento das rotas da API em seus próprios blocos:

```nginx
absolute_redirect off;

location = /index.html {
    return 301 /$is_args$args;
}

location ~ ^/(login|cadastro|pesquisa|produto|carrinho|conta|checkout|admin)(\.html|/)$ {
    return 301 /$1$is_args$args;
}

location / {
    try_files $uri $uri.html $uri/index.html =404;
}
```

## Imagem de produção

O único Dockerfile tem os alvos `development`, `build` e `production`. O Compose de desenvolvimento seleciona `development`; o Compose de produção seleciona `production`, executa os testes e o build, e serve o resultado por Nginx. As páginas sem extensão, a página 404 e o proxy de `/api`, `/sanctum` e `/storage` já estão configurados em `nginx.conf`.

Por padrão, a API usa a mesma origem da loja. `VITE_API_URL` é um argumento de build para instalações que precisam de outra origem; os cookies e CORS do backend devem acompanhar essa escolha. O procedimento completo está no README da API.
