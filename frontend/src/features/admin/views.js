import { escapeHtml } from '../../shared/lib/html.js';
import { orderStatuses as statusLabels } from '../orders/domain.js';
import { badge, dateLabel } from './presentation.js';
import { money, moneyFromCents, cents } from '../../shared/lib/money.js';
import { imageUrl } from '../../shared/lib/images.js';
import { asRecord } from './domain.js';
import { filteredRecords, newestOrderFirst, paginateRecords } from './listing.js';

export const sections = {
  overview: {
    title: 'Visão geral',
    description: 'Acompanhe sua loja e cuide de cada próximo upgrade.',
    create: 'Novo produto',
    resource: 'products',
  },
  products: {
    title: 'Produtos',
    description: 'Organize o catálogo e mantenha cada detalhe em dia.',
    create: 'Novo produto',
    resource: 'products',
  },
  orders: { title: 'Pedidos', description: 'Acompanhe cada compra, do pagamento à entrega.' },
  categories: {
    title: 'Categorias',
    description: 'Organize os produtos e defina suas características técnicas.',
    create: 'Nova categoria',
    resource: 'categories',
  },
  brands: {
    title: 'Marcas',
    description: 'Gerencie as marcas que fazem parte do seu catálogo.',
    create: 'Nova marca',
    resource: 'brands',
  },
  stock: {
    title: 'Estoque',
    description: 'Controle os saldos e acompanhe as movimentações dos produtos.',
  },
};

const activeBadge = (active) =>
  /* HTML */ `<span
    class="admin-badge ${active ? 'active' : 'inactive'} d-inline-flex align-items-center fw-semibold text-nowrap badge"
    ><span></span>${active ? 'Ativo' : 'Inativo'}</span
  >`;
const stockBadge = (stock) =>
  /* HTML */ `<span
    class="admin-badge ${stock === 0 ? 'out' : stock <= 5 ? 'low' : 'active'} d-inline-flex align-items-center fw-semibold text-nowrap badge"
    ><span></span>${stock === 0 ? 'Sem estoque' : stock <= 5 ? 'Estoque baixo' : 'Disponível'}</span
  >`;
const action = (type, id, name, icon) =>
  /* HTML */ `<button
    class="admin-icon-button p-0 btn btn-outline-secondary"
    type="button"
    data-action="${type}"
    data-id="${escapeHtml(id)}"
    aria-label="${escapeHtml(name)}"
    title="${escapeHtml(name)}"
  >
    <i class="bi ${icon}" aria-hidden="true"></i>
  </button>`;
const editAction = (resource, record) =>
  action(`edit-${resource}`, record.id, `Editar ${record.name}`, 'bi-pencil');
const empty = (
  title = 'Nenhum resultado encontrado',
  description = 'Tente outra busca ou altere os filtros.',
) =>
  /* HTML */ `<div class="admin-table-empty text-center">
    <i class="bi bi-inbox" aria-hidden="true"></i>
    <h3>${escapeHtml(title)}</h3>
    <p>${escapeHtml(description)}</p>
  </div>`;
function productName(product) {
  const url = imageUrl(product.image_url);
  const artwork = url
    ? /* HTML */ `<img
        src="${escapeHtml(url)}"
        alt=""
        loading="lazy"
        referrerpolicy="no-referrer"
      />`
    : '<i class="bi bi-cpu" aria-hidden="true"></i>';
  return /* HTML */ `<div class="admin-product-cell d-flex align-items-center">
    <span class="admin-thumb d-grid overflow-hidden">${artwork}</span>
    <div>
      <strong>${escapeHtml(product.name)}</strong
      ><small>${escapeHtml(product.brand?.name || 'Sem marca')} · #${escapeHtml(product.id)}</small>
    </div>
  </div>`;
}
function orderRow(order) {
  return /* HTML */ `<tr>
    <td>
      <strong>#${escapeHtml(order.id)}</strong
      ><small>${escapeHtml(dateLabel(order.created_at))}</small>
    </td>
    <td>
      Cliente #${escapeHtml(order.user_id)}<small
        >${escapeHtml(order.items_quantity ?? order.items_count ?? 0)}
        ${(order.items_quantity ?? order.items_count ?? 0) === 1 ? 'item' : 'itens'}</small
      >
    </td>
    <td>${badge(order.status)}</td>
    <td><strong>${money(order.total)}</strong></td>
    <td>
      <div class="admin-actions d-flex justify-content-end">
        ${action('order', order.id, `Ver pedido ${order.id}`, 'bi-arrow-up-right')}
      </div>
    </td>
  </tr>`;
}
const ordersTable = (records) =>
  /* HTML */ `<div
    class="admin-table-scroll position-relative overflow-x-auto table-responsive"
    tabindex="0"
    role="region"
    aria-label="Tabela de pedidos"
  >
    <table class="admin-table w-100 text-start table table-hover mb-0">
      <thead>
        <tr>
          <th scope="col">PEDIDO</th>
          <th scope="col">CLIENTE</th>
          <th scope="col">STATUS</th>
          <th scope="col">TOTAL</th>
          <th scope="col"><span class="visually-hidden">Ações</span></th>
        </tr>
      </thead>
      <tbody>
        ${records.map(orderRow).join('')}
      </tbody>
    </table>
  </div>`;

