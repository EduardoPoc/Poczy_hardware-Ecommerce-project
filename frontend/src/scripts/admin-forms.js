import { escapeHtml as e, statusLabels, orderTransitions, movementLabels, money, moneyFromCents, cents, dateLabel, badge, objectValues, imageUrl } from './admin-utils.js';

let attributeIndex = 0;
const idFor = name => `editor-${name.replaceAll('.', '-')}`;
function input(name, label, value = '', { type = 'text', attrs = '', help = '', wide = false } = {}) {
  return `<div class="admin-field ${wide ? 'wide' : ''} d-flex flex-column gap-2"><label class="form-label mb-0" for="${e(idFor(name))}">${e(label)}</label><input class="form-control" id="${e(idFor(name))}" name="${e(name)}" type="${type}" value="${e(value)}" ${attrs} />${help ? `<small>${e(help)}</small>` : ''}</div>`;
}
function select(name, label, options, value = '', attrs = '') {
  return `<div class="admin-field d-flex flex-column gap-2"><label class="form-label mb-0" for="${e(idFor(name))}">${e(label)}</label><select class="form-select" id="${e(idFor(name))}" name="${e(name)}" ${attrs}>${options.map(([key, text]) => `<option value="${e(key)}" ${String(key) === String(value) ? 'selected' : ''}>${e(text)}</option>`).join('')}</select></div>`;
}
function textarea(name, label, value = '', help = '') {
  return `<div class="admin-field wide d-flex flex-column gap-2"><label class="form-label mb-0" for="${e(idFor(name))}">${e(label)}</label><textarea class="form-control" id="${e(idFor(name))}" name="${e(name)}" maxlength="65535">${e(value)}</textarea>${help ? `<small>${e(help)}</small>` : ''}</div>`;
}
const activeField = active => `<div class="admin-field wide d-flex flex-column gap-2"><label class="admin-checkbox d-flex align-items-center m-0"><input type="checkbox" name="is_active" ${active ? 'checked' : ''} class="form-check-input" /> Disponível no catálogo</label></div>`;

export function productForm(product, data) {
  return `<div class="admin-form-grid d-grid">${input('name', 'Nome do produto', product?.name, { attrs: 'required maxlength="150"', wide: true })}
    ${select('category_id', 'Categoria', [['', 'Selecione uma categoria'], ...data.categories.map(category => [category.id, `${category.name}${category.is_active ? '' : ' (inativa)'}`])], product?.category_id, 'required')}
    ${select('brand_id', 'Marca', [['', 'Sem marca'], ...data.brands.map(brand => [brand.id, brand.name])], product?.brand_id)}
    ${input('price', 'Preço de venda (R$)', product?.price, { type: 'number', attrs: 'required min="0.01" max="99999999.99" step="0.01" inputmode="decimal"' })}
    ${input('cost', 'Custo (R$) · opcional', product?.cost, { type: 'number', attrs: 'min="0" max="99999999.99" step="0.01" inputmode="decimal"' })}
    ${!product ? input('stock', 'Estoque inicial', 0, { type: 'number', attrs: 'required min="0" max="2147483647" step="1"' }) : `<div class="admin-field d-flex flex-column gap-2"><label>Estoque atual</label><strong>${e(product.stock)} unidades</strong><small>Use a área Estoque para registrar entradas, saídas e ajustes.</small></div>`}
    ${activeField(product?.is_active ?? true)}
    ${input('image_url', 'URL da imagem · opcional', product?.image_url, { type: 'url', attrs: 'maxlength="500"', wide: true, help: 'Use o endereço HTTP ou HTTPS de uma imagem.' })}
    ${textarea('description', 'Descrição', product?.description)}</div>
    <section class="admin-form-section"><h3>Características técnicas</h3><p class="admin-form-help">Os campos seguem os atributos definidos para a categoria selecionada.</p><div class="admin-form-grid d-grid" id="admin-spec-fields"></div></section>`;
}

