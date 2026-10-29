# Contrato dos GETs — usuário padrão

Contrato dos oito GETs implementados em Model–Service–Controller, com Form Requests para validação da consulta e API Resources para selecionar campos públicos. Os exemplos usam registros ilustrativos; IDs e datas não são valores garantidos pelas seeders.

Os caminhos `/api/product`, `/api/categoria`, `/api/address`, `/api/user` e `/api/order` estão registrados sob o prefixo único `/api`. Os controllers estão em `app/Http/Controllers/Api`. A pesquisa atual usa `/api/catalog`, e o endpoint de saúde é `/api/health`.

## Responsabilidades no MSC

Fluxo: requisição → controller → service → model/banco → service → controller/resource → resposta JSON.

- Controller: recebe parâmetros e identidade autenticada, valida entradas (diretamente ou por Form Request), chama o service e retorna o status HTTP e a representação pública.
- Service: aplica regras de acesso, visibilidade do catálogo, filtros, cálculos de leitura e composição dos resultados. Recebe o usuário autenticado para consultas de dados pessoais.
- Model: representa os dados, casts e relacionamentos. Permanece sem validação de entrada.
- API Resource: seleciona os campos de saída; não retorna automaticamente todas as colunas ou relacionamentos do model.

As classes abaixo estão em `app/Http/Controllers/Api`, `app/Services`, `app/Http/Requests` e `app/Http/Resources`. Os models permanecem sem validação de entrada.

## Mapa de endpoints

Todos os endpoints usam GET e retornam JSON.

| Caminho | Controller | Service | Acesso | Resultado |
| --- | --- | --- | --- | --- |
| `/api/product` | `ProductController::index` | `ProductService::listPublicProducts` | Público | Produtos para os cards |
| `/api/product/{id}` | `ProductController::show` | `ProductService::getPublicProduct` | Público | Detalhes públicos de um produto |
| `/api/categoria` | `CategoryController::index` | `CategoryService::listPublicCategories` | Público | Categorias existentes e ativas |
| `/api/categoria/filter` | `ProductFilterController::__invoke` | `ProductService::filterPublicProducts` | Público | Cards dos produtos que correspondem ao filtro |
| `/api/address` | `AddressController::index` | `AddressService::listUserAddresses` | Autenticado | Somente os endereços do próprio usuário |
| `/api/user` | `UserController::show` | `UserService::getProfile` | Autenticado | Dados públicos do próprio perfil |
| `/api/order` | `OrderController::index` | `OrderService::listUserOrders` | Autenticado | Resumo das compras do próprio usuário |
| `/api/order/{id}` | `OrderController::show` | `OrderService::getUserOrder` | Autenticado | Compra do próprio usuário, itens, entrega e timeline |

Produtos, categorias e filtros podem ser consultados por um usuário padrão sem privilégios de administrador. Os endpoints públicos permitem a navegação de visitantes. Endereços, perfil e pedidos exigem sessão autenticada. As rotas pessoais iniciam sessão com o middleware `web` e exigem `auth:web`. `POST /api/login` cria a sessão e `POST /api/logout` a encerra; consulte o [contrato de escritas](../../../BUSINESS_RULES.md) para o handshake CSRF. Para persistência entre requisições HTTP, configure um driver de sessão persistente, como `SESSION_DRIVER=database`.

## Convenções de resposta

- Recurso único: `{ "data": { ... } }`.
- Coleção: `{ "data": [ ... ] }`; uma coleção vazia é `200` com `data: []`.
- Produtos, resultados filtrados e pedidos: paginação por `page` e `per_page`, padrão de 20 itens e limite de 100, com `links` e `meta`. Todas as compras permanecem acessíveis pelas páginas.
- Categorias e endereços podem ser retornados integralmente nesta primeira versão.
- Valores monetários: strings com duas casas decimais, por exemplo `"749.90"`.
- Datas: ISO 8601, com fuso explícito.
- GETs apenas consultam: não criam histórico, não alteram estoque, não recalculam valores persistidos nem atualizam status.

Os IDs entram nas listagens como identificadores técnicos para selecionar categorias e abrir detalhes. Os nomes de saída seguem os campos atuais (`name`, `price`, `image_url`); a interface pode exibir os rótulos em português.

## GET /api/product

Consulta `Product`, considerando somente produtos ativos cuja categoria também esteja ativa. Ordenação: nome e ID, para manter paginação estável.

Cada card contém apenas `id`, `name`, `price` e `image_url`. Imagens ainda não cadastradas retornam `null`.

```json
{
  "data": [
    { "id": 10, "name": "Monitor LG 23,8 IPS Full HD", "price": "749.90", "image_url": null }
  ]
}
```