function overviewStats(data) {
  const active = data.products.filter((product) => product.is_active).length;
  const open = data.orders.filter((order) =>
    ['PENDING', 'PAID', 'SHIPPED'].includes(order.status),
  ).length;
  const low = data.products
    .filter((product) => product.is_active && product.stock <= 5)
    .sort((a, b) => a.stock - b.stock);
  const confirmed = data.orders.filter((order) =>
    ['PAID', 'SHIPPED', 'DELIVERED'].includes(order.status),
  );
  const revenue = confirmed.reduce((sum, order) => sum + cents(order.total), 0n);
  const stats = [
    [
      'Produtos cadastrados',
      data.products.length,
      `${active} produtos ativos no catálogo`,
      'bi-box-seam',
      '',
    ],
    ['Pedidos em aberto', open, 'Aguardando pagamento, envio ou entrega', 'bi-bag-check', ''],
    ['Estoque em atenção', low.length, 'Produtos ativos com até 5 unidades', 'bi-boxes', ''],
    [
      'Vendas confirmadas',
      moneyFromCents(revenue),
      `${confirmed.length} pedidos pagos, enviados ou entregues`,
      'bi-wallet2',
      'revenue',
    ],
  ];
  return { stats, low };
}

export function overview(data) {
  const { stats, low } = overviewStats(data);
  const latestOrders = [...data.orders].sort(newestOrderFirst).slice(0, 6);
  const recentOrders = latestOrders.length
    ? ordersTable(latestOrders)
    : empty('Os pedidos começam por aqui', 'As novas compras aparecerão nesta lista.');
  const distribution = Object.keys(statusLabels).map((status) => ({
    status,
    count: data.orders.filter((order) => order.status === status).length,
  }));
  return /* HTML */ `<div class="admin-stats d-grid">
      ${stats
        .map(
          ([label, value, copy, icon, modifier]) =>
            /* HTML */ `<article class="admin-stat ${modifier} position-relative">
              <div class="admin-stat-heading align-items-center justify-content-between">
                <span>${label}</span><i class="bi ${icon}" aria-hidden="true"></i>
              </div>
              <strong>${value}</strong>
              <p>${copy}</p>
            </article>`,
        )
        .join('')}
    </div>
    <div class="admin-overview-grid d-grid">
      <section class="admin-panel overflow-hidden card d-block position-static">
        <div class="admin-panel-heading d-flex align-items-center justify-content-between">
          <div>
            <h2>Últimos pedidos</h2>
            <p>As compras mais recentes da sua loja.</p>
          </div>
          <a href="#orders">Ver todos <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
        </div>
        ${recentOrders}
      </section>
      <div class="admin-overview-side d-grid">
        ${orderStatusPanel(distribution, data.orders.length)} ${lowStockPanel(low)}
      </div>
    </div>`;
}