export function specificationFields(category, values = {}) {
  const definitions = Object.entries(objectValues(category?.attributes)).filter(([key]) => key !== 'brand');
  if (!definitions.length) return '<p class="admin-form-help">Esta categoria não exige características adicionais.</p>';
  return definitions.map(([key, definition]) => {
    const name = `specifications.${key}`;
    const label = `${definition.name}${definition.unit ? ` (${definition.unit})` : ''}${definition.required ? ' *' : ''}`;
    const value = values[key] ?? '';
    const required = definition.required ? 'required' : '';
    const options = definition.options;
    if (Array.isArray(options) && options.length) return select(name, label, [['', 'Selecione'], ...options.map(option => [String(option), typeof option === 'boolean' ? (option ? 'Sim' : 'Não') : option])], value, required);
    if (definition.type === 'boolean') return select(name, label, [['', 'Não informado'], ['true', 'Sim'], ['false', 'Não']], value, required);
    const numeric = ['integer', 'number'].includes(definition.type);
    const bounds = `${definition.minimum !== undefined ? `min="${e(definition.minimum)}"` : ''} ${definition.maximum !== undefined ? `max="${e(definition.maximum)}"` : ''}`;
    return input(name, label, value, { type: numeric ? 'number' : 'text', attrs: `${required} ${numeric ? `step="${definition.type === 'integer' ? '1' : 'any'}" ${bounds}` : 'maxlength="1000"'}`, help: definition.exclusive_minimum !== undefined || definition.exclusive_maximum !== undefined ? `Valor maior que ${definition.exclusive_minimum ?? 'o mínimo permitido'} e menor que ${definition.exclusive_maximum ?? 'o máximo permitido'}.` : '' });
  }).join('');
}

export function categoryForm(category) {
  attributeIndex = 0;
  return `<div class="admin-form-grid d-grid">${input('name', 'Nome da categoria', category?.name, { attrs: 'required maxlength="100"', wide: true })}${textarea('description', 'Descrição', category?.description)}${activeField(category?.is_active ?? true)}</div><section class="admin-form-section"><h3>Atributos dos produtos</h3><p class="admin-form-help">Defina os campos técnicos desta categoria. Alterações precisam ser compatíveis com os produtos já cadastrados.</p><div id="admin-attributes">${Object.entries(objectValues(category?.attributes)).map(([key, definition]) => attributeRow(key, definition)).join('')}</div><button class="admin-secondary d-inline-flex align-items-center justify-content-center gap-2 text-decoration-none btn btn-outline-secondary" type="button" id="admin-add-attribute"><i class="bi bi-plus-lg" aria-hidden="true"></i> Adicionar atributo</button></section>`;
}

export function attributeRow(key = '', definition = {}) {
  const prefix = `attribute-${attributeIndex++}`;
  return `<div class="admin-attribute position-relative" data-attribute-prefix="${prefix}"><button class="admin-danger-text admin-attribute-remove border-0 btn btn-link" type="button" data-remove-attribute><i class="bi bi-trash3" aria-hidden="true"></i> Remover atributo</button><div class="admin-form-grid d-grid">
    ${input(`${prefix}.key`, 'Identificador', key, { attrs: 'required maxlength="60" pattern="[a-z][a-z0-9_]*"', help: 'Ex.: ram_gb, socket ou screen_size. Use letras minúsculas e sublinhado.' })}
    ${input(`${prefix}.name`, 'Nome exibido', definition.name, { attrs: 'required' })}
    ${select(`${prefix}.type`, 'Tipo', [['text', 'Texto'], ['integer', 'Número inteiro'], ['number', 'Número decimal'], ['boolean', 'Sim / Não']], definition.type || 'text')}
    ${input(`${prefix}.unit`, 'Unidade · opcional', definition.unit, { attrs: 'maxlength="100"', help: 'Ex.: GB, Hz, pol.' })}
    ${textarea(`${prefix}.options`, 'Opções permitidas · opcional', Array.isArray(definition.options) ? definition.options.join('\n') : '', 'Uma opção por linha. Para Sim / Não, use true e false. Deixe vazio para permitir valores livres.')}
    <div class="admin-attribute-checks d-flex flex-wrap"><label class="admin-checkbox d-flex align-items-center m-0"><input type="checkbox" name="${prefix}.required" ${definition.required ? 'checked' : ''} class="form-check-input" /> Obrigatório</label><label class="admin-checkbox d-flex align-items-center m-0"><input type="checkbox" name="${prefix}.is_filterable" ${definition.is_filterable !== false ? 'checked' : ''} class="form-check-input" /> Disponível nos filtros</label></div>
    <details><summary>Limites numéricos · opcional</summary><div class="admin-form-grid d-grid">${['minimum', 'maximum', 'exclusive_minimum', 'exclusive_maximum'].map((bound, index) => input(`${prefix}.${bound}`, ['Mínimo (inclusive)', 'Máximo (inclusive)', 'Maior que', 'Menor que'][index], definition[bound], { type: 'number', attrs: 'step="any"' })).join('')}</div></details>
  </div></div>`;
}

export const brandForm = brand => `<div class="admin-form-grid d-grid">${input('name', 'Nome da marca', brand?.name, { attrs: 'required maxlength="100"', wide: true })}</div>`;

