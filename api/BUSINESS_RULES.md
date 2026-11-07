# Regras de negócio do backend

A aplicação usa MVC com Service Layer: Requests validam entradas, services coordenam regras e transações, models representam os dados e Resources formatam as respostas. Mensagens da API são em inglês; o frontend apresenta mensagens em português.

## Autenticação e autorização

- O navegador usa `POST /api/login`, cookies e CSRF. Cadastro em `POST /api/user` não faz login automático.
- Clientes da API usam `POST /api/auth/register` e `POST /api/auth/login`. O login retorna `access_token`, `token_type=Bearer` e `expires_in=28800`.
- Tokens Sanctum expiram em oito horas e são armazenados somente como hash. As rotas protegidas usam `auth:sanctum`, aceitando Bearer ou sessão do navegador.
- `POST /api/logout` e `POST /api/auth/logout` revogam os tokens da conta e encerram a sessão corrente, quando presente.
- Catálogo e carrinho visitante são públicos. Perfil, endereços, checkout e pedidos exigem autenticação. Administração exige o Gate `manage-shop`, com `role=ADMIN`.
- O cadastro sempre cria cliente; `role` é proibido. Alterar e-mail exige a senha atual correta e limpa `email_verified_at`. Nome e e-mail aceitam atualização parcial.
- Trocar senha exige a atual, nova senha e confirmação. A nova senha precisa de 12 caracteres, maiúsculas, minúsculas, número e símbolo, até 72 bytes e sem byte nulo. O hash, a auditoria e a revogação dos tokens são transacionais; sessões em banco também são revogadas.
- Endereços pertencem à conta autenticada. `user_id` é proibido. Endereços de outra conta retornam 404. A edição não altera endereços congelados nos pedidos.
- Contas com pedidos ou histórico de senha não podem ser excluídas, para preservar auditoria.

## Cookies e CSRF na SPA

1. Obtenha `GET /sanctum/csrf-cookie`.
2. Preserve cookies com `credentials: include`.
3. Envie o cookie `XSRF-TOKEN`, URL-decodificado, no header `X-XSRF-TOKEN` nas escritas.
4. Faça login; após logout ou troca de senha, obtenha um novo CSRF antes de entrar novamente.

O cookie padrão é `poczy-session`, configurável por `SESSION_COOKIE`. O Compose de produção publica frontend e API na mesma origem. Instalações em subdomínios precisam configurar `SESSION_DOMAIN`, origens CORS e domínios stateful do Sanctum. Requisições Bearer de clientes externos não dependem de cookies; a fusão de um carrinho visitante exige preservar sua sessão original.

Login tem limites de 20 tentativas/minuto por IP e cinco por e-mail normalizado + IP. Cadastro tem limite de cinco por hora por IP. E-mails são normalizados para minúsculas e têm unicidade no banco.

## Catálogo e estoque

- Produtos e categorias inativos ficam fora do catálogo público; detalhes inativos retornam 404.
- Produto ativo com estoque zero permanece visível como indisponível. Estoque e ativação são independentes: repor ou esgotar o estoque não altera a decisão administrativa de publicar o produto.
- Busca por nome, categoria, marcas, faixa de preço, especificações e paginação são aplicadas no servidor.
- `sort` aceita `name`, `price_asc`, `price_desc` e `newest`, com desempate por ID. Em `/api/products/filter`, os critérios ficam em `filter`, como JSON ou parâmetros aninhados; `page` e `per_page` ficam fora dele.
- Categoria define atributos técnicos tipados em JSON. Chaves desconhecidas, tipos incompatíveis, opções inválidas e limites contraditórios são rejeitados pelo `SpecificationService`.
- Preço e custo usam `decimal(10,2)`; cálculos de domínio usam `BigDecimal`. Dinheiro sai como string decimal, sem confiar em valores enviados pelo cliente.
- Descrições de categorias e produtos têm limite de 65535 bytes. Nome de produto tem até 150 caracteres.
- Imagens são enviadas em multipart, com PNG, JPG, JPEG ou WebP de até 5 MB. O servidor produz a URL; `image_url` e `image_path` são proibidos na entrada.
- Estoque inicial é permitido no cadastro. Depois, alterações usam `POST /api/admin/products/{id}/stock-movements`: `INBOUND` soma, `OUTBOUND` subtrai e `ADJUSTMENT` aplica uma diferença assinada. O saldo fica entre zero e 2147483647.
- Movimentações e pedidos bloqueiam os registros relevantes e executam dentro de transações. Falhas revertem estoque e histórico.
- Produtos vinculados a pedidos ou movimentações não podem ser excluídos fisicamente; desative-os. Categorias com produtos retornam 409 na exclusão. Excluir marca mantém produtos, com `brand_id=null`.

## Carrinho e fusão

