// Cliente HTTP de M6 (contrato contratos/m6-pagos-facturacion.yaml).
// Los errores llegan en el formato estándar del núcleo: { codigo, mensaje }.
const BASE = '/api/m6';

async function llamar(ruta, opciones = {}) {
  const resp = await fetch(`${BASE}${ruta}`, {
    ...opciones,
    headers: { 'Content-Type': 'application/json', ...(opciones.headers || {}) },
  });
  const cuerpo = await resp.json().catch(() => ({}));
  if (!resp.ok) throw Object.assign(new Error(cuerpo.mensaje || 'Error de M6'), { codigo: cuerpo.codigo, mensaje: cuerpo.mensaje });
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

export const consultarHistorial = ({ idComercio, idCliente }) => {
  const q = new URLSearchParams();
  if (idComercio) q.set('id_comercio', idComercio);
  if (idCliente) q.set('id_cliente', idCliente);
  return llamar(`/historial?${q}`);
};
