# Poczy Hardware API

Backend Laravel 12, sem frontend ou dependências Node.js. Requer PHP 8.4.1+ e Composer para as dependências fixadas neste `composer.lock`. Os testes usam PHPUnit 11.

## Executar com Docker

Na raiz do repositório, com os serviços iniciados:

```sh
docker compose exec api php artisan migrate
docker compose exec api php artisan migrate:status
docker compose exec api php artisan db:seed
docker compose exec api php artisan tinker
```

A carga de seeders é para desenvolvimento e insere o catálogo fixo e a cliente de demonstração descritos abaixo. As migrations foram reorganizadas como base para instalações novas; consulte [Aplicação das migrations](#aplicação-das-migrations) antes de usar um banco com o esquema anterior.

O Compose define `DB_HOST=mysql` e usa as credenciais `MYSQL_*` do `.env` da raiz. Os arquivos da API ficam montados no container; as configurações restantes estão em `api/.env`.

No container, `artisan serve` usa `--no-reload` para preservar as variáveis de conexão definidas pelo Compose no processo HTTP. Alterações de configuração exigem reiniciar a API; as alterações dos arquivos PHP continuam sendo lidas nas requisições.

No Linux, os argumentos de build `LOCAL_UID` e `LOCAL_GID` vêm do `.env` da raiz e devem corresponder a `id -u` e `id -g` da máquina local. A API roda sem root e precisa escrever em `storage` e `bootstrap/cache`. Se esses IDs mudarem, reconstrua a imagem e ajuste a propriedade do volume `vendor` existente para o mesmo usuário; não é necessário liberar escrita para todos nos arquivos do projeto.

## Executar localmente

```sh
cd api
composer install
cp .env.example .env
php artisan key:generate
php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"
php artisan migrate
composer run dev
```

A API fica em `http://localhost:8000`. Para instalar e migrar de uma vez, use `composer run setup`. O driver `pdo_sqlite` precisa estar habilitado no PHP para usar o banco SQLite padrão. Como alternativa, configure `DB_CONNECTION=mysql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` e `DB_PASSWORD` no `.env`, crie o banco MySQL e execute as migrações com `pdo_mysql` habilitado.

Cache em arquivos, sessões no banco e fila síncrona permitem iniciar a base sem serviços adicionais além do banco configurado. Para processamento em segundo plano, configure uma conexão de fila e um worker.

### Arquivo SQLite

`database/database.sqlite` só é usado quando a conexão ativa é SQLite. O arquivo não é versionado e contém os dados desse banco local. Ao usar somente o MySQL do Docker, ele pode ser dispensado se não contiver dados a preservar. Para voltar ao SQLite, crie o arquivo e execute as migrations. `composer run setup` também cria esse arquivo quando ausente; os testes usam `:memory:` e não dependem dele.

## Rotas implementadas

| Área | Rotas | Acesso |
| --- | --- | --- |
| Saúde | `GET /api/health`, `GET /up` | Público |
| Catálogo | `GET /api/catalog`, `/api/product`, `/api/product/{id}`, `/api/categoria`, `/api/categoria/{id}`, `/api/categoria/filter` | Público |
| Cadastro e sessão | `POST /api/user`, `POST /api/login`, `POST /api/logout` | CSRF; logout exige sessão autenticada |
| Perfil | `GET/PUT/PATCH/DELETE /api/user`, `PUT /api/user/password` | Próprio usuário |
| Endereços | `GET/POST /api/address`, `GET/PUT/PATCH/DELETE /api/address/{id}` | Próprio usuário |
| Compras | `GET/POST /api/order`, `GET/DELETE /api/order/{id}` | Próprio usuário; DELETE cancela |
| Produtos e categorias | `POST /api/product`, `PUT/PATCH/DELETE /api/product/{id}`; equivalentes em `/api/categoria` | Administrador |
| Marcas | CRUD em `/api/brand` e `/api/brand/{brand}` | Administrador |
| Estoque | `GET/POST /api/product/{id}/stock-movements` | Administrador |
| Operação | `GET /api/admin/product`, `/api/admin/product/{id}`, `/api/admin/categoria`, `/api/admin/categoria/{id}`, `/api/admin/order`, `/api/admin/order/{id}`; `PUT/PATCH /api/admin/order/{id}` | Administrador |

Consulte o [contrato de escritas e regras de negócio](BUSINESS_RULES.md) para payloads, status HTTP, permissões e transições. Os nomes singulares/portugueses das rotas anteriores foram preservados. `/api/health` verifica a resposta da aplicação, sem testar o banco; erros retornam JSON mesmo sem `Accept`. Use `APP_DEBUG=false` em produção.

O projeto usa sessão Laravel (`web`) e CSRF; Bearer tokens não autenticam essas rotas. O cadastro sempre cria `CUSTOMER`; `role` não é aceito na entrada. Cadastros e logins possuem limites de tentativas. A promoção a administrador depende de um processo confiável fora desses endpoints. As seeders são apenas para desenvolvimento.

## MVC com Service Layer e contratos HTTP

Os controllers seguem a separação de responsabilidades do tutorial anexado, preservando os oito GETs originais:

- Controller/Form Request: recebe e valida entradas, identifica o usuário autenticado e delega ao service.
- Service: concentra regras de negócio e de acesso, consultas, filtros e composição dos resultados.
- Model: representa dados, casts e relacionamentos, sem validação de entrada.
- API Resource: define os campos públicos da resposta, excluindo dados protegidos e custos internos.

O [contrato dos GETs](app/Http/Controllers/GET_ENDPOINTS.md) está salvo na pasta dos controllers. Descreve os controllers, services e rotas implementados a seguir:

| GET implementado | Retorno |
| --- | --- |
| `/api/product` | Cards com ID, nome, preço e imagem |
| `/api/product/{id}` | Detalhes públicos do produto, categoria, marca e especificações |
| `/api/categoria` | ID e nome das categorias ativas |
| `/api/categoria/filter` | Produtos que correspondem aos critérios informados |
| `/api/address` | Endereços do próprio usuário autenticado |
| `/api/user` | Perfil do usuário autenticado, sem senha ou tokens |
| `/api/order` | Resumo das compras do usuário, com quantidade de unidades e linhas de itens |
| `/api/order/{id}` | Compra do usuário, itens com snapshots, endereço de entrega e timeline |

O documento detalha campos de saída, responsabilidades, paginação, isolamento por usuário e status HTTP. A quantidade de produtos por compra é a soma de `order_items.quantity`; não se confunde com a contagem das linhas.

Todas as rotas usam o prefixo `/api`, com os controllers em `app/Http/Controllers/Api`. `/api/catalog` mantém a pesquisa atual integrada ao frontend e `/api/health` verifica a aplicação. O filtro deve ser enviado como JSON codificado no parâmetro `filter` da URL; corpo JSON em GET não é aceito para os filtros. Produtos, resultados filtrados e pedidos usam `page` e `per_page` (padrão 20, limite 100).

`CatalogController` mantém `/api/catalog`, delega consultas aos services e usa `CatalogResource` para apresentar a resposta. `ProductFilterController` concentra a ação de filtragem para manter os controllers de recursos com os métodos RESTful. A pesquisa existente continua filtrando no navegador; `/api/categoria/filter` executa filtragem remota quando chamado, sem alterar o fluxo atual do frontend.

## Banco de dados e models

O [README principal](../README.md#banco-de-dados) detalha o esquema. São 18 tabelas da aplicação: 11 de domínio e sete de suporte do Laravel, além do controle `migrations`. Cada migration do domínio contém uma tabela, com seus timestamps e índices. Os três arquivos padrão do Laravel continuam agrupados; as filas mantêm `created_at` em segundos Unix.

| Área | Models |
| --- | --- |
| Contas | `User`, `Address`, `PasswordChangeHistory` |
| Catálogo | `Category`, `Brand`, `Product` |
| Pedidos | `Order`, `OrderItem`, `OrderShippingAddress`, `OrderStatusHistory` |
| Estoque | `StockMovement` |

### Especificações e atributos

`products.specifications` armazena os dados técnicos em JSON; `description` continua sendo texto. As tabelas e models de tipos específicos de hardware foram removidos. A marca continua em `Product::brand`; a definição reservada `brand` controla a exibição do filtro sem duplicar a marca no JSON. As categorias usam ID/nome, sem `code`, e têm nome único.

`categories.attributes` guarda as definições dos atributos. Cada chave identifica um atributo, com `name`, `type` (`text`, `integer`, `number`, `boolean`) e, opcionalmente, `unit`, `options`, `required`, `is_filterable`, `minimum`, `maximum`, `exclusive_minimum` e `exclusive_maximum`. `SpecificationService` usa esses metadados para validar o cadastro e os filtros com os mesmos tipos, opções e limites. Booleanos e números preservam seu tipo JSON; `false` não é ausência. A tabela, o model e a factory `CategoryAttribute` foram removidos.

Os models mantêm dados, casts e relacionamentos. Form Requests validam os campos permitidos e `SpecificationService` verifica tipos, obrigatoriedade, opções e limites dinâmicos. A atualização parcial de produto valida o resultado completo, inclusive na troca de categoria. A categoria precisa existir antes do produto; alterações de definições que invalidem produtos existentes são rejeitadas. As rotas administrativas de escrita estão implementadas; a interface administrativa continua pendente.

### Seeders com dados fixos

- `CatalogSeeder`: 12 categorias e seus atributos/opções.
- `ProductSeeder`: executa `CatalogSeeder` e insere 24 produtos (dois por categoria), com 18 marcas, descrições, especificações, preços, custos e estoques fixos.
- `UserSeeder`: cria Mariana Costa, cliente de demonstração (`mariana.costa@example.com`, senha `PoczyDemo!2026`).
- `DatabaseSeeder`: executa as cargas de produtos e usuário na mesma transação.

As configurações são ilustrativas e não representam SKUs específicos. Preços, custos e estoques são valores de desenvolvimento, não cotações ou inventário real. As seeders não usam factories ou geradores aleatórios. Podem ser executadas novamente sem duplicar ou sobrescrever registros existentes. Não removem automaticamente os antigos exemplos de um banco já preenchido. Localizam categorias/marcas pelo nome, produtos pelo par categoria/nome e usuários pelo e-mail; renomear os registros iniciais pode recriar os nomes originais.

`CatalogSeeder` define o JSON de atributos na criação da categoria e preserva definições já editadas. A carga inicial não cria pedidos, endereços ou movimentações; esses registros são criados pelos services nos fluxos do usuário e da operação.

As factories, o autoload correspondente e o trait `HasFactory` foram removidos. Os testes criam seus registros diretamente pelos models com valores explícitos. Os models continuam sem validação de entrada.

Para carregar tudo no desenvolvimento: `php artisan db:seed`. Para carregar somente o catálogo: `php artisan db:seed --class=ProductSeeder`. Acrescente `docker compose exec api` antes dos comandos no Docker.

### Aplicação das migrations

A reorganização das migrations é uma nova base para instalações novas. Um banco com o esquema anterior precisa de exportação/conversão dos dados ou recriação em desenvolvimento. `migrate` não converte automaticamente as antigas tabelas de detalhes em JSON. Não execute `migrate:fresh` em um banco com dados que precisam ser preservados. Nenhuma recriação do banco local foi feita durante a implementação.

Para uma instalação nova, use `php artisan migrate` e, no ambiente de desenvolvimento, `php artisan db:seed`. Dentro do Docker, acrescente `docker compose exec api` antes dos comandos.

### Catálogo da pesquisa

`GET /api/catalog` é público e retorna `data.categories` (ID, nome e atributos filtráveis) e `data.products` (ID, categoria, marca, nome, descrição, especificações, preço e imagem). Produtos e categorias inativos são omitidos; custo e dados pessoais não são expostos. A pesquisa carrega o catálogo uma vez e aplica os filtros no navegador. Não há paginação no servidor neste endpoint.

Defina `VITE_API_BASE_URL` no frontend quando a API não estiver em `http://localhost:8000`. Alterações no cadastro aparecem depois de recarregar a pesquisa. A página inicial mantém destaques demonstrativos.

## Frontend e CORS

O projeto adotou autenticação por sessões e cookies, com proteção CSRF. A rota `/api/user` utiliza `auth:web`; `User` não usa mais `HasApiTokens`, e o esquema não cria `personal_access_tokens`. A recuperação de senha continua usando a tabela separada `password_reset_tokens`.

A tabela `sessions` aceita visitantes com `user_id` nulo e possui FK para `users`, com exclusão em cascata. Seu `payload` armazena os dados de sessão e o token CSRF. A configuração `SESSION_DRIVER=database` é necessária para persistir as sessões nessa tabela.

As rotas de sessão e escritas usam `web`, que inicia a sessão e verifica CSRF independentemente da lista stateful do Sanctum. Rotas pessoais também exigem `auth:web`. O `.env.example` agora usa `SESSION_DRIVER=database`; instalações existentes que ainda tenham `array` em `api/.env` precisam atualizar esse valor e executar `php artisan config:clear`. O frontend deve solicitar `/sanctum/csrf-cookie`, enviar cookies (`credentials: include`) e o valor decodificado do cookie no cabeçalho `X-XSRF-TOKEN`, então executar `POST /api/login`. Login regenera o ID da sessão; logout invalida a sessão e renova o CSRF. A integração dessas operações nas telas continua pendente. `CORS_ALLOWED_ORIGINS` lista as origens; atualmente o padrão permite `http://localhost:5173` e `http://127.0.0.1:5173`.

Alterar migrations já executadas não altera o banco existente. Remover a migration de tokens não exclui uma tabela já criada; a FK de sessões também precisa de uma migration de atualização se a tabela já existir.

## Verificação

```sh
composer test
php artisan route:list --path=api
vendor/bin/pint --dirty --format agent
```

Na raiz do repositório, verifique o frontend com:

```sh
node --test frontend/src/data/catalog.test.js
npm --prefix frontend run build
```

Os testes verificam respostas HTTP, autenticação, CORS, especificações JSON, persistência de definições de atributos, seeders fixas, timestamps, snapshots e restrições de exclusão. Executam migrations/rollback em SQLite na memória e compilam SQL MySQL sem servidor. Os testes de contas, catálogo, pedidos, consultas públicas/pessoais e sessões também foram executados em um banco temporário isolado no MySQL 8.4 do Docker. A suíte padrão continua em SQLite; nunca aponte testes com `RefreshDatabase` para o banco de desenvolvimento. Os testes de filtros do frontend usam `node --test src/data/catalog.test.js` dentro de `frontend/`.

Os testes usam dados explícitos e não dependem de factories ou do arquivo `database.sqlite`. `PublicGetEndpointsTest` e `PrivateGetEndpointsTest` cobrem os oito GETs, incluindo isolamento por usuário, sessão sem Bearer, filtros dinâmicos, paginação, proteção de custos/senhas/tokens, snapshots e timeline ordenada. Os totais de linhas dos pedidos usam `brick/math`, já incluído nas dependências do Laravel, para evitar arredondamento por ponto flutuante.

`AccountWriteEndpointsTest`, `CatalogWriteEndpointsTest` e `OrderWriteEndpointsTest` cobrem validação das escritas, permissões, auditoria de senha, CSRF, limites de login, snapshots, valores decimais, saldo insuficiente, transições de status, cancelamento repetido e rollback de operações compostas. Não foram necessárias novas dependências ou alterações no esquema para esses fluxos.
