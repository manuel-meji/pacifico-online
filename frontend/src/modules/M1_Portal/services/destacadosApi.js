// Cliente de la API de productos destacados de M1 (contrato 1.4.0, SC-08), para el panel de gestión.
// Respuestas: { ok: true, datos } o { ok: false, estado, error: { codigo, mensaje, detalles } }.
import { consulta, llamarM1 } from './m1Api';

const json = (datos) => ({ body: JSON.stringify(datos) });

// GET /destacados/tarifas (pública): [{ periodo_dias, monto, moneda, actualizada }].
export const listarTarifas = () => llamarM1('/destacados/tarifas');

// PUT /administracion/destacados/tarifas/{periodo_dias} (encargado_plataforma).
export const definirTarifa = (periodoDias, monto) =>
  llamarM1(`/administracion/destacados/tarifas/${periodoDias}`, { method: 'PUT', ...json({ monto }) });

// POST /comercio/destacados/solicitudes (encargado_comercio; el comercio sale de la sesión).
// 201 · 404 ERR-004 (producto ajeno o inexistente) · 409 ERR-M1-003 · 422 ERR-005.
export const solicitarDestacado = (idProducto, periodoDias) =>
  llamarM1('/comercio/destacados/solicitudes', { method: 'POST', ...json({ id_producto: idProducto, periodo_dias: periodoDias }) });

// GET /comercio/destacados/solicitudes: solicitudes del comercio de la sesión.
export const listarSolicitudesComercio = (estado) =>
  llamarM1(`/comercio/destacados/solicitudes${consulta({ estado })}`);

// GET /administracion/destacados/solicitudes: todas, con filtro opcional de estado y comercio.
export const listarSolicitudesAdministracion = ({ estado, idComercio } = {}) =>
  llamarM1(`/administracion/destacados/solicitudes${consulta({ estado, id_comercio: idComercio })}`);

// POST .../{id}/aprobar · 409 ERR-M1-002 (máximo 3 activos) o ERR-M1-003 (ya resuelta o ya destacado).
export const aprobarSolicitud = (idSolicitud) =>
  llamarM1(`/administracion/destacados/solicitudes/${idSolicitud}/aprobar`, { method: 'POST' });

// POST .../{id}/rechazar con motivo obligatorio.
export const rechazarSolicitud = (idSolicitud, motivo) =>
  llamarM1(`/administracion/destacados/solicitudes/${idSolicitud}/rechazar`, { method: 'POST', ...json({ motivo }) });

// POST /administracion/destacados: la plataforma destaca sin costo, con fecha y hora libres (ISO 8601).
export const destacarProductoPlataforma = ({ idProducto, fechaInicio, fechaFin }) =>
  llamarM1('/administracion/destacados', { method: 'POST', ...json({ id_producto: idProducto, fecha_inicio: fechaInicio, fecha_fin: fechaFin }) });

// Fecha y hora de un <input type="datetime-local"> (hora de Costa Rica, UTC-6, sin horario de verano) a ISO 8601.
export const aIsoCostaRica = (valorLocal) => (valorLocal ? `${valorLocal}:00-06:00` : '');
