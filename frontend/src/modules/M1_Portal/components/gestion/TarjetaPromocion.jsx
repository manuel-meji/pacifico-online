import Icono from '../IconoComercio';
import { colones } from '../../services/formato';
import { fechaHoraCostaRica } from '../../services/horaCostaRica';
import { BadgeEstado } from './promocionesUi';

// Descuento legible: "20 % de descuento" o "Precio fijo ₡12 500".
export const textoValor = (promocion) =>
  promocion.tipo === 'porcentaje'
    ? `${Number(promocion.valor).toLocaleString('es-CR')} % de descuento`
    : `Precio fijo ${colones(promocion.valor)}`;

// Una promoción del esquema Promocion, con sus acciones (botones que pasa la vista según el rol y el estado).
export default function TarjetaPromocion({ promocion, nombreProducto, nombreComercio, children }) {
  const alcance = promocion.alcance === 'producto'
    ? 'Todo el producto'
    : `${promocion.variaciones.length} ${promocion.variaciones.length === 1 ? 'variación' : 'variaciones'}`;

  return (
    <article className="flex h-full min-w-0 flex-col gap-3 rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
      <div className="flex flex-wrap items-start justify-between gap-2">
        <div className="min-w-0">
          <h3 className="break-words font-bold text-gray-900">{promocion.titulo}</h3>
          <p className="mt-0.5 break-words text-sm text-gray-600">
            {nombreProducto}{nombreComercio ? ` · ${nombreComercio}` : ''}
          </p>
        </div>
        <BadgeEstado estado={promocion.estado} />
      </div>

      <dl className="grid gap-x-4 gap-y-1 text-sm sm:grid-cols-2">
        <div className="flex items-center gap-2"><Icono nombre="etiqueta" className="h-4 w-4 shrink-0 text-red-600" /><dt className="sr-only">Descuento</dt><dd className="font-semibold text-gray-900">{textoValor(promocion)}</dd></div>
        <div className="flex items-center gap-2"><Icono nombre="paquete" className="h-4 w-4 shrink-0 text-brandBlue" /><dt className="sr-only">Alcance</dt><dd className="text-gray-700">{alcance}</dd></div>
        <div className="flex items-start gap-2 sm:col-span-2"><Icono nombre="calendario" className="mt-0.5 h-4 w-4 shrink-0 text-brandBlue" /><dt className="sr-only">Vigencia</dt>
          <dd className="text-gray-700">{fechaHoraCostaRica(promocion.fecha_inicio)} a {fechaHoraCostaRica(promocion.fecha_fin)} <span className="text-xs text-gray-500">(hora de Costa Rica)</span></dd>
        </div>
        {promocion.orden != null && (
          <div className="flex items-center gap-2"><Icono nombre="ordenar" className="h-4 w-4 shrink-0 text-brandBlue" /><dt className="sr-only">Orden</dt><dd className="text-gray-700">Posición {promocion.orden} en el portal</dd></div>
        )}
      </dl>

      {promocion.estado === 'oculta' && promocion.motivo_ocultamiento && (
        <p className="rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900">
          <span className="font-semibold">Motivo de la plataforma:</span> {promocion.motivo_ocultamiento}
        </p>
      )}

      {children && <div className="mt-auto flex flex-wrap gap-2 border-t border-gray-100 pt-3">{children}</div>}
    </article>
  );
}
