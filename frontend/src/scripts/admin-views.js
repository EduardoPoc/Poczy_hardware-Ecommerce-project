import { escapeHtml as e, statusLabels, badge, dateLabel, money, moneyFromCents, cents, imageUrl, objectValues } from './admin-utils.js';

export const sections = {
  overview: { title: 'Visão geral', description: 'Acompanhe sua loja e cuide de cada próximo upgrade.', create: 'Novo produto', resource: 'products' },
  products: { title: 'Produtos', description: 'Organize o catálogo e mantenha cada detalhe em dia.', create: 'Novo produto', resource: 'products' },
  orders: { title: 'Pedidos', description: 'Acompanhe cada compra, do pagamento à entrega.' },
  categories: { title: 'Categorias', description: 'Organize os produtos e defina suas características técnicas.', create: 'Nova categoria', resource: 'categories' },
  brands: { title: 'Marcas', description: 'Gerencie as marcas que fazem parte do seu catálogo.', create: 'Nova marca', resource: 'brands' },
  stock: { title: 'Estoque', description: 'Controle os saldos e acompanhe as movimentações dos produtos.' }
};

const activeBadge = active => `<span class="admin-badge ${active ? 'active' : 'inactive'}"><span></span>${active ? 'Ativo' : 'Inativo'}</span>`;
const stockBadge = stock => `<span class="admin-badge ${stock === 0 ? 'out' : stock <= 5 ? 'low' : 'active'}"><span></span>${stock === 0 ? 'Sem estoque' : stock <= 5 ? 'Estoque baixo' : 'Disponível'}</span>`;
const action = (type, id, name, icon) => `<button class="admin-icon-button" type="button" data-action="${type}" data-id="${e(id)}" aria-label="${e(name)}" title="${e(name)}"><i class="bi ${icon}" aria-hidden="true"></i></button>`;
const editAction = (resource, record) => action(`edit-${resource}`, record.id, `Editar ${record.name}`, 'bi-pencil');
const empty = (title = 'Nenhum resultado encontrado', description = 'Tente outra busca ou altere os filtros.') => `<div class="admin-table-empty"><i class="bi bi-inbox" aria-hidden="true"></i><h3>${e(title)}</h3><p>${e(description)}</p></div>`;
function productName(product) {
  const url = imageUrl(product.image_url);
  return `<div class="admin-product-cell"><span class="admin-thumb">${url ? `<img src="${e(url)}" alt="" loading="lazy" referrerpolicy="no-referrer" />` : '<i class="bi bi-cpu" aria-hidden="true"></i>'}</span><div><strong>${e(product.name)}</strong><small>${e(product.brand?.name || 'Sem marca')} · #${e(product.id)}</small></div></div>`;
}
function orderRow(order) {
  return `<tr><td><strong>#${e(order.id)}</strong><small>${e(dateLabel(order.created_at))}</small></td><td>Cliente #${e(order.user_id)}<small>${e(order.items_quantity ?? order.items_count ?? 0)} ${(order.items_quantity ?? order.items_count ?? 0) === 1 ? 'item' : 'itens'}</small></td><td>${badge(order.status)}</td><td><strong>${money(order.total)}</strong></td><td><div class="admin-actions">${action('order', order.id, `Ver pedido ${order.id}`, 'bi-arrow-up-right')}</div></td></tr>`;
}
const ordersTable = records => `<div class="admin-table-scroll" tabindex="0" role="region" aria-label="Tabela de pedidos"><table class="admin-table"><thead><tr><th scope="col">PEDIDO</th><th scope="col">CLIENTE</th><th scope="col">STATUS</th><th scope="col">TOTAL</th><th scope="col"><span class="visually-hidden">Ações</span></th></tr></thead><tbody>${records.map(orderRow).join('')}</tbody></table></div>`;

