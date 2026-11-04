import { apiRequest, ApiError } from './session.js';
import { allRecords, loadAdminData } from './admin-api.js';
import { escapeHtml as e, orderTransitions, statusLabels, objectValues } from './admin-utils.js';
import { sections, overview, listing, listingResults } from './admin-views.js';
import {
  productForm,
  categoryForm,
  brandForm,
  stockForm,
  movementHistory,
  orderForm,
  attributeRow,
  specificationFields,
  readPayload,
} from './admin-forms.js';

const demo = new URLSearchParams(location.search).get('demo') === '1';
const app = document.getElementById('admin-app');
const gate = document.getElementById('admin-gate');
const content = document.getElementById('admin-content');
const editor = document.getElementById('admin-editor');
const form = document.getElementById('admin-form');
const fields = document.getElementById('admin-form-fields');
const saveButton = document.getElementById('admin-save');
const deleteButton = document.getElementById('admin-delete');
const confirmDialog = document.getElementById('admin-confirm');
const feedback = document.getElementById('admin-feedback');
const mobile = window.matchMedia('(max-width: 900px)');
let data = { products: [], categories: [], brands: [], orders: [] };
let user = null;
let demoModule;
let section = 'overview';
let filters = { q: '', status: '', category: '' };
let page = 1;
let loading = false;
let saving = false;
let uncertain = false;
let activeEditor = null;
let lastEditorTrigger = null;

function announce(message, error = false) {
  feedback.textContent = message;
  feedback.classList.toggle('error', error);
  feedback.classList.toggle('alert-danger', error);
  feedback.classList.toggle('alert-info', !error);
  feedback.hidden = false;
}

function showGate(title, copy) {
  if (editor.open) editor.close();
  if (confirmDialog.open) confirmDialog.close();
  app.hidden = true;
  gate.hidden = false;
  data = { products: [], categories: [], brands: [], orders: [] };
  content.replaceChildren();
  document.getElementById('admin-gate-title').textContent = title;
  document.getElementById('admin-gate-copy').textContent = copy;
  document.getElementById('admin-gate-actions').hidden = false;
}

function accessFailure(error) {
  if (error.status === 401 || error.status === 419) {
    showGate(
      'Entre para continuar',
      'Sua sessão não está ativa. Acesse com uma conta administradora para gerenciar a loja.',
    );
    return true;
  }
  if (error.status === 403) {
    showGate(
      'Acesso restrito',
      'Esta área está disponível apenas para administradores da Poczy Hardware.',
    );
    return true;
  }
  return false;
}

function setMenu(open) {
  const sidebar = document.getElementById('admin-sidebar');
  sidebar.classList.toggle('is-open', open);
  sidebar.inert = mobile.matches && !open;
  document.querySelector('.admin-main').inert = mobile.matches && open;
  document.getElementById('admin-menu-toggle').setAttribute('aria-expanded', String(open));
  document.getElementById('admin-menu-overlay').hidden = !open || !mobile.matches;
  document
    .getElementById('admin-menu-toggle')
    .setAttribute('aria-label', open ? 'Fechar menu' : 'Abrir menu');
}

function renderResults() {
  const result = listingResults(section, data, filters, page);
  page = result.page;
  document.getElementById('admin-results').innerHTML = result.html;
}

function renderSection({ focus = false, reset = false } = {}) {
  const requested = location.hash.slice(1);
  const next = Object.hasOwn(sections, requested) ? requested : 'overview';
  if (reset || next !== section) {
    filters = { q: '', status: '', category: '' };
    page = 1;
  }
  section = next;
  const meta = sections[section];
  document.getElementById('admin-title').textContent = meta.title;
  document.getElementById('admin-location').textContent = meta.title;
  document.getElementById('admin-description').textContent = meta.description;
  document.getElementById('admin-live-notice').hidden =
    demo || !['products', 'categories', 'brands', 'stock'].includes(section);
  document.title = `${meta.title} — Administração Poczy`;
  document.querySelectorAll('[data-section]').forEach((link) => {
    if (link.dataset.section === section) link.setAttribute('aria-current', 'page');
    else link.removeAttribute('aria-current');
  });
  const create = document.getElementById('admin-create');
  create.hidden = !meta.create;
  if (meta.create)
    create.innerHTML = `<i class="bi bi-plus-lg" aria-hidden="true"></i> ${meta.create}`;
  document.getElementById('admin-orders-count').textContent = String(
    data.orders.filter((order) => ['PENDING', 'PAID', 'SHIPPED'].includes(order.status)).length,
  );
  content.innerHTML = section === 'overview' ? overview(data) : listing(section, data);
  if (section !== 'overview') {
    document.getElementById('admin-search').value = filters.q;
    const status = document.getElementById('admin-status-filter');
    const category = document.getElementById('admin-category-filter');
    if (status) status.value = filters.status;
    if (category) category.value = filters.category;
    renderResults();
  }
  setMenu(false);
  if (focus) document.getElementById('admin-title').focus();
}

