# Poczy Hardware

Projeto de e-commerce de hardware, com frontend em `frontend/` e backend Laravel em `api/`.

## Estado atual e documentação

O projeto possui migrations, models, seeders com dados fixos e uma pesquisa de produtos integrada ao catálogo da API. As categorias guardam as definições dos atributos em JSON, e os produtos guardam suas especificações em outro JSON. Os models mantêm casts e relacionamentos. Form Requests validam entradas; services aplicam as regras de negócio. A API oferece cadastro e sessão, edição do perfil, endereços, catálogo administrativo, checkout, cancelamento, mudanças de status e movimentações de estoque.

| Documento | Conteúdo |
| --- | --- |
| [Backend](api/README.md) | Execução local, rotas existentes, estrutura, seeders e verificação |
| [Contrato dos GETs](api/app/Http/Controllers/GET_ENDPOINTS.md) | Consultas públicas e pessoais, filtros e formatos de resposta |
| [Regras de negócio e escritas](api/BUSINESS_RULES.md) | Permissões, endpoints, validações, sessão/CSRF, checkout, estoque e status |
| [Banco de dados](#banco-de-dados) | Tabelas, campos, relacionamentos, índices e regras de exclusão |

A arquitetura segue MVC com Service Layer, conforme o tutorial anexado: controllers coordenam HTTP, Form Requests validam, services orquestram o negócio e API Resources apresentam o JSON. Os endpoints pessoais exigem sessão autenticada e restringem acesso ao próprio usuário; as operações administrativas exigem `role=ADMIN`. Os contratos anteriores de consulta foram preservados.

Os novos caminhos são `/api/product`, `/api/product/{id}`, `/api/categoria`, `/api/categoria/filter`, `/api/address`, `/api/user`, `/api/order` e `/api/order/{id}`. Também estão disponíveis `/api/catalog`, `/api/health` e `/up`. Todas as rotas da API usam o prefixo `/api`. A pesquisa atual usa `/api/catalog` e filtra no navegador; `/api/categoria/filter` oferece a consulta remota por JSON no parâmetro `filter` da URL, sem mudar o frontend existente.

## Desenvolvimento com Docker

O ambiente segue a estrutura do [tutorial BookShelf API](https://victor-raphael17.github.io/studies/laravel.html), adaptada para as pastas `api/` e `frontend/`. É um ambiente de desenvolvimento com PHP 8.5, Laravel 12, MySQL 8.4 e Node.js 24.

Com o Docker Desktop/daemon iniciado, execute na raiz:

```sh
cp .env.example .env
docker compose up -d --build --wait
docker compose exec api php artisan migrate
```

Em uma instalação nova de desenvolvimento, carregue o catálogo fixo e a cliente de demonstração:

```sh
docker compose exec api php artisan db:seed
```

- Frontend: `http://localhost:5173`.
- API: `http://localhost:8000/api/health`.
- MySQL: `127.0.0.1:3306`.

As credenciais ficam no `.env` da raiz. O Compose configura a conexão da API com `mysql:3306`; as demais configurações do Laravel ficam em `api/.env`, criado automaticamente junto da chave da aplicação quando ausentes. As dependências são instaladas na inicialização, e as migrations são executadas explicitamente pelo comando acima.

No Linux, configure `LOCAL_UID` e `LOCAL_GID` no `.env` da raiz com os valores de `id -u` e `id -g` antes de construir a API. Isso permite que o usuário do container escreva em `storage` e `bootstrap/cache`, montados da máquina local. Após alterar os IDs, reconstrua a imagem com `docker compose build api`. Se um volume de dependências já foi criado com outro usuário, ajuste somente a propriedade desse volume antes de reiniciar a API.

O Compose usa a rede padrão e mantém volumes para o banco, `vendor/` e `node_modules/`. Os diretórios de código são montados para desenvolvimento. `docker compose down` para os serviços e mantém os dados. Alterar as variáveis de usuário/senha do MySQL não modifica as credenciais de um banco já inicializado.

Para acompanhar a inicialização ou rodar os testes:

```sh
docker compose logs -f api mysql
docker compose exec api composer test
```

No WSL, habilite a integração com sua distribuição nas configurações do Docker Desktop. A pesquisa carrega o catálogo da API uma vez e aplica os filtros no navegador. `VITE_API_BASE_URL` define a origem da API (padrão `http://localhost:8000`). Os destaques da página inicial continuam demonstrativos.

## Desenvolvimento local

Siga os [passos de instalação da API](api/README.md#executar-localmente). Em outro terminal, inicie o frontend:

```sh
cd frontend
npm ci
npm run dev
```

O MySQL é usado pelo Compose. Na execução local, a API também aceita SQLite conforme o `.env`: `api/database/database.sqlite` só é necessário se essa conexão estiver configurada. O arquivo contém dados locais e não é versionado. Os testes da API usam SQLite em memória e não dependem dele. Usando somente MySQL, esse arquivo pode ser dispensado se não houver dados nele a preservar.

## Banco de dados

O esquema está definido em [`api/database/migrations/`](api/database/migrations/), com nomes em inglês e `snake_case`. São **18 tabelas da aplicação: 11 do domínio e sete de suporte do Laravel**. A tabela `migrations`, criada pelo framework para controlar as execuções, não entra nessa contagem.

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

`User` possui vários endereços e pedidos. O model gera o hash da senha e oculta `password` e `remember_token` na serialização automática. `role` não permite atribuição em massa. A coluna de confirmação de e-mail está disponível, mas o fluxo de verificação ainda precisa ser implementado. A autenticação escolhida usa sessões do Laravel com cookies e proteção CSRF; não há tabela de tokens Bearer no esquema atual.

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

O `DatabaseSeeder` executa `ProductSeeder` (que carrega as categorias por `CatalogSeeder`) e `UserSeeder`. O catálogo é fixo: 24 produtos, dois por categoria, e 18 marcas, com configurações plausíveis para notebooks, PCs, monitores e componentes. São configurações ilustrativas, sem vínculo com SKUs específicos; preços, custos e estoques são exemplos para desenvolvimento, não cotações ou inventário real. As opções categóricas de tela, socket, DDR, formato e tipo também são definidas nas categorias.

`UserSeeder` cria a cliente de demonstração Mariana Costa (`mariana.costa@example.com`, senha `PoczyDemo!2026`). As cargas não geram dados aleatórios e não usam factories. As factories e o suporte `HasFactory` nos models foram removidos; os testes criam registros explícitos. As seeders preservam registros existentes e inserem somente os ausentes. Elas não apagam os antigos produtos de demonstração de um banco já preenchido. Identificam categorias e marcas pelo nome, produtos pelo par categoria/nome e usuários por e-mail; renomear registros iniciais pode recriar os nomes originais na próxima carga.

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

`GET /api/catalog` fornece categorias ativas, definições filtráveis e produtos ativos com marca, preço e especificações. A pesquisa faz uma única carga ao abrir a página e filtra em memória, incluindo `false` como opção válida. Marca continua em `products.brand_id`, com a definição reservada `brand` controlando seu filtro; preço continua em `products.price`. Recarregue a página para buscar alterações do catálogo. Não existe paginação no servidor neste endpoint; carregar todos os produtos é adequado à base inicial, mas exige revisão quando o catálogo crescer. A API administrativa permite cadastrar e editar categorias, atributos, marcas e produtos. A interface administrativa no frontend continua pendente.

### Pedidos e histórico

**`orders` — model `Order`**

| Campo | Tipo no MySQL | Regra |
| --- | --- | --- |
| `id` | BIGINT UNSIGNED | Chave primária, auto incremento |
| `user_id` | BIGINT UNSIGNED | Obrigatório; referência a `users.id` |
| `status` | VARCHAR(30) | `PENDING`, `PAID`, `PROCESSING`, `SHIPPED`, `DELIVERED` ou `CANCELLED`, controlados pelo service |
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
| `quantity` | INT UNSIGNED | Obrigatório |
| `type` | ENUM('INBOUND', 'OUTBOUND', 'ADJUSTMENT') | Obrigatório |
| `change` | INT | Variação com sinal, como `+5` ou `-2` |
| `reason` | VARCHAR(150) | Anulável |

Um produto possui várias movimentações. `StockService` altera o saldo e cria a movimentação na mesma transação, com bloqueio do produto. Entradas são positivas, saídas negativas e ajustes aceitam ambos os sinais; saldo negativo é rejeitado. Criar registros diretamente pelo model não executa essa regra.

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
| `cache` | Cache quando o driver de banco é utilizado | `key` como chave primária, `value`, `expiration` |
| `cache_locks` | Bloqueios do cache | `key` como chave primária, `owner`, `expiration` |
| `jobs` | Fila de tarefas | `id`, `queue`, `payload`, `attempts`, `reserved_at`, `available_at` |
| `job_batches` | Lotes de tarefas | `id`, `name`, contadores de tarefas, `failed_job_ids`, `options`, `cancelled_at`, `finished_at` |
| `failed_jobs` | Falhas das tarefas | `id`, `uuid` único, `connection`, `queue`, `payload`, `exception`, `failed_at` |

`sessions.user_id` é uma chave estrangeira anulável para `users.id`: visitantes podem ter sessões sem usuário, e a exclusão de um usuário remove suas sessões em cascata. `payload` guarda os dados da sessão, incluindo o token CSRF. A proteção CSRF não precisa de uma tabela própria. As tabelas internas são administradas pelos serviços do Laravel e não precisam de models próprios em `App\Models`.

### Autenticação por sessões e CSRF

O esquema está preparado para sessões em banco e não cria `personal_access_tokens`. A rota protegida `/api/user` usa o guard `web`, sem autenticação Bearer. `password_reset_tokens` continua necessária para recuperação de senha, e `remember_token` para o recurso “lembrar de mim”.

Para usar a tabela `sessions`, configure `SESSION_DRIVER=database`, já definido no `.env.example` atualizado. Instalações existentes com `array` em `api/.env` devem atualizar esse valor e limpar o cache de configuração; esse driver não persiste login entre requisições. `POST /api/login` e `POST /api/logout` estão implementados. As rotas de escrita usam `web` para sessão/CSRF e as pessoais também exigem `auth:web`; o CORS permite credenciais para origens autorizadas. O frontend deve obter `/sanctum/csrf-cookie`, enviar cookies e usar `X-XSRF-TOKEN` nas escritas. A integração nas telas continua pendente; veja o [contrato de sessão](api/BUSINESS_RULES.md#cookies-e-csrf-na-spa).

Remover a migration de criação de tokens afeta instalações novas; não exclui uma tabela `personal_access_tokens` que já exista em outro banco. Alterar a migration de `sessions` também não adiciona a FK a uma tabela já migrada: nesse caso, será necessária uma migration de atualização específica.

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
docker compose exec api php artisan test --compact
```

Eles verificam os relacionamentos, timestamps automáticos, snapshots, restrições de exclusão, persistência e expiração de sessões e migrations com rollback. A suíte padrão usa SQLite em memória e também verifica a compilação do esquema MySQL. Os testes HTTP de escrita e consulta também foram executados em um banco temporário separado no MySQL 8.4 do Docker.

Os testes também verificam a carga fixa das seeders e sua reexecução sem duplicar ou sobrescrever registros. Não usam factories ou geração aleatória. Para verificar os filtros e o build do frontend a partir da raiz:

```sh
node --test frontend/src/data/catalog.test.js
npm --prefix frontend run build
```

### Regras de negócio implementadas e próximos passos

O [contrato de escritas](api/BUSINESS_RULES.md) descreve cadastro, login/logout, perfil, alteração de senha com auditoria, endereços, catálogo administrativo, checkout transacional, cancelamento e transições de status. Valores e snapshots são calculados e copiados no servidor, o estoque não pode ficar negativo e clientes não escolhem preços, frete, status ou privilégios. A exclusão de pedidos pela API representa cancelamento, preservando os registros da compra.

O [contrato dos GETs](api/app/Http/Controllers/GET_ENDPOINTS.md) mantém consultas públicas e pessoais, paginação, isolamento por usuário e filtros por JSON na query string. A pesquisa continua filtrando no navegador e mantém o carrinho de visitantes em `localStorage`.

O frete é configurado no servidor por `SHOP_SHIPPING_TOTAL`, inicialmente `0.00`. A forma de pagamento registra uma intenção; cobrança, confirmação por provedor e reembolso dependem de integração futura. Também permanecem pendentes recuperação de senha e confirmação de e-mail, compatibilidade entre componentes, interface administrativa, integração do frontend com sessão/checkout e chaves de idempotência para repetição de `POST /api/order`.
