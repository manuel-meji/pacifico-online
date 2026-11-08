// Cliente HTTP de M1 Portal público (contrato contracts/m1-portal/v1/openapi.yaml, versión 1.3.0).
// Las rutas de M1 viven en el grupo web de Laravel: los POST, PUT y DELETE llevan el token CSRF
// de Sanctum (cookie XSRF-TOKEN → cabecera X-XSRF-TOKEN), igual que el cliente de M5.
// Los errores llegan en el formato del núcleo: { error: { codigo, mensaje, detalles } }.
const BASE = '/api/m1/v1';

const METODOS_CON_CSRF = ['POST', 'PUT', 'PATCH', 'DELETE'];

// Respuesta cuando el servidor no contesta o no devuelve el formato del núcleo.
const SIN_CONEXION = {
  codigo: 'ERR-006',
  mensaje: 'No se pudo conectar con el portal. Intente de nuevo en unos minutos.',
  detalles: null,
};

function leerCookie(nombre) {
  const par = document.cookie.split('; ').find((c) => c.startsWith(`${nombre}=`));
  return par ? decodeURIComponent(par.slice(nombre.length + 1)) : null;
}

async function obtenerCookieCsrf() {
  await fetch('/sanctum/csrf-cookie', { credentials: 'include' });
}

