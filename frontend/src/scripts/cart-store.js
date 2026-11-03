import { catalog } from '../data/catalog.js';

const storageKey = 'poczy-cart';
const products = new Map(catalog.map(product => [product.id, product]));
const subscribers = new Set();
export const MAX_CART_QUANTITY = 10;
let cart = [];

function readCart() {
  try {
    const saved = JSON.parse(localStorage.getItem(storageKey) || '[]');
    const quantities = new Map();
    if (!Array.isArray(saved)) return [];
    for (const item of saved) {
      if (!item || !products.has(item.id) || !Number.isInteger(item.quantity) || item.quantity < 1) continue;
      quantities.set(item.id, Math.min(MAX_CART_QUANTITY, (quantities.get(item.id) || 0) + item.quantity));
    }
    return [...quantities].map(([id, quantity]) => ({ id, quantity }));
  } catch {
    return null;
  }
}

function emitChange() { subscribers.forEach(callback => callback()); }

function refreshCart() {
  const saved = readCart();
  if (saved !== null) { cart = saved; emitChange(); }
}

function commit(nextCart) {
  cart = nextCart;
  let persisted = true;
  try { localStorage.setItem(storageKey, JSON.stringify(cart)); }
  catch { persisted = false; }
  emitChange();
  return { ok: true, persisted };
}

export const getCartItems = () => cart.map(item => ({ ...item, product: products.get(item.id) }));
export const getCartCount = () => cart.reduce((sum, item) => sum + item.quantity, 0);
export const getCartSubtotal = () => cart.reduce((sum, item) => sum + Math.round(products.get(item.id).price * 100) * item.quantity, 0) / 100;

export function addCartItem(id, quantity = 1) {
  if (!products.has(id) || !Number.isInteger(quantity) || quantity < 1) return { ok: false, reason: 'invalid' };
  const existing = cart.find(item => item.id === id);
  if ((existing?.quantity || 0) + quantity > MAX_CART_QUANTITY) return { ok: false, reason: 'limit' };
  return commit(existing
    ? cart.map(item => item.id === id ? { id, quantity: item.quantity + quantity } : item)
    : [...cart, { id, quantity }]);
}

export function setCartQuantity(id, quantity) {
  if (!cart.some(item => item.id === id) || !Number.isInteger(quantity) || quantity < 1 || quantity > MAX_CART_QUANTITY) return { ok: false, reason: 'invalid' };
  return commit(cart.map(item => item.id === id ? { id, quantity } : item));
}

export const removeCartItem = id => commit(cart.filter(item => item.id !== id));
export function subscribeCart(callback) {
  subscribers.add(callback);
  return () => subscribers.delete(callback);
}

refreshCart();
window.addEventListener('pageshow', refreshCart);
window.addEventListener('storage', event => {
  if (event.key === storageKey || event.key === null) refreshCart();
});
