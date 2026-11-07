export function accountDestination(value, fallback = '/conta') {
  return ['/checkout', '/conta', '/carrinho'].includes(value) ? value : fallback;
}