// Devuelve { ok: true, datos } o { ok: false, estado, error }.
async function llamar(ruta, opciones = {}, reintentado = false) {
  const metodo = (opciones.method || 'GET').toUpperCase();

  if (METODOS_CON_CSRF.includes(metodo) && !leerCookie('XSRF-TOKEN')) {
    await obtenerCookieCsrf();
  }

  const xsrf = leerCookie('XSRF-TOKEN');
  let resp;

  try {
    resp = await fetch(`${BASE}${ruta}`, {
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
  } catch {
    return { ok: false, estado: 0, error: SIN_CONEXION };
  }

  // 419: token CSRF ausente o vencido. Se pide uno nuevo y se reintenta una sola vez.
  if (resp.status === 419 && !reintentado && METODOS_CON_CSRF.includes(metodo)) {
    await obtenerCookieCsrf();
    return llamar(ruta, opciones, true);
  }

  const cuerpo = await resp.json().catch(() => null);

  if (resp.ok) return { ok: true, datos: cuerpo };

  // 429: la ruta de registro admite 10 intentos por minuto.
  if (resp.status === 429) {
    return {
      ok: false,
      estado: 429,
      error: { codigo: 'ERR-429', mensaje: 'Demasiados intentos. Espere un minuto y vuelva a intentarlo.', detalles: null },
    };
  }

  return { ok: false, estado: resp.status, error: cuerpo?.error || SIN_CONEXION };
}

// POST /clientes (registrarCliente). `datos` sigue el esquema RegistroCliente 1.2.0.
// 201 → { ok: true, datos: { id_cliente } } · 409 ERR-M1-001 · 422 ERR-005 con detalles por campo.
export function registrarCliente(datos) {
  return llamar('/clientes', { method: 'POST', body: JSON.stringify(datos) });
}

// GET /clientes/sesion (obtenerClienteSesion). Datos del cliente con sesión, incluido su id_cliente.
// 200 → { ok: true, datos: Cliente } · 401 ERR-001 (sin sesión) · 404 ERR-003 (la cuenta no es de un cliente).
export function obtenerClienteSesion() {
  return llamar('/clientes/sesion');
}

// PUT /clientes/{id_cliente} (actualizarCliente). `datos` sigue el esquema DatosCliente 1.2.0.
// 200 → { ok: true, datos: { id_cliente } } · 401 ERR-001 · 404 ERR-003 · 409 ERR-M1-001 · 422 ERR-005.
export function actualizarCliente(idCliente, datos) {
  return llamar(`/clientes/${idCliente}`, { method: 'PUT', body: JSON.stringify(datos) });
}

// GET /clientes/{id_cliente}/direcciones (listarDireccionesCliente). La principal viene primero.
// 200 → { ok: true, datos: DireccionCliente[] } · 401 ERR-001 · 404 ERR-003.
export function listarDirecciones(idCliente) {
  return llamar(`/clientes/${idCliente}/direcciones`);
}

// POST /clientes/{id_cliente}/direcciones (agregarDireccionCliente). `datos` sigue DatosDireccionCliente.
// 201 → { ok: true, datos: DireccionCliente } · 401 ERR-001 · 404 ERR-003 · 422 ERR-005.
export function agregarDireccion(idCliente, datos) {
  return llamar(`/clientes/${idCliente}/direcciones`, { method: 'POST', body: JSON.stringify(datos) });
}

// PUT /clientes/{id_cliente}/direcciones/{id_direccion_cliente} (actualizarDireccionCliente).
// 200 → { ok: true, datos: DireccionCliente } · 401 ERR-001 · 404 ERR-003 o ERR-004 · 422 ERR-005
// (también si se intenta desmarcar la principal).
export function actualizarDireccion(idCliente, idDireccionCliente, datos) {
  return llamar(`/clientes/${idCliente}/direcciones/${idDireccionCliente}`, { method: 'PUT', body: JSON.stringify(datos) });
}

// ---------------------------------------------------------------------------
// Navegación del portal (rutas públicas, sin sesión). Todas devuelven { ok, datos } o
// { ok: false, estado, error } con el formato del núcleo, igual que las de clientes.
// ---------------------------------------------------------------------------

// Arma "?a=1&b=2" con los parámetros que tienen valor (omite null, undefined y '').
function consulta(parametros) {
  const pares = Object.entries(parametros).filter(([, valor]) => valor !== null && valor !== undefined && valor !== '');
  return pares.length ? `?${new URLSearchParams(pares).toString()}` : '';
}

// GET /productos/busqueda (buscarProductos). `criterio` usa los nombres del contrato:
// { q, id_categoria, id_comercio, id_marca, precio_minimo, precio_maximo, orden, pagina, por_pagina }.
// 200 → ResultadoBusqueda · 422 ERR-005 (RN-M1-08 u otro parámetro inválido) · 503 ERR-006.
export function buscarProductos(criterio = {}) {
  return llamar(`/productos/busqueda${consulta(criterio)}`);
}

// GET /categorias (listarCategoriasPortal). Menú del portal: principales con sus subcategorías.
// 200 → CategoriaPortal[] · 503 ERR-006.
export function listarCategorias() {
  return llamar('/categorias');
}

// GET /productos/{id_producto} (obtenerFichaProducto).
// 200 → FichaProducto · 404 ERR-004 (no existe o su comercio no está activo) · 503 ERR-006.
export function obtenerFichaProducto(idProducto) {
  return llamar(`/productos/${idProducto}`);
}

// GET /destacados (listarDestacados). limite de 1 a 24 (por defecto 8; fuera de rango el backend lo ajusta).
// 200 → Destacados. Si M2 o M3 no responden, la sección viene vacía y se nombra en secciones_no_disponibles.
export function listarDestacados(limite = 8) {
  return llamar(`/destacados${consulta({ limite })}`);
}

// GET /comercios (listarComercios). pagina >= 1, por_pagina de 1 a 50.
// 200 → ListadoComercios · 422 ERR-005 · 503 ERR-006.
export function listarComercios(pagina = 1, porPagina = 20) {
  return llamar(`/comercios${consulta({ pagina, por_pagina: porPagina })}`);
}

// GET /comercios/{id_comercio}/micrositio (obtenerMicrositio, CU-009).
// 200 → Micrositio (productos_disponibles false si M3 no respondió) · 404 ERR-004 · 422 ERR-005 · 503 ERR-006.
export function obtenerMicrositio(idComercio, pagina = 1, porPagina = 20) {
  return llamar(`/comercios/${idComercio}/micrositio${consulta({ pagina, por_pagina: porPagina })}`);
}
