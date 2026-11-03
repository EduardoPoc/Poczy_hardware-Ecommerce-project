# Frontend Poczy Hardware

Aplicação HTML, CSS e JavaScript com sete páginas, preparada como projeto Vite de múltiplas entradas.

## Estrutura

```text
frontend/
├── index.html                # Página inicial
├── pesquisa.html             # Pesquisa e filtros
├── produto.html              # Página individual de produto
├── carrinho.html             # Itens, quantidades e resumo do carrinho
├── login.html                # Acesso à conta e sessão atual
├── cadastro.html             # Cadastro de cliente
├── admin.html                # Painel administrativo com sessão e prévia de consulta
├── assets/                   # Imagens e ícones usados pelas páginas e importados pelos scripts
├── public/assets/favicon.svg # Ícone da aba copiado diretamente para a build
├── src/
│   ├── data/catalog.js      # Catálogo demonstrativo e regras de filtro
│   ├── assets/brand/        # Variações da marca que ainda não são publicadas
│   ├── scripts/
│   │   ├── home.js           # Cards e carrossel da página inicial
│   │   ├── search.js         # Resultados e filtros da pesquisa
│   │   ├── product.js        # Detalhes, zoom e carrinho demonstrativo
│   │   ├── products.js       # Cards, imagens, links e preços compartilhados
│   │   ├── cart.js           # Página do carrinho e estado vazio
│   │   ├── cart-store.js     # Estado local compartilhado e persistência do carrinho
│   │   ├── admin.js          # Acesso, navegação, formulários e ações administrativas
│   │   ├── admin-api.js      # Carregamento completo das coleções paginadas
│   │   ├── admin-views.js    # Indicadores, tabelas, filtros e paginação
│   │   ├── admin-forms.js    # Catálogo, atributos, estoque e detalhes de pedidos
│   │   ├── admin-utils.js    # Formatação decimal, status e escape de conteúdo
│   │   ├── shared.js         # Busca e interações comuns
│   │   ├── auth.js           # Formulários, validação e estados de autenticação
│   │   └── session.js        # Requisições com cookies de sessão e CSRF
│   └── styles/               # main.css compartilhado, auth.css, cart.css e admin.css
├── package.json
└── vite.config.js
```

## Desenvolvimento

Requer Node.js 20.19+ ou 22.12+.

```sh
cd frontend
npm install
npm run dev
```

Abra a URL informada pelo Vite. A pesquisa fica em `/pesquisa.html`; o acesso à conta, em `/login.html`; e o cadastro, em `/cadastro.html`. Os links “Entrar” da loja levam ao login.

## Página de produto

As telas e os componentes de produto da branch `frontend/sem-conexao-api` estão incorporados nesta branch, mantendo o tema e o acesso ao login/cadastro. Cards da página inicial, resultados da pesquisa e ofertas abrem `produto.html?id=<identificador>`. O identificador é gerado a partir do nome no catálogo. Links inválidos mostram uma mensagem com acesso à pesquisa.

A página inclui imagem ampliável, preço, parcelamento ilustrativo, características, descrição e produtos relacionados. “Adicionar ao carrinho” salva a quantidade escolhida; “Comprar agora” salva e abre `/carrinho.html`. Todos os links de carrinho do cabeçalho levam à nova página e exibem o contador de unidades.

## Página do carrinho

`/carrinho.html` mantém o tema da loja e apresenta imagem e link do produto, preço unitário, controles de quantidade, remoção, subtotal por item e resumo. O estado vazio oferece acesso à pesquisa, e os produtos sugeridos levam aos detalhes. É possível alterar a quantidade com os botões ou pelo campo numérico, entre 1 e 10 unidades por produto. Ao remover um item, o foco segue para outro controle ou para o acesso à loja, no estado vazio.

`cart-store.js` compartilha os dados entre produto, carrinho e contadores dos cabeçalhos, usando a chave existente `poczy-cart` no `localStorage`. Alterações são refletidas entre abas da mesma origem; registros desconhecidos são ignorados e produtos repetidos são consolidados dentro do limite. Se o navegador bloquear o armazenamento, a página informa que as alterações duram apenas enquanto estiver aberta; “Comprar agora” não navega quando os itens não puderam ser salvos.

O catálogo e o carrinho continuam locais: ainda não enviam pedidos à API, não processam pagamentos e não calculam frete. O resumo mostra somente o subtotal, com frete a calcular, e “Finalizar compra” permanece desabilitado com explicação. A sessão de login não sincroniza esse carrinho local com o carrinho do servidor.

## Login e cadastro

