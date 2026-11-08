import { useEffect, useState } from 'react';
import { consultarHistorial } from '../api/pagosApi';

/** CU-008. El aislamiento por comercio (RN-M6-08) lo aplica el backend (BelongsToComercio); la vista solo pide el historial de la sesión. */
export default function HistorialComercioView({ idComercio, api = { consultarHistorial } }) {
  const [datos, setDatos] = useState(null);
  const [error, setError] = useState(null);
  useEffect(() => {
    api.consultarHistorial({ idComercio }).then(setDatos).catch((e) => setError(e.message));
  }, [idComercio]);

  if (error) return <p role="alert" className="text-sm text-red-600">{error}</p>;
  if (!datos) return <p className="text-sm text-gray-500">Cargando historial…</p>;
  if (!datos.pagos.length && !datos.facturas.length) return <p className="text-sm">Aún no hay pagos ni facturas para este comercio.</p>;

  return (
    <section className="space-y-4">
      <h2 className="text-lg font-bold text-brandDarkBlue">Historial de pagos y facturas</h2>
      <div className="overflow-x-auto rounded-xl border border-gray-200 bg-white">
        <table className="w-full text-left text-sm">
          <thead className="bg-brandLightBg"><tr><th className="p-3">Pago</th><th className="p-3">Medio</th><th className="p-3">Monto</th><th className="p-3">Estado</th></tr></thead>
          <tbody className="divide-y divide-gray-100">
            {datos.pagos.map((p) => (
              <tr key={p.id}><td className="p-3 font-mono">{p.id}</td><td className="p-3">{p.medio_pago}</td><td className="p-3">   ₡{Number(p.monto_total).toLocaleString('es-CR', { minimumFractionDigits: 2 })}</td><td className="p-3">{p.estado}</td></tr>
            ))}
          </tbody>
        </table>
      </div>
    </section>
  );
}
