// Errores del contrato v1.2.0 de M4 en el formato estándar del núcleo (5.5): { error: { codigo, mensaje, detalles } }.
// El doble de la API (inventarioApi.js) los lanza con throw; las vistas leen err.error.
const MENSAJES = {
  // Mismos textos que ErrorInventarioException (Jorge), ErrorProveedorException (sección 9) y el catálogo de errores del contrato.
  'ERR-001': 'Tu sesión expiró, inicia sesión de nuevo.',
  'ERR-002': 'No se encuentra autorizado para realizar esta acción.',
  'ERR-003': 'El recurso solicitado no existe o no es accesible.',
  'ERR-005': 'Revisa los datos marcados (detalle por campo en detalles).',
  'ERR-006': 'Servicio temporalmente no disponible, intentar de nuevo.',
  'ERR-M4-001': 'No hay existencias suficientes para la cantidad solicitada.',
  'ERR-M4-002': 'No hay existencias registradas para la variación en esa ubicación.',
  'ERR-M4-004': 'No se encontró la reserva.',
  'ERR-M4-005': 'La reserva venció y ya no puede confirmarse.',
  'ERR-M4-006': 'La reserva no está en un estado que permita esta operación.',
  'ERR-M4-007': 'El pedido o subpedido no corresponde a la reserva.',
  'ERR-M4-008': 'La reserva ya se convirtió en salida y no puede liberarse.',
  'ERR-M4-009': 'El pedido aún no está pagado.',
  'ERR-M4-013': 'No se encontró la variación.',
  'ERR-M4-014': 'No se encontró la ubicación.',
  'ERR-M4-015': 'El comercio no está habilitado para registrar movimientos.',
};

// `mensaje` permite el texto propio de la falla (ERR-002 trae el motivo, como AutorizacionesFake).
export function errorContrato(codigo, detalles = null, mensaje = null) {
  return { error: { codigo, mensaje: mensaje ?? MENSAJES[codigo] ?? 'No se pudo completar la operación.', detalles } };
}
