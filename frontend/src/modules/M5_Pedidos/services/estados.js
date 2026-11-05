// Etiquetas y colores de estado_pedido / estado_subpedido (valores reales del backend de M5).
// 'confirmado' solo aparece en el respaldo de PedidosService::obtenerPedido; se trata como pendiente de pago.
export const ESTADOS = {
  pendiente: { etiqueta: 'Pendiente de pago', clases: 'bg-amber-50 text-amber-800 border-amber-200' },
  confirmado: { etiqueta: 'Pendiente de pago', clases: 'bg-amber-50 text-amber-800 border-amber-200' },
  pagado: { etiqueta: 'Pagado', clases: 'bg-blue-50 text-blue-800 border-blue-200' },
  en_preparacion: { etiqueta: 'En preparación', clases: 'bg-violet-50 text-violet-800 border-violet-200' },
  despachado: { etiqueta: 'Despachado', clases: 'bg-sky-50 text-sky-800 border-sky-200' },
  entregado: { etiqueta: 'Entregado', clases: 'bg-green-50 text-green-800 border-green-200' },
  cancelado: { etiqueta: 'Cancelado', clases: 'bg-red-50 text-red-700 border-red-200' },
};

const DESCONOCIDO = { clases: 'bg-gray-100 text-gray-700 border-gray-200' };

export function infoEstado(estado) {
  const clave = String(estado ?? '').toLowerCase();
  return ESTADOS[clave] ?? { ...DESCONOCIDO, etiqueta: estado || 'Sin estado' };
}

export const etiquetaEstado = (estado) => infoEstado(estado).etiqueta;

// Fecha legible en es-CR a partir de un ISO 8601 ("2026-11-05T07:14:09.000000Z").
export function formatearFecha(iso) {
  if (!iso) return '—';
  const fecha = new Date(iso);
  if (Number.isNaN(fecha.getTime())) return String(iso);
  return fecha.toLocaleString('es-CR', { dateStyle: 'medium', timeStyle: 'short' });
}