export function overview(data) {
  const active = data.products.filter(product => product.is_active).length;
  const open = data.orders.filter(order => ['PENDING', 'PAID', 'SHIPPED'].includes(order.status)).length;
  const low = data.products.filter(product => product.is_active && product.stock <= 5).sort((a, b) => a.stock - b.stock);
  const confirmed = data.orders.filter(order => ['PAID', 'SHIPPED', 'DELIVERED'].includes(order.status));
  const revenue = confirmed.reduce((sum, order) => sum + cents(order.total), 0n);
  const stats = [
    ['Produtos cadastrados', data.products.length, `${active} produtos ativos no catálogo`, 'bi-box-seam', ''],
    ['Pedidos em aberto', open, 'Aguardando pagamento, envio ou entrega', 'bi-bag-check', ''],
    ['Estoque em atenção', low.length, 'Produtos ativos com até 5 unidades', 'bi-boxes', ''],
    ['Vendas confirmadas', moneyFromCents(revenue), `${confirmed.length} pedidos pagos, enviados ou entregues`, 'bi-wallet2', 'revenue']
  ];
  const distribution = Object.keys(statusLabels).map(status => ({ status, count: data.orders.filter(order => order.status === status).length }));
  return `<div class="admin-stats">${stats.map(([label, value, copy, icon, modifier]) => `<article class="admin-stat ${modifier}"><div class="admin-stat-heading"><span>${label}</span><i class="bi ${icon}" aria-hidden="true"></i></div><strong>${value}</strong><p>${copy}</p></article>`).join('')}</div>
    <div class="admin-overview-grid"><section class="admin-panel"><div class="admin-panel-heading"><div><h2>Últimos pedidos</h2><p>As compras mais recentes da sua loja.</p></div><a href="#orders">Ver todos <i class="bi bi-arrow-right" aria-hidden="true"></i></a></div>${data.orders.length ? ordersTable([...data.orders].sort((a, b) => new Date(b.created_at) - new Date(a.created_at) || b.id - a.id).slice(0, 6)) : empty('Os pedidos começam por aqui', 'As novas compras aparecerão nesta lista.')}</section>
    <div class="admin-overview-side"><section class="admin-panel"><div class="admin-panel-heading"><div><h2>Status dos pedidos</h2><p>${data.orders.length} pedidos no total</p></div><i class="bi bi-bar-chart" aria-hidden="true"></i></div><div class="admin-status-bars" aria-hidden="true">${distribution.map(({ status, count }) => `<span class="${status.toLowerCase()}" style="width:${data.orders.length ? count / data.orders.length * 100 : 0}%"></span>`).join('')}</div><ul class="admin-status-list">${distribution.map(({ status, count }) => `<li><span class="${status.toLowerCase()}"></span>${statusLabels[status]}<strong>${count}</strong></li>`).join('')}</ul></section>
    <section class="admin-panel"><div class="admin-panel-heading"><h2>Atenção ao estoque</h2><a href="#stock">Ver estoque <i class="bi bi-arrow-right" aria-hidden="true"></i></a></div>${low.length ? low.slice(0, 4).map(product => `<div class="admin-low-item"><i class="bi bi-box-seam" aria-hidden="true"></i><div><strong>${e(product.name)}</strong><small>${product.stock === 0 ? 'Sem estoque' : `${product.stock} unidades disponíveis`}</small></div>${action('stock', product.id, `Movimentar estoque de ${product.name}`, 'bi-plus-lg')}</div>`).join('') : '<div class="admin-table-empty"><i class="bi bi-check2-circle" aria-hidden="true"></i><p>Nenhum produto ativo com estoque baixo.</p></div>'}</section></div></div>`;
}

export function listing(section, data) {
  const categoryFilter = ['products', 'stock'].includes(section) ? `<label class="visually-hidden" for="admin-category-filter">Filtrar categoria</label><select id="admin-category-filter" name="category"><option value="">Todas as categorias</option>${data.categories.map(category => `<option value="${e(category.id)}">${e(category.name)}</option>`).join('')}</select>` : '';
  const statuses = section === 'orders' ? Object.entries(statusLabels) : section === 'stock' ? [['out', 'Sem estoque'], ['low', 'Estoque baixo'], ['available', 'Mais de 5 unidades']] : ['products', 'categories'].includes(section) ? [['active', 'Ativos'], ['inactive', 'Inativos']] : [];
  return `<section class="admin-panel"><div class="admin-toolbar"><label class="admin-search" for="admin-search"><i class="bi bi-search" aria-hidden="true"></i><span class="visually-hidden">Buscar ${e(sections[section].title.toLowerCase())}</span><input type="search" id="admin-search" placeholder="${section === 'orders' ? 'Buscar por pedido ou cliente...' : 'Buscar por nome...'}" autocomplete="off" /></label>${categoryFilter}${statuses.length ? `<label class="visually-hidden" for="admin-status-filter">Filtrar situação</label><select id="admin-status-filter" name="status"><option value="">Todas as situações</option>${statuses.map(([value, label]) => `<option value="${value}">${label}</option>`).join('')}</select>` : ''}</div><div id="admin-results"></div></section>`;
}

