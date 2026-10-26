# Frontend Poczy Hardware

Aplicação HTML, CSS e JavaScript com duas páginas, preparada como projeto Vite de múltiplas entradas.

## Estrutura

```text
frontend/
├── index.html                # Página inicial
├── pesquisa.html             # Pesquisa e filtros
├── public/assets/           # Logos, imagens e ícones copiados para dist/assets/
├── src/
│   ├── data/catalog.js      # Catálogo demonstrativo e regras de filtro
│   ├── assets/brand/        # Variações da marca que ainda não são publicadas
│   ├── scripts/
│   │   ├── home.js           # Cards e carrossel da página inicial
│   │   ├── search.js         # Resultados e filtros da pesquisa
│   │   └── shared.js         # Busca e interações comuns
│   └── styles/main.css       # Estilos das duas páginas
├── package.json
└── vite.config.js
```

## Desenvolvimento

Requer Node.js 20.19+ ou 22.12+.

```sh
cd frontend
npm install
npm run dev
```

Abra a URL informada pelo Vite. A pesquisa fica em `/pesquisa.html`.

## Build para hospedagem estática

```sh
cd frontend
npm install
npm run build
```

O Vite produz `frontend/dist/` com `index.html`, `pesquisa.html` e os assets. No futuro, um container Apache ou outro servidor estático pode servir **somente o conteúdo de `dist/`**. O servidor não precisa executar Node.js após a build.

Os produtos e preços exibidos hoje são dados de demonstração em `src/data/catalog.js` e na página inicial; a integração com a API ainda não foi feita. As folhas do Bootstrap, Bootstrap Icons e a fonte Inter continuam carregadas por CDN. Quando a instalação do npm estiver disponível, o `package-lock.json` gerado deve ser versionado e os comandos podem passar a usar `npm ci`.
