// Mensagens do contrato da API em inglês e sua apresentação em português.
const messages = {
  'Guest cart merging requires a browser session.':
    'Entre pela loja para preservar o carrinho de visitante nesta sessão.',
  'Only orders awaiting payment can be paid.':
    'Somente pedidos aguardando pagamento podem ser pagos.',
  'A record with the provided unique values already exists.':
    'Já existe um registro com os dados únicos informados.',
  'The password must not exceed 72 bytes for the configured hashing algorithm.':
    'A senha deve ter no máximo 72 bytes para o algoritmo de hash utilizado.',
  'The description must not exceed 65535 bytes.': 'A descrição deve ter no máximo 65535 bytes.',
  'Upload a valid PNG, JPG, JPEG, or WebP image.':
    'Envie uma imagem válida em PNG, JPG, JPEG ou WebP.',
  'Upload a PNG, JPG, JPEG, or WebP image.': 'Envie uma imagem em PNG, JPG, JPEG ou WebP.',
  'The file must have a PNG, JPG, JPEG, or WebP extension.':
    'O arquivo deve ter extensão PNG, JPG, JPEG ou WebP.',
  'The image must not exceed 5 MB.': 'A imagem deve ter no máximo 5 MB.',
  'The image could not be uploaded. Check the file and the 5 MB limit.':
    'Não foi possível receber a imagem. Confira o arquivo e o limite de 5 MB.',
  'Specification keys must use snake_case and must not exceed 60 characters.':
    'As chaves das especificações devem usar snake_case e ter até 60 caracteres.',
  'The filter value must be a string, number, or boolean.':
    'O valor do filtro deve ser texto, número ou booleano.',
  'Payment is simulated and does not accept payment details.':
    'O pagamento é simulado e não recebe dados de pagamento.',
  'The current password is incorrect.': 'A senha atual está incorreta.',
  'The account has orders or password history that must be preserved.':
    'A conta possui pedidos ou histórico de senha que devem ser preservados.',
  'Enter a positive quantity for inbound and outbound movements, or a nonzero difference for adjustments.':
    'Informe uma quantidade positiva para entradas e saídas, ou uma variação diferente de zero para ajustes.',
  'Insufficient stock or stock balance exceeds the allowed limit.':
    'Estoque insuficiente ou saldo acima do limite permitido.',
  'The key must use snake_case and must not exceed 60 characters.':
    'A chave deve usar snake_case e ter até 60 caracteres.',
  'Brand is a reserved text filter; use brand_id on the product.':
    'Marca é um filtro textual reservado; use brand_id no produto.',
  'Only numeric attributes support bounds.': 'Somente atributos numéricos aceitam limites.',
  'Bounds must be finite.': 'Os limites devem ser finitos.',
  'The bounds do not allow any value.': 'Os limites não permitem nenhum valor.',
  'Options must match the attribute type and bounds.':
    'As opções devem respeitar o tipo e os limites do atributo.',
  'This attribute is required for the category.': 'Este atributo é obrigatório para a categoria.',
  'The attribute must exist in the category and match its type, options, and bounds.':
    'O atributo deve existir na categoria e respeitar seu tipo, opções e limites.',
  'The category was changed by another operation. Try again.':
    'A categoria foi alterada por outra operação. Tente novamente.',
  'The product belongs to orders. Use is_active to deactivate it and preserve history.':
    'O produto pertence a pedidos. Use is_active para desativá-lo e preservar o histórico.',
  'The product has stock movements. Use is_active to deactivate it.':
    'O produto possui movimentações de estoque. Use is_active para desativá-lo.',
  'The product must have at least 1 unit in stock to be active.':
    'O produto precisa ter pelo menos 1 unidade em estoque para estar ativo.',
  'The product image could not be saved.': 'Não foi possível salvar a imagem do produto.',
  'Guest cart not found.': 'Carrinho de visitante não encontrado.',
  'Enter an integer quantity between zero and 2147483647.':
    'Informe uma quantidade inteira entre zero e 2147483647.',
  'The quantity must not exceed the available stock.':
    'A quantidade não pode ultrapassar o estoque disponível.',
  'The product category has changed. Try again.':
    'A categoria do produto foi alterada. Tente novamente.',
  'The product is not available.': 'O produto não está disponível.',
  'The category has products. Deactivate it or remove the associations before deleting it.':
    'A categoria possui produtos. Desative-a ou remova os vínculos antes de excluir.',
  'Insufficient stock.': 'Estoque insuficiente.',
  'The order total or shipping amount is outside the allowed limits.':
    'O total do pedido ou frete está fora dos limites permitidos.',
  'Only orders awaiting payment can be cancelled.':
    'Somente pedidos aguardando pagamento podem ser cancelados.',
  'Status transition is not allowed.': 'Transição de status não permitida.',
  'The product was removed; stock must be reconciled before cancellation.':
    'O produto foi removido; o estoque exige conciliação antes do cancelamento.',
  'The provided credentials are invalid.': 'As credenciais informadas são inválidas.',
  'Add products to the cart before checking out.':
    'Adicione produtos ao carrinho antes de finalizar a compra.',
  'An order accepts up to 100 products and up to 10,000 units per product. Adjust your cart.':
    'O pedido aceita até 100 produtos e até 10.000 unidades de cada produto. Ajuste seu carrinho.',
  'The cart amount has changed. Review the new total before confirming Fake Pix.':
    'O valor do carrinho foi atualizado. Confira o novo total antes de confirmar o Fake Pix.',
  'The product was removed from the cart because it is unavailable.':
    'O produto foi removido do carrinho porque não está disponível.',
};

