// Cliente HTTP de las devoluciones de M5 (RF-019, RF-020, RF-021).
// Rutas en routes/modules/m5_devoluciones.php. Los errores llegan en el formato del núcleo:
// { error: { codigo, mensaje, detalles } }, igual que en m5Api.js.
//
// Este archivo NO reutiliza el cliente de m5Api.js porque la solicitud de devolución envía
// archivos (multipart/form-data) y ese cliente fuerza Content-Type: application/json.

const BASE = '/api/m5/v1';
const METODOS_CON_CSRF = ['POST', 'PUT', 'PATCH', 'DELETE'];

function leerCookie(nombre) {
  const par = document.cookie.split('; ').find((c) => c.startsWith(`${nombre}=`));
  return par ? decodeURIComponent(par.slice(nombre.length + 1)) : null;
}

async function obtenerCookieCsrf() {
  await fetch('/sanctum/csrf-cookie', { credentials: 'include' });
}

function armarRuta(ruta, query = {}) {
  const q = new URLSearchParams();
  Object.entries(query).forEach(([clave, valor]) => {
    if (valor !== undefined && valor !== null && valor !== '') q.set(clave, valor);
  });
  const texto = q.toString();
  return texto ? `${BASE}${ruta}?${texto}` : `${BASE}${ruta}`;
}

// Con FormData el navegador fija el Content-Type (con el boundary); con JSON se fija aquí.
async function solicitar(url, { method = 'GET', cuerpo, idComercio } = {}, reintentado = false) {
  const xsrf = leerCookie('XSRF-TOKEN');
  const esFormData = typeof FormData !== 'undefined' && cuerpo instanceof FormData;

  const resp = await fetch(url, {
    method,
    credentials: 'include',
    headers: {
      Accept: 'application/json',
      ...(cuerpo && !esFormData ? { 'Content-Type': 'application/json' } : {}),
      ...(xsrf ? { 'X-XSRF-TOKEN': xsrf } : {}),
      ...(idComercio ? { 'X-Comercio-Id': String(idComercio) } : {}),
    },
    body: cuerpo ? (esFormData ? cuerpo : JSON.stringify(cuerpo)) : undefined,
  });

  if (resp.status === 419 && !reintentado && METODOS_CON_CSRF.includes(method)) {
    await obtenerCookieCsrf();
    return solicitar(url, { method, cuerpo, idComercio }, true);
  }

  const datos = await resp.json().catch(() => ({}));
  if (!resp.ok) {
    const error = datos.error || datos;
    const mensaje = error.mensaje || datos.message || `Error (HTTP ${resp.status})`;
    throw Object.assign(new Error(mensaje), {
      codigo: error.codigo,
      mensaje,
      detalles: error.detalles,
      status: resp.status,
    });
  }
  return datos;
}

// ---------- Cliente (RF-019) ----------

/**
 * Solicita la devolución de un subpedido entregado.
 * @param {number} idSubpedido
 * @param {{ motivo: string, lineas?: {id_linea:number, cantidad:number}[], archivos: File[] }} datos
 *        `lineas` vacío o ausente = devolución total.
 */
export function solicitarDevolucion(idSubpedido, { motivo, lineas = [], archivos = [] }, idCliente = null) {
  const form = new FormData();
  form.append('motivo', motivo);
  lineas.forEach((l, i) => {
    form.append(`lineas[${i}][id_linea]`, String(l.id_linea));
    form.append(`lineas[${i}][cantidad]`, String(l.cantidad));
  });
  archivos.forEach((archivo) => form.append('evidencias[]', archivo));

  return solicitar(armarRuta(`/subpedidos/${idSubpedido}/devoluciones`, { id_cliente: idCliente }), {
    method: 'POST',
    cuerpo: form,
  });
}

export const listarDevolucionesSubpedido = (idSubpedido, { idCliente = null, idComercio = null } = {}) =>
  solicitar(armarRuta(`/subpedidos/${idSubpedido}/devoluciones`, { id_cliente: idCliente }), { idComercio });

export const listarMisDevoluciones = (idCliente = null, estado) =>
  solicitar(armarRuta('/devoluciones', { rol: 'cliente', id_cliente: idCliente, estado }));

// ---------- Comercio (RF-020) ----------

export const listarDevolucionesComercio = (idComercio, estado) =>
  solicitar(armarRuta('/devoluciones', { rol: 'comercio', id_comercio: idComercio, estado }), { idComercio });

export const aprobarDevolucion = (idDevolucion, idComercio) =>
  solicitar(armarRuta(`/devoluciones/${idDevolucion}/aprobar`, { id_comercio: idComercio }), {
    method: 'POST',
    cuerpo: {},
    idComercio,
  });

export const rechazarDevolucion = (idDevolucion, motivoRechazo, idComercio) =>
  solicitar(armarRuta(`/devoluciones/${idDevolucion}/rechazar`, { id_comercio: idComercio }), {
    method: 'POST',
    cuerpo: { motivo_rechazo: motivoRechazo },
    idComercio,
  });

export const reintentarReembolso = (idDevolucion, idComercio) =>
  solicitar(armarRuta(`/devoluciones/${idDevolucion}/reintentar-reembolso`, { id_comercio: idComercio }), {
    method: 'POST',
    cuerpo: {},
    idComercio,
  });

// Enlace de descarga de una evidencia (el backend valida que sea el cliente o el comercio de la devolución).
export const urlEvidencia = (evidencia, { idCliente = null, idComercio = null } = {}) =>
  `${evidencia.url}${armarRuta('', { id_cliente: idCliente, id_comercio: idComercio }).replace(BASE, '')}`;
