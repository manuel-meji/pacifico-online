import { useEffect, useState } from 'react';
import { History, Loader2 } from 'lucide-react';
import { EstadoHacienda } from '../components/DetalleFactura';
import { consultarHistorial } from '../api/pagosApi';
import { colones, fecha } from '../lib/documentos';

const SIN_RESULTADOS = 'ERR-M6-020';
const NOMBRES_MEDIO = { tarjeta: 'Tarjeta', sinpe: 'SINPE Móvil', paypal: 'PayPal' };
const ESTADOS_PAGO = {
  confirmado: 'bg-green-50 text-green-700 border-green-200',
  pendiente: 'bg-amber-50 text-amber-700 border-amber-200',
  rechazado: 'bg-red-50 text-red-700 border-red-200',
};

/**
 * Historial personal de pagos y facturas (CU-008, RN-008 / RN-12).
 * No pide ningún filtro: el backend fija el del cliente con sesión.
 * Sin sesión de cliente responde 422 (falta el filtro) y se invita a ingresar.
 */
export default function HistorialPersonalView({ api = { consultarHistorial } }) {
  const [estado, setEstado] = useState('cargando'); // cargando | listo | vacio | sinSesion | error
  const [datos, setDatos] = useState(null);
  const [error, setError] = useState(null);

  useEffect(() => {
    let activo = true;
    api.consultarHistorial({})
      .then((r) => { if (activo) { setDatos(r); setEstado('listo'); } })
      .catch((e) => {
        if (!activo) return;
        if (e.codigo === SIN_RESULTADOS) setEstado('vacio');
        else if ([401, 403, 422].includes(e.estadoHttp)) setEstado('sinSesion');
        else { setError(e.mensaje || e.message); setEstado('error'); }
      });
    return () => { activo = false; };
  }, []);

  const facturasDe = (idPedido) => (datos?.facturas ?? []).filter((f) => f.id_pedido === idPedido);

  return (
    <section className="mx-auto w-full max-w-xl space-y-4">
      <h2 className="flex items-center gap-2 text-lg font-bold text-brandDarkBlue">
        <History className="h-5 w-5" aria-hidden /> Historial de pagos
      </h2>

      {estado === 'cargando' && (
        <p className="flex items-center gap-2 text-sm text-gray-500"><Loader2 className="h-4 w-4 animate-spin" aria-hidden /> Cargando tu historial…</p>
      )}

      {estado === 'sinSesion' && (
        <p role="status" className="rounded-lg border border-gray-200 bg-white p-4 text-sm text-gray-700">
          Para ver tu historial de pagos, ingresá con tu cuenta de cliente en el <a href="/m1.html" className="font-semibold text-brandBlue underline">portal</a>.
        </p>
      )}

      {estado === 'vacio' && (
        <p role="status" className="rounded-lg border border-gray-200 bg-white p-4 text-sm text-gray-700">Todavía no tenés pagos registrados.</p>
      )}

      {estado === 'error' && (
        <p role="alert" className="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">{error}</p>
      )}

      {estado === 'listo' && datos && (
        <ul className="space-y-3">
          {datos.pagos.map((p) => {
            const facturas = facturasDe(p.id_pedido);
            return (
              <li key={p.id} className="rounded-xl border border-gray-200 bg-white p-4 text-sm">
                <div className="flex flex-wrap items-start justify-between gap-2">
                  <p className="min-w-0 font-semibold text-gray-900">Pedido #{p.id_pedido} · Pago #{p.id}</p>
                  <span className={`rounded-full border px-2.5 py-0.5 text-xs font-semibold ${ESTADOS_PAGO[p.estado] ?? ESTADOS_PAGO.pendiente}`}>{p.estado}</span>
                </div>
                <dl className="mt-2 space-y-1">
                  <div className="flex justify-between gap-4"><dt className="text-gray-500">Medio</dt><dd>{NOMBRES_MEDIO[p.medio_pago] ?? p.medio_pago}</dd></div>
                  <div className="flex justify-between gap-4"><dt className="text-gray-500">Monto</dt><dd className="font-bold">{colones(p.monto_total)}</dd></div>
                  <div className="flex justify-between gap-4"><dt className="text-gray-500">Fecha</dt><dd className="text-right">{fecha(p.fecha_confirmacion ?? p.fecha_creacion)}</dd></div>
                </dl>

                <div className="mt-3 border-t border-gray-100 pt-3">
                  <p className="mb-1 text-xs font-semibold text-gray-700">Facturas ({facturas.length})</p>
                  {facturas.length === 0 && <p className="text-xs text-gray-500">Todavía no hay facturas emitidas para este pago.</p>}
                  <ul className="space-y-1.5">
                    {facturas.map((f) => (
                      <li key={f.id} className="flex flex-wrap items-center justify-between gap-2 text-xs">
                        <span className="break-all font-medium text-gray-900">{f.consecutivo}</span>
                        <span className="flex items-center gap-2"><strong>{colones(f.monto_total)}</strong><EstadoHacienda estado={f.estado_hacienda} /></span>
                      </li>
                    ))}
                  </ul>
                  {facturas.length > 0 && (
                    <a href={`/m6.html?pedido=${p.id_pedido}`} className="mt-2 inline-block text-xs font-semibold text-brandBlue hover:underline">Ver detalle de las facturas</a>
                  )}
                </div>
              </li>
            );
          })}
        </ul>
      )}
    </section>
  );
}