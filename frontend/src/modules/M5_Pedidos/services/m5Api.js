// Cliente HTTP de M5 Carrito y Pedidos (contrato contracts/m5-pedidos/v1/openapi.yaml).
// Los errores llegan en el formato del nucleo: { error: { codigo, mensaje, detalles } }.
const BASE = '/api/m5/v1';

// Sin sesión autenticada no se envía id_cliente=1 por defecto para evitar mezclar carritos.
export const ID_CLIENTE_POR_DEFECTO = null;

const METODOS_CON_CSRF = ['POST', 'PUT', 'PATCH', 'DELETE'];

function leerCookie(nombre) {
  const par = document.cookie.split('; ').find((c) => c.startsWith(`${nombre}=`));
  return par ? decodeURIComponent(par.slice(nombre.length + 1)) : null;
}

async function obtenerCookieCsrf() {
  await fetch('/sanctum/csrf-cookie', { credentials: 'include' });
}

function armarRuta(ruta, idCliente, query = {}) {
  const q = new URLSearchParams();
  if (idCliente != null) q.set('id_cliente', idCliente);
  Object.entries(query).forEach(([clave, valor]) => {
    if (valor !== undefined && valor !== null && valor !== '') q.set(clave, valor);
  });
  const texto = q.toString();
  return texto ? `${ruta}?${texto}` : ruta;
}

async function solicitarUrl(url, opciones = {}, reintentado = false) {
  const metodo = (opciones.method || 'GET').toUpperCase();
  const xsrf = leerCookie('XSRF-TOKEN');

  const resp = await fetch(url, {
    ...opciones,
    method: metodo,
    credentials: 'include',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      ...(xsrf ? { 'X-XSRF-TOKEN': xsrf } : {}),
      ...(opciones.headers || {}),
    },
  });

  if (resp.status === 419 && !reintentado && METODOS_CON_CSRF.includes(metodo)) {
    await obtenerCookieCsrf();
    return solicitarUrl(url, opciones, true);
  }

  const cuerpo = await resp.json().catch(() => ({}));
  if (!resp.ok) {
    const error = cuerpo.error || cuerpo;
    const mensaje = error.mensaje || cuerpo.message || `Error (HTTP ${resp.status})`;
    throw Object.assign(new Error(mensaje), {
      codigo: error.codigo,
      mensaje,
      detalles: error.detalles,
      status: resp.status,
    });
  }
  return cuerpo;
}

const llamar = (ruta, opciones = {}) => solicitarUrl(`${BASE}${ruta}`, opciones);

const json = (datos) => JSON.stringify(datos ?? {});

export const validarCupon = ({ codigo, subtotal, ids_comercios, subtotales_por_comercio }) =>
  solicitarUrl('/api/m1/v1/cupones/validar', {
    method: 'POST',
    body: json({ codigo, subtotal, ids_comercios, subtotales_por_comercio }),
  });

// ---------- Carrito ----------

export const obtenerCarrito = (idCliente = ID_CLIENTE_POR_DEFECTO, query = {}) =>
  llamar(armarRuta('/carrito', idCliente, query));

export const previsualizarCarritoInvitado = (items = [], validarItem = null) =>
  llamar('/carrito/previsualizar', {
    method: 'POST',
    body: json({
      items,
      ...(validarItem ? { validar_item: validarItem } : {}),
    }),
  });

// Respuesta: { id_cliente, direcciones: [{ id_direccion_cliente, texto_completo, es_principal, ... }] }.
export const obtenerDirecciones = (idCliente = ID_CLIENTE_POR_DEFECTO) =>
  llamar(armarRuta('/carrito/direcciones', idCliente));

export const vaciarCarrito =(idCliente = ID_CLIENTE_POR_DEFECTO) =>
  llamar(armarRuta('/carrito', idCliente), { method: 'DELETE' });

export const agregarItem = ({ id_variacion, cantidad = 1, id_reserva = null }, idCliente = ID_CLIENTE_POR_DEFECTO) =>
  llamar(armarRuta('/carrito/items', idCliente), {
    method: 'POST',
    body: json({ id_variacion, cantidad, id_reserva }),
  });

export const actualizarCantidad = (idItemCarrito, cantidad, idCliente = ID_CLIENTE_POR_DEFECTO) =>
  llamar(armarRuta(`/carrito/items/${idItemCarrito}`, idCliente), {
    method: 'PUT',
    body: json({ cantidad }),
  });

