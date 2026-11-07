# Poczy Hardware

Projeto de e-commerce de hardware, com frontend em `frontend/` e backend Laravel em `api/`.

## Estado atual e documentação

O backend usa Laravel 12 e MySQL 8.4. Os fluxos previstos têm rotas e camadas HTTP conectadas, incluindo validação por Form Requests e respostas por Resources. O navegador usa sessão, cookies e CSRF; clientes da API também podem autenticar com Bearer tokens Sanctum de oito horas. A validação integrada de persistência depende de executar a suíte com o MySQL isolado, conforme o [guia da API](api/README.md#testes-com-mysql-isolado). A loja em Vue 3 consulta o catálogo e pesquisa os produtos pela API, com filtros e paginação no servidor.

O [README da API](api/README.md) reúne os comandos de desenvolvimento, testes MySQL isolados e produção com PHP-FPM/Nginx. O esquema do banco está descrito abaixo.

## Desenvolvimento com Docker

Com Docker iniciado, prepare `.env` a partir de `.env.example` se ainda não existir. No Linux/WSL, configure `LOCAL_UID` e `LOCAL_GID` conforme `id -u` e `id -g`.

```sh
docker compose up -d --build --wait
docker compose exec api php artisan migrate
docker compose exec api php artisan db:seed
```

- Frontend: `http://localhost`.
- API: `http://localhost/api/products`.
- Saúde: `http://localhost/up`.
- MySQL: `127.0.0.1:3306`.

O serviço `proxy` usa Nginx como entrada única para o frontend e encaminha `/api`, `/sanctum`, `/storage` e `/up` para a API. As portas do frontend e da API ficam internas à rede Docker. O frontend mantém o Vite e a atualização automática via WebSocket.

O Compose de desenvolvimento usa `api/Dockerfile.dev`, monta o código e mantém volumes para banco e dependências. As credenciais de banco ficam no `.env` da raiz. Dentro do container, a API conecta em `mysql:3306`; as demais configurações ficam em `api/.env`. As migrations e seeders são comandos explícitos.

## Testes MySQL

O ambiente de testes usa um banco separado, sem portas publicadas e com dados temporários:

```sh
docker compose -f compose.test.yaml up --build --abort-on-container-exit --exit-code-from tests
docker compose -f compose.test.yaml down
```

Não execute a suíte contra o banco de desenvolvimento. O banco e usuário de testes são `ecommerce_test`, e a suíte verifica essa configuração antes de recriar tabelas.

## Produção

`api/Dockerfile` gera imagens PHP-FPM e Nginx. Prepare `.env.production` a partir de `.env.production.example` com credenciais e `APP_KEY` próprias. Os passos de build, migrations e inicialização estão no [guia da API](api/README.md#produção). O Compose de produção é `compose.prod.yaml` e não executa seeders fictícios automaticamente.

## Desenvolvimento local

Siga os [passos da API](api/README.md#executar-localmente), usando PHP 8.5 com `pdo_mysql` e MySQL 8.4. Para o frontend:

```sh
cd frontend
npm ci
npm run dev
```

## Banco de dados

O esquema está definido em [`api/database/migrations/`](api/database/migrations/), com nomes em inglês e `snake_case`. São **20 tabelas da aplicação**. A tabela `migrations`, criada pelo framework para controlar as execuções, não entra nessa contagem.

Esta documentação descreve o resultado de executar todas as migrations atuais. Para conferir quais já foram aplicadas no seu banco:

```sh
docker compose exec api php artisan migrate:status
```

### Organização das migrations e timestamps

Cada migration do domínio cria e remove somente uma tabela. Os três arquivos padrão do Laravel (`0001_*`) mantêm suas tabelas agrupadas. Todas as tabelas definem `created_at` e `updated_at` na própria criação, sem migration central de timestamps. `jobs.created_at` e `job_batches.created_at` continuam inteiros Unix, compatíveis com as filas.

Esta base foi remodelada para instalações novas. Um banco que já executou as migrations anteriores precisa de exportação/conversão dos dados ou de recriação em desenvolvimento; `migrate` sozinho não converte as antigas tabelas de hardware em JSON. Não execute `migrate:fresh` em um banco com dados que precisam ser preservados. A implementação e os testes não recriam o banco local.

### Contas e endereços

**`users` — model `User`**

| Campo | Tipo no MySQL | Regra |
| --- | --- | --- |
| `id` | BIGINT UNSIGNED | Chave primária, auto incremento |
| `name` | VARCHAR(100) | Obrigatório |
| `email` | VARCHAR(150) | Obrigatório, único |
| `email_verified_at` | TIMESTAMP | Anulável; momento da confirmação do e-mail |
| `password` | VARCHAR(255) | Obrigatório; hash da senha |
| `role` | ENUM('CUSTOMER', 'ADMIN') | Padrão `CUSTOMER` |
| `remember_token` | VARCHAR(100) | Anulável; recurso “lembrar de mim” da autenticação por sessão |

`User` possui vários endereços e pedidos. O model gera o hash da senha e oculta `password` e `remember_token` na serialização automática. `role` não permite atribuição em massa. A coluna de confirmação de e-mail está disponível, mas o fluxo de verificação permanece fora da primeira versão. A autenticação aceita sessões Laravel com cookies e CSRF e tokens Bearer Sanctum, cujos hashes ficam em `personal_access_tokens`.

**`password_change_histories` — model `PasswordChangeHistory`**

| Campo | Tipo no MySQL | Regra |
| --- | --- | --- |
| `id` | BIGINT UNSIGNED | Chave primária, auto incremento |
| `id_user` | BIGINT UNSIGNED | Obrigatório; chave estrangeira para `users.id`, sem restrição de unicidade |

Os campos `created_at` e `updated_at` são criados na própria migration. Um usuário pode ter vários registros, acessíveis por `User::passwordChanges()`; cada registro pertence ao usuário por `PasswordChangeHistory::user()`. A exclusão do usuário é bloqueada enquanto houver histórico, preservando os registros de auditoria.

Essa tabela não guarda e-mail, senhas ou tokens. `PUT /api/user/password` altera a senha e cria o registro de auditoria na mesma transação. O fluxo exige a senha atual, encerra a sessão corrente e revoga as demais sessões quando o driver é `database`. `password_reset_tokens` continua responsável pelas solicitações temporárias de recuperação.

**`addresses` — model `Address`**

| Campo | Tipo no MySQL | Regra |
| --- | --- | --- |
| `id` | BIGINT UNSIGNED | Chave primária, auto incremento |
| `user_id` | BIGINT UNSIGNED | Obrigatório; referência a `users.id` |
| `postal_code` | VARCHAR(9) | Obrigatório |
| `street` | VARCHAR(150) | Obrigatório |
| `number` | VARCHAR(20) | Obrigatório |
| `complement` | VARCHAR(100) | Anulável |
| `city` | VARCHAR(100) | Obrigatório |
| `state` | CHAR(2) | Obrigatório |

Um usuário pode ter vários endereços. Quando a exclusão do usuário é permitida pelas restrições de pedidos e histórico de senhas, seus endereços são excluídos em cascata.

### Catálogo

**`categories` — model `Category`**

| Campo | Tipo no MySQL | Regra |
| --- | --- | --- |
| `id` | BIGINT UNSIGNED | Chave primária, auto incremento |
| `name` | VARCHAR(100) | Obrigatório, único |
| `description` | TEXT | Anulável |
| `attributes` | JSON | Anulável; definições e regras dos atributos |
| `is_active` | BOOLEAN | Padrão `true` |

O `CatalogSeeder` cadastra as 12 categorias e as definições iniciais dos atributos. As categorias têm nome único e são relacionadas por ID, sem `code`. A carga procura categorias pelo nome e preenche o JSON de atributos somente ao criar uma categoria; as definições de categorias existentes são preservadas. Renomear uma categoria inicial e executar o seeder novamente cadastra o nome original de novo. Execute esta carga para inserir somente categorias e definições:

```sh
docker compose exec api php artisan db:seed --class=CatalogSeeder
```

O `DatabaseSeeder` carrega categorias, marcas, produtos, usuários e cenários completos de compra. A base inicial contém 49 produtos, 12 categorias e 30 marcas, incluindo indisponibilidade e desativação. O `DemoOrdersSeeder` cria cinco pedidos nos diferentes estados e carrinhos de conta e visitante. Os exemplos são fictícios e os seeders bloqueiam execução em produção.

Credenciais exclusivas de desenvolvimento/testes:

| Papel | E-mail | Senha |
| --- | --- | --- |
| Admin | admin@example.com | PoczyAdmin!2026 |
| Cliente | mariana.costa@example.com | PoczyDemo!2026 |
| Cliente | lucas.ferreira@example.com | PoczyDemo!2026 |
| Cliente | beatriz.oliveira@example.com | PoczyDemo!2026 |
| Cliente | rafael.martins@example.com | PoczyDemo!2026 |
| Cliente | ana.santos@example.com | PoczyDemo!2026 |
| Pedidos demonstrativos | pedidos.demo@example.com | PoczyDemo!2026 |

A reexecução preserva registros existentes e não repete os pedidos nem as movimentações de demonstração. Em produção, crie seu próprio administrador pelo comando interativo `php artisan app:create-admin` e cadastre os produtos pelo painel.

**`brands` — model `Brand`**

| Campo | Tipo no MySQL | Regra |
| --- | --- | --- |
| `id` | BIGINT UNSIGNED | Chave primária, auto incremento |
| `name` | VARCHAR(100) | Obrigatório, único |

**`products` — model `Product`**

| Campo | Tipo no MySQL | Regra |
| --- | --- | --- |
| `id` | BIGINT UNSIGNED | Chave primária, auto incremento |
| `category_id` | BIGINT UNSIGNED | Obrigatório; referência a `categories.id` |
| `brand_id` | BIGINT UNSIGNED | Anulável; referência a `brands.id` |
| `name` | VARCHAR(150) | Obrigatório |
| `description` | TEXT | Anulável |
| `specifications` | JSON | Anulável; valores técnicos padronizados |
| `price` | DECIMAL(10,2) UNSIGNED | Obrigatório |
| `cost` | DECIMAL(10,2) UNSIGNED | Anulável |
| `stock` | INT UNSIGNED | Padrão `0` |
| `image_url` | VARCHAR(500) | Anulável |
| `is_active` | BOOLEAN | Padrão `true` |

Uma categoria e uma marca possuem vários produtos. `products` concentra preço, custo, estoque e informações comuns; os atributos específicos ficam no JSON `specifications`. O model oferece os relacionamentos `category`, `brand`, `orderItems` e `stockMovements`.

### Especificações e filtros dinâmicos

Os modelos e tabelas específicos de hardware foram substituídos por `Product.specifications`. A descrição permanece um texto de apresentação. Exemplo de JSON para um notebook:

```json
{"storage_capacity_gb":512,"has_dedicated_gpu":false,"ram_capacity_gb":16,"screen_size_inches":15.6,"screen_type":"IPS"}
```

A própria categoria guarda o JSON com as definições dos atributos em `categories.attributes`. Cada chave corresponde a um atributo aceito nos produtos. Não existe mais tabela/model `category_attributes`.

```json
{
  "screen_size_inches": {
    "name": "Tamanho da tela",
    "type": "number",
    "unit": "pol",
    "exclusive_minimum": 0,
    "required": true,
    "is_filterable": true
  }
}
```

| Campo da definição | Uso |
| --- | --- |
| Chave no objeto | Chave técnica em snake_case, por exemplo `screen_size_inches` |
| `name` | Nome exibido, usado para ordenar os filtros |
| `type` | `text`, `integer`, `number` (inteiro ou decimal) ou `boolean` |
| `unit` | Unidade opcional, por exemplo GB, Hz ou pol |
| `options` | Lista opcional de valores permitidos, do tipo declarado |
| `required` | Exige o atributo; padrão `false` |
| `is_filterable` | Exibe no filtro; padrão `true` |
| `minimum`, `maximum` | Limites numéricos inclusivos opcionais |
| `exclusive_minimum`, `exclusive_maximum` | Limites numéricos exclusivos opcionais |

A categoria deve existir antes dos produtos, conforme a chave estrangeira `products.category_id`. As definições podem ser editadas sem reescrever os valores existentes; alterações que invalidem produtos cadastrados são rejeitadas. `SpecificationService` aplica `type`, `required`, `options` e limites tanto nos cadastros/edições quanto nos filtros recebidos por `FilterProductsRequest`.

Os models mantêm casts, campos preenchíveis e relacionamentos, sem eventos de validação ou normalização das especificações. Os JSONs são persistidos preservando os tipos fornecidos. Desativar `is_filterable` oculta o filtro e preserva os dados do produto. A descrição continua um texto de apresentação.

A loja em Vue 3 pesquisa e pagina no servidor por `GET /api/products/filter`, enviando nome, categoria, marcas, preços e especificações em `filter`. `GET /api/categories/{id}/filters` fornece marcas e opções de características da categoria, preservando números e booleanos, inclusive `false`. Os links usam IDs reais, e os detalhes vêm de `GET /api/products/{id}`. O endpoint `GET /api/catalog` continua disponível para integrações que precisam da carga completa. Marca permanece em `products.brand_id`, com a definição reservada `brand` controlando seu filtro; preço permanece em `products.price`. O painel administrativo permite cadastrar e editar categorias, atributos, marcas e produtos; as consultas da vitrine refletem esses registros.

### Pedidos e histórico

**`orders` — model `Order`**

| Campo | Tipo no MySQL | Regra |
| --- | --- | --- |
| `id` | BIGINT UNSIGNED | Chave primária, auto incremento |
| `user_id` | BIGINT UNSIGNED | Obrigatório; referência a `users.id` |
| `status` | VARCHAR(30) | `PENDING`, `PAID`, `SHIPPED`, `DELIVERED` ou `CANCELLED`, controlados pelo service |
| `payment_method` | VARCHAR(40) | Anulável |
| `products_total` | DECIMAL(10,2) UNSIGNED | Obrigatório |
| `shipping_total` | DECIMAL(10,2) UNSIGNED | Obrigatório |
| `total` | DECIMAL(10,2) UNSIGNED | Obrigatório |

Cada pedido pertence a um usuário, possui vários itens e registros de histórico, e pode ter um endereço de entrega associado. Os relacionamentos são `user`, `items`, `statusHistory` e `shippingAddress`.

**`order_shipping_addresses` — model `OrderShippingAddress`**

| Campo | Tipo no MySQL | Regra |
| --- | --- | --- |
| `order_id` | BIGINT UNSIGNED | Chave primária e referência a `orders.id` |
| `postal_code` | VARCHAR(9) | Obrigatório |
| `street` | VARCHAR(150) | Obrigatório |
| `number` | VARCHAR(20) | Obrigatório |
| `complement` | VARCHAR(100) | Anulável |
| `city` | VARCHAR(100) | Obrigatório |
| `state` | CHAR(2) | Obrigatório |

Essa tabela guarda uma cópia do endereço usado na compra, com no máximo um registro por pedido. Editar `addresses` não altera essa cópia. `OrderService::create` preenche o snapshot na mesma transação do pedido, itens, movimentações e histórico.

**`order_items` — model `OrderItem`**

| Campo | Tipo no MySQL | Regra |
| --- | --- | --- |
| `id` | BIGINT UNSIGNED | Chave primária, auto incremento |
| `order_id` | BIGINT UNSIGNED | Obrigatório; referência a `orders.id` |
| `product_id` | BIGINT UNSIGNED | Anulável; referência a `products.id` |
| `category_id` | BIGINT UNSIGNED | Anulável; referência a `categories.id` |
| `category_name_snapshot` | VARCHAR(100) | Obrigatório |
| `product_name_snapshot` | VARCHAR(150) | Obrigatório |
| `quantity` | INT UNSIGNED | Obrigatório |
| `unit_price` | DECIMAL(10,2) UNSIGNED | Obrigatório |
| `unit_cost` | DECIMAL(10,2) UNSIGNED | Anulável |

Os snapshots e valores unitários preservam os dados da compra mesmo após alterações no catálogo. O model se relaciona com `order`, `product` e `category`.

**`order_status_histories` — model `OrderStatusHistory`**

| Campo | Tipo no MySQL | Regra |
| --- | --- | --- |
| `id` | BIGINT UNSIGNED | Chave primária, auto incremento |
| `order_id` | BIGINT UNSIGNED | Obrigatório; referência a `orders.id` |
| `status` | VARCHAR(30) | Obrigatório |
| `changed_at` | DATETIME | Padrão: data e hora atuais no banco |

`changed_at` registra o momento da mudança de status, enquanto `created_at` e `updated_at` registram a criação e edição da linha. As mudanças pela API passam por `OrderService`, que valida a transição e grava o histórico na mesma transação. Alterações diretas pelos models não executam esse fluxo.

### Movimentações de estoque

**`stock_movements` — model `StockMovement`**

| Campo | Tipo no MySQL | Regra |
| --- | --- | --- |
| `id` | BIGINT UNSIGNED | Chave primária, auto incremento |
| `product_id` | BIGINT UNSIGNED | Obrigatório; referência a `products.id` |
| `quantity` | INT | Obrigatório; ajustes aceitam valores negativos |
| `type` | ENUM('INBOUND', 'OUTBOUND', 'ADJUSTMENT') | Obrigatório |
| `reason` | VARCHAR(150) | Anulável |

Um produto possui várias movimentações. `StockService` altera o saldo e cria a movimentação na mesma transação, com bloqueio do produto. Entradas somam e saídas subtraem uma quantidade positiva; ajustes usam quantidade com sinal; saldo negativo é rejeitado. Criar registros diretamente pelo model não executa essa regra.

### Regras de exclusão e índices

| Operação | Comportamento definido pelas chaves estrangeiras |
| --- | --- |
| Excluir usuário | Bloqueado se possuir pedidos ou histórico de senha; endereços e sessões são excluídos em cascata quando a exclusão é permitida |
| Excluir categoria | Bloqueado se possuir produtos; referências nos itens de pedidos ficam nulas quando a exclusão é permitida |
| Excluir marca | `products.brand_id` passa a `NULL` |
| Excluir produto | Bloqueado se possuir movimentações; quando permitido, anula referências nos itens de pedidos |
| Excluir pedido | Exclui itens, endereço de entrega e histórico de status em cascata |

As cópias de nomes e valores nos itens permanecem após a exclusão de produto ou categoria. Para desativar produtos e categorias sem excluir seus registros, use `is_active`; as consultas precisam aplicar esse filtro explicitamente.

Além das chaves primárias, estrangeiras e restrições de unicidade, existem os seguintes índices:

- `products(category_id)`, criado na migration de produtos.
- `orders(user_id, created_at)` e `orders(status, created_at)`, criados na própria migration de pedidos.
- `stock_movements(product_id, created_at)`, criado na própria migration de movimentações.
- `order_status_histories(order_id, changed_at)`, criado na migration de pedidos.
- `failed_jobs(connection, queue, failed_at)`, criado na migration de filas.

### Tabelas de suporte do Laravel

| Tabela | Finalidade | Estrutura principal, além dos timestamps |
| --- | --- | --- |
| `password_reset_tokens` | Recuperação de senha | `id` como chave primária, `email` único e `token` |
| `sessions` | Sessões quando o driver de banco é utilizado | `id`, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity` |
| `personal_access_tokens` | Autenticação Bearer com Sanctum | Identificação da conta, nome, hash único do token, abilities, last_used_at e expires_at |
| `cache` | Cache quando o driver de banco é utilizado | `key` como chave primária, `value`, `expiration` |
| `cache_locks` | Bloqueios do cache | `key` como chave primária, `owner`, `expiration` |
| `jobs` | Fila de tarefas | `id`, `queue`, `payload`, `attempts`, `reserved_at`, `available_at` |
| `job_batches` | Lotes de tarefas | `id`, `name`, contadores de tarefas, `failed_job_ids`, `options`, `cancelled_at`, `finished_at` |
| `failed_jobs` | Falhas das tarefas | `id`, `uuid` único, `connection`, `queue`, `payload`, `exception`, `failed_at` |

`sessions.user_id` é uma chave estrangeira anulável para `users.id`: visitantes podem ter sessões sem usuário, e a exclusão de um usuário remove suas sessões em cascata. `payload` guarda os dados da sessão, incluindo o token CSRF. A proteção CSRF não precisa de uma tabela própria. As tabelas internas são administradas pelos serviços do Laravel e não precisam de models próprios em `App\Models`.

### Autenticação por sessão e token

As rotas protegidas usam `auth:sanctum`. No navegador, `POST /api/login` preserva a sessão em banco, com cookies e CSRF. Obtenha `/sanctum/csrf-cookie` antes das escritas e envie `X-XSRF-TOKEN` com cookies.

Para clientes da API, `POST /api/auth/login` retorna Bearer token de oito horas; envie-o em `Authorization`. `POST /api/auth/logout` revoga os tokens da conta. A migration de `personal_access_tokens` mantém hashes das credenciais. Troca de senha revoga tokens e sessões. As regras estão no [contrato da API](api/BUSINESS_RULES.md).

O Compose de produção publica frontend, API, documentação e imagens na mesma origem, com volumes persistentes. O README da API contém os passos de migrations, criação do administrador, HTTPS e backup. O workflow `.github/workflows/ci.yml` verifica testes MySQL e imagens de produção antes da entrega.

### Consultar e verificar o banco

Na raiz do projeto, abra o Tinker dentro da API:

```sh
docker compose exec api php artisan tinker
```

Exemplos de consultas:

```php
DB::connection()->getDatabaseName();
Illuminate\Support\Facades\Schema::getTableListing();
App\Models\User::with('addresses', 'orders')->first();
App\Models\Product::with('category', 'brand')->take(5)->get();
App\Models\Order::with('items', 'shippingAddress', 'statusHistory')->first();
```

Para acessar diretamente o MySQL, o comando abaixo solicita a senha definida em `MYSQL_PASSWORD` no `.env` da raiz:

```sh
docker compose exec mysql sh -c 'mysql -u "$MYSQL_USER" -p "$MYSQL_DATABASE"'
```

Os testes podem ser executados com:

```sh
docker compose -f compose.test.yaml up --build --abort-on-container-exit --exit-code-from tests
```

Eles verificam os relacionamentos, timestamps automáticos, snapshots, restrições de exclusão, persistência e expiração de sessões e migrations com rollback. A suíte usa exclusivamente o MySQL 8.4 isolado de `compose.test.yaml`, com migrations reais e transações. O banco de desenvolvimento não é usado pelos testes.

Os testes também verificam a carga fixa das seeders e sua reexecução sem duplicar ou sobrescrever registros. Não usam factories ou geração aleatória. Para verificar os filtros e o build do frontend a partir da raiz:

```sh
npm --prefix frontend run build
```

### Regras de negócio implementadas e próximos passos

O [contrato de escritas](api/BUSINESS_RULES.md) descreve cadastro, login/logout, perfil, alteração de senha com auditoria, endereços, catálogo administrativo, checkout transacional, cancelamento e transições de status. Valores e snapshots são calculados e copiados no servidor, o estoque não pode ficar negativo e clientes não escolhem preços, frete, status ou privilégios. A exclusão de pedidos pela API representa cancelamento, preservando os registros da compra.

O [guia da API](api/README.md) descreve as rotas atuais e os fluxos já disponíveis nos services. A pesquisa filtra e pagina os produtos no servidor; o carrinho de visitantes usa a sessão e os endpoints da API.

O frete fixo é configurado no servidor por `SHOP_SHIPPING_TOTAL`, inicialmente `30.00`. O checkout do frontend usa `POST /api/checkout` com uma chave de idempotência por tentativa. O pedido nasce pendente e o cliente confirma explicitamente o pagamento simulado ou cancela antes de pagar. A API não coleta dados financeiros nem integra um gateway real, conforme o escopo acadêmico.

Recuperação de senha por link, confirmação de e-mail e análise de compatibilidade entre componentes permanecem fora da primeira versão. Antes da publicação, execute o CI completo e valide a jornada no navegador seguindo o guia de produção.
