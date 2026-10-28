# Contrato proposto dos GETs — usuário padrão

Documento de planejamento da API REST, usando MSC como Model–Service–Controller. Não implementa controllers, services, validações nem novas rotas. Os exemplos usam registros ilustrativos; IDs e datas não são valores garantidos pelas seeders.

Os caminhos abaixo preservam os nomes solicitados. Atualmente a aplicação registra `/api/v1/catalog`, `/api/v1/health` e `/api/v1/user`. Adotar `/api/product`, `/api/categoria` e os demais caminhos deste documento é uma mudança proposta; o prefixo `/v1` e a transição do catálogo atual precisam ser definidos na implementação.

## Responsabilidades no MSC

Fluxo: requisição → controller → service → model/banco → service → controller/resource → resposta JSON.

- Controller: recebe parâmetros e identidade autenticada, valida entradas (diretamente ou por Form Request), chama o service e retorna o status HTTP e a representação pública.
- Service: aplica regras de acesso, visibilidade do catálogo, filtros, cálculos de leitura e composição dos resultados. Recebe o usuário autenticado para consultas de dados pessoais.
- Model: representa os dados, casts e relacionamentos. Permanece sem validação de entrada.
- API Resource: seleciona os campos de saída; não retorna automaticamente todas as colunas ou relacionamentos do model.

Os nomes das classes e dos métodos abaixo são propostas para orientar a implementação.

## Mapa de endpoints

Todos os endpoints usam GET e retornam JSON.

| Caminho | Controller | Service | Acesso proposto | Resultado |
| --- | --- | --- | --- | --- |
| `/api/product` | `ProductController::index` | `ProductService::listPublicProducts` | Público | Produtos para os cards |
| `/api/product/{id}` | `ProductController::show` | `ProductService::getPublicProduct` | Público | Detalhes públicos de um produto |
| `/api/categoria` | `CategoryController::index` | `CategoryService::listPublicCategories` | Público | Categorias existentes e ativas |
| `/api/categoria/filter` | `CategoryController::filter` | `ProductService::filterPublicProducts` | Público | Cards dos produtos que correspondem ao filtro |
| `/api/address` | `AddressController::index` | `AddressService::listUserAddresses` | Autenticado | Somente os endereços do próprio usuário |
| `/api/user` | `UserController::show` | `UserService::getProfile` | Autenticado | Dados públicos do próprio perfil |
| `/api/order` | `OrderController::index` | `OrderService::listUserOrders` | Autenticado | Resumo das compras do próprio usuário |
| `/api/order/{id}` | `OrderController::show` | `OrderService::getUserOrder` | Autenticado | Compra do próprio usuário, itens, entrega e timeline |

Produtos, categorias e filtros podem ser consultados por um usuário padrão sem privilégios de administrador. Torná-los públicos é a proposta para permitir a navegação de visitantes. Endereços, perfil e pedidos exigem sessão autenticada. A configuração completa de autenticação da SPA continua fora desta etapa documental.

## Convenções de resposta

- Recurso único: `{ "data": { ... } }`.
- Coleção: `{ "data": [ ... ] }`; uma coleção vazia é `200` com `data: []`.
- Proposta para produtos, resultados filtrados e pedidos: paginação por `page` e `per_page`, padrão de 20 itens e limite de 100, com `links` e `meta`. Todas as compras permanecem acessíveis pelas páginas.
- Categorias e endereços podem ser retornados integralmente nesta primeira versão.
- Valores monetários: strings com duas casas decimais, por exemplo `"749.90"`.
- Datas: ISO 8601, com fuso explícito.
- GETs apenas consultam: não criam histórico, não alteram estoque, não recalculam valores persistidos nem atualizam status.

Os IDs entram nas listagens como identificadores técnicos para selecionar categorias e abrir detalhes. Os nomes de saída seguem os campos atuais (`name`, `price`, `image_url`); a interface pode exibir os rótulos em português.

## GET /api/product

Consulta `Product`, considerando somente produtos ativos cuja categoria também esteja ativa. Ordenação inicial proposta: nome e ID, para manter paginação estável.

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

As definições de atributos ficam em `categories.attributes`, mas não fazem parte desta listagem mínima. O frontend atual as recebe por `/api/v1/catalog`. Ao substituir esse endpoint, será preciso manter uma forma de carregar essas definições para montar os filtros; não removê-la silenciosamente.

## GET /api/categoria/filter

Objetivo: receber os critérios e retornar os mesmos campos de card de `/api/product`, com a paginação proposta.

O pedido original prevê um corpo JSON. Para fechar o contrato, a recomendação é manter GET e enviar o JSON codificado no parâmetro `filter` da URL. Conteúdo no corpo de GET não tem semântica geral definida e pode ser rejeitado por implementações HTTP. Referência: [RFC 9110, seção 9.3.1](https://www.rfc-editor.org/rfc/rfc9110.html#section-9.3.1).

Estrutura lógica proposta do JSON, não um corpo GET:

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

Se receber o JSON no corpo for obrigatório, a alternativa a discutir é uma consulta por POST. Nenhuma rota POST está sendo definida ou implementada aqui.

Regras propostas para a futura implementação:

- `category_id` é obrigatório nesta consulta; a categoria deve estar ativa.
- Preços e marcas são opcionais. Lista de marcas vazia não restringe os resultados.
- `specifications` aceita somente chaves marcadas como filtráveis no JSON da categoria. Tipos, opções e limites são verificados na entrada pelo controller/Form Request.
- Os valores de uma mesma chave são alternativas (RAM 16 OU 32). Chaves distintas são combinadas (RAM selecionada E GPU dedicada selecionada).
- Lista de valores vazia não restringe aquela chave. `false` é um valor válido de filtro e não pode ser tratado como ausente.
- `min_price` e `max_price` são inclusivos; mínimo maior que máximo é uma entrada inválida.
- A marca é filtrada por `brand_id`; não é duplicada em `specifications`.
- Sem correspondências, retornar `200` com coleção vazia.

Esta rota propõe filtragem remota. Isso difere da pesquisa atual, que carrega `/api/v1/catalog` uma vez e filtra no navegador. A implementação deverá escolher quando enviar os critérios; esta etapa não altera o frontend nem inicia consultas a cada clique.

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

## Status HTTP previstos

| Status | Situação |
| --- | --- |
| `200` | Consulta concluída, inclusive uma coleção vazia |
| `401` | Sessão ausente ou inválida em endpoints pessoais |
| `404` | Recurso inexistente, indisponível ao público ou pedido fora do escopo do usuário |
| `422` | Parâmetros de consulta ou filtros inválidos |

As rotas GET permanecem somente leitura. Cadastro de categorias/atributos, escritas administrativas, checkout e mudanças de status serão tratados em etapas posteriores.