export function listing(section, data) {
  const categoryOptions = data.categories
    .map(
      (category) =>
        /* HTML */ `<option value="${escapeHtml(category.id)}">
          ${escapeHtml(category.name)}
        </option>`,
    )
    .join('');
  const categoryFilter = ['products', 'stock'].includes(section)
    ? /* HTML */ `<label class="visually-hidden" for="admin-category-filter"
          >Filtrar categoria</label
        ><select class="form-select" id="admin-category-filter" name="category">
          <option value="">Todas as categorias</option>
          ${categoryOptions}
        </select>`
    : '';
  return /* HTML */ `<section class="admin-panel overflow-hidden card d-block position-static">
    <div class="admin-toolbar d-flex flex-wrap">
      <label class="admin-search d-flex align-items-center" for="admin-search"
        ><i class="bi bi-search" aria-hidden="true"></i
        ><span class="visually-hidden"
          >Buscar ${escapeHtml(sections[section].title.toLowerCase())}</span
        ><input
          class="form-control"
          type="search"
          id="admin-search"
          placeholder="${section === 'orders' ? 'Buscar por pedido ou cliente...' : 'Buscar por nome...'}"
          autocomplete="off" /></label
      >${categoryFilter}${statusFilter(section)}
    </div>
    <div id="admin-results"></div>
  </section>`;
}

export function listingResults(section, data, filters, page = 1) {
  const records = filteredRecords(section, data, filters);
  const pagination = paginateRecords(records, page);
  const table =
    section === 'orders'
      ? ordersTable(pagination.visible)
      : catalogTable(section, data, pagination.visible);
  return {
    html: `${records.length ? table : empty()}${listingPagination(pagination)}`,
    page: pagination.current,
  };
}

function catalogTable(section, data, visible) {
  const headers =
    section === 'products'
      ? ['PRODUTO', 'CATEGORIA', 'PREÇO', 'ESTOQUE', 'SITUAÇÃO']
      : section === 'stock'
        ? ['PRODUTO', 'CATEGORIA', 'SALDO', 'SITUAÇÃO']
        : section === 'categories'
          ? ['CATEGORIA', 'ATRIBUTOS', 'PRODUTOS', 'SITUAÇÃO']
          : ['MARCA', 'PRODUTOS'];
  const rows = visible.map((record) => catalogRow(section, data, record));
  return /* HTML */ `<div
    class="admin-table-scroll position-relative overflow-x-auto table-responsive"
    tabindex="0"
    role="region"
    aria-label="Tabela de ${escapeHtml(sections[section].title.toLowerCase())}"
  >
    <table class="admin-table w-100 text-start table table-hover mb-0">
      <thead>
        <tr>
          ${headers.map((header) => /* HTML */ `<th scope="col">${header}</th>`).join('')}
          <th scope="col"><span class="visually-hidden">Ações</span></th>
        </tr>
      </thead>
      <tbody>
        ${rows.join('')}
      </tbody>
    </table>
  </div>`;
}

function listingPagination({ current, pages, label }) {
  return /* HTML */ ` <div class="admin-pagination d-flex justify-content-between">
    <span>${label}</span>
    <div>
      <button
        class="admin-icon-button p-0 btn btn-outline-secondary"
        data-page="${current - 1}"
        type="button"
        aria-label="Página anterior"
        ${current === 1 ? 'disabled' : ''}
      >
        <i class="bi bi-chevron-left" aria-hidden="true"></i></button
      ><span>Página ${current} de ${pages}</span
      ><button
        class="admin-icon-button p-0 btn btn-outline-secondary"
        data-page="${current + 1}"
        type="button"
        aria-label="Próxima página"
        ${current === pages ? 'disabled' : ''}
      >
        <i class="bi bi-chevron-right" aria-hidden="true"></i>
      </button>
    </div>
  </div>`;
}

function orderStatusPanel(distribution, orderCount) {
  const bars = distribution
    .map(
      ({ status, count }) =>
        /* HTML */ `<span
          class="${status.toLowerCase()}"
          style="width:${orderCount ? (count / orderCount) * 100 : 0}%"
        ></span>`,
    )
    .join('');
  const labels = distribution
    .map(
      ({ status, count }) =>
        /* HTML */ `<li>
          <span class="${status.toLowerCase()}"></span>${statusLabels[status]}<strong
            >${count}</strong
          >
        </li>`,
    )
    .join('');
  return /* HTML */ `<section class="admin-panel overflow-hidden card d-block position-static">
    <div class="admin-panel-heading d-flex align-items-center justify-content-between">
      <div>
        <h2>Status dos pedidos</h2>
        <p>${orderCount} pedidos no total</p>
      </div>
      <i class="bi bi-bar-chart" aria-hidden="true"></i>
    </div>
    <div class="admin-status-bars d-flex gap-1 overflow-hidden" aria-hidden="true">${bars}</div>
    <ul class="admin-status-list m-0">
      ${labels}
    </ul>
  </section>`;
}

