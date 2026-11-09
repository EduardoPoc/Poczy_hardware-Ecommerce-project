# API Poczy Hardware

Laravel 12, PHP 8.5, MySQL 8.4, Sanctum e PHPUnit. Controllers recebem Requests validados e delegam regras aos services; Resources formatam JSON. O contrato detalhado está em [BUSINESS_RULES.md](BUSINESS_RULES.md) e no Swagger UI em `/api/docs`.

## Desenvolvimento com Docker

Na raiz do repositório, prepare `.env` usando `.env.example` e configure credenciais locais. Configure `LOCAL_UID`/`LOCAL_GID` conforme seu usuário no Linux/WSL. A API usa as variáveis MySQL do Compose; `api/.env` reúne as outras opções locais.

```sh
docker compose up -d --build
docker compose exec api php artisan migrate --no-interaction
docker compose exec api php artisan db:seed --no-interaction
```

- API: `http://localhost/api/products`.
- Saúde: `http://localhost/up`.
- Frontend: `http://localhost`.
- MySQL local: `127.0.0.1:3306`.

O serviço Nginx `proxy` publica a porta 80 e encaminha `/api`, `/sanctum`, `/storage` e `/up` para a API. A porta 8000 fica acessível apenas na rede Docker.

O serviço `api` usa `Dockerfile.dev`, monta o código e mantém as dependências em um volume próprio. O `CMD` do Dockerfile instala dependências, limpa o cache de configuração e cria `.env` e `APP_KEY` quando ausentes antes de iniciar o servidor. O healthcheck HTTP usa o próprio PHP. O frontend usa `frontend/Dockerfile.dev`, com Vite e dependências em volume, e o proxy aguarda ambos responderem aos healthchecks. `LOCAL_UID` e `LOCAL_GID` são aplicados aos dois serviços. As migrations e seeders são comandos explícitos. O servidor usa `--no-reload` para preservar as variáveis MySQL fornecidas pelo Compose no processo HTTP. Após alterar variáveis do Compose, execute `docker compose up -d --wait` para recriar os containers com os novos valores; mudanças nos arquivos PHP continuam disponíveis nas requisições.

Dentro do Compose, `DB_HOST=mysql`. As credenciais vêm das variáveis `MYSQL_*` do `.env` da raiz. O healthcheck do MySQL executa `SELECT 1` com o usuário e banco da aplicação. `APP_URL`, também na raiz, tem padrão `http://localhost`, correspondente ao proxy e às URLs públicas de imagens; cookies seguros ficam desativados no desenvolvimento HTTP. O MySQL mantém seus dados no volume `mysql_data`; alterar as variáveis não altera usuários de um volume já inicializado.

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

```sh
docker compose -f compose.test.yaml up --build --abort-on-container-exit --exit-code-from tests
docker compose -f compose.test.yaml down
```

O banco e usuário são `ecommerce_test`, em MySQL 8.4 temporário, sem porta publicada. `Tests\TestCase` verifica ambiente, driver, banco, usuário e ausência de cache de configuração antes de testes que usam `RefreshDatabase`. A suíte nunca deve usar um banco com dados a preservar.

Testes sem banco podem ser executados diretamente com `php artisan test --compact <arquivo>`. Os cenários de persistência, estoque, snapshots, autorização Bearer, seeders e transações dependem da suíte MySQL. O workflow do GitHub também testa as imagens e páginas de produção.

O arquivo de testes é independente do Compose de desenvolvimento. Ele cria `mysql_test` sem publicar portas e com armazenamento temporário. A imagem `Dockerfile.dev` contém as dependências de teste e o autoload preparados durante o build. O Compose substitui o `CMD` padrão por um comando que cria `.env` a partir do exemplo quando ausente, limpa o cache de configuração e executa `php artisan test --compact`; a `APP_KEY` vem do ambiente de testes.

## Autenticação

