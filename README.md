# Poczy Hardware

Projeto de e-commerce de hardware. A interface atual fica em `frontend/`.

## Modelo lógico inicial do banco de dados

O catálogo usa `PRODUTOS` como tabela central. Preço, marca, estoque e informações comuns ficam nela. Atributos usados por filtros específicos ficam em tabelas de detalhes com relação 1:1 com o produto. Este é um modelo lógico inicial, ainda não uma migração aplicada ao banco.

### Contas e endereços

**USUARIOS**

| Campo | Tipo | Regra |
| --- | --- | --- |
| `id` | INT | Chave primária, auto incremento |
| `nome` | VARCHAR(100) | Obrigatório |
| `email` | VARCHAR(150) | Obrigatório, único |
| `senha_hash` | VARCHAR(255) | Obrigatório |
| `tipo` | ENUM('CLIENTE', 'ADMIN') | Obrigatório |
| `criado_em` | DATETIME | Obrigatório |

**ENDERECOS**

| Campo | Tipo | Regra |
| --- | --- | --- |
| `id` | INT | Chave primária, auto incremento |
| `usuario_id` | INT | Obrigatório; chave estrangeira para `USUARIOS.id` |
| `cep` | VARCHAR(9) | Obrigatório |
| `logradouro` | VARCHAR(150) | Obrigatório |
| `numero` | VARCHAR(20) | Obrigatório |
| `complemento` | VARCHAR(100) | Opcional |
| `cidade` | VARCHAR(100) | Obrigatório |
| `estado` | CHAR(2) | Obrigatório |

Um usuário pode ter vários endereços.

### Catálogo

**CATEGORIAS**

| Campo | Tipo | Regra |
| --- | --- | --- |
| `id` | INT | Chave primária, auto incremento |
| `codigo` | VARCHAR(40) | Obrigatório, único; identificador estável da categoria |
| `nome` | VARCHAR(100) | Obrigatório |
| `descricao` | TEXT | Opcional |
| `ativa` | BOOLEAN | Obrigatório |

Exemplos de `codigo`: `NOTEBOOKS`, `PCS_MONTADOS`, `MONITORES`, `PERIFERICOS`, `PROCESSADORES`, `MEMORIAS_RAM`, `PLACAS_VIDEO`, `PLACAS_MAE`, `ARMAZENAMENTO`, `FONTES`, `GABINETES` e `REFRIGERACAO`. O código permanece estável mesmo que o nome exibido mude.

**MARCAS**

| Campo | Tipo | Regra |
| --- | --- | --- |
| `id` | INT | Chave primária, auto incremento |
| `nome` | VARCHAR(100) | Obrigatório, único |

**PRODUTOS**

| Campo | Tipo | Regra |
| --- | --- | --- |
| `id` | INT | Chave primária, auto incremento |
| `categoria_id` | INT | Obrigatório; chave estrangeira para `CATEGORIAS.id` |
| `marca_id` | INT | Chave estrangeira para `MARCAS.id`; opcional enquanto a marca não estiver cadastrada |
| `nome` | VARCHAR(150) | Obrigatório |
| `descricao` | TEXT | Opcional |
| `preco` | DECIMAL(10,2) | Obrigatório |
| `custo` | DECIMAL(10,2) | Opcional; usado no cálculo do lucro |
| `estoque` | INT | Obrigatório |
| `imagem_url` | VARCHAR(500) | Opcional |
| `ativo` | BOOLEAN | Obrigatório |
| `criado_em` | DATETIME | Obrigatório |

Uma categoria e uma marca podem estar associadas a vários produtos. Preço e marca são filtrados por `PRODUTOS.preco` e `PRODUTOS.marca_id` em todas as categorias. Notebooks, PCs montados e monitores usam somente esses filtros por enquanto e não precisam de tabela de detalhes.

### Atributos específicos para filtros

Cada tabela abaixo usa `produto_id` INT como **chave primária e chave estrangeira para `PRODUTOS.id`**. Portanto, cada produto tem no máximo um registro na tabela de detalhes correspondente à sua categoria. Preço e marca não são duplicados nessas tabelas.

| Tabela | Campos além de `produto_id` |
| --- | --- |
| **PERIFERICOS** | `tipo` VARCHAR(30): mouse, teclado, headset ou fone comum |
| **PROCESSADORES** | `socket` VARCHAR(40) |
| **MEMORIAS_RAM** | `velocidade_mhz` INT, `capacidade_gb` INT, `ddr` VARCHAR(10) |
| **PLACAS_VIDEO** | `memoria_video_gb` INT |
| **PLACAS_MAE** | `formato` VARCHAR(30), `ddr` VARCHAR(10), `socket` VARCHAR(40) |
| **ARMAZENAMENTO** | `tipo` VARCHAR(30), `velocidade_leitura_mbps` INT, `velocidade_gravacao_mbps` INT, `capacidade_gb` INT |
| **FONTES** | `certificacao_80_plus` VARCHAR(30), `modularidade` VARCHAR(20) |
| **GABINETES** | `formato_placa_mae` VARCHAR(30) |
| **REFRIGERACAO** | `tipo` VARCHAR(30): water cooler, air cooler ou ventoinha |

