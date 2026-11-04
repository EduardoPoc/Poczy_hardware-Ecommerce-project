import { ApiError, currentUser, submitSession } from './session.js';

const isRegister = document.body.dataset.authMode === 'register';
const form = document.getElementById('auth-form');
const submitButton = document.getElementById('auth-submit');
const feedback = document.getElementById('auth-feedback');
const result = document.getElementById('auth-result');
const logoutButton = document.getElementById('auth-logout');
const fields = [...form.querySelectorAll('input')];
const password = form.elements.password;
const initialSubmitLabel = submitButton.querySelector('span').textContent;
let hasInteracted = false;
let submitting = false;
let completed = false;

form.addEventListener('input', () => {
  hasInteracted = true;
});

document.querySelectorAll('[data-password-toggle]').forEach((button) => {
  button.hidden = false;
  button.addEventListener('click', () => {
    const input = document.getElementById(button.dataset.passwordToggle);
    const visible = input.type === 'password';
    input.type = visible ? 'text' : 'password';
    button.setAttribute('aria-pressed', String(visible));
    button.setAttribute(
      'aria-label',
      `${visible ? 'Ocultar' : 'Mostrar'} ${input.name === 'password_confirmation' ? 'confirmação de senha' : 'senha'}`,
    );
    button.querySelector('i').className = `bi ${visible ? 'bi-eye-slash' : 'bi-eye'}`;
  });
});

function passwordRules(value) {
  return {
    length: [...value].length >= 12,
    case: /\p{Lu}/u.test(value) && /\p{Ll}/u.test(value),
    symbol: /[0-9]/.test(value) && /[^\p{L}\p{N}\s]/u.test(value),
  };
}

function fieldMessage(input) {
  if (!input.value || (input.name === 'name' && !input.value.trim())) return 'Preencha este campo.';
  if (input.name === 'email' && input.validity.typeMismatch)
    return 'Informe um e-mail válido, como voce@exemplo.com.';
  if (input.maxLength > 0 && input.value.length > input.maxLength)
    return `Use no máximo ${input.maxLength} caracteres.`;
  if (isRegister && input.name === 'password') {
    if (!Object.values(passwordRules(input.value)).every(Boolean))
      return 'Use pelo menos 12 caracteres, com maiúscula, minúscula, número e símbolo.';
    if (new TextEncoder().encode(input.value).length > 72)
      return 'A senha deve ocupar no máximo 72 bytes. Reduza os caracteres acentuados ou o tamanho.';
    if (input.value.includes('\0')) return 'Remova o caractere inválido da senha.';
  }
  if (input.name === 'password_confirmation' && input.value !== password.value)
    return 'As senhas precisam ser iguais.';
  return '';
}

function setFieldError(input, message) {
  const error = document.getElementById(`${input.name}-error`);
  error.textContent = message;
  error.hidden = !message;
  if (message) input.setAttribute('aria-invalid', 'true');
  else input.removeAttribute('aria-invalid');
}

function showFeedback(message) {
  feedback.textContent = message;
  feedback.hidden = false;
  feedback.focus();
}

function setBusy(busy) {
  submitting = busy;
  form.setAttribute('aria-busy', String(busy));
  submitButton.disabled = busy;
  submitButton.querySelector('span').textContent = busy
    ? isRegister
      ? 'Criando sua conta…'
      : 'Entrando…'
    : initialSubmitLabel;
  fields.forEach((input) => {
    input.readOnly = busy;
  });
}

