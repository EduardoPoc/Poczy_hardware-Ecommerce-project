# Regras de negócio esperadas do backend

A Poczy vende produtos de hardware organizados por categoria e marca. Cada categoria define os atributos técnicos aceitos no JSON de especificações. Clientes mantêm seu perfil e endereços e realizam pedidos; administradores mantêm catálogo, estoque e andamento das compras.

Este documento define o contrato esperado do projeto. As regras de carrinho, fusão, pagamento simulado, transições de pedidos e preservação de produtos descritas aqui prevalecem sobre os comportamentos anteriores que estiverem em conflito. Documentar uma regra não significa que ela já esteja implementada; as pendências conhecidas estão registradas ao final.

A arquitetura usa MVC com Service Layer, Eloquent e injeção de dependências. Os models representam dados, casts e relacionamentos; os services coordenam regras de negócio e transações. Requests validam entradas HTTP e Resources organizam respostas. A implementação do mecanismo de autenticação fica fora do escopo desta revisão, mas as regras de acesso esperadas permanecem documentadas.

## Acesso e autorização

- Catálogo, detalhe do produto e carrinho de visitante são públicos.
- Checkout, pedidos, perfil e administração exigem autenticação.
- Somente administradores criam, editam ou excluem produtos e categorias e alteram administrativamente o status dos pedidos.
- Somente o dono do pedido ou um administrador pode visualizá-lo, pagá-lo ou cancelá-lo. Essas ações também devem respeitar o estado atual do pedido.
- O pagamento simulado pelo dono do pedido é uma ação de negócio permitida; isso não lhe concede permissão para editar livremente o status.

## Carrinho

- Um usuário autenticado possui no máximo um carrinho ativo.
- A combinação carrinho + produto é única. Adicionar novamente um produto existente soma a quantidade ao item atual.
- Itens mantidos no carrinho têm quantidade mínima de 1. Informar quantidade 0 remove o item.
- A quantidade de um item não pode ultrapassar o estoque disponível.
- Produtos inativos ou sem estoque não podem ser adicionados ao carrinho.
- O carrinho de visitante é identificado por um token opaco gerado pelo servidor, imprevisível e protegido contra reutilização por outro visitante.
- O carrinho não congela preços: suas consultas e totais refletem o preço atual do produto no banco. O congelamento ocorre apenas ao criar o pedido.

### Interface do carrinho

| Método | Rota | Ação |
| --- | --- | --- |
| GET | `/api/cart` | Obtém ou cria o carrinho da sessão ou conta |
| POST | `/api/cart/items` | Recebe `product_id` e `quantity`; soma ao item existente |
| PATCH | `/api/cart/items/{product}` | Substitui a quantidade pelo valor de `quantity` |
| DELETE | `/api/cart/items/{product}` | Remove o item |

O token de visitante é emitido em `data.cart_token` quando o carrinho é criado. O frontend deve guardá-lo e enviá-lo em `X-Cart-Token`, mantendo também os cookies da mesma sessão. O servidor armazena somente o hash do token e o vincula a um segredo independente da sessão; o token isolado não permite acessar o carrinho em outra sessão. O token deve ser enviado também no login ou cadastro para solicitar a fusão, cuja integração permanece pendente conforme a seção final.

As rotas de carrinho usam sessão e CSRF nas escritas. Operações da mesma sessão são serializadas por bloqueio de sessão para evitar criação concorrente e perda do vínculo do visitante. O cache usado pelo ambiente precisa suportar locks compartilhados entre os processos da aplicação. As migrations de `carts` e `cart_items` adicionam as restrições de unicidade sem recriar as tabelas existentes.

### Fusão de carrinhos no login ou cadastro — obrigatória

Esta é a regra central do projeto e deve poder ser explicada pelo aluno na avaliação.

