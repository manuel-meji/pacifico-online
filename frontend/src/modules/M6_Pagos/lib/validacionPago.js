// Reglas de validación del lado cliente. RN-M6-01 se vuelve a validar en el backend
// (ProcesarPagoControlador::validarSolicitud); aquí solo se evita una llamada inútil.
export const MEDIOS_PAGO = ['tarjeta', 'sinpe', 'paypal'];

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

/** @returns {{valido:boolean, errores:Record<string,string>}} */
export function validarSolicitudPago({ pedido, montoIngresado, medioPago, token }) {
  const errores = {};
  if (!pedido || pedido.estado !== 'confirmado') {
    errores.pedido = 'Solo se puede cobrar un pedido confirmado (RN-M6-01).';
  }
  if (!Number.isFinite(Number(montoIngresado)) || Number(montoIngresado) <= 0) {
    errores.monto = 'El monto debe ser mayor a cero.';
  } else if (pedido && centimos(montoIngresado) !== centimos(pedido.monto_total)) {
    errores.monto = 'El monto no coincide con el total del pedido (RN-M6-01).';
  }
  if (!MEDIOS_PAGO.includes(medioPago)) {
    errores.medioPago = 'Elija un medio de pago disponible.';
  } else if (!TOKENS_SIMULADOR[medioPago].some((t) => t.token === token)) {
    errores.token = 'Elija un medio de pago simulado.';
  }
  return { valido: Object.keys(errores).length === 0, errores };
}
