import { cents, asRecord, allowedOrderTransitions } from './admin-utils.js';

function fieldValue(form, name) {
  return form.elements.namedItem(name)?.value?.trim() ?? '';
}
function readDescription(form) {
  const text = fieldValue(form, 'description');
  if (new TextEncoder().encode(text).length > 65535)
    throw new Error('A descrição é muito longa. Reduza o texto para até 65.535 bytes.');
  return text || null;
}
function parseAttributeValue(raw, type) {
  if (type === 'boolean') {
    if (!['true', 'false'].includes(raw))
      throw new Error('Use somente true ou false nas opções de Sim / Não.');
    return raw === 'true';
  }
  if (['integer', 'number'].includes(type)) {
    const result = Number(raw);
    if (!Number.isFinite(result) || (type === 'integer' && !Number.isSafeInteger(result)))
      throw new Error('Confira os valores numéricos dos atributos.');
    return result;
  }
  return raw;
}

function stockPayload(form) {
  const quantity = Number(fieldValue(form, 'quantity'));
  const type = fieldValue(form, 'type');
  if (
    !Number.isSafeInteger(quantity) ||
    quantity === 0 ||
    Math.abs(quantity) > 2147483647 ||
    (type !== 'ADJUSTMENT' && quantity < 1)
  ) {
    throw new Error('Informe uma quantidade inteira válida e diferente de zero.');
  }
  return { type, quantity, reason: fieldValue(form, 'reason') };
}

function orderPayload(form, record) {
  const status = fieldValue(form, 'status');
  if (!allowedOrderTransitions(record).includes(status)) {
    throw new Error('Selecione uma próxima etapa válida para este pedido.');
  }
  return { status };
}

function attributeDefinition(form, prefix) {
  const get = (key) => fieldValue(form, `${prefix}.${key}`);
  const type = get('type');
  const definition = {
    name: get('name'),
    type,
    required: form.elements.namedItem(`${prefix}.required`).checked,
    is_filterable: form.elements.namedItem(`${prefix}.is_filterable`).checked,
  };
  if (get('unit')) definition.unit = get('unit');
  if (get('options')) {
    definition.options = get('options')
      .split('\n')
      .map((option) => parseAttributeValue(option.trim(), type));
  }
  for (const bound of ['minimum', 'maximum', 'exclusive_minimum', 'exclusive_maximum']) {
    if (get(bound) !== '') definition[bound] = parseAttributeValue(get(bound), 'number');
  }
  return definition;
}

function categoryAttributes(form) {
  const attributes = [];
  const keys = new Set();
  for (const row of form.querySelectorAll('[data-attribute-prefix]')) {
    const prefix = row.dataset.attributePrefix;
    const key = fieldValue(form, `${prefix}.key`);
    if (!/^[a-z][a-z0-9_]{0,59}$/.test(key) || keys.has(key)) {
      throw new Error(
        'Cada atributo precisa de um identificador único, em letras minúsculas e sublinhado.',
      );
    }
    keys.add(key);
    attributes.push([key, attributeDefinition(form, prefix)]);
  }
  if (attributes.length > 100) throw new Error('Use no máximo 100 atributos por categoria.');
  return Object.fromEntries(attributes);
}

function categoryPayload(form) {
  const attributes = categoryAttributes(form);
  return {
    name: fieldValue(form, 'name'),
    description: readDescription(form),
    is_active: form.elements.is_active.checked,
    attributes,
  };
}

function productSpecifications(form, category) {
  const specifications = [];
  for (const [key, definition] of Object.entries(asRecord(category.attributes))) {
    if (key === 'brand') continue;
    const raw = fieldValue(form, `specifications.${key}`);
    // Vazio indica ausência; false e zero são valores válidos que precisam chegar à API.
    if (raw !== '') specifications.push([key, parseAttributeValue(raw, definition.type)]);
  }
  return Object.fromEntries(specifications);
}

function productPrices(form) {
  const price = fieldValue(form, 'price');
  const cost = fieldValue(form, 'cost');
  // Compara centavos inteiros para evitar arredondamentos no limite monetário da API.
  if (
    !/^\d+(?:\.\d{1,2})?$/.test(price) ||
    cents(price) <= 0n ||
    cents(price) > 9999999999n ||
    (cost && (!/^\d+(?:\.\d{1,2})?$/.test(cost) || cents(cost) < 0n || cents(cost) > 9999999999n))
  ) {
    throw new Error('Informe valores monetários válidos, com até duas casas decimais.');
  }
  return { price, cost: cost || null };
}

function productPayload(form, data, record) {
  const categoryId = Number(fieldValue(form, 'category_id'));
  const category = data.categories.find((entry) => entry.id === categoryId);
  if (!category) throw new Error('Selecione uma categoria válida.');
  const specifications = productSpecifications(form, category);
  const prices = productPrices(form);
  const stock = record ? Number(record.stock) : Number(fieldValue(form, 'stock'));
  if (form.elements.is_active.checked && !(stock >= 1)) {
    throw new Error('O produto precisa ter pelo menos 1 unidade em estoque para estar ativo.');
  }
  const image = form.elements.namedItem('image')?.files?.[0];
  if (image) {
    if (
      !/\.(png|jpe?g|webp)$/i.test(image.name) ||
      !['image/png', 'image/jpeg', 'image/webp'].includes(image.type)
    )
      throw new Error('Selecione uma imagem PNG, JPG, JPEG ou WebP.');
    if (image.size > 5 * 1024 * 1024) throw new Error('A imagem deve ter no máximo 5 MB.');
  }
  const payload = {
    name: fieldValue(form, 'name'),
    category_id: categoryId,
    brand_id: fieldValue(form, 'brand_id') ? Number(fieldValue(form, 'brand_id')) : null,
    description: readDescription(form),
    ...prices,
    specifications,
    is_active: form.elements.is_active.checked,
  };
  if (!record) payload.stock = Number(fieldValue(form, 'stock'));
  if (!image) return payload;

  const body = new FormData();
  for (const [key, value] of Object.entries(payload)) {
    body.append(
      key,
      value === null
        ? ''
        : typeof value === 'object'
          ? JSON.stringify(value)
          : typeof value === 'boolean'
            ? String(Number(value))
            : String(value),
    );
  }
  body.append('image', image);
  return body;
}

/** Lê e valida o formulário sem fazer requisições ou alterar os campos da tela. */
export function readPayload(kind, form, data, record) {
  switch (kind) {
    case 'brands':
      return { name: fieldValue(form, 'name') };
    case 'stock':
      return stockPayload(form);
    case 'order':
      return orderPayload(form, record);
    case 'categories':
      return categoryPayload(form);
    case 'products':
      return productPayload(form, data, record);
    default:
      throw new Error('Tipo de formulário desconhecido.');
  }
}
