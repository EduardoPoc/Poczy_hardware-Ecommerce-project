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

Cache em arquivos, sessões em memória e fila síncrona permitem iniciar a base sem serviços adicionais. Para processamento em segundo plano, configure uma conexão de fila e um worker.

### Arquivo SQLite

`database/database.sqlite` só é usado quando a conexão ativa é SQLite. O arquivo não é versionado e contém os dados desse banco local. Ao usar somente o MySQL do Docker, ele pode ser dispensado se não contiver dados a preservar. Para voltar ao SQLite, crie o arquivo e execute as migrations. `composer run setup` também cria esse arquivo quando ausente; os testes usam `:memory:` e não dependem dele.

## Rotas implementadas

| Método | Rota | Autenticação | Resposta |
| --- | --- | --- | --- |
| GET | `/api/v1/health` | Pública | `{"status":"ok","name":"Poczy Hardware API"}` |
| GET | `/api/v1/catalog` | Pública | Categorias, atributos e produtos ativos para filtragem local |
| GET | `/api/v1/user` | Sessão Laravel (guard `web`) | Usuário autenticado em `data`, sem senha |
| GET | `/up` | Pública | Verificação de inicialização do Laravel |

O status em `/api/v1/health` verifica que a aplicação responde; não verifica a conexão com o banco. Os erros retornam JSON mesmo sem o cabeçalho `Accept`. Use `APP_DEBUG=false` em produção.

O Sanctum permanece instalado para apoiar a autenticação da SPA por sessões e CSRF. A migration de tokens Bearer foi removida; cadastro, login, logout e regras dos módulos do e-commerce ainda precisam ser implementados. O modelo `User` usa a tabela `users`, os campos `name` e `password`, e os timestamps `created_at` e `updated_at`. O campo `role` assume `CUSTOMER` no banco e não permite atribuição em massa. O seeder padrão carrega as categorias, 24 produtos de referência com especificações e um usuário de exemplo; não o execute em produção.

## Arquitetura MSC e contrato dos GETs

O padrão previsto para os próximos endpoints é Model–Service–Controller:

- Controller/Form Request: recebe e valida entradas, identifica o usuário autenticado e delega ao service.
- Service: concentra regras de negócio e de acesso, consultas, filtros e composição dos resultados.
- Model: representa dados, casts e relacionamentos, sem validação de entrada.
- API Resource: define os campos públicos da resposta, excluindo dados protegidos e custos internos.

O [contrato dos GETs](app/Http/Controllers/GET_ENDPOINTS.md) está salvo na pasta dos controllers. É documentação de planejamento, sem implementar os controllers, services ou rotas a seguir:

| GET proposto | Retorno |
| --- | --- |
| `/api/product` | Cards com ID, nome, preço e imagem |
| `/api/product/{id}` | Detalhes públicos do produto, categoria, marca e especificações |
| `/api/categoria` | ID e nome das categorias ativas |
| `/api/categoria/filter` | Produtos que correspondem aos critérios informados |
| `/api/address` | Endereços do próprio usuário autenticado |
| `/api/user` | Perfil do usuário autenticado, sem senha ou tokens |
| `/api/order` | Resumo das compras do usuário, com quantidade de unidades e linhas de itens |
| `/api/order/{id}` | Compra do usuário, itens com snapshots, endereço de entrega e timeline |

O documento detalha campos de saída, responsabilidades, paginação proposta, isolamento por usuário e status HTTP. A quantidade de produtos por compra é a soma de `order_items.quantity`; não se confunde com a contagem das linhas.

A implementação atual mantém o prefixo `/api/v1`; os caminhos propostos acima ainda não estão registrados. A migração de prefixo e o envio do JSON dos filtros precisam ser definidos. O contrato recomenda transportar os filtros na URL para manter GET e registra POST como alternativa se o corpo JSON for obrigatório.

`CatalogController` já atende `/api/v1/catalog` e ainda consulta os models diretamente. A separação em services é a arquitetura prevista para os novos endpoints, não uma camada já implementada no catálogo atual. A pesquisa existente continua filtrando no navegador; `/api/categoria/filter` propõe filtragem remota para uma etapa posterior.

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

`categories.attributes` guarda as definições dos atributos. Cada chave identifica um atributo, com `name`, `type` (`text`, `integer`, `number`, `boolean`) e, opcionalmente, `unit`, `options`, `required`, `is_filterable`, `minimum`, `maximum`, `exclusive_minimum` e `exclusive_maximum`. Esses metadados descrevem as regras pretendidas para o cadastro. A tabela, o model e a factory `CategoryAttribute` foram removidos.

Os models não validam entradas nem normalizam os valores dos JSONs. A validação deverá ser implementada posteriormente nos controllers/Form Requests. Por enquanto, `Category` e `Product` apenas persistem os JSONs via casts e oferecem os relacionamentos. A FK do banco continua exigindo uma categoria existente antes do produto. Alterar as definições não modifica produtos existentes. O cadastro administrativo ainda precisa de interface/rotas de escrita.

### Seeders com dados fixos