As telas preservam o tema grafite e verde-lima, a fonte Inter, a marca, os ícones e a imagem de notebook já usados pela loja. O formulário de acesso e o convite ao cadastro usam como referência a [identificação da Pichau](https://www.pichau.com.br/account/login); o contexto de conta, pedidos e compras também foi consultado no [KaBuM!](https://www.kabum.com.br/hotsite/app/). Os campos foram reduzidos aos aceitos pela API da Poczy.

Os formulários usam a API Laravel real: `GET /sanctum/csrf-cookie`, `POST /api/login`, `POST /api/user`, `GET /api/user` e `POST /api/logout`. As requisições enviam cookies e `X-XSRF-TOKEN`; senhas e tokens de autenticação não são gravados em `localStorage` ou `sessionStorage`. O cadastro confirma a criação e direciona ao login, pois a API não autentica automaticamente após cadastrar. Quando há sessão ativa, o login mostra a conta conectada e a opção de sair, e o cabeçalho da loja mostra “Minha conta”.

Em desenvolvimento, a API usa o mesmo hostname do frontend na porta `8000` (por exemplo, `localhost:8000`). Em produção, usa a mesma origem da página. Para separar os serviços, defina `VITE_API_URL` em um arquivo de ambiente local antes de iniciar o Vite ou gerar a build; veja `.env.example`. A API precisa estar acessível, com sessões persistentes e CORS autorizando a origem do frontend. Siga o [contrato de cookies e CSRF](../api/BUSINESS_RULES.md#cookies-e-csrf-na-spa).

O cadastro valida nome, e-mail, senha de pelo menos 12 caracteres com maiúsculas, minúsculas, número e símbolo, limite de 72 bytes e confirmação. Há mensagens por campo, visibilidade de senha e estados de envio, erro e sucesso. A recuperação de senha informa sua indisponibilidade, porque a API ainda não oferece esse fluxo. Login social e permanência de sessão por “lembrar de mim” não são oferecidos, pois não estão implementados no backend.

## Painel administrativo

`/admin.html` usa a sessão existente e exige `role=ADMIN`. Os endpoints administrativos também são protegidos pela API. Clientes e visitantes não recebem o conteúdo administrativo. O login de uma conta administradora oferece o acesso ao painel; nos cabeçalhos da loja, o atalho da conta passa a indicar “Administração”. O painel não cria administradores nem promove contas.

Áreas disponíveis:

- **Visão geral:** produtos cadastrados, pedidos em aberto, produtos ativos com até cinco unidades, soma dos pedidos pagos/enviados/entregues, últimos pedidos e distribuição por status. Os indicadores são calculados com os dados carregados da API, sem percentuais de crescimento inventados.
- **Produtos:** busca, filtros, paginação, cadastro, edição, ativação/desativação e exclusão quando permitida pelo servidor. As características seguem os atributos da categoria, preservando os tipos JSON, incluindo `false`. O estoque inicial pertence ao cadastro; edições de saldo usam movimentações.
- **Categorias e marcas:** cadastro, edição e exclusão, com ativação/desativação de categorias. O editor de atributos oferece identificador, nome, tipo, unidade, opções, obrigatoriedade, filtros e limites numéricos.
- **Estoque:** filtro de saldos, entradas, saídas, ajustes com motivo e histórico de movimentações.
- **Pedidos:** busca, filtro por status, itens e valores da compra, endereço preservado, histórico e próximas etapas permitidas. As transições seguem `PENDING → PAID → SHIPPED → DELIVERED`, com cancelamento somente em `PENDING`. O pagamento do projeto continua simulado.

As escritas usam cookies e CSRF via `session.js`, com POST/PATCH/DELETE, validação nativa e mensagens do servidor. Exclusões exibem confirmação com o registro envolvido. Conflitos de vínculo/histórico são respeitados. Se uma escrita perde a resposta, novas tentativas ficam bloqueadas até atualizar os dados para evitar repetir uma operação sem confirmação. Uma falha de atualização mantém os dados anteriores identificados como desatualizados.

O painel carrega as coleções completas, percorrendo a paginação da API em lotes de até 100 registros, e pagina as tabelas localmente em dez registros. Isso permite indicadores consistentes com o catálogo inicial. Para bases maiores, será necessário criar indicadores agregados e filtros/paginação no servidor, especialmente para pedidos.

**Demonstração:** `/admin.html?demo=1` apresenta somente os dados públicos e fictícios de `src/data/admin-demo.js`. Permite navegar, filtrar e explorar os formulários; salvar e excluir ficam bloqueados e nenhuma escrita é enviada à API. A demonstração não concede acesso ao catálogo real. A tela de acesso oferece esse link quando a API não está disponível.

O painel real modifica os registros da API. A vitrine e o carrinho da loja ainda utilizam o catálogo demonstrativo local, portanto essas alterações ainda não são refletidas automaticamente na vitrine. Nenhum produto/pedido real é gerado pela demonstração.

## Build para hospedagem estática

```sh
cd frontend
npm install
npm run build
```

O Vite produz `frontend/dist/` com `index.html`, `pesquisa.html`, `produto.html`, `carrinho.html`, `login.html`, `cadastro.html`, `admin.html` e os assets. No futuro, um container Apache ou outro servidor estático pode servir **somente o conteúdo de `dist/`**. O servidor não precisa executar Node.js após a build.

Os produtos e preços exibidos hoje são dados de demonstração em `src/data/catalog.js`, compartilhados pela página inicial, pesquisa e detalhes do produto; a integração do catálogo com a API ainda não foi feita. As folhas do Bootstrap, Bootstrap Icons e a fonte Inter continuam carregadas por CDN. Quando a instalação do npm estiver disponível, o `package-lock.json` gerado deve ser versionado e os comandos podem passar a usar `npm ci`.