O exemplo omite `links` e `meta` para destacar os campos do card.

## GET /api/product/{id}

Retorna `id`, `name`, `description`, `price`, `image_url`, `stock`, `specifications`, categoria (`id`, `name`) e marca (`id`, `name`, ou `null`). A marca permanece no relacionamento do produto.

“Todos os detalhes” significa os detalhes públicos do produto. Não inclui `cost`, movimentações de estoque, pedidos associados ou dados dos compradores. Produto inexistente, inativo ou de categoria inativa retorna `404` para esse público.

```json
{
  "data": {
    "id": 10,
    "name": "Monitor LG 23,8 IPS Full HD",
    "description": "Monitor de referência para uso diário, com painel IPS e resolução Full HD.",
    "price": "749.90",
    "image_url": null,
    "stock": 15,
    "category": { "id": 3, "name": "Monitores" },
    "brand": { "id": 4, "name": "LG" },
    "specifications": { "screen_size_inches": 23.8, "screen_type": "IPS" }
  }
}
```

## GET /api/categoria

Retorna as categorias ativas, ordenadas por nome, com `id` e `name`.

```json
{
  "data": [
    { "id": 3, "name": "Monitores" },
    { "id": 1, "name": "Notebooks" }
  ]
}
```

As definições de atributos ficam em `categories.attributes`, mas não fazem parte desta listagem mínima. O frontend atual as recebe por `/api/catalog`. Ao substituir esse endpoint, será preciso manter uma forma de carregar essas definições para montar os filtros; não removê-la silenciosamente.

## GET /api/categoria/filter

Objetivo: receber os critérios e retornar os mesmos campos de card de `/api/product`, com paginação.