export function stockForm(product) {
  return `<div class="admin-detail-card"><h3>${e(product.name)}</h3><p>Saldo atual: <strong>${e(product.stock)} unidades</strong></p></div><section class="admin-form-section"><div class="admin-form-grid d-grid">${select('type', 'Tipo de movimentação', [['INBOUND', 'Entrada'], ['OUTBOUND', 'Saída'], ['ADJUSTMENT', 'Ajuste de saldo']], 'INBOUND')}${input('quantity', 'Quantidade', 1, { type: 'number', attrs: 'required min="1" max="2147483647" step="1"' })}${input('reason', 'Motivo da movimentação', '', { attrs: 'required maxlength="150"', wide: true, help: 'Descreva o motivo. O registro fica preservado no histórico.' })}</div><p class="admin-form-help" id="admin-stock-preview"></p></section>`;
}

export function movementHistory(product, movements) {
  return `<div class="admin-detail-card"><h3>${e(product.name)}</h3><p>Saldo atual: <strong>${e(product.stock)} unidades</strong></p></div><section class="admin-form-section"><h3>Movimentações registradas</h3>${movements.length ? `<div class="admin-table-scroll position-relative overflow-x-auto table-responsive" tabindex="0" role="region" aria-label="Histórico de estoque"><table class="admin-table w-100 text-start table table-hover mb-0"><thead><tr><th scope="col">DATA</th><th scope="col">TIPO</th><th scope="col">QUANTIDADE</th><th scope="col">MOTIVO</th></tr></thead><tbody>${movements.map(movement => `<tr><td>${e(dateLabel(movement.created_at))}</td><td>${e(movementLabels[movement.type] || movement.type)}</td><td>${movement.type === 'OUTBOUND' ? '−' : movement.quantity > 0 ? '+' : ''}${e(movement.quantity)}</td><td class="admin-name">${e(movement.reason || '—')}</td></tr>`).join('')}</tbody></table></div>` : '<p class="admin-form-help">Ainda não há movimentações registradas para este produto.</p>'}</section>`;
}

export function orderForm(order) {
  const address = order.shipping_address;
  const payment = { PIX: 'Pix', CREDIT_CARD: 'Cartão de crédito', BOLETO: 'Boleto' }[order.payment_method] || 'Não informado';
  const next = orderTransitions[order.status] || [];
  return `<div class="admin-detail-grid d-grid"><section class="admin-detail-card"><h3>Pedido #${e(order.id)}</h3>${badge(order.status)}<p>Cliente #${e(order.user_id)}<br />${e(dateLabel(order.created_at))}<br />Pagamento: ${e(payment)}</p></section><section class="admin-detail-card"><h3>Endereço da compra</h3><p>${address ? `${e(address.street)}, ${e(address.number)}${address.complement ? ` · ${e(address.complement)}` : ''}<br />${e(address.city)} / ${e(address.state)}<br />CEP ${e(address.postal_code)}` : 'Endereço não disponível neste registro.'}</p></section></div>
    <section class="admin-form-section"><h3>Produtos do pedido</h3><div class="admin-table-scroll position-relative overflow-x-auto table-responsive" tabindex="0" role="region" aria-label="Itens do pedido"><table class="admin-table w-100 text-start table table-hover mb-0"><thead><tr><th scope="col">PRODUTO</th><th scope="col">QTD.</th><th scope="col">UNITÁRIO</th><th scope="col">SUBTOTAL</th></tr></thead><tbody>${(order.items || []).map(item => `<tr><td class="admin-name"><strong>${e(item.product_name_snapshot)}</strong><small>${e(item.category_name_snapshot)}</small></td><td>${e(item.quantity)}</td><td>${money(item.unit_price)}</td><td>${item.line_total !== undefined ? money(item.line_total) : moneyFromCents(cents(item.unit_price) * BigInt(item.quantity))}</td></tr>`).join('')}</tbody></table></div><div class="admin-detail-totals"><p>Produtos <span>${money(order.products_total)}</span></p><p>Frete <span>${money(order.shipping_total)}</span></p><p>Total <strong>${money(order.total)}</strong></p></div></section>
    <section class="admin-form-section"><h3>Histórico do pedido</h3><ul class="admin-history-list p-0">${(order.status_history || []).map(event => `<li>${badge(event.status)}<time>${e(dateLabel(event.changed_at))}</time></li>`).join('') || '<li>Nenhum evento disponível.</li>'}</ul></section>
    <section class="admin-form-section"><h3>Andamento do pedido</h3>${next.length ? `${select('status', 'Próxima etapa', [['', 'Selecione uma ação'], ...next.map(status => [status, statusLabels[status]])], '', 'required')}<p class="admin-form-help" id="admin-order-notice">Selecione a próxima etapa e revise antes de confirmar.</p>` : '<p class="admin-form-help">Este pedido está encerrado e não permite novas transições.</p>'}</section>`;
}

