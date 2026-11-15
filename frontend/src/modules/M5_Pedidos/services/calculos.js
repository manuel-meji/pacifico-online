export const colones = (n) => '₡' + Math.round(n).toLocaleString('es-CR', { maximumFractionDigits: 0 });

// Los grupos por comercio (nombre, subtotales, impuesto, envío y total) vienen calculados por la API
// en grupos_comercio y resumen (RF-003, RN-M5-01, RN-M5-02); aquí solo se formatea y valida.

// Validación del formulario de checkout. Reglas: CU-004 paso 2 (dirección obligatoria)
// y CrearPedidoRequest del contrato de M5 (id_direccion_cliente entero; cupon_descuento y tarjeta_regalo, máximo 50 caracteres).
// El método de pago se selecciona en la pasarela de M6.
export function validarCheckout({ idDireccion, cupon = '', tarjetaRegalo = '' }) {
  const e = {};
  const id = Number(idDireccion);
  if (!Number.isInteger(id) || id < 1) e.idDireccion = 'Indique una dirección de envío válida.';
  if (cupon.length > 50) e.cupon = 'El cupón no puede superar los 50 caracteres.';
  if (tarjetaRegalo.length > 50) e.tarjetaRegalo = 'La tarjeta de regalo no puede superar los 50 caracteres.';
  return e;
}