O JSON é recebido no parâmetro `filter` da URL, não no corpo da requisição GET. JSON inválido ou ausente retorna `422`. Conteúdo no corpo de GET não tem semântica geral definida; referência: [RFC 9110, seção 9.3.1](https://www.rfc-editor.org/rfc/rfc9110.html#section-9.3.1).

Estrutura do JSON enviado em `filter`:

```json
{
  "category_id": 1,
  "min_price": "2000.00",
  "max_price": "6000.00",
  "brand_ids": [2, 5],
  "specifications": {
    "ram_capacity_gb": [16, 32],
    "has_dedicated_gpu": [false]
  }
}
```

Exemplo reduzido de URL com JSON codificado:

```http
GET /api/categoria/filter?filter=%7B%22category_id%22%3A1%2C%22specifications%22%3A%7B%22ram_capacity_gb%22%3A%5B16%2C32%5D%7D%7D&page=1&per_page=20
```

Esta implementação não recebe filtros pelo corpo GET e não registra uma consulta por POST.

Regras implementadas no `FilterProductsRequest`:

- `category_id` é obrigatório nesta consulta; a categoria deve estar ativa.
- Preços e marcas são opcionais. Lista de marcas vazia não restringe os resultados.
- `specifications` aceita somente chaves marcadas como filtráveis no JSON da categoria. Tipos, opções e limites são verificados na entrada pelo controller/Form Request.
- Os valores de uma mesma chave são alternativas (RAM 16 OU 32). Chaves distintas são combinadas (RAM selecionada E GPU dedicada selecionada).
- Lista de valores vazia não restringe aquela chave. `false` é um valor válido de filtro e não pode ser tratado como ausente.
- `min_price` e `max_price` são inclusivos; mínimo maior que máximo é uma entrada inválida.
- A marca é filtrada por `brand_id`; não é duplicada em `specifications`.
- Sem correspondências, retornar `200` com coleção vazia.

Esta rota executa filtragem remota quando chamada. A pesquisa atual continua carregando `/api/catalog` uma vez e filtrando no navegador; ela não foi migrada para o novo endpoint.

## GET /api/address

Usa a identidade da sessão e consulta os endereços pelo relacionamento do próprio usuário. Não recebe um `user_id` livre para escolher o dono dos dados.

Retorna, por endereço: `id`, `postal_code`, `street`, `number`, `complement`, `city`, `state`, `created_at` e `updated_at`. Ausência de endereços retorna coleção vazia.

## GET /api/user

Retorna os dados do usuário autenticado: `id`, `name`, `email`, `role`, `email_verified_at`, `created_at` e `updated_at`.

Excluir explicitamente `password`, `remember_token`, tokens de autenticação/recuperação, dados internos da sessão e quaisquer outros segredos. Os relacionamentos de pedidos, endereços e histórico de senhas não são carregados automaticamente neste retorno. `role` é somente leitura neste contrato.

## GET /api/order

Consulta somente os pedidos do usuário autenticado, mais recentes primeiro (`created_at` e ID decrescentes).

Cada resumo contém `id`, `status`, `payment_method`, `total`, `created_at`, `items_quantity` e `items_count`.

- `items_quantity`: soma de `order_items.quantity`, ou zero se não houver itens.
- `items_count`: quantidade de linhas de itens, sem confundir com a soma de unidades.
- Exemplo: duas unidades de um teclado e três de um mouse resultam em `items_quantity: 5` e `items_count: 2`.

Não é necessário carregar o detalhe completo de todos os itens e timelines para montar essa listagem. Os totais retornados são os valores salvos da compra.

```json
{
  "data": [
    {
      "id": 42,
      "status": "PAID",
      "payment_method": "PIX",
      "total": "879.50",
      "created_at": "2026-01-15T13:00:00Z",
      "items_quantity": 5,
      "items_count": 2
    }
  ]
}
```

## GET /api/order/{id}

Busca o ID dentro dos pedidos do usuário autenticado. Retorna `404` tanto para um pedido inexistente quanto para um pedido de outra pessoa, sem revelar se esse outro pedido existe.

O detalhe contém:

- Pedido: `id`, `status`, `payment_method`, `products_total`, `shipping_total`, `total`, `created_at`, `updated_at`, `items_quantity` e `items_count`.
- `items`: `id`, `product_id`, `category_id`, `product_name_snapshot`, `category_name_snapshot`, `quantity`, `unit_price` e `line_total` (quantidade multiplicada pelo preço unitário salvo).
- `shipping_address`: cópia do endereço de entrega salvo no pedido, com CEP, rua, número, complemento, cidade e estado. Não usar o endereço atual do cadastro para reconstruir uma entrega antiga.
- `timeline`: registros de `OrderStatusHistory`, expondo `id`, `status` e `changed_at`, ordenados por `changed_at` e ID crescentes.

Não expor `unit_cost` dos itens ou custos internos. Nomes, categorias e preços dos itens vêm dos snapshots da compra, preservados mesmo se o catálogo mudar. `product_id` e `category_id` podem ser `null` depois da exclusão do registro original.

O esquema atual não guarda snapshots de imagem, marca ou especificações completas do produto nos itens. O detalhe da compra não deve prometer versões históricas desses dados. Endereço de entrega ausente retorna `null`; timeline ausente retorna `[]`. A leitura não inventa eventos para preencher o histórico.

## Status HTTP

| Status | Situação |
| --- | --- |
| `200` | Consulta concluída, inclusive uma coleção vazia |
| `401` | Sessão ausente ou inválida em endpoints pessoais |
| `404` | Recurso inexistente, indisponível ao público ou pedido fora do escopo do usuário |
| `422` | Parâmetros de consulta ou filtros inválidos |

As rotas GET permanecem somente leitura. Cadastro, administração, checkout e mudanças de status são implementados por operações de escrita descritas em [BUSINESS_RULES.md](../../../BUSINESS_RULES.md).

## Verificação

`PublicGetEndpointsTest` cobre cards, detalhes, paginação, visibilidade e filtros, incluindo booleanos, números decimais, opções e entradas inválidas. `PrivateGetEndpointsTest` cobre sessão, isolamento por usuário, proteção de campos, snapshots, timeline e leituras sem alteração de dados do domínio. Os testes criam dados explícitos e usam SQLite em memória.

A listagem completa pode ser conferida com `php artisan route:list --path=api`. Os contratos de saída dos oito GETs originais foram preservados; a implementação das escritas não exigiu alterações nos models/migrations.

## Consultas adicionais

- `GET /api/categoria/{id}`: detalhe de categoria ativa, com descrição, definições de atributos e `is_active`.
- `GET /api/address/{id}`: detalhe de endereço do usuário autenticado; outro proprietário retorna `404`.
- `GET /api/admin/product` e `/{id}`: produtos ativos/inativos, incluindo custo e estado de publicação; somente administrador. A listagem é paginada.
- `GET /api/admin/categoria` e `/{id}`: categorias ativas/inativas com definições; somente administrador.
- `GET /api/brand` e `/{brand}`: marcas; somente administrador.
- `GET /api/product/{id}/stock-movements`: histórico paginado de estoque; somente administrador.
- `GET /api/admin/order` e `/{id}`: pedidos de todos os clientes; somente administrador. A listagem usa os resumos paginados e o detalhe inclui `user_id`.

As API Resources usam relacionamentos carregados pelos services (`whenLoaded`), evitando consultas implícitas na apresentação. `/api/catalog` mantém o formato consumido pelo frontend e também usa Resources.
