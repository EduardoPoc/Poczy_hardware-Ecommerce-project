const PAGE_SIZE = 10;

function matchesStatus(section, record, status) {
  if (!status) return true;
  if (section === 'orders') return record.status === status;
  if (['products', 'categories'].includes(section)) {
    return Boolean(record.is_active) === (status === 'active');
  }
  if (section === 'stock') {
    if (status === 'out') return record.stock === 0;
    if (status === 'low') return record.stock >= 1 && record.stock <= 5;
    return record.stock > 5;
  }
  return true;
}

export function newestOrderFirst(first, second) {
  return new Date(second.created_at) - new Date(first.created_at) || second.id - first.id;
}

/** Filtra os dados já carregados do painel. A busca pública de produtos consulta a API. */
export function filteredRecords(section, data, filters) {
  const source = data[section === 'stock' ? 'products' : section];
  const query = filters.q.trim().toLocaleLowerCase('pt-BR');
  return source
    .filter((record) => {
      const text =
        section === 'orders'
          ? `#${record.id} ${record.id} ${record.user_id}`
          : `${record.name} ${record.brand?.name || ''}`;
      const matchesQuery = !query || text.toLocaleLowerCase('pt-BR').includes(query);
      const matchesCategory = !filters.category || String(record.category_id) === filters.category;
      return matchesQuery && matchesCategory && matchesStatus(section, record, filters.status);
    })
    .sort(
      section === 'orders' ? newestOrderFirst : (a, b) => a.name.localeCompare(b.name, 'pt-BR'),
    );
}

export function paginateRecords(records, page) {
  const pages = Math.max(1, Math.ceil(records.length / PAGE_SIZE));
  const current = Math.min(page, pages);
  const offset = (current - 1) * PAGE_SIZE;
  return {
    current,
    pages,
    visible: records.slice(offset, current * PAGE_SIZE),
    label: records.length
      ? `${offset + 1}–${Math.min(current * PAGE_SIZE, records.length)} de ${records.length} ${records.length === 1 ? 'registro' : 'registros'}`
      : '0 registros',
  };
}