async function reloadData(initial = false) {
  if (loading) return false;
  loading = true;
  document.getElementById('admin-refresh').disabled = true;
  document.getElementById('admin-create').disabled = true;
  content.setAttribute('aria-busy', 'true');
  try {
    const nextData = demo ? demoModule.demoData : await loadAdminData();
    data = nextData;
    uncertain = false;
    renderSection();
    document.getElementById('admin-sync').textContent = demo
      ? 'Dados de demonstração · Somente consulta'
      : `Atualizado às ${new Intl.DateTimeFormat('pt-BR', { timeStyle: 'short' }).format(new Date())}`;
    return true;
  } catch (error) {
    if (!accessFailure(error)) {
      if (initial)
        showGate(
          'Não foi possível abrir o painel',
          'Não conseguimos carregar os dados da loja. Verifique a conexão e tente novamente.',
        );
      else {
        announce(
          `${error.message} Os dados anteriores continuam na tela; atualize antes de fazer outra alteração.`,
          true,
        );
        document.getElementById('admin-sync').textContent = 'Falha na atualização';
        uncertain = true;
      }
    }
    return false;
  } finally {
    loading = false;
    content.setAttribute('aria-busy', 'false');
    document.getElementById('admin-refresh').disabled = false;
    document.getElementById('admin-create').disabled = false;
  }
}

async function boot() {
  document.getElementById('admin-gate-actions').hidden = true;
  document.getElementById('admin-gate-title').textContent = 'Verificando seu acesso';
  document.getElementById('admin-gate-copy').textContent =
    'Aguarde enquanto verificamos sua sessão.';
  try {
    if (demo) {
      demoModule = await import('../data/admin-demo.js');
      user = { name: 'Prévia da loja', role: 'DEMO' };
    } else {
      const response = await apiRequest('/api/user');
      user = response?.data ?? response;
      if (user?.role !== 'ADMIN') {
        showGate(
          'Acesso restrito',
          'Esta área está disponível apenas para administradores da Poczy Hardware.',
        );
        return;
      }
    }
    if (!(await reloadData(true))) return;
    document.getElementById('admin-user-name').textContent = user.name;
    document.getElementById('admin-avatar').textContent = user.name
      .split(/\s+/)
      .slice(0, 2)
      .map((word) => word[0])
      .join('')
      .toUpperCase();
    document.getElementById('admin-user-role').textContent = demo
      ? 'Demonstração'
      : 'Administrador';
    document.getElementById('admin-demo-banner').hidden = !demo;
    document.getElementById('admin-logout').hidden = demo;
    gate.hidden = true;
    app.hidden = false;
    setMenu(false);
  } catch (error) {
    if (!accessFailure(error))
      showGate(
        'Não foi possível conectar à loja',
        'Verifique se a loja está disponível e tente novamente. Você também pode conhecer a demonstração do painel.',
      );
  }
}

function formError(error) {
  const element = document.getElementById('admin-form-error');
  const messages = Object.entries(error.errors || {}).flatMap(([key, values]) => {
    const field = form.elements.namedItem(key);
    if (field instanceof HTMLElement) field.setAttribute('aria-invalid', 'true');
    return (Array.isArray(values) ? values : [values]).map((message) => `<li>${e(message)}</li>`);
  });
  element.innerHTML = `${e(error.message)}${messages.length ? `<ul>${messages.join('')}</ul>` : ''}`;
  element.hidden = false;
  element.focus();
}