| Fluxo | Rotas |
| --- | --- |
| Navegador | `GET /sanctum/csrf-cookie`, `POST /api/login`, `POST /api/logout` |
| Token | `POST /api/auth/register`, `POST /api/auth/login`, `POST /api/auth/logout` |
| Perfil | `GET/PUT/PATCH/DELETE /api/user`, `PUT /api/user/password` |

O navegador preserva cookies e envia `X-XSRF-TOKEN` nas escritas. Clientes externos recebem `access_token`, `token_type=Bearer` e `expires_in=28800`, usando `Authorization: Bearer <token>`. O token é armazenado como hash e expira em oito horas. Logout revoga os tokens da conta; troca de senha revoga também as sessões em banco. Cadastro não autentica automaticamente.

Rotas pessoais e administrativas usam `auth:sanctum`; administração também exige o Gate `manage-shop`, com `role=ADMIN`. CORS permite credenciais somente para `CORS_ALLOWED_ORIGINS`. No navegador, produção usa uma única origem e o cookie `poczy-session` por padrão.

## Carrinho, catálogo e pedidos

O token visitante é emitido uma vez em `data.cart_token`, guardado pelo frontend e enviado em `X-Cart-Token`, junto da sessão original. A fusão soma quantidades, limita ao estoque e informa ajustes. O token sozinho não autoriza outra sessão a acessar o carrinho.

O catálogo aceita ordenação por nome, preço crescente/decrescente e mais recentes. Ativos sem estoque são exibidos como indisponíveis. Nos carrinhos, `is_available=false` sinaliza produtos ou quantidades indisponíveis, que impedem finalizar.

`POST /api/checkout` usa os itens do carrinho, endereço próprio e `checkout_token` UUID. Cria PENDING, reserva estoque, congela valores e endereço e esvazia o carrinho em transação. Repetir a mesma tentativa retorna o pedido anterior sem duplicar efeitos. O cliente paga explicitamente em `POST /api/orders/{id}/pay` ou cancela o pendente em `DELETE /api/orders/{id}`. O pagamento é simulado e não recebe dados financeiros.

O fluxo é `PENDING → PAID → SHIPPED → DELIVERED`; apenas PENDING admite CANCELLED. Cancelamento devolve estoque uma vez. Repetir pagamento retorna 409. O administrador pode consultar pedidos de outras contas, mas a rota de pagamento é exclusiva do dono.

## Produção

Prepare `.env.production` a partir de `.env.production.example`. Use uma `APP_KEY` própria e persistente, credenciais de banco próprias e a URL HTTPS real da loja em `APP_URL` e `CORS_ALLOWED_ORIGINS`. `VITE_API_URL` e `SESSION_DOMAIN` ficam vazios para a implantação padrão na mesma origem.

`compose.prod.yaml` contém quatro serviços: `mysql`, `api`, `frontend` e `proxy`. `Dockerfile` tem os alvos `production` (PHP-FPM, serviço `api`) e `web` (Nginx, serviço `proxy`). A imagem PHP final contém o código e dependências de produção instaladas durante o build; não contém Composer ou a suíte de testes. `frontend/Dockerfile` compila a loja com Vite e entrega somente a build para uma imagem Nginx na porta interna 80. O código não é montado da máquina host.

Gere a chave antes do deploy, por exemplo com `php artisan key:generate --show` em um ambiente de desenvolvimento. Nunca publique o arquivo de ambiente.

```sh
docker compose --env-file .env.production -f compose.prod.yaml build
docker compose --env-file .env.production -f compose.prod.yaml up -d mysql --wait
docker compose --env-file .env.production -f compose.prod.yaml run --rm api php artisan migrate --force --no-interaction
docker compose --env-file .env.production -f compose.prod.yaml up -d --wait --remove-orphans
docker compose --env-file .env.production -f compose.prod.yaml exec api php artisan app:create-admin
```

