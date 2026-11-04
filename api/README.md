# Poczy Hardware API

API Laravel 12 com PHP 8.5, MySQL 8.4 e PHPUnit 11. Desenvolvimento, testes com banco e produção usam MySQL. As imagens PHP incluem `pdo_mysql`, `bcmath`, `intl` e `zip`.

## Desenvolvimento com Docker

Na raiz do repositório, prepare `.env` a partir de `.env.example` se ainda não existir. No Linux/WSL, ajuste `LOCAL_UID` e `LOCAL_GID` conforme `id -u` e `id -g`.

```sh
docker compose up -d --build --wait
docker compose exec api php artisan migrate
docker compose exec api php artisan db:seed
```

- API: `http://localhost:8000/api/products`.
- Saúde: `http://localhost:8000/up`.
- Frontend: `http://localhost:5173`.
- MySQL local: `127.0.0.1:3306`.

O serviço `api` usa `Dockerfile.dev`, monta o código e mantém as dependências em um volume próprio. O entrypoint de desenvolvimento instala dependências, limpa o cache de configuração e cria `.env` e `APP_KEY` quando ausentes. As migrations e seeders são comandos explícitos. O servidor usa `--no-reload` para preservar as variáveis MySQL fornecidas pelo Compose no processo HTTP. Após alterar variáveis de ambiente, reinicie a API; mudanças nos arquivos PHP continuam disponíveis nas requisições.

Dentro do Compose, `DB_HOST=mysql`. As credenciais vêm das variáveis `MYSQL_*` do `.env` da raiz. O MySQL mantém seus dados no volume `mysql_data`; alterar as variáveis não altera usuários de um volume já inicializado.

## Executar localmente

Com PHP 8.5, Composer, `pdo_mysql` e as demais extensões instaladas, inicie apenas o banco pelo Compose:

