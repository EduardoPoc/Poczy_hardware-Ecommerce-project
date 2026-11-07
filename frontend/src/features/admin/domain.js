export const movementLabels = { INBOUND: 'Entrada', OUTBOUND: 'Saída', ADJUSTMENT: 'Ajuste' };

export const asRecord = (value) =>
  value && typeof value === 'object' && !Array.isArray(value) ? value : {};
