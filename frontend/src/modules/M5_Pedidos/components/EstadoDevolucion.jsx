import { Check, Clock, FileText, RotateCcw, X } from 'lucide-react';
import { colones } from '../services/calculos';
import { formatearFecha } from '../services/estados';
import { urlEvidencia } from '../services/devolucionesApi';

const ESTADOS = {
  solicitada: { etiqueta: 'En revisión por el comercio', caja: 'border-amber-200 bg-amber-50 text-amber-900', Icono: Clock, icono: 'text-amber-700' },
  aprobada: { etiqueta: 'Aprobada', caja: 'border-green-200 bg-green-50 text-green-900', Icono: Check, icono: 'text-green-700' },
  rechazada: { etiqueta: 'Rechazada', caja: 'border-red-200 bg-red-50 text-red-900', Icono: X, icono: 'text-red-700' },
};

const REEMBOLSO = {
  pendiente: 'Reembolso en trámite',
  procesado: 'Reembolso procesado',
  error: 'Reembolso pendiente de resolver por el comercio',
};

/**
 * Estado de una devolución para el cliente (RF-019): qué pidió, cómo va la revisión del comercio
 * y, si se aprobó, el estado del reembolso. Recibe un elemento de `devoluciones` de la API.
 */
export function EstadoDevolucion({ devolucion, idCliente = null }) {
  const info = ESTADOS[devolucion.estado] ?? ESTADOS.solicitada;
  const Icono = info.Icono;

  return (
    <div className={`rounded-lg border px-3 py-2.5 text-xs flex items-start gap-2 ${info.caja}`} data-testid="estado-devolucion">
      <Icono className={`w-4 h-4 shrink-0 mt-0.5 ${info.icono}`} aria-hidden="true" />
      <div className="min-w-0 space-y-1">
        <p className="font-bold">
          Devolución {devolucion.tipo === 'total' ? 'total' : 'parcial'} #{devolucion.id_devolucion} · {info.etiqueta}
        </p>
        <p className="break-words">Motivo: {devolucion.motivo}</p>
        <p>
          Monto: <span className="font-semibold">{colones(Number(devolucion.monto_solicitado ?? 0))}</span>
          {' · '}Solicitada el {formatearFecha(devolucion.fecha_solicitud)}
        </p>

        {devolucion.estado === 'rechazada' && devolucion.motivo_rechazo && (
          <p className="break-words">Motivo del rechazo: {devolucion.motivo_rechazo}</p>
        )}

        {devolucion.estado === 'aprobada' && devolucion.estado_reembolso && (
          <p className="inline-flex items-center gap-1 font-semibold">
            <RotateCcw className="w-3.5 h-3.5" aria-hidden="true" />
            {REEMBOLSO[devolucion.estado_reembolso] ?? devolucion.estado_reembolso}
          </p>
        )}

        {(devolucion.evidencias ?? []).length > 0 && (
          <ul className="flex flex-wrap gap-x-3 gap-y-1 pt-0.5">
            {devolucion.evidencias.map((e) => (
              <li key={e.id_evidencia}>
                <a href={urlEvidencia(e, { idCliente })} target="_blank" rel="noreferrer"
                  className="inline-flex items-center gap-1 underline underline-offset-2">
                  <FileText className="w-3.5 h-3.5" aria-hidden="true" />
                  <span className="truncate max-w-40">{e.nombre_original}</span>
                </a>
              </li>
            ))}
          </ul>
        )}
      </div>
    </div>
  );
}

export default EstadoDevolucion;