```sh
docker compose up -d mysql
cd api
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

Se `api/.env` já existir, ajuste as variáveis de banco em vez de sobrescrever o arquivo. Fora do container, use `DB_HOST=127.0.0.1`, porta 3306 e as mesmas credenciais do banco de desenvolvimento. Depois de alterar variáveis, execute `php artisan config:clear`.

## Testes com MySQL isolado

Na raiz do repositório:

```sh
docker compose -f compose.test.yaml up --build --abort-on-container-exit --exit-code-from tests
docker compose -f compose.test.yaml down
```

O código de saída do primeiro comando é o resultado da suíte. O segundo remove os containers e a rede após a execução.

O arquivo de testes é independente do Compose de desenvolvimento. Ele cria `mysql_test` sem publicar portas e com armazenamento temporário. A imagem `Dockerfile.dev` contém as dependências de teste e o comando executado é `php artisan test --compact`.

O `phpunit.xml` fixa `APP_ENV=testing`, driver MySQL, banco `ecommerce_test` e usuário `ecommerce_test`. Dentro do Compose de testes, o host é `mysql_test`; para executar PHP diretamente na máquina, é necessário que o MySQL de testes esteja acessível em `127.0.0.1:3306` (ou configure `DB_HOST`/`DB_PORT`) e tenha esse banco e usuário. O banco de desenvolvimento (`ecommerce`) e seu usuário não substituem essa configuração. Um MySQL pode estar saudável para a API dentro da rede Docker enquanto a porta local usada pelo PHP no host permanece inacessível.

`Tests\TestCase` verifica o ambiente, a ausência de configuração em cache e o banco/usuário antes de qualquer `RefreshDatabase`. Os testes que usam esse trait recriam tabelas no banco de testes. Não compartilhe esse banco com dados de desenvolvimento ou produção.

A suíte cobre:

- Migrations reais no MySQL, tipos JSON/DECIMAL, chaves estrangeiras e unicidade.
- Rotas atuais de produtos e marcas, permissões administrativas e respostas JSON.
- Filtros dos services e opções de características por categoria.
- Cadastro, autenticação, endereços e pedidos pelos services.
- Transações, snapshots, cancelamentos e movimentações de estoque.
- Reexecução dos seeders sem duplicação ou sobrescrita dos registros existentes.

Os controllers estão conectados aos services, Form Requests e Resources existentes. As escritas de perfil, endereços e senha também encaminham somente dados validados. Os testes HTTP de conexão das camadas usam services simulados para verificar delegação e formato sem banco; testes de persistência, unicidade e isolamento usam MySQL.

Para conferir manualmente o ciclo de migrations somente no ambiente de testes:

```sh
docker compose -f compose.test.yaml run --rm tests php artisan migrate
docker compose -f compose.test.yaml run --rm tests php artisan migrate:rollback
docker compose -f compose.test.yaml run --rm tests php artisan migrate
```

## Produção

`Dockerfile` tem os alvos `production` (PHP-FPM) e `web` (Nginx). A imagem PHP final contém o código e dependências de produção instaladas durante o build; não contém Composer ou a suíte de testes. O código não é montado da máquina host.

Prepare `.env.production` na raiz usando `.env.production.example`. Configure credenciais próprias, `APP_URL`, origens CORS e uma `APP_KEY` persistente. Gere a chave antes do deploy, por exemplo com `php artisan key:generate --show` em um ambiente de desenvolvimento. Nunca publique o arquivo de ambiente.

```sh
docker compose --env-file .env.production -f compose.prod.yaml build
docker compose --env-file .env.production -f compose.prod.yaml up -d mysql --wait
docker compose --env-file .env.production -f compose.prod.yaml run --rm api php artisan migrate --force
docker compose --env-file .env.production -f compose.prod.yaml up -d --wait
```

O entrypoint de produção exige `APP_KEY` e gera os caches de configuração e rotas com as variáveis do ambiente. Não instala dependências, não gera outra chave e não executa migrations ou seeders automaticamente.

O Nginx publica HTTP em `127.0.0.1:8080` por padrão. Use um proxy HTTPS do servidor na frente desse endereço; `SESSION_SECURE_COOKIE=true` pressupõe acesso HTTPS no navegador. `HTTP_BIND` e `HTTP_PORT` permitem ajustar a publicação. O MySQL não publica porta no host. Banco e `storage` usam volumes persistentes. O frontend não faz parte deste Compose de produção.

`APP_ENV=production`, `APP_DEBUG=false` e logs em stderr são configurados pelo Compose. Seeders com usuários fictícios são exclusivos de desenvolvimento/testes. O endpoint `/up` verifica a aplicação; a prontidão do MySQL é verificada pelo healthcheck do banco.

## Rotas atuais

| Acesso            | Rotas                                                                          |
| ----------------- | ------------------------------------------------------------------------------ |
| Público           | `GET /api/products`, `GET /api/products/{product}`                             |
| Público           | `GET /api/brands`, `GET /api/brands/{brand}`                                   |
| Público           | `GET /api/categories`, `GET /api/categories/{category}`, `GET /api/catalog`    |
| Público           | `GET /api/products/filter?filter={...}`                                        |
| Público e autenticado | `GET /api/cart`, `POST /api/cart/items`, `PATCH/DELETE /api/cart/items/{product}` |
| Sessão e cadastro | `POST /api/login`, `POST /api/user`                                            |
| Autenticado       | `POST /api/logout`, `GET/PUT/PATCH/DELETE /api/user`, `PUT /api/user/password` |
| Autenticado       | CRUD em `/api/addresses` e `/api/addresses/{id}`                               |
| Autenticado       | `GET/POST /api/orders`, `GET/DELETE /api/orders/{id}`, `POST /api/orders/{id}/pay` |
| Administrador     | CRUD em `/api/admin/products` e `/api/admin/products/{product}`                |
| Administrador     | CRUD em `/api/admin/brands` e `/api/admin/brands/{brand}`                      |
| Administrador     | CRUD em `/api/admin/categories` e `/api/admin/categories/{category}`           |
| Administrador     | `GET /api/admin/orders`, `GET/PUT/PATCH /api/admin/orders/{id}`                |
| Administrador     | `GET/POST /api/admin/products/{id}/stock-movements`                            |
| Saúde             | `GET /up`                                                                      |

As rotas administrativas usam sessão Laravel (`web`, `auth:web`) e o Gate `manage-shop`, que exige `role=ADMIN`. Perfil, endereços e pedidos exigem `auth:web`. As rotas de sessão e as escritas usam o middleware `web`, com CSRF. Login e cadastro usam os limitadores `login` e `registration` definidos em `bootstrap/app.php`.

A API usa autenticação por sessão/cookie e proteção CSRF. Autenticação por token Bearer não está implementada.

Os controllers delegam consultas e escritas aos services e usam os Resources existentes. Perfil, endereços, categorias, produtos, marcas, estoque, pedidos, carrinho e catálogo retornam envelope `data`; listagens paginadas usam `links` e `meta`. Login e cadastro preservam os campos da conta na raiz, serializados por `UserResource`, e o campo opcional `cart_merge`. Operações sem corpo continuam retornando `204`. Os models representam dados, casts e relacionamentos; os services coordenam consultas, transações e regras de negócio. O catálogo agora utiliza `CatalogService` e `CatalogResource`, omitindo custos internos dos produtos.

A pesquisa do frontend ainda usa o catálogo local de demonstração. No backend, os filtros de produtos aceitam `q` (nome), `category_id`, `brand_ids`, `min_price`, `max_price` e `specifications`. Valores de especificações JSON usam os tipos definidos pela categoria. Categorias e marcas não exigem paginação; produtos usam `page` e `per_page`.

## Carrinho e fusão

`GET /api/cart` obtém ou cria o carrinho da sessão ou da conta. Visitantes recebem `data.cart_token` somente na criação; o frontend deve guardar esse token e enviar `X-Cart-Token` junto aos cookies da mesma sessão. O token isolado não funciona em outra sessão. As escritas também exigem CSRF (`X-XSRF-TOKEN`), obtido pelo fluxo de `/sanctum/csrf-cookie`.

- `POST /api/cart/items`: recebe `product_id` e `quantity`, somando ao item existente.
- `PATCH /api/cart/items/{product}`: substitui a quantidade; zero remove o item.
- `DELETE /api/cart/items/{product}`: remove o item.
- `POST /api/orders/{id}/pay`: pagamento simulado pelo dono ou admin, sem dados de pagamento ou cartão.

O carrinho não reserva estoque nem congela preços. Quantidades devem respeitar a disponibilidade; preços e totais usam os valores atuais do banco e aritmética decimal.

Ao enviar `X-Cart-Token` no login (`POST /api/login`) ou cadastro (`POST /api/user`), a API funde os itens do visitante ao carrinho da conta. Produtos repetidos têm as quantidades somadas, limitadas ao estoque. Produtos do visitante que ficaram inativos, ou cuja categoria foi desativada, são removidos da fusão, inclusive o item correspondente da conta; os demais itens continuam sendo fundidos. Produtos exclusivos da conta são preservados.

A resposta mantém os campos da conta na raiz e acrescenta `cart_merge.cart` e `cart_merge.adjustments`. Cada ajuste contém `product_id`, `requested_quantity`, `quantity`, `reason` e `message`: `stock_limit` indica redução por estoque, e `unavailable` indica remoção por indisponibilidade. O frontend deve exibir os avisos.

Depois do sucesso, o carrinho de visitante é excluído, o token é invalidado e o frontend deve descartá-lo. Falhas preservam o carrinho e o vínculo do visitante para nova tentativa; cadastro e fusão são atômicos. O cadastro continua sem login automático. Sem o header do token, não ocorre fusão.

O fluxo dos pedidos é `PENDING → PAID → SHIPPED → DELIVERED`, com cancelamento somente a partir de `PENDING`. Produtos vinculados a pedidos não podem ser excluídos fisicamente; use `is_active=false`.

## Estado da implementação e validação

As rotas atuais estão conectadas a controllers, services, Form Requests e Resources. Os cálculos de preço e totais do carrinho são preparados pelo `CartService`; os Resources apenas formatam os valores para JSON. A implementação da autenticação disponível usa sessão Laravel, não token Bearer.

A suíte exige MySQL e banco isolado `ecommerce_test`. A execução local do PHP conecta a `127.0.0.1:3306`; no Compose de testes a conexão usa `mysql_test`. Assim, o MySQL de desenvolvimento pode estar funcionando dentro da rede Docker sem estar acessível a um processo de teste executado no host. Se a porta local não aceitar conexão, use o Compose de testes acima. Enquanto os testes de persistência não passarem, as regras de transação, estoque, unicidade e fusão não devem ser consideradas integradamente validadas.

O método `CategoryService::availableFilters()` existe e tem cobertura direta de Service, mas não possui rota/controller conectado. O endpoint público de filtro disponível é `/api/products/filter`; não há endpoint de opções de filtro por categoria.

## Banco e seeders

São 20 tabelas da aplicação, além de `migrations`. Consulte o [esquema no README principal](../README.md#banco-de-dados). As migrations usam nomes em inglês, relações por chaves estrangeiras e dados técnicos em JSON. A coluna `stock_movements.quantity` aceita sinal em ajustes; não há coluna `change`.

Os seeders criam 12 categorias, 30 marcas, 48 produtos, cinco clientes, um administrador e seis endereços fictícios. Produtos novos com saldo recebem uma movimentação de entrada inicial. A carga usa dados fixos, preserva registros existentes e não cria pedidos.

- Administrador: `admin@example.com` / `PoczyAdmin!2026`.
- Cliente: `mariana.costa@example.com` / `PoczyDemo!2026`.

As migrations atuais descrevem uma instalação nova. Bancos com esquemas antigos precisam de conversão ou recriação explícita em desenvolvimento. Esta mudança de configuração não converte nem remove arquivos de bancos locais antigos.
