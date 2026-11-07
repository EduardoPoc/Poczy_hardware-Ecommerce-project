export const orderStatuses = {
  PENDING: 'Aguardando pagamento',
  PAID: 'Pago',
  SHIPPED: 'Enviado',
  DELIVERED: 'Entregue',
  CANCELLED: 'Cancelado',
};

export function paymentLabel(method) {
  return (
    { FAKE_PIX: 'Fake Pix', PIX: 'Pix', CREDIT_CARD: 'Cartão de crédito', BOLETO: 'Boleto' }[
      method
    ] ||
    method ||
    'Não informado'
  );
}

export const orderTransitions = {
  PENDING: ['PAID', 'CANCELLED'],
  PAID: ['SHIPPED'],
  SHIPPED: ['DELIVERED'],
  DELIVERED: [],
  CANCELLED: [],
};

export function allowedOrderTransitions(order) {
  if (Array.isArray(order.allowed_statuses))
    return order.allowed_statuses.filter((status) => Object.hasOwn(orderStatuses, status));
  return orderTransitions[order.status] || [];
}
