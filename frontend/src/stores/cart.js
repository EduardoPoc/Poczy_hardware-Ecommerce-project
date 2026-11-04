import { computed, reactive, readonly } from 'vue';
import * as api from '../api/cart.js';
import { ApiError, errorMessage } from '../api/http.js';

const state = reactive({ cart: null, loading: false, saving: false, error: '', uncertain: false });
let loadingPromise;

function acceptCart(cart) {
  if (!Array.isArray(cart?.items)) throw new ApiError('Não foi possível confirmar o carrinho.');
  // A sessão identifica o carrinho; credenciais não devem fazer parte do estado visual.
  const { cart_token, ...details } = cart;
  state.cart = details;
}

export async function loadCart({ force = false } = {}) {
  if (loadingPromise) return loadingPromise;
  if (state.cart && !force) return true;

  state.loading = true;
  state.error = '';
  loadingPromise = (async () => {
    try {
      acceptCart(await api.getCart());
      state.uncertain = false;
      return true;
    } catch (error) {
      state.error = errorMessage(error);
      return false;
    } finally {
      state.loading = false;
      loadingPromise = null;
    }
  })();

  return loadingPromise;
}

async function changeCart(operation) {
  // Serializa escritas e exige atualização após uma resposta perdida para evitar alterações duplicadas.
  if (state.saving || state.uncertain) return false;
  state.saving = true;
  state.error = '';

  try {
    if (!(await loadCart())) return false;
    acceptCart(await operation());
    return true;
  } catch (error) {
    state.uncertain = error.status === 0 || !error.status;
    state.error = state.uncertain
      ? 'Não foi possível confirmar a alteração. Atualize o carrinho antes de tentar novamente.'
      : errorMessage(error);
    return false;
  } finally {
    state.saving = false;
  }
}

export function useCart() {
  return {
    state: readonly(state),
    items: computed(() => state.cart?.items || []),
    quantity: computed(() => state.cart?.items_quantity || 0),
    subtotal: computed(() => state.cart?.products_total || '0.00'),
    load: loadCart,
    add: (id, quantity) => changeCart(() => api.addCartItem(id, quantity)),
    update: (id, quantity) => changeCart(() => api.updateCartItem(id, quantity)),
    remove: (id) => changeCart(() => api.removeCartItem(id)),
  };
}
