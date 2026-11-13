// Gestión de cupones de descuento (contrato M1 1.5.0, SC-11). Usa el mismo cliente HTTP de m1Api.js
// (CSRF de Sanctum y errores del núcleo): cada función devuelve { ok, datos } o { ok: false, estado, error }.
// Errores: 401 ERR-001 · 403 ERR-002 · 404 ERR-004 · 409 ERR-M1-007 (código repetido) · 422 ERR-005 con detalles por campo.
import { consulta, llamarM1 as llamar } from './m1Api';

// ---------------------------------------------------------------------------
// Portal público (contrato 1.6.0, SC-13).
// ---------------------------------------------------------------------------

// GET /cupones/vigentes (listarCuponesVigentes). Pública: hasta 12 cupones globales que se pueden usar hoy.
// 200 → { cupones: CuponPublico[] }.
export function listarCuponesVigentes() {
  return llamar('/cupones/vigentes');
}

// ---------------------------------------------------------------------------
// Comercio (rol encargado_comercio). El comercio sale de la sesión.
// ---------------------------------------------------------------------------

// GET /comercio/cupones (listarCuponesComercio). estado: programado | vigente | vencido | agotado | desactivado.
export function listarCuponesComercio(estado = null) {
  return llamar(`/comercio/cupones${consulta({ estado })}`);
}

// POST /comercio/cupones (crearCuponComercio). datos sigue el esquema DatosCupon. 201 → Cupon.
export function crearCuponComercio(datos) {
  return llamar('/comercio/cupones', { method: 'POST', body: JSON.stringify(datos) });
}

// POST /comercio/cupones/{id}/desactivar (desactivarCuponComercio). No se puede reactivar.
export function desactivarCuponComercio(idCupon) {
  return llamar(`/comercio/cupones/${idCupon}/desactivar`, { method: 'POST' });
}

// ---------------------------------------------------------------------------
// Plataforma (rol encargado_plataforma).
// ---------------------------------------------------------------------------

// GET /administracion/cupones (listarCuponesAdministracion). soloGlobales deja los de la plataforma.
export function listarCuponesAdministracion({ estado = null, idComercio = null, soloGlobales = false } = {}) {
  return llamar(`/administracion/cupones${consulta({ estado, id_comercio: idComercio, ambito: soloGlobales ? 'global' : null })}`);
}

// POST /administracion/cupones (crearCuponGlobal). Cupón global de la plataforma (sin comercio).
export function crearCuponGlobal(datos) {
  return llamar('/administracion/cupones', { method: 'POST', body: JSON.stringify(datos) });
}

// POST /administracion/cupones/{id}/desactivar (desactivarCuponAdministracion). Cualquier cupón.
export function desactivarCuponAdministracion(idCupon) {
  return llamar(`/administracion/cupones/${idCupon}/desactivar`, { method: 'POST' });
}

// ---------------------------------------------------------------------------
// Checkout (cliente con sesión). Lo consume el checkout de M5.
// ---------------------------------------------------------------------------

// POST /cupones/validar (validarCupon). datos: { codigo, subtotal, ids_comercios, subtotales_por_comercio }.
// 200 → ResultadoCupon, también si no aplica (valido=false con motivo y mensaje).
export function validarCupon(datos) {
  return llamar('/cupones/validar', { method: 'POST', body: JSON.stringify(datos) });
}