Ao autenticar pelo fluxo de login ou registro, se o frontend enviar um token de carrinho de visitante, a API deve fundir esse carrinho ao carrinho da conta, esteja ele vazio ou não. A regra adotada é somar as quantidades e limitá-las ao estoque disponível:

1. Produto presente apenas no carrinho de visitante é copiado para o carrinho do usuário, respeitando o estoque disponível.
2. Produto presente nos dois carrinhos tem as quantidades somadas, mantendo um único item.
3. Se a quantidade resultante exceder o estoque, ela é ajustada para o máximo disponível e a resposta deve avisar o usuário sobre o ajuste.
4. Quantidade final 0 não mantém um item no carrinho, conforme a regra de remoção por quantidade zero.
5. Concluída a fusão, o carrinho de visitante é descartado e seu token é invalidado, impedindo seu uso posterior.

Exemplo: a conta tem 2 unidades e o visitante tem 3. Com estoque de 4, o carrinho da conta termina com 4 unidades, e o usuário recebe um aviso de ajuste. Com estoque de 5 ou mais, termina com 5 unidades.

Uma regra alternativa, como prevalecer a maior quantidade, só é aceita se estiver documentada no README e implementada de forma consistente. O contrato atual adota a soma limitada ao estoque.

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

As respostas de erro devem usar um formato JSON consistente, com `message` e, quando aplicável, `errors` por campo. Entradas inválidas devem ser tratadas pela validação, sem se tornarem erros internos `500`. Uma transição de pedido não permitida retorna `409`.

Produtos, marcas, pedidos e carrinhos retornam Resources com envelope `data`; produtos e pedidos paginados incluem `links` e `meta`. Os demais controllers retornam objetos ou arrays diretamente, e suas listas paginadas usam o formato nativo do Laravel: `data`, `total`, `current_page`, `per_page` e URLs de navegação na raiz. Produtos, pedidos e movimentos de estoque têm paginação com `page` e `per_page` (padrão 20, máximo 100). `/api/catalog` mantém seu envelope `data` para compatibilidade com o frontend. Valores monetários são strings com duas casas decimais. Os Resources públicos de produtos e itens de pedidos omitem custos internos. O controller de catálogo ainda retorna models diretamente; a ocultação de custos nesse endpoint depende da integração pendente de seu Resource.

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

O cadastro retorna `201` com `role=CUSTOMER`. Quando houver token de carrinho de visitante no fluxo de cadastro ou login, a fusão obrigatória descrita acima deve ser executada para a conta. E-mails recebidos são normalizados para minúsculas; são únicos e limitados a 150 caracteres. Nome aceita até 100 caracteres. Senhas novas exigem ao menos 12 caracteres, maiúsculas, minúsculas, números, símbolo e confirmação, com limite de 72 caracteres. O hash é aplicado pelo cast do model, e senha/tokens de autenticação nunca entram na resposta JSON.

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

CRUD em `/api/addresses` e `/api/addresses/{id}`, sempre do próprio usuário. POST exige `postal_code`, `street`, `number`, `city` e `state`; `complement` é opcional/anulável. PUT/PATCH aceitam atualização parcial. CEP aceita oito dígitos com ou sem hífen; estado deve ser uma UF brasileira em maiúsculas. Limites de tamanho acompanham as colunas do banco. `user_id` é proibido.

Editar ou excluir um endereço cadastrado não modifica os snapshots já salvos nos pedidos. Endereços de outro usuário retornam `404` em leitura, atualização e exclusão.

## Catálogo administrativo

| Recurso | Criação | Atualização/exclusão | Consulta administrativa |
| --- | --- | --- | --- |
| Categoria | `POST /api/admin/categories` | `PUT/PATCH/DELETE /api/admin/categories/{category}` | `GET /api/admin/categories` e `/{category}` |
| Produto | `POST /api/admin/products` | `PUT/PATCH/DELETE /api/admin/products/{product}` | `GET /api/admin/products` e `/{product}` |
| Marca | `POST /api/admin/brands` | `PUT/PATCH/DELETE /api/admin/brands/{brand}` | `GET /api/admin/brands` e `/{brand}` |

