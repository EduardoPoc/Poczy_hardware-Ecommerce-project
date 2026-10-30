# Regras de negócio e contrato de escritas

A Poczy vende produtos de hardware organizados por categoria e marca. Cada categoria define os atributos técnicos aceitos no JSON de especificações. Clientes mantêm seu perfil e endereços e realizam pedidos; administradores mantêm catálogo, estoque e andamento das compras.

A implementação aplica o tutorial BookShelf anexado ao domínio existente: MVC com Service Layer, Form Requests, dados de entrada obtidos por `validated()`, API Resources, Eloquent, injeção de dependências e estilo Laravel/Pint. Os caminhos anteriores foram preservados para compatibilidade com o frontend. Os models continuam responsáveis por dados, casts e relacionamentos; as regras descritas aqui são executadas pelos services, sem eventos de negócio nos models.

## Respostas e acesso

| Status | Significado |
| --- | --- |
| `200` | Consulta ou atualização concluída |
| `201` | Recurso criado, incluindo defaults do banco na resposta |
| `204` | Exclusão, cancelamento, troca de senha ou logout concluídos; sem corpo |
| `401` | Sessão ausente ou inválida |
| `403` | Usuário autenticado sem a permissão administrativa |
| `404` | Recurso inexistente, indisponível ao público ou fora do escopo do cliente |
| `409` | Conflito de negócio: exclusão bloqueada, transição inválida ou colisão de unicidade concorrente |
| `419` | CSRF ausente, inválido ou sessão expirada em uma escrita |
| `422` | Dados inválidos, especificações incompatíveis ou estoque insuficiente |
| `429` | Limite de requisições atingido |

Recursos e coleções são envelopados em `data`. Produtos, pedidos e movimentos de estoque têm paginação com `page` e `per_page` (padrão 20, máximo 100). Valores monetários são strings com duas casas decimais. Custos internos aparecem apenas nas respostas administrativas de produtos, nunca no catálogo público nem nos itens das compras do cliente.

As operações administrativas exigem sessão `web` e o Gate `manage-shop`, que aceita somente `role=ADMIN`. Cadastro e edição do perfil não aceitam `role`; a promoção de um administrador deve ocorrer por um processo confiável fora da API pública. Endereços e pedidos do cliente são buscados pelos relacionamentos do usuário da sessão, sem aceitar `user_id` fornecido pelo cliente.

## Sessão e perfil

| Método | Rota | Entrada/ação |
| --- | --- | --- |
| POST | `/api/user` | Cadastro: `name`, `email`, `password`, `password_confirmation` |
| POST | `/api/login` | `email`, `password`; regenera o ID da sessão |
| POST | `/api/logout` | Encerra sessão e renova CSRF |
| PUT/PATCH | `/api/user` | Edita `name` e/ou `email`; mudança de e-mail exige `current_password` |
| PUT | `/api/user/password` | `current_password`, `password`, `password_confirmation` |
| DELETE | `/api/user` | Exige `current_password`; bloqueado se houver pedidos ou histórico de senha |

O cadastro retorna `201` com `role=CUSTOMER`, sem autenticar automaticamente. E-mails recebidos são normalizados para minúsculas; são únicos e limitados a 150 caracteres. Nome aceita até 100 caracteres. Senhas novas exigem ao menos 12 caracteres, maiúsculas, minúsculas, números, símbolo e confirmação, com limite de 72 caracteres. O hash é aplicado pelo cast do model, e senha/tokens nunca entram na Resource.

Mudanças de e-mail limpam `email_verified_at`. A troca de senha verifica novamente a senha atual com o usuário bloqueado, altera o hash e cria `password_change_histories` na mesma transação. Depois, encerra a sessão corrente. Com `SESSION_DRIVER=database`, também remove as sessões desse usuário e preserva as de outras contas. Para esse fluxo, mantenha a conexão das sessões no mesmo banco da aplicação. O histórico impede exclusão física posterior da conta, conforme a FK existente; anonimização exige uma política e um fluxo próprios.

Login permite cinco tentativas por minuto por combinação e-mail/IP e vinte por IP. Cadastro permite cinco requisições por hora por IP. Credenciais inválidas retornam uma mensagem genérica em `422`.

### Cookies e CSRF na SPA

Use `SESSION_DRIVER=database`, execute as migrations e mantenha frontend/API no mesmo host de desenvolvimento, por exemplo `localhost:5173` e `localhost:8000`. O `.env.example` já usa sessões persistentes. Instalações existentes com `SESSION_DRIVER=array` em `api/.env` precisam alterar esse valor e limpar o cache de configuração. `array` não mantém login entre requisições HTTP.

1. Solicite `GET /sanctum/csrf-cookie` com `credentials: 'include'`.
2. Leia e decodifique o cookie `XSRF-TOKEN` e envie seu valor em `X-XSRF-TOKEN` nas escritas.
3. Execute `POST /api/login` e envie os cookies nas requisições seguintes.
4. Execute `POST /api/logout` para encerrar a sessão. Após logout ou troca de senha, obtenha novamente o cookie CSRF antes de outro login.

