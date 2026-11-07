const storageKey = 'poczy-guest-cart-token';
let memoryToken = null;

export function guestCartToken() {
  try {
    memoryToken = localStorage.getItem(storageKey);
  } catch {
    // Browsers may disable persistent storage; the current tab still works.
  }
  return typeof memoryToken === 'string' && /^[a-f0-9]{64}$/.test(memoryToken) ? memoryToken : null;
}

export function rememberGuestCartToken(token) {
  if (typeof token !== 'string' || !/^[a-f0-9]{64}$/.test(token)) return;
  memoryToken = token;
  try {
    localStorage.setItem(storageKey, token);
  } catch {
    // Keep the token in memory when storage is unavailable.
  }
}

export function forgetGuestCartToken() {
  memoryToken = null;
  try {
    localStorage.removeItem(storageKey);
  } catch {
    // The session binding also protects carts when storage is unavailable.
  }
}