Consultas públicas continuam omitindo produtos inativos ou de categoria inativa. Consultas administrativas incluem registros desativados. As respostas públicas anteriores de cards/categorias e `/api/catalog` mantêm o formato original; o detalhe de categoria também está disponível em `GET /api/categories/{category}`.

Categoria aceita `name` único (até 100 caracteres), `description`, `attributes` e `is_active`. Marca aceita `name` único com até 100 caracteres. Produto exige `category_id`, `name` e `price`; aceita `brand_id`, `description`, `specifications`, `cost`, `stock` inicial, `image_url` e `is_active`. Nome tem até 150 caracteres; imagem é URL HTTP/HTTPS com até 500 caracteres. Preço deve ser positivo, custo não negativo; ambos têm no máximo duas casas e limite de `99999999.99`.

`stock` é permitido somente no cadastro, entre zero e `2147483647`. Saldo inicial positivo gera uma entrada na mesma transação do produto. Edições posteriores usam exclusivamente o endpoint de movimentações. Produto novo retorna os defaults efetivos de `is_active`, estoque e relacionamentos.

Exclusões respeitam as FKs do domínio:

- Categoria com produtos retorna `409`; use `is_active=false` para retirá-la do catálogo.
- Produto com movimentações retorna `409`; preserve-o desativado para manter auditoria.
- Produto que já pertence a um pedido não pode ser removido fisicamente, mesmo que não tenha movimentações registradas. Deve ser desativado com `is_active=false` ou preservado por exclusão lógica, mantendo o vínculo e o histórico do pedido.
- A ausência de movimentações, isoladamente, não autoriza a exclusão física: é necessário verificar também os vínculos com pedidos.
- Excluir marca mantém os produtos e torna `brand_id` nulo.
- Pedido não possui exclusão física pela API: `DELETE /api/orders/{id}` cancela conforme as regras abaixo.

### Atributos dinâmicos

`attributes` é um objeto de até 100 definições, com chaves em snake_case de até 60 caracteres. Cada definição exige `name` e `type` (`text`, `integer`, `number` ou `boolean`), e pode informar `unit`, `options`, `required`, `is_filterable` e limites numéricos. Opções devem respeitar o próprio tipo e os limites; intervalos contraditórios são rejeitados. Limites são números JSON finitos, e indicadores são booleanos JSON.

`SpecificationService` compartilha a validação com `FilterProductsRequest`. `integer` aceita inteiros JSON, `number` aceita inteiros/decimais finitos, `boolean` aceita `true`/`false` e `text` aceita texto de até 1.000 caracteres. Strings numéricas e `0/1` não substituem os tipos JSON. `required` verifica presença da chave, preservando `false` como valor válido. Atributos desconhecidos e valores nulos são rejeitados; atributos opcionais podem ser omitidos.

`brand` é uma definição reservada para o filtro da marca: deve ser textual e não obrigatória; a marca do produto vem de `brand_id`, sem duplicação em `specifications`. Desativar `is_filterable` apenas remove a disponibilidade nos filtros; o atributo continua válido no cadastro.

No PUT/PATCH, o objeto `specifications` enviado substitui o objeto anterior. Se omitido, os valores existentes são preservados e revalidados, inclusive ao trocar `category_id`. Alterações nas definições da categoria são verificadas contra todos os seus produtos; se alguma especificação ficar inválida, a atualização retorna `422` e nada é gravado. Corrija os produtos antes de restringir as definições. Registros antigos inseridos diretamente no banco/seeders não são reescritos automaticamente.

## Estoque

`GET /api/admin/products/{id}/stock-movements` lista o histórico paginado. `POST` no mesmo caminho exige administrador e recebe:

```json
{"type":"INBOUND","quantity":5,"reason":"Reposição de estoque"}
```

