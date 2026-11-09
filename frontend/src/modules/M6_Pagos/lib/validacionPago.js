// Reglas de validación del lado cliente. RN-M6-01 se vuelve a validar en el backend
// (ProcesarPagoControlador::validarSolicitud); aquí solo se evita una llamada inútil.
export const MEDIOS_PAGO = ['tarjeta', 'sinpe', 'paypal'];

// M6 nunca solicita ni transmite datos de tarjeta. Esta lista es una defensa
// adicional para que un cambio futuro no introduzca PAN/CVV en el payload.
export const CAMPOS_SENSIBLES_PAGO = [
  'numero_tarjeta',
  'pan',
  'cvv',
  'cvc',
  'codigo_seguridad',
];

// Tokens que entrega el SIMULADOR de pasarela. M6 nunca recibe número de tarjeta ni CVV.
export const TOKENS_SIMULADOR = {
  tarjeta: [
    { token: 'tok_sim_aprobada', etiqueta: 'Tarjeta simulada · aprobada' },
    { token: 'tok_sim_rechazada', etiqueta: 'Tarjeta simulada · fondos insuficientes' },
    { token: 'tok_sim_timeout', etiqueta: 'Tarjeta simulada · sin respuesta (timeout)' },
  ],
  sinpe: [{ token: 'tok_sim_sinpe', etiqueta: 'SINPE Móvil simulado' }],
  paypal: [{ token: 'tok_sim_paypal', etiqueta: 'PayPal simulado' }],
};

const centimos = (n) => Math.round(Number(n) * 100);

const esNumeroPositivo = (n) => Number.isFinite(Number(n)) && Number(n) > 0;

export function contieneDatosSensibles(datos) {
  if (!datos || typeof datos !== 'object') {
    return false;
  }

  return CAMPOS_SENSIBLES_PAGO.some((campo) =>
    Object.prototype.hasOwnProperty.call(datos, campo),
  );
}

/** @returns {{valido:boolean, errores:Record<string,string>}} */
export function validarSolicitudPago({
  pedido,
  montoIngresado,
  medioPago,
  token,
  datosPago = {},
}) {
  const errores = {};
  const idPedido = Number(pedido?.id_pedido);

  if (!pedido || !Number.isInteger(idPedido) || idPedido <= 0) {
    errores.pedido = 'No se encontró el pedido a pagar (RN-M6-01).';
  } else if (pedido.estado !== 'confirmado') {
    errores.pedido = 'Solo se puede cobrar un pedido confirmado (RN-M6-01).';
  }

  if (!esNumeroPositivo(montoIngresado)) {
    errores.monto = 'El monto debe ser mayor a cero.';
  } else if (!pedido || !Number.isFinite(Number(pedido.monto_total))) {
    errores.monto = 'El pedido no tiene un monto total válido (RN-M6-01).';
  } else if (centimos(montoIngresado) !== centimos(pedido.monto_total)) {
    errores.monto = 'El monto no coincide con el total del pedido (RN-M6-01).';
  }

  if (!MEDIOS_PAGO.includes(medioPago)) {
    errores.medioPago = 'Elija un medio de pago disponible.';
  } else {
    const tokensDelMedio = TOKENS_SIMULADOR[medioPago] ?? [];

    if (!tokensDelMedio.some((t) => t.token === token)) {
      errores.token = 'Elija un medio de pago simulado.';
    }
  }

  if (contieneDatosSensibles(datosPago)) {
    errores.datosSensibles = 'No se permiten datos de tarjeta ni CVV; use el token del medio de pago.';
  }

  return { valido: Object.keys(errores).length === 0, errores };
}