`web` aplica sessão e CSRF nas rotas de escrita, inclusive cadastro e login; não há exceções de CSRF. O middleware de teste do Laravel dispensa CSRF por padrão; há um teste específico exercitando a proteção com esse bypass desativado. Os endpoints usam `auth:web`, sem autenticação por Bearer. CORS aceita credenciais apenas nas origens configuradas em `CORS_ALLOWED_ORIGINS`.

## Endereços

CRUD em `/api/address` e `/api/address/{id}`, sempre do próprio usuário. POST exige `postal_code`, `street`, `number`, `city` e `state`; `complement` é opcional/anulável. PUT/PATCH aceitam atualização parcial. CEP aceita oito dígitos com ou sem hífen; estado deve ser uma UF brasileira em maiúsculas. Limites de tamanho acompanham as colunas do banco. `user_id` é proibido.

Editar ou excluir um endereço cadastrado não modifica os snapshots já salvos nos pedidos. Endereços de outro usuário retornam `404` em leitura, atualização e exclusão.

## Catálogo administrativo

| Recurso | Criação | Atualização/exclusão | Consulta administrativa |
| --- | --- | --- | --- |
| Categoria | `POST /api/categoria` | `PUT/PATCH/DELETE /api/categoria/{id}` | `GET /api/admin/categoria` e `/{id}` |
| Produto | `POST /api/product` | `PUT/PATCH/DELETE /api/product/{id}` | `GET /api/admin/product` e `/{id}` |
| Marca | `POST /api/brand` | `PUT/PATCH/DELETE /api/brand/{brand}` | `GET /api/brand` e `/{brand}` |

Consultas públicas continuam omitindo produtos inativos ou de categoria inativa. Consultas administrativas incluem registros desativados. As respostas públicas anteriores de cards/categorias e `/api/catalog` mantêm o formato original; o detalhe de categoria também está disponível em `GET /api/categoria/{id}`.

Categoria aceita `name` único (até 100 caracteres), `description`, `attributes` e `is_active`. Marca aceita `name` único com até 100 caracteres. Produto exige `category_id`, `name` e `price`; aceita `brand_id`, `description`, `specifications`, `cost`, `stock` inicial, `image_url` e `is_active`. Nome tem até 150 caracteres; imagem é URL HTTP/HTTPS com até 500 caracteres. Preço deve ser positivo, custo não negativo; ambos têm no máximo duas casas e limite de `99999999.99`.

`stock` é permitido somente no cadastro, entre zero e `2147483647`. Saldo inicial positivo gera uma entrada na mesma transação do produto. Edições posteriores usam exclusivamente o endpoint de movimentações. Produto novo retorna os defaults efetivos de `is_active`, estoque e relacionamentos.

Exclusões respeitam as FKs do domínio:

- Categoria com produtos retorna `409`; use `is_active=false` para retirá-la do catálogo.
- Produto com movimentações retorna `409`; preserve-o desativado para manter auditoria.
- Produto sem movimentações pode ser removido; referências em itens de pedidos tornam-se nulas e os snapshots permanecem.
- Excluir marca mantém os produtos e torna `brand_id` nulo.
- Pedido não possui exclusão física pela API: `DELETE /api/order/{id}` cancela conforme as regras abaixo.

### Atributos dinâmicos

`attributes` é um objeto de até 100 definições, com chaves em snake_case de até 60 caracteres. Cada definição exige `name` e `type` (`text`, `integer`, `number` ou `boolean`), e pode informar `unit`, `options`, `required`, `is_filterable` e limites numéricos. Opções devem respeitar o próprio tipo e os limites; intervalos contraditórios são rejeitados. Limites são números JSON finitos, e indicadores são booleanos JSON.

`SpecificationService` compartilha a validação com `FilterProductsRequest`. `integer` aceita inteiros JSON, `number` aceita inteiros/decimais finitos, `boolean` aceita `true`/`false` e `text` aceita texto de até 1.000 caracteres. Strings numéricas e `0/1` não substituem os tipos JSON. `required` verifica presença da chave, preservando `false` como valor válido. Atributos desconhecidos e valores nulos são rejeitados; atributos opcionais podem ser omitidos.

`brand` é uma definição reservada para o filtro da marca: deve ser textual e não obrigatória; a marca do produto vem de `brand_id`, sem duplicação em `specifications`. Desativar `is_filterable` apenas remove a disponibilidade nos filtros; o atributo continua válido no cadastro.

No PUT/PATCH, o objeto `specifications` enviado substitui o objeto anterior. Se omitido, os valores existentes são preservados e revalidados, inclusive ao trocar `category_id`. Alterações nas definições da categoria são verificadas contra todos os seus produtos; se alguma especificação ficar inválida, a atualização retorna `422` e nada é gravado. Corrija os produtos antes de restringir as definições. Registros antigos inseridos diretamente no banco/seeders não são reescritos automaticamente.

## Estoque

`GET /api/product/{id}/stock-movements` lista o histórico paginado. `POST` no mesmo caminho exige administrador e recebe:

```json
{"type":"INBOUND","change":5,"reason":"Reposição de estoque"}
```

