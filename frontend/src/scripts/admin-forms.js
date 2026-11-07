import {
  escapeHtml,
  statusLabels,
  allowedOrderTransitions,
  movementLabels,
  money,
  moneyFromCents,
  cents,
  dateLabel,
  badge,
  asRecord,
} from './admin-utils.js';
import { paymentLabel } from '../domain/customer.js';

let attributeIndex = 0;
const idFor = (name) => `editor-${name.replaceAll('.', '-')}`;
function input(
  name,
  label,
  value = '',
  { type = 'text', attrs = '', help = '', wide = false } = {},
) {
  return /* HTML */ `<div class="admin-field ${wide ? 'wide' : ''} d-flex flex-column gap-2">
    <label class="form-label mb-0" for="${escapeHtml(idFor(name))}">${escapeHtml(label)}</label
    ><input
      class="form-control"
      id="${escapeHtml(idFor(name))}"
      name="${escapeHtml(name)}"
      type="${type}"
      value="${escapeHtml(value)}"
      ${attrs}
    />${help ? /* HTML */ `<small>${escapeHtml(help)}</small>` : ''}
  </div>`;
}
function select(name, label, options, value = '', attrs = '') {
  const optionMarkup = options
    .map(
      ([key, text]) =>
        /* HTML */ `<option
          value="${escapeHtml(key)}"
          ${String(key) === String(value) ? 'selected' : ''}
        >
          ${escapeHtml(text)}
        </option>`,
    )
    .join('');
  return /* HTML */ `<div class="admin-field d-flex flex-column gap-2">
    <label class="form-label mb-0" for="${escapeHtml(idFor(name))}">${escapeHtml(label)}</label
    ><select
      class="form-select"
      id="${escapeHtml(idFor(name))}"
      name="${escapeHtml(name)}"
      ${attrs}
    >
      ${optionMarkup}
    </select>
  </div>`;
}
function textarea(name, label, value = '', help = '') {
  return /* HTML */ `<div class="admin-field wide d-flex flex-column gap-2">
    <label class="form-label mb-0" for="${escapeHtml(idFor(name))}">${escapeHtml(label)}</label
    ><textarea
      class="form-control"
      id="${escapeHtml(idFor(name))}"
      name="${escapeHtml(name)}"
      maxlength="65535"
    >
${escapeHtml(value)}</textarea
    >${help ? /* HTML */ `<small>${escapeHtml(help)}</small>` : ''}
  </div>`;
}
const activeField = (active) =>
  /* HTML */ `<div class="admin-field wide d-flex flex-column gap-2">
    <label class="admin-checkbox d-flex align-items-center m-0"
      ><input
        type="checkbox"
        name="is_active"
        ${active ? 'checked' : ''}
        class="form-check-input"
      />
      Disponível no catálogo</label
    >
  </div>`;

function productCatalogFields(product, data) {
  const categoryField = select(
    'category_id',
    'Categoria',
    [
      ['', 'Selecione uma categoria'],
      ...data.categories.map((category) => [
        category.id,
        `${category.name}${category.is_active ? '' : ' (inativa)'}`,
      ]),
    ],
    product?.category_id,
    'required',
  );
  const brandField = select(
    'brand_id',
    'Marca',
    [['', 'Sem marca'], ...data.brands.map((brand) => [brand.id, brand.name])],
    product?.brand_id,
  );
  return categoryField + brandField;
}

function productPricingFields(product) {
  const priceField = input('price', 'Preço de venda (R$)', product?.price, {
    type: 'number',
    attrs: 'required min="0.01" max="99999999.99" step="0.01" inputmode="decimal"',
  });
  const costField = input('cost', 'Custo (R$) · opcional', product?.cost, {
    type: 'number',
    attrs: 'min="0" max="99999999.99" step="0.01" inputmode="decimal"',
  });
  const stockField = !product
    ? input('stock', 'Estoque inicial', 1, {
        type: 'number',
        attrs: 'required min="0" max="2147483647" step="1"',
      })
    : /* HTML */ `<div class="admin-field d-flex flex-column gap-2">
        <label>Estoque atual</label><strong>${escapeHtml(product.stock)} unidades</strong
        ><small>Use a área Estoque para registrar entradas, saídas e ajustes.</small>
      </div>`;
  return priceField + costField + stockField;
}

