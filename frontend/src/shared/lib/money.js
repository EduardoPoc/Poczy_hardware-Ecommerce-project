export function cents(value = '0') {
  const match = String(value ?? '0').match(/^(-?)(\d+)(?:\.(\d{1,2}))?$/);
  if (!match) throw new Error('Valor monetário inválido.');
  return (
    (BigInt(match[2]) * 100n + BigInt((match[3] || '').padEnd(2, '0'))) * (match[1] ? -1n : 1n)
  );
}

export function moneyFromCents(value) {
  const absolute = value < 0n ? -value : value;
  return `${value < 0n ? '-' : ''}R$ ${(absolute / 100n).toLocaleString('pt-BR')},${String(absolute % 100n).padStart(2, '0')}`;
}

export function money(value) {
  return moneyFromCents(cents(typeof value === 'number' ? value.toFixed(2) : value));
}