export const eliminarItem = (idItemCarrito, idCliente = ID_CLIENTE_POR_DEFECTO) =>
  llamar(armarRuta(`/carrito/items/${idItemCarrito}`, idCliente), { method: 'DELETE' });

// ---------- Pedidos ----------

export const listarPedidos = ({ estado_pedido, pagina, por_pagina, id_comercio, modo_comercio } = {}, idCliente = ID_CLIENTE_POR_DEFECTO) =>
  llamar(armarRuta('/pedidos', idCliente, { estado_pedido, pagina, por_pagina, id_comercio, modo_comercio }));

// datos: { id_direccion_cliente, medio_pago, cupon_descuento, tarjeta_regalo, nota }
export const crearPedido = (datos = {}, idCliente = ID_CLIENTE_POR_DEFECTO) =>
  llamar(armarRuta('/pedidos', idCliente), { method: 'POST', body: json(datos) });

export const obtenerPedido = (idPedido, idCliente = ID_CLIENTE_POR_DEFECTO) =>
  llamar(armarRuta(`/pedidos/${idPedido}`, idCliente));

export const cancelarPedido = (idPedido, { motivo, requiere_reembolso = true }, idCliente = ID_CLIENTE_POR_DEFECTO) =>
  llamar(armarRuta(`/pedidos/${idPedido}/cancelar`, idCliente), {
    method: 'POST',
    body: json({ motivo, requiere_reembolso }),
  });

// No está en el openapi.yaml v1.1.0; el backend lo expone en POST /pedidos/{id}/pago-confirmado.
export const confirmarPago = (idPedido, referenciaPago, idCliente = ID_CLIENTE_POR_DEFECTO) =>
  llamar(armarRuta(`/pedidos/${idPedido}/pago-confirmado`, idCliente), {
    method: 'POST',
    body: json(referenciaPago ? { referencia_pago: referenciaPago } : {}),
  });

// ---------- Subpedidos ----------

export const prepararSubpedido = (idSubpedido, idCliente = ID_CLIENTE_POR_DEFECTO) =>
  llamar(armarRuta(`/subpedidos/${idSubpedido}/preparar`, idCliente), { method: 'POST' });

export const despacharSubpedido = (idSubpedido, { guia_transporte = null } = {}, idCliente = ID_CLIENTE_POR_DEFECTO) =>
  llamar(armarRuta(`/subpedidos/${idSubpedido}/despachar`, idCliente), {
    method: 'POST',
    body: json({ guia_transporte }),
  });

export const entregarSubpedido = (idSubpedido, idCliente = ID_CLIENTE_POR_DEFECTO) =>
  llamar(armarRuta(`/subpedidos/${idSubpedido}/entregar`, idCliente), { method: 'POST' });

export const cancelarSubpedido = (idSubpedido, { motivo, requiere_reembolso = true }, idCliente = ID_CLIENTE_POR_DEFECTO) =>
  llamar(armarRuta(`/subpedidos/${idSubpedido}/cancelar`, idCliente), {
    method: 'POST',
    body: json({ motivo, requiere_reembolso }),
  });

export const solicitarDevolucionSubpedido = (idSubpedido, { motivo }, idCliente = ID_CLIENTE_POR_DEFECTO) =>
  llamar(armarRuta(`/subpedidos/${idSubpedido}/devolucion`, idCliente), {
    method: 'POST',
    body: json({ motivo }),
  });

// ---------- Lista de Deseos (Wishlist) ----------

export const obtenerDeseos = (idCliente = ID_CLIENTE_POR_DEFECTO) =>
  llamar(armarRuta('/deseos', idCliente));

export const agregarDeseo = (idVariacion, idCliente = ID_CLIENTE_POR_DEFECTO) =>
  llamar(armarRuta('/deseos', idCliente), {
    method: 'POST',
    body: json({ id_variacion: idVariacion }),
  });

export const eliminarDeseo = (idDeseo, idCliente = ID_CLIENTE_POR_DEFECTO) =>
  llamar(armarRuta(`/deseos/${idDeseo}`, idCliente), { method: 'DELETE' });

export const moverDeseoAlCarrito = (idDeseo, idCliente = ID_CLIENTE_POR_DEFECTO) =>
  llamar(armarRuta(`/deseos/${idDeseo}/mover-al-carrito`, idCliente), { method: 'POST' });
