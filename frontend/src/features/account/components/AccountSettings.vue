<script setup>
import { computed, nextTick, reactive, ref, useId, watch } from 'vue';
import { changePassword, updateProfile } from '../api.js';
import { errorMessage } from '../../../shared/api/http.js';
import { profilePayload } from '../domain.js';

const props = defineProps({ user: { type: Object, required: true } });
const emit = defineEmits(['updated', 'password-changed', 'unauthorized']);
const prefix = useId();
const profileForm = ref(null);
const passwordForm = ref(null);
const profile = reactive({ name: '', email: '', current_password: '' });
const passwords = reactive({ current_password: '', password: '', password_confirmation: '' });
const savingProfile = ref(false);
const savingPassword = ref(false);
const profileError = ref('');
const passwordError = ref('');
const profileErrors = ref({});
const passwordErrors = ref({});
const feedback = ref('');
const showPasswords = ref(false);
const busy = computed(() => savingProfile.value || savingPassword.value);
const changes = computed(() => profilePayload(profile, props.user));
const emailChanged = computed(() => Object.hasOwn(changes.value, 'email'));
const profileInputs = computed(() => [
  { key: 'name', label: 'Nome completo', type: 'text', max: 100, autocomplete: 'name' },
  { key: 'email', label: 'E-mail', type: 'email', max: 150, autocomplete: 'email' },
  ...(emailChanged.value
    ? [
        {
          key: 'current_password',
          label: 'Senha atual',
          type: 'password',
          autocomplete: 'current-password',
          wide: true,
        },
      ]
    : []),
]);
const passwordInputs = [
  { key: 'current_password', label: 'Senha atual', autocomplete: 'current-password', wide: true },
  { key: 'password', label: 'Nova senha', autocomplete: 'new-password', min: 12, max: 72 },
  {
    key: 'password_confirmation',
    label: 'Confirmar nova senha',
    autocomplete: 'new-password',
    max: 72,
  },
];

watch(
  () => props.user,
  (user) => {
    profile.name = user.name;
    profile.email = user.email;
    profile.current_password = '';
  },
  { immediate: true },
);

function handleFailure(failure, errors, message) {
  if ([401, 419].includes(failure.status)) {
    emit('unauthorized');
    return;
  }
  errors.value = failure.errors || {};
  message.value = errorMessage(failure);
}

async function focusInvalidField(form) {
  await nextTick();
  form.value?.querySelector('[aria-invalid="true"]')?.focus();
}

async function saveProfile() {
  if (busy.value || !Object.keys(changes.value).length) return;
  savingProfile.value = true;
  profileError.value = '';
  profileErrors.value = {};
  feedback.value = '';
  try {
    const user = await updateProfile(changes.value);
    profile.current_password = '';
    feedback.value = 'Dados da conta atualizados com sucesso.';
    emit('updated', user);
  } catch (failure) {
    handleFailure(failure, profileErrors, profileError);
  } finally {
    savingProfile.value = false;
    await focusInvalidField(profileForm);
  }
}

async function savePassword() {
  if (busy.value) return;
  passwordError.value = '';
  passwordErrors.value = {};
  if (passwords.password !== passwords.password_confirmation) {
    passwordErrors.value = {
      password_confirmation: ['A confirmação deve ser igual à nova senha.'],
    };
    await focusInvalidField(passwordForm);
    return;
  }
  savingPassword.value = true;
  try {
    await changePassword({ ...passwords });
    for (const key of Object.keys(passwords)) passwords[key] = '';
    showPasswords.value = false;
    emit('password-changed');
  } catch (failure) {
    handleFailure(failure, passwordErrors, passwordError);
  } finally {
    savingPassword.value = false;
    await focusInvalidField(passwordForm);
  }
}
</script>

