export const states = [
  'AC',
  'AL',
  'AP',
  'AM',
  'BA',
  'CE',
  'DF',
  'ES',
  'GO',
  'MA',
  'MT',
  'MS',
  'MG',
  'PA',
  'PB',
  'PR',
  'PE',
  'PI',
  'RJ',
  'RN',
  'RS',
  'RO',
  'RR',
  'SC',
  'SP',
  'SE',
  'TO',
];

export function profilePayload(values, user) {
  const data = {};
  const name = String(values.name ?? '').trim();
  const email = String(values.email ?? '')
    .trim()
    .toLowerCase();
  if (name !== user.name) data.name = name;
  if (email !== user.email.toLowerCase()) {
    data.email = email;
    data.current_password = values.current_password;
  }
  return data;
}

export function addressPayload(values) {
  const data = Object.fromEntries(
    ['postal_code', 'street', 'number', 'complement', 'city', 'state'].map((key) => [
      key,
      String(values[key] ?? '').trim(),
    ]),
  );
  data.state = data.state.toUpperCase();
  data.complement ||= null;
  return data;
}
