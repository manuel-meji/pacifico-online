import { useEffect, useState } from 'react';
import { History, Loader2, Search } from 'lucide-react';
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
 * CU-008. Historial de pagos y facturas (GET /api/m6/v1/historial).
 * El aislamiento (RN-008 / RN-12) lo aplica el backend: con sesión de cliente o de comercio,
 * el filtro se fija al propio y pedir el de otro responde 404.
 * Se muestran tarjetas en lugar de tabla para no tener desplazamiento horizontal desde 360 px.
 */
export default function HistorialComercioView({ idComercio = null, idCliente = null, api = { consultarHistorial } }) {
  const [tipo, setTipo] = useState(idCliente && !idComercio ? 'cliente' : 'comercio');
  const [valor, setValor] = useState(String(idComercio ?? idCliente ?? ''));
  const [datos, setDatos] = useState(null);
  const [estado, setEstado] = useState('inicial'); // inicial | cargando | listo | vacio | error
  const [error, setError] = useState(null);

  async function cargar(tipoFiltro, texto) {
    const id = Number(texto);
    if (!Number.isInteger(id) || id < 1) {
      setError('Indique un número de comercio o de cliente válido.');
      setEstado('error');
      return;
    }

    setEstado('cargando');
    setError(null);
    try {
      const r = await api.consultarHistorial(tipoFiltro === 'comercio' ? { idComercio: id } : { idCliente: id });
      setDatos(r);
      setEstado('listo');
    } catch (e) {
      if (e.codigo === SIN_RESULTADOS) {
        setDatos(null);
        setEstado('vacio');
        return;
      }
      setError(e.mensaje || e.message);
      setEstado('error');
    }
  }

  // Si la pantalla recibe el comercio o el cliente (por ejemplo, desde la sesión), consulta al abrir.
  useEffect(() => {
    if (idComercio || idCliente) cargar(tipo, valor);
  }, [idComercio, idCliente]);

  const enviar = (e) => {
    e.preventDefault();
    cargar(tipo, valor);
  };

  return (
    <section className="mx-auto w-full max-w-xl space-y-4">
      <h2 className="flex items-center gap-2 text-lg font-bold text-brandDarkBlue">
        <History className="h-5 w-5" aria-hidden /> Historial de pagos y facturas
      </h2>

      <form onSubmit={enviar} noValidate className="grid grid-cols-1 gap-3 rounded-xl border border-gray-200 bg-white p-4 sm:grid-cols-3 sm:items-end">
        <div>
          <label htmlFor="tipo-filtro" className="mb-1 block text-sm font-medium">Buscar por</label>
          <select id="tipo-filtro" value={tipo} onChange={(e) => setTipo(e.target.value)}
            className="w-full rounded-lg border border-gray-300 p-2 text-sm">
            <option value="comercio">Comercio</option>
            <option value="cliente">Cliente</option>
          </select>
        </div>
        <div>
          <label htmlFor="valor-filtro" className="mb-1 block text-sm font-medium">Número</label>
          <input id="valor-filtro" type="number" min="1" inputMode="numeric" value={valor}
            onChange={(e) => setValor(e.target.value)}
            className="w-full rounded-lg border border-gray-300 p-2 text-sm" />
        </div>
        <button type="submit" disabled={estado === 'cargando'}
          className="flex items-center justify-center gap-2 rounded-lg bg-brandDarkBlue px-4 py-2 text-sm font-bold text-white hover:bg-brandBlue disabled:opacity-60">
          <Search className="h-4 w-4" aria-hidden /> Consultar
        </button>
      </form>

      {estado === 'cargando' && (
        <p className="flex items-center gap-2 text-sm text-gray-500"><Loader2 className="h-4 w-4 animate-spin" aria-hidden /> Cargando historial…</p>
      )}

      {estado === 'error' && (
        <p role="alert" className="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">{error}</p>
      )}

      {estado === 'vacio' && (
        <p role="status" className="rounded-lg border border-gray-200 bg-white p-4 text-sm text-gray-700">
          No hay pagos ni facturas para este {tipo === 'comercio' ? 'comercio' : 'cliente'}.
        </p>
      )}

      {estado === 'listo' && datos && (
        <>
          <div className="space-y-2">
            <h3 className="text-sm font-semibold text-gray-700">Pagos ({datos.pagos.length})</h3>
            {tipo === 'comercio' && datos.pagos.length > 0 && (
              <p className="text-xs text-gray-500">El monto de cada pago corresponde al pedido completo, que puede incluir otros comercios.</p>
            )}
            <ul className="space-y-2">
              {datos.pagos.map((p) => (
                <li key={p.id} className="rounded-xl border border-gray-200 bg-white p-4 text-sm">
                  <div className="flex flex-wrap items-start justify-between gap-2">
                    <p className="min-w-0 font-semibold text-gray-900">Pago #{p.id} · Pedido #{p.id_pedido}</p>
                    <span className={`rounded-full border px-2.5 py-0.5 text-xs font-semibold ${ESTADOS_PAGO[p.estado] ?? ESTADOS_PAGO.pendiente}`}>{p.estado}</span>
                  </div>
                  <dl className="mt-2 space-y-1">
                    <div className="flex justify-between gap-4"><dt className="text-gray-500">Medio</dt><dd>{NOMBRES_MEDIO[p.medio_pago] ?? p.medio_pago}</dd></div>
                    <div className="flex justify-between gap-4"><dt className="text-gray-500">Monto</dt><dd className="font-bold">{colones(p.monto_total)}</dd></div>
                    <div className="flex justify-between gap-4"><dt className="text-gray-500">Fecha</dt><dd className="text-right">{fecha(p.fecha_confirmacion ?? p.fecha_creacion)}</dd></div>
                  </dl>
                </li>
              ))}
            </ul>
          </div>

          <div className="space-y-2">
            <h3 className="text-sm font-semibold text-gray-700">Facturas ({datos.facturas.length})</h3>
            {datos.facturas.length === 0 && (
              <p className="text-sm text-gray-500">Todavía no hay facturas emitidas.</p>
            )}
            <ul className="space-y-2">
              {datos.facturas.map((f) => (
                <li key={f.id} className="rounded-xl border border-gray-200 bg-white p-4 text-sm">
                  <div className="flex flex-wrap items-start justify-between gap-2">
                    <div className="min-w-0">
                      <p className="break-all font-semibold text-gray-900">Factura {f.consecutivo}</p>
                      <p className="text-xs text-gray-500">Comercio {f.id_comercio} · Subpedido {f.id_subpedido}</p>
                    </div>
                    <EstadoHacienda estado={f.estado_hacienda} />
                  </div>
                  <dl className="mt-2 space-y-1">
                    <div className="flex justify-between gap-4"><dt className="text-gray-500">Total</dt><dd className="font-bold">{colones(f.monto_total)}</dd></div>
                    <div className="flex justify-between gap-4"><dt className="text-gray-500">Emitida</dt><dd className="text-right">{fecha(f.fecha_emision)}</dd></div>
                  </dl>
                </li>
              ))}
            </ul>
          </div>
        </>
      )}
    </section>
  );
}