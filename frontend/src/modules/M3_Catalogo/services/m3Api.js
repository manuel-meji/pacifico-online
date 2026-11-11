// Cliente HTTP de M3 (contrato contracts/m3-catalogo/v1/openapi_M3_v1.3.0.yaml, servidor /api/m3/v1).
// Mismo esquema que M4 (inventarioApi.js): sesión del núcleo con cookie de Sanctum, CSRF en las escrituras y errores
// en el formato del núcleo { error: { codigo, mensaje, detalles } }. Las pantallas no tienen reglas de negocio: todo
// lo valida el servidor, y el comercio sale de la sesión, nunca del formulario (RN-12).

const BASE = '/api/m3/v1';
const METODOS_CON_CSRF = ['POST', 'PUT', 'PATCH', 'DELETE'];

// Texto de respaldo cuando la respuesta no trae mensaje (por ejemplo, sin red).
const MENSAJES = {
  'ERR-001': 'Tu sesión expiró, iniciá sesión de nuevo.',
  'ERR-002': 'Tu cuenta no tiene permiso para esta acción.',
  'ERR-004': 'No se encontró lo que buscás.',
  'ERR-005': 'Revisá los datos marcados.',
  'ERR-006': 'El servicio no está disponible en este momento. Intentá de nuevo.',
  'ERR-M3-001': 'Ya existe una variación con el mismo SKU en este comercio.',
  'ERR-M3-002': 'Los productos relacionados deben ser del mismo comercio.',
  'ERR-M3-003': 'El producto ya alcanzó el máximo de 8 imágenes.',
  'ERR-M3-004': 'El comercio está suspendido.',
};

function errorLocal(codigo, detalles = null) {
  return { codigo, mensaje: MENSAJES[codigo] ?? 'No se pudo completar la operación.', detalles };
}

function leerCookie(nombre) {
  const par = document.cookie.split('; ').find((c) => c.startsWith(`${nombre}=`));
  return par ? decodeURIComponent(par.slice(nombre.length + 1)) : null;
}

const obtenerCookieCsrf = () => fetch('/sanctum/csrf-cookie', { credentials: 'include' });

async function llamar(url, opciones = {}, reintentado = false) {
  const metodo = (opciones.method || 'GET').toUpperCase();
  const esFormulario = opciones.body instanceof FormData;

  if (METODOS_CON_CSRF.includes(metodo) && !leerCookie('XSRF-TOKEN')) {
    await obtenerCookieCsrf().catch(() => null);
  }

  const xsrf = leerCookie('XSRF-TOKEN');
  let resp;
  try {
    resp = await fetch(url, {
      ...opciones,
      method: metodo,
      credentials: 'include',
      headers: {
        // Con FormData el navegador pone el Content-Type con su separador.
        ...(esFormulario ? {} : { 'Content-Type': 'application/json' }),
        Accept: 'application/json',
        ...(xsrf ? { 'X-XSRF-TOKEN': xsrf } : {}),
      },
    });
  } catch {
    throw { error: errorLocal('ERR-006', { motivo: 'sin_conexion' }), status: 0 };
  }

  // 419: token CSRF ausente o vencido. Se pide uno nuevo y se reintenta una sola vez.
  if (resp.status === 419 && !reintentado && METODOS_CON_CSRF.includes(metodo)) {
    await obtenerCookieCsrf();
    return llamar(url, opciones, true);
  }

  if (resp.status === 204) return null;
  const cuerpo = await resp.json().catch(() => ({}));
  if (!resp.ok) {
    const respaldo = resp.status === 401 ? 'ERR-001' : resp.status === 403 ? 'ERR-002' : 'ERR-006';
    throw { error: cuerpo.error ?? errorLocal(respaldo), status: resp.status };
  }
  return cuerpo;
}

const consulta = (params) => {
  const q = new URLSearchParams();
  Object.entries(params).forEach(([k, v]) => {
    if (v !== undefined && v !== null && v !== '') q.set(k, v);
  });
  const texto = q.toString();
  return texto ? `?${texto}` : '';
};

const enviar = (metodo, ruta, datos) => llamar(`${BASE}${ruta}`, { method: metodo, body: JSON.stringify(datos) });

// ---------- Sesión del núcleo ----------

// GET /api/user del núcleo: null si no hay sesión.
export async function usuarioActual() {
  try {
    return (await llamar('/api/user')).user;
  } catch (falla) {
    if (falla.status === 401) return null;
    throw falla;
  }
}

// ---------- Lecturas públicas ----------

export const listarCategorias = () => llamar(`${BASE}/categorias`);
export const listarMarcas = () => llamar(`${BASE}/marcas`);

// filtros: q, id_categoria, id_marca, orden, pagina, por_pagina (operación buscarProductos).
export const buscarProductos = (filtros = {}) => llamar(`${BASE}/productos${consulta(filtros)}`);
export const obtenerProducto = (idProducto) => llamar(`${BASE}/productos/${idProducto}`);

// ---------- Alta del catálogo (Encargado del comercio) ----------

export const crearCategoria = (datos) => enviar('POST', '/categorias', datos);
export const crearMarca = (datos) => enviar('POST', '/marcas', datos);
export const crearProducto = (datos) => enviar('POST', '/productos', datos);
export const crearVariacion = (idProducto, datos) => enviar('POST', `/productos/${idProducto}/variaciones`, datos);
export const publicarProducto = (idProducto) => enviar('PATCH', `/productos/${idProducto}`, { estado_producto: 'publicado' });

// La imagen viaja como archivo (multipart/form-data, campo "archivo").
export function agregarImagen(idProducto, archivo) {
  const formulario = new FormData();
  formulario.append('archivo', archivo);
  return llamar(`${BASE}/productos/${idProducto}/imagenes`, { method: 'POST', body: formulario });
}

// ---------- Errores ----------

// Texto para mostrar: el mensaje del servidor y, si es ERR-005, el primer motivo de cada campo.
export function textoDeError(falla) {
  const error = falla?.error ?? errorLocal('ERR-006');
  const mensaje = error.mensaje ?? MENSAJES[error.codigo] ?? 'No se pudo completar la operación.';
  const detalles = error.detalles;

  if (error.codigo !== 'ERR-005' || !detalles || typeof detalles !== 'object') return mensaje;

  const motivos = Object.values(detalles)
    .map((valor) => (Array.isArray(valor) ? valor[0] : valor))
    .filter((valor) => typeof valor === 'string');

  return motivos.length > 0 ? `${mensaje} ${motivos.join(' ')}` : mensaje;
}
