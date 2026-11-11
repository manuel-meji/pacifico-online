// Gestión de promociones (contrato M1 1.4.0, SC-08). Usa el mismo cliente HTTP de m1Api.js
// (CSRF de Sanctum y errores del núcleo): cada función devuelve { ok, datos } o { ok: false, estado, error }.
// Errores: 401 ERR-001 · 403 ERR-002 · 404 ERR-004 · 409 ERR-M1-004 (traslape) y ERR-M1-005 (no modificable) ·
// 422 ERR-005 con detalles por campo · 503 ERR-006.
import { consulta, llamarM1 as llamar } from './m1Api';

// ---------------------------------------------------------------------------
// Comercio (rol encargado_comercio). El comercio sale de la sesión.
// ---------------------------------------------------------------------------

// GET /comercio/promociones (listarPromocionesComercio). estado: programada | vigente | finalizada | desactivada | oculta.
export function listarPromocionesComercio(estado = null) {
  return llamar(`/comercio/promociones${consulta({ estado })}`);
}

// POST /comercio/promociones (crearPromocion). datos sigue el esquema DatosPromocion. 201 → Promocion.
export function crearPromocion(datos) {
  return llamar('/comercio/promociones', { method: 'POST', body: JSON.stringify(datos) });
}

// PUT /comercio/promociones/{id} (actualizarPromocion). Programada: DatosPromocion completo.
// Ya iniciada: solo { fecha_fin } anterior a la actual (AdelantoPromocion).
export function actualizarPromocion(idPromocion, datos) {
  return llamar(`/comercio/promociones/${idPromocion}`, { method: 'PUT', body: JSON.stringify(datos) });
}

// POST /comercio/promociones/{id}/desactivar (desactivarPromocion).
export function desactivarPromocion(idPromocion) {
  return llamar(`/comercio/promociones/${idPromocion}/desactivar`, { method: 'POST' });
}

// ---------------------------------------------------------------------------
// Plataforma (rol encargado_plataforma).
// ---------------------------------------------------------------------------

// GET /administracion/promociones (listarPromocionesAdministracion), con filtro opcional por estado y comercio.
export function listarPromocionesAdministracion({ estado = null, idComercio = null } = {}) {
  return llamar(`/administracion/promociones${consulta({ estado, id_comercio: idComercio })}`);
}

// PUT /administracion/promociones/{id}/orden (ordenarPromocion). orden de 1 a 32767, o null para quitarlo.
export function ordenarPromocion(idPromocion, orden) {
  return llamar(`/administracion/promociones/${idPromocion}/orden`, { method: 'PUT', body: JSON.stringify({ orden }) });
}

// POST /administracion/promociones/{id}/ocultar (ocultarPromocion). Oculta ya no rebaja el precio.
export function ocultarPromocion(idPromocion, motivo) {
  return llamar(`/administracion/promociones/${idPromocion}/ocultar`, { method: 'POST', body: JSON.stringify({ motivo }) });
}

// POST /administracion/promociones/{id}/mostrar (mostrarPromocion).
export function mostrarPromocion(idPromocion) {
  return llamar(`/administracion/promociones/${idPromocion}/mostrar`, { method: 'POST' });
}
