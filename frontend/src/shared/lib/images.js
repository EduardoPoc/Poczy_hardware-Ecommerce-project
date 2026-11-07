export function imageUrl(value) {
  if (!value || typeof value !== 'string') return '';
  try {
    const base = import.meta.env?.VITE_API_URL || globalThis.location?.origin;
    const url = new URL(value, base);
    return ['http:', 'https:'].includes(url.protocol) ? url.href : '';
  } catch {
    return '';
  }
}