const fields = {
  name: 'Nome',
  email: 'E-mail',
  password: 'Senha',
  current_password: 'Senha atual',
  password_confirmation: 'Confirmação de senha',
  postal_code: 'CEP',
  street: 'Rua',
  number: 'Número',
  complement: 'Complemento',
  city: 'Cidade',
  state: 'Estado',
  address_id: 'Endereço',
  category_id: 'Categoria',
  brand_id: 'Marca',
  brand_ids: 'Marcas',
  description: 'Descrição',
  price: 'Preço',
  cost: 'Custo',
  stock: 'Estoque',
  quantity: 'Quantidade',
  product_id: 'Produto',
  is_active: 'Ativo',
  image: 'Imagem',
  specifications: 'Especificações',
  attributes: 'Atributos',
  options: 'Opções',
  type: 'Tipo',
  unit: 'Unidade',
  required: 'Obrigatório',
  is_filterable: 'Disponível no filtro',
  minimum: 'Valor mínimo',
  maximum: 'Valor máximo',
  exclusive_minimum: 'Limite mínimo exclusivo',
  exclusive_maximum: 'Limite máximo exclusivo',
  items: 'Itens',
  cart: 'Carrinho',
  payment_method: 'Forma de pagamento',
  checkout_token: 'Tentativa de pagamento',
  expected_total: 'Total esperado',
  total: 'Total',
  shipping_total: 'Frete',
  products_total: 'Total dos produtos',
  status: 'Status',
  reason: 'Motivo',
  page: 'Página',
  per_page: 'Itens por página',
  filter: 'Filtro',
  q: 'Pesquisa',
  min_price: 'Preço mínimo',
  max_price: 'Preço máximo',
  user_id: 'Usuário',
  role: 'Permissão',
};

function fieldLabel(field) {
  const parts = String(field || '')
    .split('.')
    .filter((part) => !/^\d+$/.test(part));
  const key = [parts.at(-1), parts[0]].find((part) => Object.hasOwn(fields, part));
  return fields[key] || 'Este campo';
}

