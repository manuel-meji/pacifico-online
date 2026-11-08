// Cliente HTTP de M4 (contrato contracts/m4-inventario/v1/openapi_M4_v1.3.0.yaml, servidor /api/m4/v1).
// Mismo esquema que M5 (m5Api.js): sesión del núcleo con cookie de Sanctum, CSRF en las escrituras y errores en el
// formato del núcleo { error: { codigo, mensaje, detalles } }. La vista no tiene reglas de negocio: todo lo valida
// InventarioModuleInterface en el servidor, y el aislamiento por comercio (RN-12) lo aplica el núcleo.
import { errorContrato } from './errores';

const BASE = '/api/m4/v1';
const METODOS_CON_CSRF = ['POST', 'PUT', 'PATCH', 'DELETE'];

function leerCookie(nombre) {
  const par = document.cookie.split('; ').find((c) => c.startsWith(`${nombre}=`));
  return par ? decodeURIComponent(par.slice(nombre.length + 1)) : null;
}

const obtenerCookieCsrf = () => fetch('/sanctum/csrf-cookie', { credentials: 'include' });

async function llamar(url, opciones = {}, reintentado = false) {
  const metodo = (opciones.method || 'GET').toUpperCase();
  const xsrf = leerCookie('XSRF-TOKEN');
  let resp;
  try {
    resp = await fetch(url, {
      ...opciones,
      method: metodo,
      credentials: 'include',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        ...(xsrf ? { 'X-XSRF-TOKEN': xsrf } : {}),
      },
    });
  } catch {
    throw { ...errorContrato('ERR-006', { motivo: 'sin_conexion' }), status: 0 };
  }

  // 419: token CSRF ausente o vencido. Se pide uno nuevo y se reintenta una sola vez.
  if (resp.status === 419 && !reintentado && METODOS_CON_CSRF.includes(metodo)) {
    await obtenerCookieCsrf();
    return llamar(url, opciones, true);
  }

  if (resp.status === 204) return null;
  const cuerpo = await resp.json().catch(() => ({}));
  if (!resp.ok) {
    const error = cuerpo.error ?? errorContrato(resp.status === 401 ? 'ERR-001' : 'ERR-006').error;
    throw { error, status: resp.status };
  }
  return cuerpo;
}

const consulta = (params) => {
  const q = new URLSearchParams();
  Object.entries(params).forEach(([k, v]) => { if (v !== undefined && v !== null && v !== '') q.set(k, v); });
  const texto = q.toString();
  return texto ? `?${texto}` : '';
};

// ---------- Sesión del núcleo ----------

export async function iniciarSesion(correo, contrasena) {
  await obtenerCookieCsrf();
  return llamar('/login', { method: 'POST', body: JSON.stringify({ correo, contrasena }) });
}

export const cerrarSesion = () => llamar('/logout', { method: 'POST' });

// GET /api/user del núcleo: null si no hay sesión.
export async function usuarioActual() {
  try {
    return (await llamar('/api/user')).user;
  } catch (falla) {
    if (falla.status === 401) return null;
    throw falla;
  }
}

// ---------- API de M4 para un comercio ----------

export function crearApi(idComercio) {
  return {
    idComercio,

    // ObtenerComercioActivo de M2 (/api/m2/v1): se consulta al abrir la pantalla de operaciones (H9).
    consultarEstadoComercio: () => llamar(`/api/m2/v1/comercios/${idComercio}/estado`),

    // ObtenerSedesComercio de M2: nombres de las ubicaciones (id_sede = id_ubicacion). Si M2 no responde, se usan los ids.
    async consultarSedes() {
      try {
        return (await llamar(`/api/m2/v1/comercios/${idComercio}/sedes`)).sedes ?? [];
      } catch {
        return [];
      }
    },

    // GET /existencias → { datos: Existencia[], paginacion }
    listarExistencias: (filtros = {}) => llamar(`${BASE}/existencias${consulta({ id_comercio: idComercio, por_pagina: 200, ...filtros })}`),

    // PUT /existencias/{id}/stock-minimo → Existencia
    fijarStockMinimo: (idExistencia, stockMinimo) => llamar(`${BASE}/existencias/${idExistencia}/stock-minimo`, {
      method: 'PUT', body: JSON.stringify({ stock_minimo: stockMinimo }),
    }),

    // POST /movimientos (MovimientoSolicitud) → 201 Movimiento
    registrarMovimientoManual: (solicitud) => llamar(`${BASE}/movimientos`, {
      method: 'POST', body: JSON.stringify({ id_comercio: idComercio, ...solicitud }),
    }),

    // GET /kardex → KardexRespuesta { datos, paginacion }, del más reciente al más antiguo
    consultarKardex: (filtros) => llamar(`${BASE}/kardex${consulta({ id_comercio: idComercio, ...filtros })}`),
  };
}

export const disponible = (e) => e.cantidad_disponible ?? e.cantidad_fisica - e.cantidad_reservada;

export const nombreVariacion = (e) => (e?.sku ? `${e.sku} · variación ${e.id_variacion}` : `Variación ${e?.id_variacion}`);

export const nombreSede = (sedes, id) => sedes.find((s) => s.id_sede === id)?.nombre ?? `Ubicación ${id}`;