`INBOUND` soma `quantity` ao estoque; `OUTBOUND` subtrai. Ambos exigem quantidade positiva. `ADJUSTMENT` soma a quantidade com sinal, permitindo correções positivas ou negativas. Zero é inválido. `reason` é obrigatório e tem até 150 caracteres. O servidor restringe o saldo final ao intervalo de zero a `2147483647`. Movimentações são imutáveis pela API.

`StockService` usa transação e bloqueio de linha para alterar o saldo e registrar a movimentação juntos. Pedidos geram saídas; cancelamentos geram entradas. O estoque inicial das seeders continua sendo a base ilustrativa existente, sem criação retroativa de históricos.

## Checkout

`POST /api/orders`, autenticado e com CSRF:

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

Aceita de um a 100 produtos distintos, com quantidades inteiras de um a 10.000. Repetir um produto na mesma lista retorna `422`; consolide a quantidade antes de enviar. As formas de pagamento aceitas são `PIX`, `CREDIT_CARD` e `BOLETO`. Esses valores representam a modalidade informada; o pagamento é simulado, sem cobrança real nem coleta de dados de cartão.

O service valida que o endereço é do cliente, bloqueia categorias/produtos em ordem de ID e exige produtos/categorias ativos e saldo suficiente. Preços, custos, nomes e categorias são obtidos do banco. A transação grava:

1. Pedido `PENDING` com totais calculados por `brick/math`, sem ponto flutuante.
2. Itens com preço/custo unitário e snapshots dos nomes.
3. Cópia do endereço de entrega.
4. Baixa do estoque e movimentações `OUTBOUND`.
5. Primeiro evento do histórico de status.

O total do pedido é sempre calculado no servidor a partir dos preços gravados no banco, e cada item guarda o nome e o preço unitário praticados no momento da compra. A criação valida o estoque e o decrementa dentro da mesma transação. Se qualquer item falhar, nada é gravado e nenhuma baixa de estoque permanece.

`user_id`, `status`, `products_total`, `shipping_total` e `total` são proibidos na entrada. Campos adicionais nos itens, como preço ou custo, também são rejeitados. O total não pode exceder `99999999.99`.

O frete vem de `config/shop.php`, configurado por `SHOP_SHIPPING_TOTAL`, inicialmente `0.00`. Configure um valor monetário não negativo com duas casas. Esse valor é uma regra fixa provisória, sem cotação de transportadora. Frete e totais são congelados no pedido; futuras alterações de preço, catálogo ou endereço não mudam a compra.

## Valores monetários

A escolha do projeto é `DECIMAL(10,2)` no banco, com representação decimal em strings e cálculos por `BigDecimal` na aplicação. Preços, custos, valores unitários, frete e totais devem seguir essa escolha de forma consistente. Valores monetários não devem usar ponto flutuante em cálculos. Não deve haver mistura entre valores decimais e inteiros em centavos sem uma conversão explícita.

## Pagamento simulado, cancelamento e andamento

O pagamento é uma simulação disponível somente ao dono do pedido ou a um administrador, para pedidos aguardando pagamento. Não há integração com provedor financeiro, cobrança real nem solicitação ou armazenamento de dados de cartão. O pagamento simulado concluído leva o pedido de `PENDING` para `PAID`.

`POST /api/orders/{id}/pay` executa o pagamento simulado e não recebe dados de pagamento. Repetir a operação em um pedido já pago não duplica o histórico nem altera o estoque. O dono ou administrador também pode consultar e cancelar pelo endpoint comum do pedido, respeitando os estados permitidos.

Cliente: `DELETE /api/orders/{id}` cancela somente pedido próprio em `PENDING`, retorna `204` e preserva pedido, itens, endereço e histórico. O estoque retorna por movimentações `INBOUND`. Repetir o cancelamento não duplica entradas nem eventos. Pedido próprio em outro status retorna `409`; pedido alheio retorna `404`.