| Método | Rota | Ação |
| --- | --- | --- |
| GET | `/api/cart` | Obtém ou cria carrinho da sessão ou conta |
| POST | `/api/cart/items` | Soma `quantity` ao `product_id` existente |
| PATCH | `/api/cart/items/{product}` | Define a quantidade; zero remove |
| DELETE | `/api/cart/items/{product}` | Remove o item |

Existe no máximo um carrinho por conta e uma linha por carrinho + produto. Visitantes recebem um token opaco em `data.cart_token`; o frontend o guarda separadamente do estado visual e envia `X-Cart-Token`. O banco mantém seu hash e o hash de um vínculo independente com a sessão. O token isolado não autoriza outra sessão a acessar o carrinho.

Preços e totais são sempre recalculados no servidor. Itens indisponíveis permanecem visíveis com `is_available=false`, permitindo ajustar a quantidade ou remover o item. Eles impedem o checkout. Produtos inativos ou sem estoque não podem ser adicionados.

No login/cadastro, `X-Cart-Token` ou `merge_cart=true` solicita a fusão:

1. Produtos exclusivos do visitante são copiados para a conta.
2. Quantidades compartilhadas são somadas e limitadas ao estoque disponível.
3. Produtos indisponíveis na fusão são removidos e geram aviso; ajustes de quantidade também são informados.
4. O carrinho visitante é descartado e seu token invalidado após commit. O frontend apaga a credencial após confirmar a fusão.
5. Falha de autenticação ou transação preserva o carrinho anterior. Um token local de sessão expirada pode ser descartado para iniciar um novo carrinho; escritas sem resposta confirmada não são repetidas automaticamente.

## Pedido, pagamento e cancelamento

`POST /api/checkout` cria o pedido a partir do carrinho da conta. Exige endereço próprio, `payment_method=FAKE_PIX`, `checkout_token` UUID e `expected_total`. A transação valida disponibilidade, calcula preços e frete, cria snapshots, baixa estoque e esvazia o carrinho. O resultado é `PENDING`, aguardando pagamento.

O token da tentativa é único por conta. Reenviar o mesmo `checkout_token` retorna o pedido existente sem reservar estoque de novo nem apagar novos itens do carrinho. Total alterado retorna 409 e desfaz a transação. Carrinho vazio ou estoque insuficiente retorna 422.

`POST /api/orders` também permite criação por uma lista explícita de itens validados e endereço próprio, com preços calculados no servidor; a criação também esvazia o carrinho da conta. As modalidades PIX, CREDIT_CARD e BOLETO são apenas rótulos de simulação e não recebem dados financeiros.

O pedido guarda nomes, preços, custos e endereço daquele momento. Mudanças no catálogo ou no endereço não modificam compras antigas. Custos internos só aparecem em Resources administrativos.

| Estado atual | Próximos estados |
| --- | --- |
| PENDING | PAID ou CANCELLED |
| PAID | SHIPPED |
| SHIPPED | DELIVERED |
| DELIVERED | Nenhum |
| CANCELLED | Nenhum |

- `POST /api/orders/{id}/pay` é uma ação explícita do dono. Outra conta, inclusive administrador, recebe 403. Apenas PENDING pode ser pago; repetir pagamento retorna 409. O histórico registra o momento de PAID.
- `DELETE /api/orders/{id}` cancela pedido pendente do dono ou de administrador, devolvendo estoque uma única vez. Repetir cancelamento é idempotente. Pedidos pagos, enviados ou entregues não podem ser cancelados.
- Administradores avançam estados por `PUT/PATCH /api/admin/orders/{id}`, respeitando a tabela. Repetir uma atualização administrativa para o mesmo estado não duplica histórico.
- A lista pessoal contém somente pedidos próprios. Administradores podem consultar detalhes de outras contas. Clientes recebem 404 ao consultar pedidos alheios.
- Toda cobrança é fictícia. Não existe gateway nem coleta de dados de cartão.

## Demonstração e produção

Seeders são permitidos somente em `local` e `testing`. Incluem catálogo, clientes, administrador, um produto ativo sem estoque, um inativo, carrinhos populados e pedidos PENDING, PAID, SHIPPED, DELIVERED e CANCELLED. A reexecução identifica fixtures por chaves estáveis e não repete reservas ou movimentações.

Em produção, crie o administrador com `php artisan app:create-admin`, informando a senha em prompts ocultos. Cadastre o catálogo pelo painel. Banco e uploads usam volumes persistentes; faça backup de ambos e ensaie a restauração em ambiente isolado.

Swagger UI em `/api/docs` documenta as operações atuais. Os testes verificam cobertura das rotas e resolução das referências OpenAPI. O CI executa a suíte MySQL isolada, testes do frontend, build das imagens e verificações HTTP de produção. A aprovação de uma publicação exige esses checks e a jornada em navegador aprovados.