function setSaving(busy) {
  saving = busy;
  fields.disabled = busy;
  form.setAttribute('aria-busy', String(busy));
  document.querySelectorAll('[data-close-editor]').forEach((button) => {
    button.disabled = busy;
  });
  saveButton.disabled = busy || demo || uncertain;
  deleteButton.disabled = busy || demo || uncertain;
  if (activeEditor?.kind === 'order')
    saveButton.textContent = busy ? 'Atualizando…' : 'Atualizar pedido';
  else if (activeEditor?.kind === 'stock')
    saveButton.textContent = busy ? 'Registrando…' : 'Registrar movimentação';
  else saveButton.textContent = busy ? 'Salvando…' : 'Salvar alterações';
}

function refreshSpecificationFields() {
  const category = data.categories.find(
    (entry) => String(entry.id) === form.elements.category_id.value,
  );
  const values = Object.fromEntries(
    [...fields.querySelectorAll('[name^="specifications."]')]
      .filter((field) => field.value !== '')
      .map((field) => [field.name.slice('specifications.'.length), field.value]),
  );
  document.getElementById('admin-spec-fields').innerHTML = specificationFields(
    category,
    Object.keys(values).length ? values : activeEditor.record?.specifications || {},
  );
}

function stockPreview() {
  const quantity = Number(form.elements.quantity.value);
  const type = form.elements.type.value;
  const input = form.elements.quantity;
  input.min = type === 'ADJUSTMENT' ? '-2147483647' : '1';
  if (type !== 'ADJUSTMENT' && quantity < 1) input.value = '1';
  const delta = Number(input.value) * (type === 'OUTBOUND' ? -1 : 1);
  const next = activeEditor.record.stock + delta;
  document.getElementById('admin-stock-preview').textContent =
    type === 'ADJUSTMENT'
      ? `O ajuste soma ou subtrai do saldo atual. Prévia do saldo: ${next} unidades.`
      : `Prévia do saldo após a ${type === 'OUTBOUND' ? 'saída' : 'entrada'}: ${next} unidades.`;
}

async function openEditor(kind, id = null) {
  if (loading || saving || editor.open || confirmDialog.open) return;
  lastEditorTrigger = document.activeElement;
  document.getElementById('admin-form-error').hidden = true;
  const resource =
    kind === 'stock' || kind === 'history' ? 'products' : kind === 'order' ? 'orders' : kind;
  let record = null;
  let movements = [];
  loading = true;
  try {
    if (id !== null) {
      const local = data[resource].find((entry) => String(entry.id) === String(id));
      if (!local) throw new Error('Registro não encontrado. Atualize os dados.');
      record = demo
        ? local
        : (await apiRequest(`/api/admin/${resource}/${encodeURIComponent(id)}`)).data;
      if (!record) throw new Error('Não foi possível confirmar os dados do registro.');
      if (kind === 'history')
        movements = demo
          ? demoModule.demoMovements(record.id)
          : await allRecords(`/api/admin/products/${encodeURIComponent(id)}/stock-movements`);
    }
    activeEditor = { kind, resource, record };
    form.reset();
    const titles = {
      products: record ? 'Editar produto' : 'Novo produto',
      categories: record ? 'Editar categoria' : 'Nova categoria',
      brands: record ? 'Editar marca' : 'Nova marca',
      stock: 'Movimentar estoque',
      history: 'Histórico de estoque',
      order: `Pedido #${record?.id}`,
    };
    document.getElementById('admin-editor-title').textContent = titles[kind];
    document.getElementById('admin-editor-eyebrow').textContent = demo
      ? 'DEMONSTRAÇÃO · SOMENTE CONSULTA'
      : 'GERENCIAR LOJA';
    const notice = document.getElementById('admin-form-notice');
    notice.hidden = !demo && !uncertain;
    notice.textContent = demo
      ? 'Você pode explorar os campos. Para salvar alterações reais, acesse com uma conta administradora.'
      : 'Atualize os dados da loja antes de fazer outra alteração.';
    const builders = {
      products: () => productForm(record, data),
      categories: () => categoryForm(record),
      brands: () => brandForm(record),
      stock: () => stockForm(record),
      history: () => movementHistory(record, movements),
      order: () => orderForm(record),
    };
    fields.innerHTML = builders[kind]();
    fields.disabled = false;
    saveButton.hidden =
      kind === 'history' || (kind === 'order' && !(orderTransitions[record.status] || []).length);
    deleteButton.hidden = demo || !record || !['products', 'categories', 'brands'].includes(kind);
    setSaving(false);
    if (kind === 'products') refreshSpecificationFields();
    if (kind === 'stock') stockPreview();
    editor.showModal();
  } catch (error) {
    if (!accessFailure(error)) {
      announce(error.message, true);
      feedback.focus();
    }
  } finally {
    loading = false;
  }
}

