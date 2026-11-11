const storageKey = 'poczy.learning-notice.v1';

export function showLearningNotice() {
  if (document.getElementById('learning-notice')) return;

  try {
    if (sessionStorage.getItem(storageKey) === 'dismissed') return;
  } catch {
    // O aviso continua disponível quando o navegador bloqueia o armazenamento.
  }

  const dialog = document.createElement('dialog');
  dialog.id = 'learning-notice';
  dialog.className = 'learning-notice';
  dialog.setAttribute('aria-labelledby', 'learning-notice-title');
  dialog.setAttribute('aria-describedby', 'learning-notice-purpose learning-notice-data');
  dialog.innerHTML = `
    <span class="learning-notice-label">PROJETO EDUCACIONAL</span>
    <h2 id="learning-notice-title">Este site é para testes e aprendizado</h2>
    <p id="learning-notice-purpose">
      A Poczy Hardware é uma demonstração. Compras e pagamentos são simulados,
      sem venda ou entrega de produtos.
    </p>
    <p id="learning-notice-data" class="learning-notice-warning">
      <strong>Use apenas dados fictícios.</strong>
      Não informe nome, e-mail, telefone, CPF, endereço ou dados de cartão reais.
      Não reutilize senhas de outros serviços.
    </p>
    <div class="learning-notice-actions">
      <button type="button" autofocus>Entendi, vou usar dados fictícios</button>
      <a href="/#terms-panel">Ler termos de uso</a>
    </div>
  `;

  function dismiss() {
    try {
      sessionStorage.setItem(storageKey, 'dismissed');
    } catch {
      // Sem armazenamento, o aviso será exibido novamente na próxima página.
    }
    dialog.close();
  }

  dialog.querySelector('button').addEventListener('click', dismiss);
  dialog.querySelector('a').addEventListener('click', dismiss);
  dialog.addEventListener('cancel', (event) => {
    event.preventDefault();
    dismiss();
  });
  dialog.addEventListener('close', () => dialog.remove(), { once: true });
  document.body.append(dialog);
  dialog.showModal();
}
