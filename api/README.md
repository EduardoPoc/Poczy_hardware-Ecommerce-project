# Poczy Hardware API

Backend Laravel 12, sem frontend ou dependências Node.js. Requer PHP 8.4.1+ e Composer para as dependências fixadas neste `composer.lock`. Os testes usam PHPUnit 11.

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

## Rotas iniciais

| Método | Rota | Autenticação | Resposta |
| --- | --- | --- | --- |
| GET | `/api/v1/health` | Pública | `{"status":"ok","name":"Poczy Hardware API"}` |
| GET | `/api/v1/user` | Bearer token (Sanctum) | Usuário autenticado em `data`, sem senha |
| GET | `/up` | Pública | Verificação de inicialização do Laravel |

O status em `/api/v1/health` verifica que a aplicação responde; não verifica a conexão com o banco. Os erros retornam JSON mesmo sem o cabeçalho `Accept`. Use `APP_DEBUG=false` em produção.

O Sanctum e sua migração estão instalados; cadastro, login, emissão de tokens e regras dos módulos do e-commerce ainda precisam ser implementados. O modelo `User` usa a tabela `usuarios`, os campos `nome` e `senha_hash`, e o timestamp `criado_em`. O campo `tipo` assume `CLIENTE` no banco e não permite atribuição em massa. O seeder padrão cria apenas um usuário de exemplo; não o execute em produção.

## Banco inicial

As migrations em `database/migrations/` incluem 19 tabelas do domínio:

- Contas: `usuarios` e `enderecos`.
- Catálogo: `categorias`, `marcas` e `produtos`.
- Detalhes 1:1: `perifericos`, `processadores`, `memorias_ram`, `placas_video`, `placas_mae`, `armazenamento`, `fontes`, `gabinetes` e `refrigeracao`.
- Compras: `pedidos`, `endereco_entrega_pedido`, `itens_pedido` e `historico_status_pedido`.
- Auditoria: `movimentacoes_estoque`.

Também permanecem as oito tabelas de suporte do Laravel para autenticação, sessões, cache, filas e Sanctum. Os nomes das tabelas são minúsculos e os IDs usam `BIGINT UNSIGNED`, compatíveis com `id()` e `foreignId()` do Laravel. Valores monetários usam `DECIMAL(10,2)`; estoque e quantidades usam inteiros sem sinal. Campos específicos usados nos filtros e as consultas principais de produtos e pedidos possuem índices.

O endereço de entrega é uma cópia independente dos endereços do usuário. Os itens guardam snapshots do nome, categoria, preço e custo. Excluir um produto ou uma categoria anula a referência no item, preservando seus snapshots; categorias com produtos e usuários com pedidos têm exclusão bloqueada. Produtos com movimentações de estoque também têm exclusão bloqueada para preservar a auditoria. Para retirar esses registros de circulação, prefira os campos `ativo`/`ativa` disponíveis no catálogo.

As migrations criam a estrutura. A gravação dos snapshots, o cálculo dos totais, as mudanças de estoque e status, quantidades maiores que zero, opções padronizadas de filtros e a correspondência entre categoria e tabela de detalhes precisam ser garantidos pelos próximos módulos da aplicação.

Esta versão inicial substitui a migration padrão de `users` por `usuarios` e pressupõe um banco novo. Se uma base já tiver recebido as migrations anteriores, será necessária uma migração de conversão dos dados.

Quando o MySQL estiver disponível, configure as variáveis `DB_*` no `.env` e execute, no ambiente PHP da API:

```sh
php artisan config:clear
php artisan migrate
```

## Frontend e CORS

`CORS_ALLOWED_ORIGINS` aceita origens separadas por vírgulas. O padrão permite `http://localhost:5173` e `http://127.0.0.1:5173`. A base está preparada para autenticação por Bearer token, sem cookies entre origens. O frontend deve enviar `Accept: application/json` e, nas rotas protegidas, `Authorization: Bearer <token>`.

## Verificação

```sh
composer test
php artisan route:list --path=api
vendor/bin/pint
```

Os testes verificam status, erros JSON, acesso autenticado, ocultação da senha, CORS e a adaptação do usuário ao esquema em português. Também compilam as migrations usando a gramática MySQL para verificar tabelas, snapshots, chaves primárias 1:1 e a ordem de criação e rollback. Essa verificação não conecta a um servidor nem substitui a execução das migrations em MySQL; persistência e validação de tokens reais ainda precisam de verificação com o banco disponível.