- `CatalogSeeder`: 12 categorias e seus atributos/opções.
- `ProductSeeder`: executa `CatalogSeeder` e insere 24 produtos (dois por categoria), com 18 marcas, descrições, especificações, preços, custos e estoques fixos.
- `UserSeeder`: cria Mariana Costa, cliente de demonstração (`mariana.costa@example.com`, senha `PoczyDemo!2026`).
- `DatabaseSeeder`: executa as cargas de produtos e usuário na mesma transação.

As configurações são ilustrativas e não representam SKUs específicos. Preços, custos e estoques são valores de desenvolvimento, não cotações ou inventário real. As seeders não usam factories ou geradores aleatórios. Podem ser executadas novamente sem duplicar ou sobrescrever registros existentes. Não removem automaticamente os antigos exemplos de um banco já preenchido. Localizam categorias/marcas pelo nome, produtos pelo par categoria/nome e usuários pelo e-mail; renomear os registros iniciais pode recriar os nomes originais.

`CatalogSeeder` define o JSON de atributos na criação da categoria e preserva definições já editadas. A carga inicial não cria pedidos, endereços ou movimentações; esses registros pertencem aos futuros fluxos do usuário e da operação.

As factories, o autoload correspondente e o trait `HasFactory` foram removidos. Os testes criam seus registros diretamente pelos models com valores explícitos. Os models continuam sem validação de entrada.

Para carregar tudo no desenvolvimento: `php artisan db:seed`. Para carregar somente o catálogo: `php artisan db:seed --class=ProductSeeder`. Acrescente `docker compose exec api` antes dos comandos no Docker.

### Aplicação das migrations

A reorganização das migrations é uma nova base para instalações novas. Um banco com o esquema anterior precisa de exportação/conversão dos dados ou recriação em desenvolvimento. `migrate` não converte automaticamente as antigas tabelas de detalhes em JSON. Não execute `migrate:fresh` em um banco com dados que precisam ser preservados. Nenhuma recriação do banco local foi feita durante a implementação.

Para uma instalação nova, use `php artisan migrate` e, no ambiente de desenvolvimento, `php artisan db:seed`. Dentro do Docker, acrescente `docker compose exec api` antes dos comandos.

### Catálogo da pesquisa

`GET /api/v1/catalog` é público e retorna `data.categories` (ID, nome e atributos filtráveis) e `data.products` (ID, categoria, marca, nome, descrição, especificações, preço e imagem). Produtos e categorias inativos são omitidos; custo e dados pessoais não são expostos. A pesquisa carrega o catálogo uma vez e aplica os filtros no navegador. Não há paginação no servidor neste endpoint.

Defina `VITE_API_BASE_URL` no frontend quando a API não estiver em `http://localhost:8000`. Alterações no cadastro aparecem depois de recarregar a pesquisa. A página inicial mantém destaques demonstrativos.

## Frontend e CORS

O projeto adotou autenticação por sessões e cookies, com proteção CSRF. A rota `/api/v1/user` utiliza `auth:web`; `User` não usa mais `HasApiTokens`, e o esquema não cria `personal_access_tokens`. A recuperação de senha continua usando a tabela separada `password_reset_tokens`.

A tabela `sessions` aceita visitantes com `user_id` nulo e possui FK para `users`, com exclusão em cascata. Seu `payload` armazena os dados de sessão e o token CSRF. A configuração `SESSION_DRIVER=database` é necessária para persistir as sessões nessa tabela.

A configuração completa da SPA ainda está pendente: iniciar as sessões nas rotas da API, configurar os domínios stateful, habilitar credenciais no CORS e implementar login/logout. O frontend deverá solicitar `/sanctum/csrf-cookie`, enviar cookies com as requisições e usar o cabeçalho `X-XSRF-TOKEN` nas operações protegidas. `CORS_ALLOWED_ORIGINS` lista as origens; atualmente o padrão permite `http://localhost:5173` e `http://127.0.0.1:5173`.

Alterar migrations já executadas não altera o banco existente. Remover a migration de tokens não exclui uma tabela já criada; a FK de sessões também precisa de uma migration de atualização se a tabela já existir.

## Verificação

```sh
composer test
php artisan route:list --path=api
vendor/bin/pint
```

Na raiz do repositório, verifique o frontend com:

```sh
node --test frontend/src/data/catalog.test.js
npm --prefix frontend run build
```

Os testes verificam respostas HTTP, autenticação, CORS, especificações JSON, persistência de definições de atributos, seeders fixas, timestamps, snapshots e restrições de exclusão. Executam migrations/rollback em SQLite na memória e compilam SQL MySQL sem servidor. A aplicação no MySQL do Docker deve ser verificada nesse ambiente. Os testes de filtros do frontend usam `node --test src/data/catalog.test.js` dentro de `frontend/`.

Os testes usam dados explícitos e não dependem de factories ou do arquivo `database.sqlite`. O contrato dos oito GETs continua documental; a cobertura de implementação desses endpoints será adicionada quando os controllers e services forem desenvolvidos.