`INBOUND` exige variação positiva; `OUTBOUND`, negativa; `ADJUSTMENT` aceita ambos os sinais. Zero é inválido. `reason` é obrigatório e tem até 150 caracteres. O servidor deriva `quantity=abs(change)` e restringe o saldo final ao intervalo de zero a `2147483647`. Movimentações são imutáveis pela API.

`StockService` usa transação e bloqueio de linha para alterar o saldo e registrar a movimentação juntos. Pedidos geram saídas; cancelamentos geram entradas. O estoque inicial das seeders continua sendo a base ilustrativa existente, sem criação retroativa de históricos.

## Checkout

`POST /api/order`, autenticado e com CSRF:

```json
{
  "address_id": 1,
  "payment_method": "PIX",
  "items": [
    {"product_id": 10, "quantity": 2},
    {"product_id": 12, "quantity": 1}
  ]
}
```

Aceita de um a 100 produtos distintos, com quantidades inteiras de um a 10.000. Repetir um produto na mesma lista retorna `422`; consolide a quantidade antes de enviar. As formas de pagamento aceitas são `PIX`, `CREDIT_CARD` e `BOLETO`. A API registra a intenção e não coleta dados de cartão nem executa cobranças.

O service valida que o endereço é do cliente, bloqueia categorias/produtos em ordem de ID e exige produtos/categorias ativos e saldo suficiente. Preços, custos, nomes e categorias são obtidos do banco. A transação grava:

1. Pedido `PENDING` com totais calculados por `brick/math`, sem ponto flutuante.
2. Itens com preço/custo unitário e snapshots dos nomes.
3. Cópia do endereço de entrega.
4. Baixa do estoque e movimentações `OUTBOUND`.
5. Primeiro evento do histórico de status.

Qualquer falha desfaz o conjunto inteiro. `user_id`, `status`, `products_total`, `shipping_total` e `total` são proibidos na entrada. Campos adicionais nos itens, como preço ou custo, também são rejeitados. O total não pode exceder `99999999.99`.

O frete vem de `config/shop.php`, configurado por `SHOP_SHIPPING_TOTAL`, inicialmente `0.00`. Configure um valor monetário não negativo com duas casas. Esse valor é uma regra fixa provisória, sem cotação de transportadora. Frete e totais são congelados no pedido; futuras alterações de preço, catálogo ou endereço não mudam a compra.

## Cancelamento e andamento

Cliente: `DELETE /api/order/{id}` cancela somente pedido próprio em `PENDING`, retorna `204` e preserva pedido, itens, endereço e histórico. O estoque retorna por movimentações `INBOUND`. Repetir o cancelamento não duplica entradas nem eventos. Pedido próprio em outro status retorna `409`; pedido alheio retorna `404`.

Administrador: `PUT/PATCH /api/admin/order/{id}` recebe somente o status desejado, por exemplo `{"status":"PROCESSING"}`. Transições aceitas:

| Atual | Próximos estados |
| --- | --- |
| `PENDING` | `PAID`, `CANCELLED` |
| `PAID` | `PROCESSING`, `CANCELLED` |
| `PROCESSING` | `SHIPPED` |
| `SHIPPED` | `DELIVERED` |
| `DELIVERED` | Nenhum |
| `CANCELLED` | Nenhum |

Repetir o estado atual é uma operação sem efeito e não duplica histórico. Alteração de status, histórico e eventual devolução de estoque usam a mesma transação, com bloqueio do pedido. Um produto removido em registros antigos bloqueia cancelamento com `409` até conciliar o estoque. Não há edição de itens/preços depois da compra.

`PAID` é uma confirmação operacional manual por administrador; ainda não comprova uma cobrança por provedor. Cancelar um pedido pago não executa reembolso financeiro. Essas integrações precisam anteceder o uso comercial desse fluxo.

## Validação e limites atuais

`composer test` executa a suíte padrão em SQLite em memória. Os testes de escrita verificam permissões, validações dinâmicas, auditoria, CSRF, revogação de sessões, totais, snapshots, rollback e cancelamento repetido. Os testes HTTP de escrita/consulta e de sessões também foram executados em um banco temporário isolado no MySQL 8.4. SQLite não reproduz bloqueios de linha; os testes atuais não simulam múltiplos checkouts em processos concorrentes.

Use `php artisan route:list --path=api` para conferir as rotas e `vendor/bin/pint --dirty --format agent` para padronizar PHP. Os testes usam `RefreshDatabase`: nunca os aponte para o banco de desenvolvimento ou produção. Esta implementação não exigiu novas dependências, mudanças de schema nem recriação dos dados locais.

Permanecem para evolução: provedor de pagamento/reembolso, cotação de frete, recuperação de senha, confirmação de e-mail, política de anonimização, compatibilidade entre componentes, interface administrativa e integração do frontend com sessão/checkout. `POST /api/order` ainda não possui chave de idempotência: uma nova chamada válida cria outro pedido e baixa estoque novamente. A idempotência descrita acima se aplica a cancelamentos e repetição do status, não à criação.