const validationRules = [
  [/^is required\.$/, () => 'é obrigatório.'],
  [/^must (?:have a value|be present)\.$/, () => 'precisa ser preenchido.'],
  [/^must be a valid email address\.$/, () => 'deve ser um e-mail válido.'],
  [/^must be an integer\.$/, () => 'deve ser um número inteiro.'],
  [/^must be a number\.$/, () => 'deve ser um número.'],
  [/^must be a string\.$/, () => 'deve ser um texto.'],
  [/^must be (?:an array|a list)\.$/, () => 'deve ser uma lista válida.'],
  [/^must be true or false\.$/, () => 'deve ser verdadeiro ou falso.'],
  [/^confirmation does not match\.$/, () => 'não corresponde à confirmação.'],
  [/^has already been taken\.$/, () => 'já está em uso.'],
  [/^has a duplicate value\.$/, () => 'contém um valor repetido.'],
  [/^(?:format is invalid|is invalid)\.$/, () => 'tem um formato inválido.'],
  [/^is prohibited\.$/, () => 'não pode ser enviado.'],
  [/^must be a valid UUID\.$/, () => 'deve conter um identificador válido.'],
  [
    /^must not be greater than (\d+(?:\.\d+)?) characters\.$/,
    ([, max]) => `deve ter no máximo ${max} caracteres.`,
  ],
  [/^must be at least (\d+) characters\.$/, ([, min]) => `deve ter pelo menos ${min} caracteres.`],
  [
    /^must (?:not have more than|have at least) (\d+) items\.$/,
    ([text, value]) =>
      `deve ter ${text.includes('not') ? 'no máximo' : 'pelo menos'} ${value} itens.`,
  ],
  [/^must not be greater than (\d+(?:\.\d+)?)\.$/, ([, max]) => `deve ser no máximo ${max}.`],
  [/^must be at least (\d+(?:\.\d+)?)\.$/, ([, min]) => `deve ser pelo menos ${min}.`],
  [
    /^must be between (\d+(?:\.\d+)?) and (\d+(?:\.\d+)?)( characters| items| kilobytes)?\.$/,
    ([, min, max, unit]) =>
      `deve estar entre ${min} e ${max}${{ ' characters': ' caracteres', ' items': ' itens', ' kilobytes': ' KB' }[unit] || ''}.`,
  ],
  [
    /^must have (\d+(?:-\d+)?) decimal places\.$/,
    ([, places]) => `deve ter ${places.replace('-', ' a ')} casas decimais.`,
  ],
  [
    /^must be greater than or equal to (.+)\.$/,
    ([, min]) =>
      `deve ser maior ou igual a ${/^\d+(?:\.\d+)?$/.test(min) ? min : fieldLabel(min.replaceAll(' ', '_'))}.`,
  ],
  [
    /^must contain at least one uppercase and one lowercase letter\.$/,
    () => 'deve conter letras maiúsculas e minúsculas.',
  ],
  [
    /^must contain at least one (letter|number|symbol)\.$/,
    ([, type]) =>
      `deve conter pelo menos ${{ letter: 'uma letra', number: 'um número', symbol: 'um símbolo' }[type]}.`,
  ],
];

export function translateApiMessage(
  message,
  { field, fallback = 'Não foi possível concluir. Tente novamente em instantes.' } = {},
) {
  if (typeof message !== 'string') return fallback;
  if (Object.hasOwn(messages, message)) return messages[message];
  // Permite respostas antigas durante a atualização da API e do frontend.
  if (Object.values(messages).includes(message)) return message;
  if (/^The .+ has already been taken\.$/.test(message))
    return `${fieldLabel(field)}: já está em uso.`;
  if (/^The .+ failed to upload\.$/.test(message))
    return `${fieldLabel(field)}: não foi possível enviar o arquivo.`;
  if (/^The selected .+ is invalid\.$/.test(message))
    return `${fieldLabel(field)}: selecione uma opção válida.`;
  const validation = message.match(/^The .+? field (.+)$/);
  if (validation) {
    for (const [pattern, translate] of validationRules) {
      const match = validation[1].match(pattern);
      if (match) return `${fieldLabel(field)}: ${translate(match)}`;
    }
  }
  return field ? `${fieldLabel(field)}: confira o valor informado.` : fallback;
}

export function translateValidationErrors(errors) {
  if (!errors || typeof errors !== 'object' || Array.isArray(errors)) return {};
  return Object.fromEntries(
    Object.entries(errors).map(([field, values]) => [
      field,
      (Array.isArray(values) ? values : [values]).map((message) =>
        translateApiMessage(message, { field }),
      ),
    ]),
  );
}

export function cartAdjustmentMessage(adjustment) {
  if (adjustment.reason === 'unavailable')
    return 'O produto foi removido do carrinho porque não está disponível.';
  if (
    adjustment.reason === 'stock_limit' &&
    Number.isSafeInteger(adjustment.requested_quantity) &&
    Number.isSafeInteger(adjustment.quantity)
  )
    return `A quantidade foi ajustada de ${adjustment.requested_quantity} para ${adjustment.quantity} conforme o estoque disponível.`;
  return translateApiMessage(adjustment.message, {
    fallback: 'O carrinho foi atualizado. Confira os itens antes de continuar.',
  });
}