function showResult(user, registered = false, focus = true) {
  completed = true;
  document.getElementById('auth-form-view').hidden = true;
  result.hidden = false;
  const firstName = user.name.trim().split(/\s+/)[0];
  document.getElementById('auth-result-title').textContent = registered
    ? 'Sua conta está pronta!'
    : `Olá, ${firstName}!`;
  document.getElementById('auth-result-copy').textContent = registered
    ? 'Bem-vindo à Poczy. Agora é só entrar com seu e-mail e senha para continuar.'
    : 'Você está conectado à Poczy. Tudo pronto para encontrar seu próximo upgrade.';
  const link = document.getElementById('auth-result-link');
  link.href = registered ? 'login.html' : user.role === 'ADMIN' ? 'admin.html' : 'index.html';
  link.replaceChildren(
    document.createTextNode(
      registered
        ? 'Entrar na minha conta '
        : user.role === 'ADMIN'
          ? 'Abrir painel administrativo '
          : 'Continuar na loja ',
    ),
  );
  const arrow = document.createElement('i');
  arrow.className = 'bi bi-arrow-right';
  arrow.setAttribute('aria-hidden', 'true');
  link.append(arrow);
  logoutButton.hidden = registered;
  if (focus) result.focus();
  form.reset();
  form.querySelectorAll('[data-password-toggle]').forEach((button) => {
    const input = document.getElementById(button.dataset.passwordToggle);
    input.type = 'password';
    button.setAttribute('aria-pressed', 'false');
    button.setAttribute(
      'aria-label',
      `Mostrar ${input.name === 'password_confirmation' ? 'confirmação de senha' : 'senha'}`,
    );
    button.querySelector('i').className = 'bi bi-eye';
  });
}

fields.forEach((input) => {
  input.addEventListener('blur', () => setFieldError(input, fieldMessage(input)));
  input.addEventListener('input', () => {
    feedback.hidden = true;
    if (input.hasAttribute('aria-invalid')) setFieldError(input, fieldMessage(input));
    if (input === password) {
      const confirmation = form.elements.password_confirmation;
      if (confirmation?.hasAttribute('aria-invalid'))
        setFieldError(confirmation, fieldMessage(confirmation));
    }
  });
});

if (isRegister)
  password.addEventListener('input', () => {
    for (const [key, valid] of Object.entries(passwordRules(password.value))) {
      const item = document.querySelector(`[data-password-rule="${key}"]`);
      item.classList.toggle('is-valid', valid);
      item.querySelector('i').className = `bi ${valid ? 'bi-check-circle-fill' : 'bi-circle'}`;
    }
  });

form.addEventListener('submit', async (event) => {
  event.preventDefault();
  if (submitting || completed) return;
  hasInteracted = true;
  feedback.hidden = true;
  form.elements.email.value = form.elements.email.value.trim();
  if (isRegister) form.elements.name.value = form.elements.name.value.trim();
  fields.forEach((input) => setFieldError(input, fieldMessage(input)));
  const firstInvalid = fields.find((input) => input.getAttribute('aria-invalid') === 'true');
  if (firstInvalid) {
    firstInvalid.focus();
    return;
  }

  setBusy(true);
  try {
    const values = Object.fromEntries(fields.map((input) => [input.name, input.value]));
    values.email = values.email.toLowerCase();
    const response = await submitSession(isRegister ? '/api/user' : '/api/login', values);
    const user = response?.data ?? response;
    if (typeof user?.name !== 'string')
      throw new ApiError('Não foi possível confirmar seu acesso. Tente novamente.', 0);
    showResult(user, isRegister);
  } catch (error) {
    if (error instanceof ApiError) {
      let hasFieldErrors = false;
      for (const [name, messages] of Object.entries(error.errors || {})) {
        const input = fields.find((field) => field.name === name);
        if (input) {
          setFieldError(input, Array.isArray(messages) ? messages.join(' ') : String(messages));
          hasFieldErrors = true;
        }
      }
      if (hasFieldErrors) fields.find((input) => input.hasAttribute('aria-invalid')).focus();
      else showFeedback(error.message);
    } else showFeedback('Não foi possível concluir. Tente novamente em instantes.');
  } finally {
    setBusy(false);
  }
});

logoutButton.addEventListener('click', async () => {
  const message = document.getElementById('auth-result-feedback');
  message.hidden = true;
  logoutButton.disabled = true;
  logoutButton.textContent = 'Saindo…';
  try {
    await submitSession('/api/logout', {});
    window.location.replace('login.html');
  } catch (error) {
    message.textContent = error.message;
    message.hidden = false;
    logoutButton.disabled = false;
    logoutButton.textContent = 'Sair da conta';
  }
});

currentUser().then((user) => {
  if (user && !hasInteracted && !submitting && !completed) showResult(user, false, false);
});