O administrador também pode cancelar um pedido, desde que ele esteja em `PENDING`. Pedidos pagos, enviados ou entregues não podem ser cancelados por este fluxo. O cancelamento deve devolver o estoque dos itens e preservar o histórico.

Administrador: `PUT/PATCH /api/admin/orders/{id}` recebe somente o status desejado, por exemplo `{"status":"SHIPPED"}`. O fluxo esperado é aguardando pagamento → pago → enviado → entregue, com cancelamento a partir de aguardando pagamento. Transições aceitas:

| Atual | Próximos estados |
| --- | --- |
| `PENDING` | `PAID`, `CANCELLED` |
| `PAID` | `SHIPPED` |
| `SHIPPED` | `DELIVERED` |
| `DELIVERED` | Nenhum |
| `CANCELLED` | Nenhum |

`PENDING` significa aguardando pagamento, `PAID` pago, `SHIPPED` enviado, `DELIVERED` entregue e `CANCELLED` cancelado. `PROCESSING` não faz parte do fluxo esperado. Uma transição fora da tabela retorna `409`.

Repetir o estado atual é uma operação sem efeito e não duplica histórico. Alteração de status, histórico e eventual devolução de estoque usam a mesma transação, com bloqueio do pedido. Um produto removido em registros antigos bloqueia cancelamento com `409` até conciliar o estoque. Não há edição de itens/preços depois da compra.

## Validação e limites atuais

`composer test` executa a suíte com MySQL no banco isolado `ecommerce_test`, conforme `phpunit.xml` e a proteção em `Tests\TestCase`. A suíte verifica permissões, validação HTTP de produtos e marcas, especificações dinâmicas, auditoria, revogação de sessões, totais, snapshots, rollback e cancelamento repetido. Os testes com banco podem ser executados pelo Compose de testes com MySQL 8.4. Os testes atuais não simulam múltiplos checkouts em processos concorrentes.

Use `php artisan route:list --path=api` para conferir as rotas e `vendor/bin/pint --dirty --format agent` para padronizar PHP. Os testes usam `RefreshDatabase`: nunca os aponte para o banco de desenvolvimento ou produção.

### Situação da implementação

Estão implementados o carrinho de visitante e da conta, token vinculado à sessão, unicidade, quantidades e preços atuais; o pagamento simulado e o fluxo de pedidos sem `PROCESSING`; a proteção de produtos vendidos contra exclusão física; e a validação HTTP de checkout, status, categorias, estoque e filtros.

Permanecem pendentes:

- Concluir a fusão no login e cadastro, seus avisos de ajuste e a invalidação do token de visitante. Os cenários de testes estão preparados, mas a integração ainda não foi concluída.
- Definir o tratamento de produtos que se tornaram inativos antes da fusão: remover esses itens e avisar, ou rejeitar a fusão. Essa decisão de negócio depende de consulta, sem adoção silenciosa de uma política.
- Validar em MySQL as regras de estoque, rollback, concorrência e unicidade, incluindo os cenários de carrinho e fusão. O ambiente de execução atual não permite conexão ao banco de testes nem ao socket Docker; testes HTTP de validação e apresentação sem banco podem ser executados separadamente.
- Integrar os Requests e Resources restantes de perfil, endereços e catálogo quando essas áreas forem tratadas. O mecanismo de autenticação não foi reformulado nesta implementação.

O envio do token está definido no header `X-Cart-Token`, acompanhado da mesma sessão que criou o carrinho. Não há conversão automática de pedidos históricos em `PROCESSING`; uma eventual migração desses registros também exige uma política definida.

Permanecem fora do contrato atual: integração financeira real, cotação de frete por transportadora, recuperação de senha, confirmação de e-mail, política de anonimização e compatibilidade entre componentes. `POST /api/orders` ainda não possui chave de idempotência: uma nova chamada válida cria outro pedido e baixa estoque novamente. A idempotência descrita acima se aplica a cancelamentos e repetição do status, não à criação.