/** Combina dados do catálogo com as características definidas pela categoria selecionada. */
export function productForm(product, data) {
  const imageField = input('image', 'Imagem do produto · opcional', '', {
    type: 'file',
    attrs: 'accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp"',
    wide: true,
    help: 'PNG, JPG, JPEG ou WebP, até 5 MB. Na edição, envie um arquivo para substituir a imagem atual.',
  });
  return /* HTML */ `<div class="admin-form-grid d-grid">
      ${input('name', 'Nome do produto', product?.name, { attrs: 'required maxlength="150"', wide: true })}
      ${productCatalogFields(product, data)} ${productPricingFields(product)}
      ${activeField(product?.is_active ?? true)} ${imageField}
      <p class="admin-field admin-form-help wide">
        Produtos ativos sem estoque continuam visíveis como indisponíveis. Desative o produto para
        removê-lo do catálogo.
      </p>
      ${textarea('description', 'Descrição', product?.description)}
    </div>
    <section class="admin-form-section">
      <h3>Características técnicas</h3>
      <p class="admin-form-help">
        Os campos seguem os atributos definidos para a categoria selecionada.
      </p>
      <div class="admin-form-grid d-grid" id="admin-spec-fields"></div>
    </section>`;
}

export function specificationFields(category, values = {}) {
  const definitions = Object.entries(asRecord(category?.attributes)).filter(
    ([key]) => key !== 'brand',
  );
  if (!definitions.length)
    return '<p class="admin-form-help">Esta categoria não exige características adicionais.</p>';
  return definitions
    .map(([key, definition]) => specificationField(key, definition, values[key] ?? ''))
    .join('');
}

function specificationField(key, definition, value) {
  const name = `specifications.${key}`;
  const label = `${definition.name}${definition.unit ? ` (${definition.unit})` : ''}${definition.required ? ' *' : ''}`;
  const required = definition.required ? 'required' : '';
  const options = definition.options;
  if (Array.isArray(options) && options.length)
    return select(
      name,
      label,
      [
        ['', 'Selecione'],
        ...options.map((option) => [
          String(option),
          typeof option === 'boolean' ? (option ? 'Sim' : 'Não') : option,
        ]),
      ],
      value,
      required,
    );
  if (definition.type === 'boolean')
    return select(
      name,
      label,
      [
        ['', 'Não informado'],
        ['true', 'Sim'],
        ['false', 'Não'],
      ],
      value,
      required,
    );
  const numeric = ['integer', 'number'].includes(definition.type);
  const bounds = `${definition.minimum !== undefined ? `min="${escapeHtml(definition.minimum)}"` : ''} ${definition.maximum !== undefined ? `max="${escapeHtml(definition.maximum)}"` : ''}`;
  return input(name, label, value, {
    type: numeric ? 'number' : 'text',
    attrs: `${required} ${numeric ? `step="${definition.type === 'integer' ? '1' : 'any'}" ${bounds}` : 'maxlength="1000"'}`,
    help:
      definition.exclusive_minimum !== undefined || definition.exclusive_maximum !== undefined
        ? `Valor maior que ${definition.exclusive_minimum ?? 'o mínimo permitido'} e menor que ${definition.exclusive_maximum ?? 'o máximo permitido'}.`
        : '',
  });
}

export function categoryForm(category) {
  attributeIndex = 0;
  return /* HTML */ `<div class="admin-form-grid d-grid">
      ${input('name', 'Nome da categoria', category?.name, { attrs: 'required maxlength="100"', wide: true })}${textarea('description', 'Descrição', category?.description)}${activeField(category?.is_active ?? true)}
    </div>
    <section class="admin-form-section">
      <h3>Atributos dos produtos</h3>
      <p class="admin-form-help">
        Defina os campos técnicos desta categoria. Alterações precisam ser compatíveis com os
        produtos já cadastrados.
      </p>
      <div id="admin-attributes">
        ${Object.entries(asRecord(category?.attributes))
          .map(([key, definition]) => attributeRow(key, definition))
          .join('')}
      </div>
      <button
        class="admin-secondary d-inline-flex align-items-center justify-content-center gap-2 text-decoration-none btn btn-outline-secondary"
        type="button"
        id="admin-add-attribute"
      >
        <i class="bi bi-plus-lg" aria-hidden="true"></i> Adicionar atributo
      </button>
    </section>`;
}

