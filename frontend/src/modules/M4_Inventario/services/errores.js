// Errores del contrato v1.3.0 de M4 en el formato estándar del núcleo (5.5): { error: { codigo, mensaje, detalles } }.
// La API los devuelve así; este catálogo solo da un texto cuando la respuesta no trae mensaje (por ejemplo, sin red).
const MENSAJES = {
  'ERR-001': 'Tu sesión expiró, inicia sesión de nuevo.',
  'ERR-002': 'No se encuentra autorizado para realizar esta acción.',
  'ERR-003': 'El recurso solicitado no existe o no es accesible.',
  'ERR-005': 'Revisa los datos marcados (detalle por campo en detalles).',
  'ERR-006': 'Servicio temporalmente no disponible, intentar de nuevo.',
  'ERR-M4-001': 'No hay existencias suficientes para la cantidad solicitada.',
  'ERR-M4-002': 'No hay existencias registradas para la variación en esa ubicación.',
  'ERR-M4-012': 'El rango de fechas no es válido.',
  'ERR-M4-013': 'No se encontró la variación.',
  'ERR-M4-014': 'No se encontró la ubicación.',
  'ERR-M4-015': 'El comercio no está habilitado para registrar movimientos.',
  'ERR-M4-016': 'El conteo físico está desactualizado; vuelve a contar.',
  'ERR-M4-017': 'El ajuste dejaría el stock por debajo de lo comprometido.',
};

export function errorContrato(codigo, detalles = null, mensaje = null) {
  return { error: { codigo, mensaje: mensaje ?? MENSAJES[codigo] ?? 'No se pudo completar la operación.', detalles } };
}

// ERR-005 de Laravel trae en detalles una lista de mensajes por campo; la vista muestra el primero.
export function erroresPorCampo(detalle) {
  if (!detalle || detalle.codigo !== 'ERR-005' || !detalle.detalles) return {};
  return Object.fromEntries(Object.entries(detalle.detalles).map(([campo, v]) => [campo, Array.isArray(v) ? v[0] : String(v)]));
}