Os atributos exigidos para anunciar um produto devem ser obrigatórios na categoria correspondente. Dados que podem ser desconhecidos, como a velocidade de leitura de um HD, podem ser nulos. A aplicação e o banco devem usar valores padronizados para opções como DDR, formato, tipo, certificação e modularidade, evitando que grafias diferentes criem filtros duplicados.

### Pedidos e histórico

**PEDIDOS**

| Campo | Tipo | Regra |
| --- | --- | --- |
| `id` | INT | Chave primária, auto incremento |
| `usuario_id` | INT | Obrigatório; chave estrangeira para `USUARIOS.id` |
| `status` | VARCHAR(30) | Obrigatório |
| `forma_pagamento` | VARCHAR(40) | Opcional |
| `valor_produtos` | DECIMAL(10,2) | Obrigatório |
| `valor_frete` | DECIMAL(10,2) | Obrigatório |
| `valor_total` | DECIMAL(10,2) | Obrigatório |
| `criado_em` | DATETIME | Obrigatório |

Cada pedido pertence a um usuário. O endereço de entrega usado na compra deve ser copiado para um snapshot do pedido, em campos do próprio pedido ou em uma tabela `ENDERECO_ENTREGA_PEDIDO`. Assim, uma edição futura em `ENDERECOS` não altera o histórico da compra.

**ITENS_PEDIDO**

| Campo | Tipo | Regra |
| --- | --- | --- |
| `id` | INT | Chave primária, auto incremento |
| `pedido_id` | INT | Obrigatório; chave estrangeira para `PEDIDOS.id` |
| `produto_id` | INT | Chave estrangeira para `PRODUTOS.id` |
| `categoria_id` | INT | Chave estrangeira para `CATEGORIAS.id` |
| `categoria_nome_snapshot` | VARCHAR(100) | Obrigatório |
| `nome_produto_snapshot` | VARCHAR(150) | Obrigatório |
| `quantidade` | INT | Obrigatório |
| `preco_unitario` | DECIMAL(10,2) | Obrigatório |
| `custo_unitario` | DECIMAL(10,2) | Opcional |

Os snapshots preservam o nome da categoria, o nome do produto, o preço e o custo da época da compra. Referências para produto e categoria podem ser mantidas para consultas, mas não devem substituir os snapshots na exibição do histórico.

**HISTORICO_STATUS_PEDIDO**

| Campo | Tipo | Regra |
| --- | --- | --- |
| `id` | INT | Chave primária, auto incremento |
| `pedido_id` | INT | Obrigatório; chave estrangeira para `PEDIDOS.id` |
| `status` | VARCHAR(30) | Obrigatório |
| `alterado_em` | DATETIME | Obrigatório |

Registra cada mudança de status, incluindo processamento e cancelamento.

**MOVIMENTACOES_ESTOQUE** (opcional)

| Campo | Tipo | Regra |
| --- | --- | --- |
| `id` | INT | Chave primária, auto incremento |
| `produto_id` | INT | Obrigatório; chave estrangeira para `PRODUTOS.id` |
| `quantidade` | INT | Obrigatório |
| `tipo` | ENUM('ENTRADA', 'SAIDA', 'AJUSTE') | Obrigatório |
| `variacao` | INT | Obrigatório; positivo para aumento, negativo para redução |
| `motivo` | VARCHAR(150) | Opcional |
| `criado_em` | DATETIME | Obrigatório |

### Regras de consulta e implementação

- Opções escolhidas **dentro do mesmo filtro** funcionam como alternativas: marca AMD **ou** Intel.
- **Filtros diferentes** são combinados: uma dessas marcas **e** socket AM4 **e** preço até R$ 1.500.
- Ao implementar as consultas, considerar índices em `PRODUTOS(categoria_id, preco)`, `PRODUTOS.marca_id` e nos atributos específicos mais pesquisados. Os índices definitivos dependem do volume e das consultas reais.
- O carrinho de visitantes permanece em `localStorage` até o início da finalização da compra.
- O painel administrativo não precisa de tabela própria: pedidos, itens, produtos, categorias e usuários permitem calcular vendas, faturamento, ticket médio, lucro, novos usuários e estoque por período. Os cálculos financeiros devem considerar o status dos pedidos para não contar cancelamentos como vendas concluídas.