/** O prefixo exclusivo mantém os campos de cada atributo independentes ao adicionar/remover linhas. */
export function attributeRow(key = '', definition = {}) {
  const prefix = `attribute-${attributeIndex++}`;
  const keyField = input(`${prefix}.key`, 'Identificador', key, {
    attrs: 'required maxlength="60" pattern="[a-z][a-z0-9_]*"',
    help: 'Ex.: ram_gb, socket ou screen_size. Use letras minúsculas e sublinhado.',
  });
  const unitField = input(`${prefix}.unit`, 'Unidade · opcional', definition.unit, {
    attrs: 'maxlength="100"',
    help: 'Ex.: GB, Hz, pol.',
  });
  const optionsField = textarea(
    `${prefix}.options`,
    'Opções permitidas · opcional',
    Array.isArray(definition.options) ? definition.options.join('\n') : '',
    'Uma opção por linha. Para Sim / Não, use true e false. Deixe vazio para permitir valores livres.',
  );
  return /* HTML */ `<div
    class="admin-attribute position-relative"
    data-attribute-prefix="${prefix}"
  >
    <button
      class="admin-danger-text admin-attribute-remove border-0 btn btn-link"
      type="button"
      data-remove-attribute
    >
      <i class="bi bi-trash3" aria-hidden="true"></i> Remover atributo
    </button>
    <div class="admin-form-grid d-grid">
      ${keyField} ${input(`${prefix}.name`, 'Nome exibido', definition.name, { attrs: 'required' })}
      ${select(
        `${prefix}.type`,
        'Tipo',
        [
          ['text', 'Texto'],
          ['integer', 'Número inteiro'],
          ['number', 'Número decimal'],
          ['boolean', 'Sim / Não'],
        ],
        definition.type || 'text',
      )}
      ${unitField} ${optionsField} ${attributeOptions(prefix, definition)}
    </div>
  </div>`;
}

function attributeOptions(prefix, definition) {
  const numericBounds = ['minimum', 'maximum', 'exclusive_minimum', 'exclusive_maximum']
    .map((bound, index) =>
      input(
        `${prefix}.${bound}`,
        ['Mínimo (inclusive)', 'Máximo (inclusive)', 'Maior que', 'Menor que'][index],
        definition[bound],
        { type: 'number', attrs: 'step="any"' },
      ),
    )
    .join('');
  return /* HTML */ `<div class="admin-attribute-checks d-flex flex-wrap">
      <label class="admin-checkbox d-flex align-items-center m-0"
        ><input
          type="checkbox"
          name="${prefix}.required"
          ${definition.required ? 'checked' : ''}
          class="form-check-input"
        />
        Obrigatório</label
      ><label class="admin-checkbox d-flex align-items-center m-0"
        ><input
          type="checkbox"
          name="${prefix}.is_filterable"
          ${definition.is_filterable !== false ? 'checked' : ''}
          class="form-check-input"
        />
        Disponível nos filtros</label
      >
    </div>
    <details>
      <summary>Limites numéricos · opcional</summary>
      <div class="admin-form-grid d-grid">${numericBounds}</div>
    </details>`;
}

export const brandForm = (brand) =>
  /* HTML */ `<div class="admin-form-grid d-grid">
    ${input('name', 'Nome da marca', brand?.name, { attrs: 'required maxlength="100"', wide: true })}
  </div>`;

export function stockForm(product) {
  const reasonField = input('reason', 'Motivo da movimentação', '', {
    attrs: 'required maxlength="150"',
    wide: true,
    help: 'Descreva o motivo. O registro fica preservado no histórico.',
  });
  return /* HTML */ `<div class="admin-detail-card">
      <h3>${escapeHtml(product.name)}</h3>
      <p>Saldo atual: <strong>${escapeHtml(product.stock)} unidades</strong></p>
    </div>
    <section class="admin-form-section">
      <div class="admin-form-grid d-grid">
        ${select(
          'type',
          'Tipo de movimentação',
          [
            ['INBOUND', 'Entrada'],
            ['OUTBOUND', 'Saída'],
            ['ADJUSTMENT', 'Ajuste de saldo'],
          ],
          'INBOUND',
        )}${input('quantity', 'Quantidade', 1, { type: 'number', attrs: 'required min="1" max="2147483647" step="1"' })}${reasonField}
      </div>
      <p class="admin-form-help" id="admin-stock-preview"></p>
    </section>`;
}

export function movementHistory(product, movements) {
  return /* HTML */ `<div class="admin-detail-card">
      <h3>${escapeHtml(product.name)}</h3>
      <p>Saldo atual: <strong>${escapeHtml(product.stock)} unidades</strong></p>
    </div>
    <section class="admin-form-section">
      <h3>Movimentações registradas</h3>
      ${
        movements.length
          ? /* HTML */ `<div
              class="admin-table-scroll position-relative overflow-x-auto table-responsive"
              tabindex="0"
              role="region"
              aria-label="Histórico de estoque"
            >
              <table class="admin-table w-100 text-start table table-hover mb-0">
                <thead>
                  <tr>
                    <th scope="col">DATA</th>
                    <th scope="col">TIPO</th>
                    <th scope="col">QUANTIDADE</th>
                    <th scope="col">MOTIVO</th>
                  </tr>
                </thead>
                <tbody>
                  ${movements
                    .map(
                      (movement) =>
                        /* HTML */ `<tr>
                          <td>${escapeHtml(dateLabel(movement.created_at))}</td>
                          <td>${escapeHtml(movementLabels[movement.type] || movement.type)}</td>
                          <td>
                            ${movement.type === 'OUTBOUND' ? '−' : movement.quantity > 0 ? '+' : ''}${escapeHtml(movement.quantity)}
                          </td>
                          <td class="admin-name">${escapeHtml(movement.reason || '—')}</td>
                        </tr>`,
                    )
                    .join('')}
                </tbody>
              </table>
            </div>`
          : '<p class="admin-form-help">Ainda não há movimentações registradas para este produto.</p>'
      }
    </section>`;
}

