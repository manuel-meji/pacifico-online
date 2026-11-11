import { comercios } from './datosMock';

export const colones = (n) => '₡' + Math.round(n).toLocaleString('es-CR', { maximumFractionDigits: 0 });

// Equivale a agruparItemPorComercio() + calcularCostosYsubtotales() del ControladorCarrito (RF-003, RN-M5-01, RN-M5-02).
export function agruparPorComercio(items) {
  const grupos = {};
  items.forEach((it) => {
    (grupos[it.id_comercio] ??= { comercio: comercios[it.id_comercio], items: [] }).items.push(it);
  });
  return Object.values(grupos).map((g) => {
    const subtotal = g.items.reduce((s, i) => s + i.precio_unitario * i.cantidad, 0);
    const impuesto = g.items.reduce((s, i) => s + i.precio_unitario * i.cantidad * i.tarifa_impuesto, 0);
    const costo_envio = g.comercio.costo_envio;
    return { ...g, subtotal, impuesto, costo_envio, total: subtotal + impuesto + costo_envio };
  });
}

// Total del Pedido Maestro = Σ subtotales + Σ impuestos + Σ envíos por comercio (RN-M5-01).
export const totalGeneral = (grupos) => grupos.reduce((s, g) => s + g.total, 0);

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
