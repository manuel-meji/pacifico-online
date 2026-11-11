// Cliente de la API de administración de noticias de M1 (contrato 1.4.0, SC-08), para el panel de gestión.
// Todas las rutas son solo para encargado_plataforma. Estados: borrador, publicada y archivada.
// Respuestas: { ok: true, datos } o { ok: false, estado, error: { codigo, mensaje, detalles } }.
import { consulta, llamarM1 } from './m1Api';

const json = (datos) => ({ body: JSON.stringify(datos) });

// GET /administracion/noticias (listarNoticiasAdministracion). `estado` es opcional (borrador, publicada
// o archivada). 200 → Noticia[], de la modificada más recientemente a la más antigua · 401 ERR-001 ·
// 403 ERR-002 · 422 ERR-005.
export const listarNoticiasAdministracion = (estado) =>
  llamarM1(`/administracion/noticias${consulta({ estado })}`);

// POST /administracion/noticias (crearNoticia). `datos` sigue DatosNoticia
// (titulo, resumen, contenido, imagen_url, fecha_fin, ids_promocion).
// 201 → Noticia en borrador · 401 ERR-001 · 403 ERR-002 · 422 ERR-005 con detalles por campo.
export const crearNoticia = (datos) =>
  llamarM1('/administracion/noticias', { method: 'POST', ...json(datos) });

// PUT /administracion/noticias/{id_noticia} (actualizarNoticia). Reemplaza todo: `datos` lleva siempre
// los campos completos de DatosNoticia, incluidos los enlaces a promociones.
// 200 → Noticia · 404 ERR-004 · 409 ERR-M1-006 (archivada) · 422 ERR-005.
export const actualizarNoticia = (idNoticia, datos) =>
  llamarM1(`/administracion/noticias/${idNoticia}`, { method: 'PUT', ...json(datos) });

// POST /administracion/noticias/{id_noticia}/publicar (publicarNoticia). `datos` es opcional:
// { fecha_publicacion } en ISO 8601 para programarla; sin valor, se publica ahora.
// 200 → Noticia · 404 ERR-004 · 409 ERR-M1-006 (ya publicada o archivada) · 422 ERR-005.
export const publicarNoticia = (idNoticia, datos = {}) =>
  llamarM1(`/administracion/noticias/${idNoticia}/publicar`, { method: 'POST', ...json(datos) });

// POST /administracion/noticias/{id_noticia}/archivar (archivarNoticia). Sin cuerpo.
// 200 → Noticia · 404 ERR-004 · 409 ERR-M1-006 (ya archivada).
export const archivarNoticia = (idNoticia) =>
  llamarM1(`/administracion/noticias/${idNoticia}/archivar`, { method: 'POST' });

// GET /administracion/promociones (listarPromocionesAdministracion). Promociones de todos los comercios,
// con filtro opcional por estado; el panel pide las vigentes para enlazarlas a una noticia.
// 200 → Promocion[] ({ id_promocion, titulo, estado, … }) · 401 ERR-001 · 403 ERR-002 · 422 ERR-005.
export const listarPromocionesAdministracion = (estado) =>
  llamarM1(`/administracion/promociones${consulta({ estado })}`);
