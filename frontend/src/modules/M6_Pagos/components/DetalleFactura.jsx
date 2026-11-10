import { useState } from 'react';
import { CheckCircle2, Clock, Code, Download, XCircle } from 'lucide-react';
import FormularioNotaAjuste from './FormularioNotaAjuste';
import { colones, descargarXml, fecha } from '../lib/documentos';

const ESTADOS_HACIENDA = {
  aceptada: { Icono: CheckCircle2, clase: 'bg-green-50 text-green-700 border-green-200', texto: 'Aceptada por Hacienda' },
  pendiente: { Icono: Clock, clase: 'bg-amber-50 text-amber-700 border-amber-200', texto: 'Pendiente en Hacienda' },
  rechazada: { Icono: XCircle, clase: 'bg-red-50 text-red-700 border-red-200', texto: 'Rechazada por Hacienda' },
};

export function EstadoHacienda({ estado }) {
  const e = ESTADOS_HACIENDA[estado] ?? ESTADOS_HACIENDA.pendiente;
  return (
    <span className={`inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-semibold ${e.clase}`}>
      <e.Icono className="h-3.5 w-3.5" aria-hidden /> {e.texto}
    </span>
  );
}

/**
 * CU-004 (y CU-005 / CU-006 desde la misma tarjeta).
 * props.factura = schema Factura del contrato. Si se pasa props.api, se reenvía al formulario de notas.
 */
export default function DetalleFactura({ factura, api }) {
  const [verXml, setVerXml] = useState(false);
  const [notas, setNotas] = useState([]);

  return (
    <article className="space-y-4 rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
      <header className="flex flex-wrap items-start justify-between gap-2">
        <div className="min-w-0">
          <h3 className="text-base font-bold text-brandDarkBlue">Factura {factura.consecutivo}</h3>
          <p className="text-xs text-gray-500">Comercio {factura.id_comercio} · Subpedido {factura.id_subpedido}</p>
        </div>
        <EstadoHacienda estado={factura.estado_hacienda} />
      </header>

      <dl className="space-y-1 rounded-lg bg-brandLightBg p-3 text-sm">
        <div className="flex justify-between gap-4"><dt>Total</dt><dd className="font-extrabold">{colones(factura.monto_total)}</dd></div>
        <div className="flex justify-between gap-4"><dt>Emitida</dt><dd>{fecha(factura.fecha_emision)}</dd></div>
        {factura.clave_numerica && (
          <div className="flex flex-col gap-0.5 sm:flex-row sm:justify-between sm:gap-4">
            <dt>Clave</dt><dd className="break-all font-mono text-xs">{factura.clave_numerica}</dd>
          </div>
        )}
      </dl>

      <div className="flex flex-wrap gap-2">
        <button type="button" onClick={() => descargarXml(factura.xml, factura.consecutivo)}
          className="flex items-center justify-center gap-2 rounded-lg bg-brandDarkBlue px-4 py-2 text-sm font-bold text-white transition duration-200 hover:bg-brandBlue">
          <Download className="h-4 w-4" aria-hidden /> Descargar XML
        </button>
        <button type="button" onClick={() => setVerXml((v) => !v)} aria-expanded={verXml}
          className="flex items-center gap-2 rounded-lg border border-brandBlue px-4 py-2 text-sm font-semibold text-brandBlue transition hover:bg-blue-50">
          <Code className="h-4 w-4" aria-hidden /> {verXml ? 'Ocultar XML' : 'Ver XML'}
        </button>
      </div>

      {verXml && (
        <pre className="max-h-72 overflow-auto rounded-lg bg-gray-900 p-3 text-xs leading-relaxed text-gray-100">{factura.xml}</pre>
      )}

      {notas.length > 0 && (
        <section aria-label="Notas emitidas">
          <h4 className="mb-2 text-sm font-semibold text-brandDarkBlue">Notas emitidas</h4>
          <ul className="divide-y divide-gray-100 rounded-lg border border-gray-200">
            {notas.map((n) => (
              <li key={`${n.tipo}-${n.id}`} className="flex flex-wrap items-center justify-between gap-2 p-3 text-sm">
                <div className="min-w-0">
                  <p className="font-semibold">
                    {n.tipo === 'credito' ? 'Nota de crédito' : 'Nota de débito'} #{n.id} · {n.tipo === 'credito' ? '−' : '+'}{colones(n.monto)}
                  </p>
                  <p className="truncate text-xs text-gray-500">{n.motivo} · {fecha(n.fecha_emision)}</p>
                </div>
                <button type="button" onClick={() => descargarXml(n.xml, `nota-${n.tipo}-${n.id}`)}
                  className="flex items-center gap-1.5 rounded-lg border border-brandBlue px-3 py-1.5 text-xs font-semibold text-brandBlue transition hover:bg-blue-50">
                  <Download className="h-3.5 w-3.5" aria-hidden /> XML
                </button>
              </li>
            ))}
          </ul>
        </section>
      )}

      <FormularioNotaAjuste factura={factura} api={api} onEmitida={(nota) => setNotas((prev) => [nota, ...prev])} />
    </article>
  );
}