function closeEditor() {
  if (saving) return;
  editor.close();
  if (lastEditorTrigger?.isConnected && !lastEditorTrigger.disabled) lastEditorTrigger.focus();
}

async function submitEditor(event) {
  event.preventDefault();
  if (demo || saving || uncertain || !activeEditor || saveButton.hidden) return;
  if (!form.reportValidity()) return;
  let payload;
  try {
    payload = readPayload(activeEditor.kind, form, data, activeEditor.record);
  } catch (error) {
    formError(error);
    return;
  }
  document.getElementById('admin-form-error').hidden = true;
  const { kind, resource, record } = activeEditor;
  const path =
    kind === 'stock'
      ? `/api/admin/products/${record.id}/stock-movements`
      : `/api/admin/${resource}${record ? `/${record.id}` : ''}`;
  const method = kind === 'stock' || !record ? 'POST' : 'PATCH';
  setSaving(true);
  let success = false;
  try {
    await apiRequest(path, { method, data: payload });
    success = true;
    editor.close();
    const refreshed = await reloadData();
    if (refreshed)
      announce(
        kind === 'stock'
          ? 'Movimentação registrada. O saldo e o histórico foram atualizados.'
          : kind === 'order'
            ? 'Pedido atualizado com sucesso.'
            : 'Registro salvo com sucesso.',
      );
  } catch (error) {
    if (!accessFailure(error)) {
      if (error instanceof ApiError && error.status === 0) {
        uncertain = true;
        formError(
          new Error(
            'Não foi possível confirmar a resposta. A alteração pode ter sido aplicada. Feche esta janela e atualize os dados antes de tentar novamente.',
          ),
        );
      } else formError(error);
    }
  } finally {
    setSaving(false);
    if (success) document.getElementById('admin-title').focus();
  }
}

function showDelete() {
  if (demo || saving || uncertain || !activeEditor?.record) return;
  const name = activeEditor.record.name;
  document.getElementById('admin-confirm-copy').textContent =
    `Excluir “${name}”? Esta operação é permanente. Registros com vínculos ou histórico podem ter a exclusão impedida; nesse caso, desative o registro quando essa opção estiver disponível.`;
  document.getElementById('admin-confirm-error').hidden = true;
  document.getElementById('admin-confirm-submit').disabled = false;
  confirmDialog.showModal();
}

document.getElementById('admin-confirm-form').addEventListener('submit', async (event) => {
  event.preventDefault();
  if (demo || saving || uncertain || !activeEditor?.record) return;
  saving = true;
  document.getElementById('admin-confirm-submit').disabled = true;
  document.getElementById('admin-confirm-cancel').disabled = true;
  const { resource, record } = activeEditor;
  try {
    await apiRequest(`/api/admin/${resource}/${record.id}`, { method: 'DELETE' });
    confirmDialog.close();
    editor.close();
    const refreshed = await reloadData();
    if (refreshed) announce('Registro excluído com sucesso.');
    document.getElementById('admin-title').focus();
  } catch (error) {
    if (!accessFailure(error)) {
      const message = document.getElementById('admin-confirm-error');
      if (error instanceof ApiError && error.status === 0) uncertain = true;
      message.textContent = uncertain
        ? 'Não foi possível confirmar a exclusão. Feche esta janela e atualize os dados antes de tentar novamente.'
        : error.message;
      message.hidden = false;
      message.focus();
    }
  } finally {
    saving = false;
    document.getElementById('admin-confirm-submit').disabled = uncertain;
    document.getElementById('admin-confirm-cancel').disabled = false;
    setSaving(false);
  }
});

