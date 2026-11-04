import { apiRequest, resourceData } from './http.js';

export async function currentUser() {
  try {
    const user = resourceData(await apiRequest('/api/user'));
    return typeof user?.name === 'string' && typeof user?.email === 'string' ? user : null;
  } catch {
    return null;
  }
}

export function submitSession(path, values) {
  return apiRequest(path, { method: 'POST', data: values });
}
