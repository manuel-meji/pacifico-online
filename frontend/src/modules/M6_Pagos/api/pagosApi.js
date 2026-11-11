// Cliente HTTP de M6 (rutas de routes/modules/m6_pagos_api.php, prefijo /api/m6/v1).
// Los errores llegan en el formato estándar del núcleo: { error: { codigo, mensaje, detalles } }.
const BASE = '/api/m6/v1';

async function llamar(ruta, opciones = {}) {
  const resp = await fetch(`${BASE}${ruta}`, {
    ...opciones,
    headers: { 'Content-Type': 'application/json', Accept: 'application/json', ...(opciones.headers || {}) },
  });
  const cuerpo = await resp.json().catch(() => ({}));
  if (!resp.ok) {
    const error = cuerpo.error ?? {};
    throw Object.assign(new Error(error.mensaje || 'Error de M6'), {
      codigo: error.codigo,
      mensaje: error.mensaje,
      motivo: error.detalles?.motivo,
      detalles: error.detalles ?? {},
      estadoHttp: resp.status,
    });
  }
  return cuerpo;
}

export const crearClaveIdempotencia = () => crypto.randomUUID();

export const procesarPago = ({ id_pedido, medio_pago, monto_total, token_medio_pago }, claveIdempotencia) =>
  llamar('/pagos', {
    method: 'POST',
    headers: { 'Idempotency-Key': claveIdempotencia },
    body: JSON.stringify({ id_pedido, medio_pago, monto_total, token_medio_pago }),
  });

export const obtenerFactura = (idFactura) => llamar(`/facturas/${idFactura}`);

// CU-004: un pedido multicomercio tiene una factura por subpedido.
export const listarFacturasPedido = (idPedido) => llamar(`/facturas?id_pedido=${encodeURIComponent(idPedido)}`);

// CU-005 (tipo "credito") y CU-006 (tipo "debito").
export const emitirNota = (idFactura, tipo, { monto, motivo }) =>
  llamar(`/facturas/${idFactura}/${tipo === 'debito' ? 'notas-debito' : 'notas-credito'}`, {
    method: 'POST',
    body: JSON.stringify({ monto, motivo }),
  });

export const consultarHistorial = ({ idComercio, idCliente }) => {
  const q = new URLSearchParams();
  if (idComercio) q.set('id_comercio', idComercio);
  if (idCliente) q.set('id_cliente', idCliente);
  return llamar(`/historial?${q}`);
};