function lowStockPanel(low) {
  return /* HTML */ `<section class="admin-panel overflow-hidden card d-block position-static">
    <div class="admin-panel-heading d-flex align-items-center justify-content-between">
      <h2>Atenção ao estoque</h2>
      <a href="#stock">Ver estoque <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
    </div>
    ${
      low.length
        ? low
            .slice(0, 4)
            .map(
              (product) =>
                /* HTML */ `<div class="admin-low-item align-items-center">
                  <i class="bi bi-box-seam" aria-hidden="true"></i>
                  <div>
                    <strong>${escapeHtml(product.name)}</strong
                    ><small
                      >${product.stock === 0 ? 'Sem estoque' : `${product.stock} unidades disponíveis`}</small
                    >
                  </div>
                  ${action('stock', product.id, `Movimentar estoque de ${product.name}`, 'bi-plus-lg')}
                </div>`,
            )
            .join('')
        : '<div class="admin-table-empty text-center"><i class="bi bi-check2-circle" aria-hidden="true"></i><p>Nenhum produto ativo com estoque baixo.</p></div>'
    }
  </section>`;
}

function catalogRow(section, data, record) {
  const row = ['products', 'stock'].includes(section)
    ? productCells(section, data, record)
    : taxonomyCells(section, data, record);
  return /* HTML */ `<tr>
    ${row.cells}
    <td><div class="admin-actions d-flex justify-content-end">${row.buttons}</div></td>
  </tr>`;
}

function productCells(section, data, record) {
  const categoryName = escapeHtml(
    record.category?.name ||
      data.categories.find((category) => category.id === record.category_id)?.name ||
      '—',
  );
  const cells = /* HTML */ `<td class="admin-name">${productName(record)}</td>
    <td>${categoryName}</td>
    ${section === 'products' ? /* HTML */ `<td><strong>${money(record.price)}</strong></td>` : ''}
    <td><strong>${escapeHtml(record.stock)}</strong><small>unidades</small></td>
    <td>${section === 'stock' ? stockBadge(record.stock) : activeBadge(record.is_active)}</td>`;
  const buttons =
    section === 'products'
      ? editAction('products', record) +
        action('stock', record.id, `Movimentar estoque de ${record.name}`, 'bi-box-arrow-in-down')
      : action('stock', record.id, `Movimentar estoque de ${record.name}`, 'bi-plus-lg') +
        action('history', record.id, `Histórico de ${record.name}`, 'bi-clock-history');
  return { cells, buttons };
}

function taxonomyCells(section, data, record) {
  const count = data.products.filter(
    (product) => product[section === 'categories' ? 'category_id' : 'brand_id'] === record.id,
  ).length;
  const attributeCountCell =
    section === 'categories'
      ? /* HTML */ `<td>
          ${Object.keys(asRecord(record.attributes)).length}
          ${Object.keys(asRecord(record.attributes)).length === 1 ? 'atributo' : 'atributos'}
        </td>`
      : '';
  const cells = /* HTML */ `<td class="admin-name">
      <strong>${escapeHtml(record.name)}</strong><small>#${escapeHtml(record.id)}</small>
    </td>
    ${attributeCountCell}
    <td>${count} ${count === 1 ? 'produto' : 'produtos'}</td>
    ${section === 'categories' ? /* HTML */ `<td>${activeBadge(record.is_active)}</td>` : ''}`;
  const buttons = editAction(section, record);
  return { cells, buttons };
}

function statusFilter(section) {
  const statuses =
    section === 'orders'
      ? Object.entries(statusLabels)
      : section === 'stock'
        ? [
            ['out', 'Sem estoque'],
            ['low', 'Estoque baixo'],
            ['available', 'Mais de 5 unidades'],
          ]
        : ['products', 'categories'].includes(section)
          ? [
              ['active', 'Ativos'],
              ['inactive', 'Inativos'],
            ]
          : [];
  return statuses.length
    ? /* HTML */ `<label class="visually-hidden" for="admin-status-filter">Filtrar situação</label
        ><select class="form-select" id="admin-status-filter" name="status">
          <option value="">Todas as situações</option>
          ${statuses.map(([value, label]) => /* HTML */ `<option value="${value}">${label}</option>`).join('')}
        </select>`
    : '';
}