function value(form, name) { return form.elements.namedItem(name)?.value?.trim() ?? ''; }
function description(form) {
  const text = value(form, 'description');
  if (new TextEncoder().encode(text).length > 65535) throw new Error('A descrição é muito longa. Reduza o texto para até 65.535 bytes.');
  return text || null;
}
function typed(raw, type) {
  if (type === 'boolean') {
    if (!['true', 'false'].includes(raw)) throw new Error('Use somente true ou false nas opções de Sim / Não.');
    return raw === 'true';
  }
  if (['integer', 'number'].includes(type)) {
    const result = Number(raw);
    if (!Number.isFinite(result) || (type === 'integer' && !Number.isSafeInteger(result))) throw new Error('Confira os valores numéricos dos atributos.');
    return result;
  }
  return raw;
}

export function readPayload(kind, form, data, record) {
  if (kind === 'brands') return { name: value(form, 'name') };
  if (kind === 'stock') {
    const quantity = Number(value(form, 'quantity'));
    const type = value(form, 'type');
    if (!Number.isSafeInteger(quantity) || quantity === 0 || Math.abs(quantity) > 2147483647 || (type !== 'ADJUSTMENT' && quantity < 1)) throw new Error('Informe uma quantidade inteira válida e diferente de zero.');
    return { type, quantity, reason: value(form, 'reason') };
  }
  if (kind === 'order') {
    const status = value(form, 'status');
    if (!(orderTransitions[record.status] || []).includes(status)) throw new Error('Selecione uma próxima etapa válida para este pedido.');
    return { status };
  }
  if (kind === 'categories') {
    const attributes = [];
    const keys = new Set();
    for (const row of form.querySelectorAll('[data-attribute-prefix]')) {
      const prefix = row.dataset.attributePrefix;
      const get = key => value(form, `${prefix}.${key}`);
      const key = get('key');
      if (!/^[a-z][a-z0-9_]{0,59}$/.test(key) || keys.has(key)) throw new Error('Cada atributo precisa de um identificador único, em letras minúsculas e sublinhado.');
      keys.add(key);
      const type = get('type');
      const definition = { name: get('name'), type, required: form.elements.namedItem(`${prefix}.required`).checked, is_filterable: form.elements.namedItem(`${prefix}.is_filterable`).checked };
      if (get('unit')) definition.unit = get('unit');
      if (get('options')) definition.options = get('options').split('\n').map(option => typed(option.trim(), type));
      for (const bound of ['minimum', 'maximum', 'exclusive_minimum', 'exclusive_maximum']) if (get(bound) !== '') definition[bound] = typed(get(bound), 'number');
      attributes.push([key, definition]);
    }
    if (attributes.length > 100) throw new Error('Use no máximo 100 atributos por categoria.');
    return { name: value(form, 'name'), description: description(form), is_active: form.elements.is_active.checked, attributes: Object.fromEntries(attributes) };
  }
  const categoryId = Number(value(form, 'category_id'));
  const category = data.categories.find(entry => entry.id === categoryId);
  if (!category) throw new Error('Selecione uma categoria válida.');
  const specifications = [];
  for (const [key, definition] of Object.entries(objectValues(category.attributes))) {
    if (key === 'brand') continue;
    const raw = value(form, `specifications.${key}`);
    if (raw !== '') specifications.push([key, typed(raw, definition.type)]);
  }
  const price = value(form, 'price');
  const cost = value(form, 'cost');
  if (!/^\d+(?:\.\d{1,2})?$/.test(price) || cents(price) <= 0n || cents(price) > 9999999999n || (cost && (!/^\d+(?:\.\d{1,2})?$/.test(cost) || cents(cost) < 0n || cents(cost) > 9999999999n))) throw new Error('Informe valores monetários válidos, com até duas casas decimais.');
  const url = value(form, 'image_url');
  if (url && !imageUrl(url)) throw new Error('A imagem precisa de uma URL HTTP ou HTTPS.');
  const payload = { name: value(form, 'name'), category_id: categoryId, brand_id: value(form, 'brand_id') ? Number(value(form, 'brand_id')) : null, description: description(form), price, cost: cost || null, image_url: url || null, specifications: Object.fromEntries(specifications), is_active: form.elements.is_active.checked };
  if (!record) payload.stock = Number(value(form, 'stock'));
  return payload;
}