export function listingResults(section, data, filters, page = 1) {
  const source = data[section === 'stock' ? 'products' : section];
  const query = filters.q.trim().toLocaleLowerCase('pt-BR');
  const records = source.filter(record => {
    const text = section === 'orders' ? `#${record.id} ${record.id} ${record.user_id}` : `${record.name} ${record.brand?.name || ''}`;
    if (query && !text.toLocaleLowerCase('pt-BR').includes(query)) return false;
    if (filters.category && String(record.category_id) !== filters.category) return false;
    if (filters.status) {
      if (section === 'orders' && record.status !== filters.status) return false;
      if (['products', 'categories'].includes(section) && Boolean(record.is_active) !== (filters.status === 'active')) return false;
      if (section === 'stock' && (filters.status === 'out' ? record.stock !== 0 : filters.status === 'low' ? record.stock < 1 || record.stock > 5 : record.stock <= 5)) return false;
    }
    return true;
  }).sort((a, b) => section === 'orders' ? new Date(b.created_at) - new Date(a.created_at) || b.id - a.id : a.name.localeCompare(b.name, 'pt-BR'));
  const pages = Math.max(1, Math.ceil(records.length / 10));
  const current = Math.min(page, pages);
  const visible = records.slice((current - 1) * 10, current * 10);
  const headers = section === 'products' ? ['PRODUTO', 'CATEGORIA', 'PREÇO', 'ESTOQUE', 'SITUAÇÃO'] : section === 'stock' ? ['PRODUTO', 'CATEGORIA', 'SALDO', 'SITUAÇÃO'] : section === 'categories' ? ['CATEGORIA', 'ATRIBUTOS', 'PRODUTOS', 'SITUAÇÃO'] : ['MARCA', 'PRODUTOS'];
  let table;
  if (section === 'orders') table = ordersTable(visible);
  else {
    const rows = visible.map(record => {
      let cells;
      let buttons;
      if (['products', 'stock'].includes(section)) {
        cells = `<td class="admin-name">${productName(record)}</td><td>${e(record.category?.name || data.categories.find(category => category.id === record.category_id)?.name || '—')}</td>${section === 'products' ? `<td><strong>${money(record.price)}</strong></td>` : ''}<td><strong>${e(record.stock)}</strong><small>unidades</small></td><td>${section === 'stock' ? stockBadge(record.stock) : activeBadge(record.is_active)}</td>`;
        buttons = section === 'products' ? editAction('products', record) + action('stock', record.id, `Movimentar estoque de ${record.name}`, 'bi-box-arrow-in-down') : action('stock', record.id, `Movimentar estoque de ${record.name}`, 'bi-plus-lg') + action('history', record.id, `Histórico de ${record.name}`, 'bi-clock-history');
      } else {
        const count = data.products.filter(product => product[section === 'categories' ? 'category_id' : 'brand_id'] === record.id).length;
        cells = `<td class="admin-name"><strong>${e(record.name)}</strong><small>#${e(record.id)}</small></td>${section === 'categories' ? `<td>${Object.keys(objectValues(record.attributes)).length} ${Object.keys(objectValues(record.attributes)).length === 1 ? 'atributo' : 'atributos'}</td>` : ''}<td>${count} ${count === 1 ? 'produto' : 'produtos'}</td>${section === 'categories' ? `<td>${activeBadge(record.is_active)}</td>` : ''}`;
        buttons = editAction(section, record);
      }
      return `<tr>${cells}<td><div class="admin-actions">${buttons}</div></td></tr>`;
    });
    table = `<div class="admin-table-scroll" tabindex="0" role="region" aria-label="Tabela de ${e(sections[section].title.toLowerCase())}"><table class="admin-table"><thead><tr>${headers.map(header => `<th scope="col">${header}</th>`).join('')}<th scope="col"><span class="visually-hidden">Ações</span></th></tr></thead><tbody>${rows.join('')}</tbody></table></div>`;
  }
  const html = `${records.length ? table : empty()}<div class="admin-pagination"><span>${records.length ? `${(current - 1) * 10 + 1}–${Math.min(current * 10, records.length)} de ${records.length} ${records.length === 1 ? 'registro' : 'registros'}` : '0 registros'}</span><div><button class="admin-icon-button" data-page="${current - 1}" type="button" aria-label="Página anterior" ${current === 1 ? 'disabled' : ''}><i class="bi bi-chevron-left" aria-hidden="true"></i></button><span>Página ${current} de ${pages}</span><button class="admin-icon-button" data-page="${current + 1}" type="button" aria-label="Próxima página" ${current === pages ? 'disabled' : ''}><i class="bi bi-chevron-right" aria-hidden="true"></i></button></div></div>`;
  return { html, page: current };
}