content.addEventListener('input', (event) => {
  if (event.target.id !== 'admin-search') return;
  filters.q = event.target.value;
  page = 1;
  renderResults();
});
content.addEventListener('change', (event) => {
  if (event.target.id === 'admin-category-filter') filters.category = event.target.value;
  else if (event.target.id === 'admin-status-filter') filters.status = event.target.value;
  else return;
  page = 1;
  renderResults();
});
content.addEventListener('click', (event) => {
  const pagination = event.target.closest('[data-page]');
  if (pagination && !pagination.disabled) {
    page = Number(pagination.dataset.page);
    renderResults();
    document.querySelector('.admin-pagination button:not(:disabled)')?.focus();
    return;
  }
  const button = event.target.closest('[data-action]');
  if (!button) return;
  const action = button.dataset.action;
  openEditor(action.startsWith('edit-') ? action.slice(5) : action, button.dataset.id);
});
fields.addEventListener('change', (event) => {
  if (event.target.name === 'category_id') refreshSpecificationFields();
  if (activeEditor?.kind === 'stock' && ['type', 'quantity'].includes(event.target.name))
    stockPreview();
  if (activeEditor?.kind === 'order' && event.target.name === 'status') {
    const status = event.target.value;
    document.getElementById('admin-order-notice').textContent =
      status === 'CANCELLED'
        ? 'O cancelamento preserva o histórico e devolve os itens ao estoque.'
        : status === 'PAID'
          ? 'O pagamento deste projeto é simulado. Confirme para marcar o pedido como pago.'
          : status
            ? `Confirme para alterar o pedido para “${statusLabels[status]}”.`
            : 'Selecione uma próxima etapa.';
  }
});
fields.addEventListener('click', (event) => {
  if (event.target.closest('#admin-add-attribute')) {
    const container = document.getElementById('admin-attributes');
    if (container.children.length >= 100) {
      formError(new Error('Use no máximo 100 atributos por categoria.'));
      return;
    }
    container.insertAdjacentHTML('beforeend', attributeRow());
    container.lastElementChild.querySelector('input').focus();
  }
  const remove = event.target.closest('[data-remove-attribute]');
  if (remove) {
    const row = remove.closest('[data-attribute-prefix]');
    const next = row.nextElementSibling || row.previousElementSibling;
    row.remove();
    if (next) next.querySelector('input').focus();
    else document.getElementById('admin-add-attribute').focus();
  }
});
form.addEventListener('input', (event) => event.target.removeAttribute('aria-invalid'));
form.addEventListener('submit', submitEditor);
editor.addEventListener('cancel', (event) => {
  if (saving) event.preventDefault();
});
confirmDialog.addEventListener('cancel', (event) => {
  if (saving) event.preventDefault();
});
document
  .querySelectorAll('[data-close-editor]')
  .forEach((button) => button.addEventListener('click', closeEditor));
deleteButton.addEventListener('click', showDelete);
document.getElementById('admin-confirm-cancel').addEventListener('click', () => {
  confirmDialog.close();
  deleteButton.focus();
});
document
  .getElementById('admin-create')
  .addEventListener('click', () => openEditor(sections[section].resource));
document.getElementById('admin-refresh').addEventListener('click', async () => {
  feedback.hidden = true;
  const updated = await reloadData();
  if (updated)
    announce(
      demo
        ? 'Demonstração atualizada. Os dados continuam ilustrativos.'
        : 'Dados atualizados com sucesso.',
    );
});
document.getElementById('admin-retry').addEventListener('click', boot);
document.getElementById('admin-logout').addEventListener('click', async (event) => {
  if (demo || saving) return;
  const button = event.currentTarget;
  button.disabled = true;
  try {
    await apiRequest('/api/logout', { method: 'POST', data: {} });
    location.replace('login.html');
  } catch (error) {
    if (!accessFailure(error)) announce(error.message, true);
    button.disabled = false;
  }
});
document.getElementById('admin-menu-toggle').addEventListener('click', () => {
  const open =
    document.getElementById('admin-menu-toggle').getAttribute('aria-expanded') !== 'true';
  setMenu(open);
  if (open) document.querySelector('[data-section][aria-current="page"]').focus();
});
document.getElementById('admin-menu-overlay').addEventListener('click', () => {
  setMenu(false);
  document.getElementById('admin-menu-toggle').focus();
});
document.addEventListener('keydown', (event) => {
  if (event.key === 'Escape' && !editor.open && !confirmDialog.open && mobile.matches) {
    setMenu(false);
    document.getElementById('admin-menu-toggle').focus();
  }
});
mobile.addEventListener('change', () => setMenu(false));
window.addEventListener('hashchange', () => {
  if (!app.hidden) {
    feedback.hidden = true;
    renderSection({ focus: true });
  }
});
boot();