<template>
  <section class="customer-panel" :aria-labelledby="`${prefix}-profile-title`">
    <div class="customer-section-heading">
      <div>
        <h2 :id="`${prefix}-profile-title`">Dados da sua conta</h2>
        <p>Atualize seu nome e e-mail de acesso.</p>
      </div>
    </div>
    <p v-if="feedback" class="alert alert-info" role="status">{{ feedback }}</p>
    <p v-if="profileError" class="alert alert-danger" role="alert">{{ profileError }}</p>
    <form
      ref="profileForm"
      class="customer-account-form"
      :aria-busy="savingProfile"
      @submit.prevent="saveProfile"
    >
      <fieldset :disabled="busy" class="border-0 p-0 m-0">
        <legend class="visually-hidden">Editar dados da conta</legend>
        <div class="admin-form-grid d-grid">
          <div
            v-for="input in profileInputs"
            :key="input.key"
            class="admin-field d-flex flex-column gap-2"
            :class="{ wide: input.wide }"
          >
            <label :for="`${prefix}-profile-${input.key}`">{{ input.label }}</label>
            <input
              :id="`${prefix}-profile-${input.key}`"
              v-model="profile[input.key]"
              :name="input.key"
              :type="input.type"
              :maxlength="input.max"
              :autocomplete="input.autocomplete"
              :autocapitalize="input.key === 'email' ? 'none' : undefined"
              class="form-control"
              required
              :aria-invalid="Boolean(profileErrors[input.key])"
              :aria-describedby="
                profileErrors[input.key]
                  ? `${prefix}-profile-${input.key}-error`
                  : input.key === 'current_password'
                    ? `${prefix}-email-hint`
                    : undefined
              "
              @input="
                feedback = '';
                profileError = '';
                profileErrors = {};
              "
            />
            <small
              v-if="profileErrors[input.key]"
              :id="`${prefix}-profile-${input.key}-error`"
              class="customer-field-error"
            >
              {{ profileErrors[input.key].join(' ') }}
            </small>
            <small v-if="input.key === 'current_password'" :id="`${prefix}-email-hint`">
              Confirme sua senha atual para alterar o e-mail.
            </small>
          </div>
        </div>
        <div class="customer-actions">
          <button
            type="submit"
            class="admin-primary btn btn-primary"
            :disabled="!Object.keys(changes).length"
          >
            {{ savingProfile ? 'Salvando…' : 'Salvar alterações' }}
          </button>
        </div>
      </fieldset>
    </form>
  </section>
  <section class="customer-panel" :aria-labelledby="`${prefix}-password-title`">
    <div class="customer-section-heading">
      <div>
        <h2 :id="`${prefix}-password-title`">Alterar senha</h2>
        <p>Após salvar a nova senha, você precisará entrar novamente na sua conta.</p>
      </div>
    </div>
    <p v-if="passwordError" class="alert alert-danger" role="alert">{{ passwordError }}</p>
    <form
      ref="passwordForm"
      class="customer-account-form"
      :aria-busy="savingPassword"
      @submit.prevent="savePassword"
    >
      <fieldset :disabled="busy" class="border-0 p-0 m-0">
        <legend class="visually-hidden">Definir nova senha</legend>
        <div class="admin-form-grid d-grid">
          <div
            v-for="input in passwordInputs"
            :key="input.key"
            class="admin-field d-flex flex-column gap-2"
            :class="{ wide: input.wide }"
          >
            <label :for="`${prefix}-password-${input.key}`">{{ input.label }}</label>
            <input
              :id="`${prefix}-password-${input.key}`"
              v-model="passwords[input.key]"
              :name="input.key"
              :type="showPasswords ? 'text' : 'password'"
              :minlength="input.min"
              :maxlength="input.max"
              :autocomplete="input.autocomplete"
              class="form-control"
              required
              :aria-invalid="Boolean(passwordErrors[input.key])"
              :aria-describedby="
                passwordErrors[input.key]
                  ? `${prefix}-password-${input.key}-error`
                  : input.key === 'password'
                    ? `${prefix}-password-hint`
                    : undefined
              "
              @input="
                passwordError = '';
                passwordErrors = {};
              "
            />
            <small
              v-if="passwordErrors[input.key]"
              :id="`${prefix}-password-${input.key}-error`"
              class="customer-field-error"
            >
              {{ passwordErrors[input.key].join(' ') }}
            </small>
            <small v-if="input.key === 'password'" :id="`${prefix}-password-hint`">
              Use pelo menos 12 caracteres, com letras maiúsculas, minúsculas, número e símbolo.
            </small>
          </div>
        </div>
        <label class="customer-password-visibility">
          <input v-model="showPasswords" type="checkbox" /> Mostrar senhas
        </label>
        <div class="customer-actions">
          <button type="submit" class="admin-primary btn btn-primary">
            {{ savingPassword ? 'Alterando…' : 'Alterar senha' }}
          </button>
        </div>
      </fieldset>
    </form>
  </section>
</template>