O último comando solicita a senha em prompts ocultos, valida sua força e cria uma conta administrativa nova. Seeders de demonstração são bloqueados em produção. Cadastre o catálogo pelo painel após criar o administrador.

O `CMD` de produção exige `APP_KEY`, gera os caches de configuração e rotas com as variáveis do ambiente e inicia o PHP-FPM. Não instala dependências, não gera outra chave e não executa migrations ou seeders automaticamente. Comandos explícitos em `docker compose run`, como a migration acima, substituem esse `CMD` e executam diretamente com o código e as dependências já presentes na imagem e as variáveis fornecidas pelo Compose.

O serviço `proxy` publica HTTP em `127.0.0.1:8080` por padrão. Encaminha `/api`, `/sanctum` e `/up` ao PHP-FPM, serve `/api/docs` e `/storage` e encaminha as páginas para o frontend. Os caminhos sem extensão e redirecionamentos de `.html` são resolvidos pelo Nginx do frontend. Use um proxy HTTPS do servidor na frente desse endereço, sobrescrevendo `Host`, `X-Forwarded-Proto` e `X-Forwarded-For` com os dados da conexão real. `SESSION_SECURE_COOKIE=true` pressupõe acesso HTTPS no navegador. Restrinja `TRUSTED_PROXIES` às redes dos proxies usados. `HTTP_BIND` e `HTTP_PORT` permitem ajustar a publicação. O MySQL não publica porta no host. Banco e `storage` usam volumes persistentes. O healthcheck da API verifica a porta PHP-FPM, e o do proxy verifica `/up` passando pelo Laravel.

O Nginx da API lê os uploads pelo mesmo volume persistente usado pelo PHP-FPM. `APP_DEBUG=false`, logs em stderr e configurações PHP de produção são definidos na imagem/Compose.

Para instalações com subdomínios, configure `SESSION_DOMAIN=.seu-dominio`, origens CORS e domínios stateful do Sanctum, além da URL de API no build. A mesma origem é o caminho padrão configurado e testado pelo CI.

## Backup e restauração

Banco e uploads fazem parte da mesma recuperação. Execute os comandos na raiz do repositório e guarde os arquivos em um destino protegido fora dos volumes da aplicação:

```sh
umask 077
mkdir -p backups
docker compose --env-file .env.production -f compose.prod.yaml exec -T mysql sh -c 'MYSQL_PWD="$MYSQL_PASSWORD" exec mysqldump --single-transaction --no-tablespaces --user="$MYSQL_USER" "$MYSQL_DATABASE"' > backups/database.sql
docker compose --env-file .env.production -f compose.prod.yaml exec -T api tar -czf - -C storage/app/public . > backups/uploads.tar.gz
```

Ensaie a restauração antes de depender do procedimento. Prepare `.env.restore` com as credenciais e a `APP_KEY` do backup. O nome de projeto `poczy-restore` abaixo cria volumes separados da aplicação publicada, sem iniciar suas portas HTTP:

```sh
docker compose --project-name poczy-restore --env-file .env.restore -f compose.prod.yaml up -d mysql --wait
docker compose --project-name poczy-restore --env-file .env.restore -f compose.prod.yaml exec -T mysql sh -c 'MYSQL_PWD="$MYSQL_PASSWORD" exec mysql --user="$MYSQL_USER" "$MYSQL_DATABASE"' < backups/database.sql
docker compose --project-name poczy-restore --env-file .env.restore -f compose.prod.yaml run --rm --no-deps -T api tar -xzf - -C storage/app/public < backups/uploads.tar.gz
```

Preserve também a `APP_KEY` e as configurações do ambiente em um gerenciador de segredos. Antes de publicar, exija CI aprovado e confira a jornada visitante → login/fusão → pedido pendente → pagamento → administração em janela anônima e em outro dispositivo. Confirmação de e-mail e recuperação por link permanecem fora da primeira versão.