export function orderForm(order) {
  const address = order.shipping_address;
  const payment = paymentLabel(order.payment_method);
  const next = allowedOrderTransitions(order);
  const addressMarkup = address
    ? /* HTML */ `${escapeHtml(address.street)},
        ${escapeHtml(address.number)}${address.complement ? ` · ${escapeHtml(address.complement)}` : ''}<br />${escapeHtml(address.city)}
        / ${escapeHtml(address.state)}<br />CEP ${escapeHtml(address.postal_code)}`
    : 'Endereço não disponível neste registro.';
  return /* HTML */ `<div class="admin-detail-grid d-grid">
      <section class="admin-detail-card">
        <h3>Pedido #${escapeHtml(order.id)}</h3>
        ${badge(order.status)}
        <p>
          Cliente #${escapeHtml(order.user_id)}<br />${escapeHtml(dateLabel(order.created_at))}<br />Pagamento:
          ${escapeHtml(payment)}
        </p>
      </section>
      <section class="admin-detail-card">
        <h3>Endereço da compra</h3>
        <p>${addressMarkup}</p>
      </section>
    </div>
    ${orderItems(order)} ${orderHistory(order)} ${orderProgress(next)}`;
}

function orderItems(order) {
  return /* HTML */ `<section class="admin-form-section">
    <h3>Produtos do pedido</h3>
    <div
      class="admin-table-scroll position-relative overflow-x-auto table-responsive"
      tabindex="0"
      role="region"
      aria-label="Itens do pedido"
    >
      <table class="admin-table w-100 text-start table table-hover mb-0">
        <thead>
          <tr>
            <th scope="col">PRODUTO</th>
            <th scope="col">QTD.</th>
            <th scope="col">UNITÁRIO</th>
            <th scope="col">SUBTOTAL</th>
          </tr>
        </thead>
        <tbody>
          ${(order.items || [])
            .map(
              (item) =>
                /* HTML */ `<tr>
                  <td class="admin-name">
                    <strong>${escapeHtml(item.product_name_snapshot)}</strong
                    ><small>${escapeHtml(item.category_name_snapshot)}</small>
                  </td>
                  <td>${escapeHtml(item.quantity)}</td>
                  <td>${money(item.unit_price)}</td>
                  <td>
                    ${item.line_total !== undefined ? money(item.line_total) : moneyFromCents(cents(item.unit_price) * BigInt(item.quantity))}
                  </td>
                </tr>`,
            )
            .join('')}
        </tbody>
      </table>
    </div>
    <div class="admin-detail-totals">
      <p>Produtos <span>${money(order.products_total)}</span></p>
      <p>Frete <span>${money(order.shipping_total)}</span></p>
      <p>Total <strong>${money(order.total)}</strong></p>
    </div>
  </section>`;
}

function orderHistory(order) {
  const events =
    (order.status_history || [])
      .map(
        (event) =>
          /* HTML */ `<li>
            ${badge(event.status)}<time>${escapeHtml(dateLabel(event.changed_at))}</time>
          </li>`,
      )
      .join('') || '<li>Nenhum evento disponível.</li>';
  return /* HTML */ `<section class="admin-form-section">
    <h3>Histórico do pedido</h3>
    <ul class="admin-history-list p-0">
      ${events}
    </ul>
  </section>`;
}

function orderProgress(next) {
  const statusField = select(
    'status',
    'Próxima etapa',
    [['', 'Selecione uma ação'], ...next.map((status) => [status, statusLabels[status]])],
    '',
    'required',
  );
  return /* HTML */ `<section class="admin-form-section">
    <h3>Andamento do pedido</h3>
    ${
      next.length
        ? /* HTML */ `${statusField}
            <p class="admin-form-help" id="admin-order-notice">
              Selecione a próxima etapa e revise antes de confirmar.
            </p>`
        : '<p class="admin-form-help">Este pedido está encerrado e não permite novas transições.</p>'
    }
  </section>`;
}
