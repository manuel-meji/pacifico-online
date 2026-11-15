
import { useEffect, useState } from 'react';
import EncabezadoM7 from '../components/EncabezadoM7';
import { consultarReporteGerencial } from '../services/reporteGerencialApi';

function formatoMoneda(monto) {
  if (monto === null || monto === undefined || monto === '') {
    return '—';
  }

  return new Intl.NumberFormat('es-CR', {
    style: 'currency',
    currency: 'CRC',
    maximumFractionDigits: 2,
  }).format(Number(monto));
}

function formatoFecha(fecha) {
  if (!fecha) return '';
  const [anio, mes, dia] = fecha.slice(0, 10).split('-');
  return `${dia}/${mes}/${anio}`;
}

function formatoPeriodo(periodo) {
  const [anio, mes] = String(periodo).split('-');
  const fecha = new Date(Number(anio), Number(mes) - 1, 1);

  if (Number.isNaN(fecha.getTime())) return periodo;

  const texto = new Intl.DateTimeFormat('es-CR', {
    month: 'long',
    year: 'numeric',
  }).format(fecha);

  return texto.charAt(0).toUpperCase() + texto.slice(1);
}

function formatoPorcentaje(valor) {
  return new Intl.NumberFormat('es-CR', {
    style: 'percent',
    maximumFractionDigits: 1,
  }).format(valor);
}

export default function EstadoResultadosView({ onNavegar, idComercio }) {
  const [fechaDesde, setFechaDesde] = useState('');
  const [fechaHasta, setFechaHasta] = useState('');
  const [reporte, setReporte] = useState(null);
  const [cargando, setCargando] = useState(true);
  const [error, setError] = useState('');

  const fechasIncompletas = Boolean(fechaDesde) !== Boolean(fechaHasta);

  const rangoInvalido =
    fechaDesde !== '' && fechaHasta !== '' && fechaHasta < fechaDesde;

  const filtrosInvalidos = fechasIncompletas || rangoInvalido;

  useEffect(() => {
    if (filtrosInvalidos) {
      setCargando(false);
      setError('');
      setReporte(null);
      return undefined;
    }

    let activo = true;

    async function cargarReporte() {
      setCargando(true);
      setError('');
      setReporte(null);

      try {
        const datos = await consultarReporteGerencial({
          id_comercio: idComercio,
          fecha_desde: fechaDesde,
          fecha_hasta: fechaHasta,
        });

        if (activo) setReporte(datos);
      } catch (e) {
        if (activo) {
          setError(e.message || 'No se pudo cargar el estado de resultados.');
          setReporte(null);
        }
      } finally {
        if (activo) setCargando(false);
      }
    }

    cargarReporte();

    return () => {
      activo = false;
    };
  }, [fechaDesde, fechaHasta, filtrosInvalidos, idComercio]);

  function limpiarFiltros() {
    setFechaDesde('');
    setFechaHasta('');
  }

  const resumen = reporte?.resumen ?? null;
  const mensual = reporte?.mensual ?? [];
  const hayMovimientos = mensual.length > 0;

  const ingresos = resumen ? Number(resumen.ingresos) : null;
  const utilidadBruta = resumen ? Number(resumen.utilidad_bruta) : null;
  const margenBruto =
    resumen && ingresos > 0 ? utilidadBruta / ingresos : null;

  return (
    <div className="m7-estado-resultados min-h-screen bg-[#f5f7fa]">
      <EncabezadoM7
        seccionActiva="Estado de resultados"
        onNavegar={onNavegar}
      />

      <main id="contenido" className="mx-auto max-w-7xl px-4 py-8 sm:px-6">
        <div className="mb-6">
          <p className="mb-2 text-sm text-gray-500">
            Contabilidad / Estado de resultados
          </p>

          <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
            <div>
              <h1 className="text-2xl font-bold text-[#1e3a8a] sm:text-3xl">
                Estado de resultados
              </h1>
              <p className="mt-2 text-sm text-gray-600">
                Ingresos por ventas, costo de ventas y utilidad bruta del
                período, a partir de los asientos registrados.
              </p>
            </div>

            <button
              type="button"
              onClick={() => window.print()}
              className="rounded-lg bg-[#1e3a8a] px-5 py-3 text-sm font-semibold text-white transition hover:bg-blue-800"
            >
              Imprimir reporte
            </button>
          </div>
        </div>

        <section className="mb-6 rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div>
              <label
                htmlFor="fecha-desde-resultados"
                className="mb-2 block text-sm font-medium text-gray-700"
              >
                Desde
              </label>
              <input
                id="fecha-desde-resultados"
                type="date"
                value={fechaDesde}
                onChange={(e) => setFechaDesde(e.target.value)}
                aria-invalid={filtrosInvalidos}
                aria-describedby={
                  filtrosInvalidos ? 'error-rango-resultados' : undefined
                }
                className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm outline-none focus:border-blue-500"
              />
            </div>

            <div>
              <label
                htmlFor="fecha-hasta-resultados"
                className="mb-2 block text-sm font-medium text-gray-700"
              >
                Hasta
              </label>
              <input
                id="fecha-hasta-resultados"
                type="date"
                value={fechaHasta}
                min={fechaDesde || undefined}
                onChange={(e) => setFechaHasta(e.target.value)}
                aria-invalid={filtrosInvalidos}
                aria-describedby={
                  filtrosInvalidos ? 'error-rango-resultados' : undefined
                }
                className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm outline-none focus:border-blue-500"
              />
            </div>

            <div className="flex items-end">
              <button
                type="button"
                onClick={limpiarFiltros}
                className="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50"
              >
                Mes actual
              </button>
            </div>
          </div>

          {filtrosInvalidos && (
            <p
              id="error-rango-resultados"
              role="alert"
              className="mt-3 text-sm font-medium text-red-700"
            >
              {fechasIncompletas
                ? 'Debe seleccionar ambas fechas para consultar el período.'
                : 'La fecha final no puede ser anterior a la fecha inicial.'}
            </p>
          )}

          <p className="mt-3 text-sm text-gray-500" aria-live="polite">
            {reporte
              ? `Período consultado: ${formatoFecha(reporte.fecha_desde)} al ${formatoFecha(reporte.fecha_hasta)}.`
              : 'Si no elige fechas, se consulta el mes actual.'}
          </p>
        </section>

        {error && (
          <div
            role="alert"
            className="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800"
          >
            {error}
          </div>
        )}

        <section
          className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4"
          aria-busy={cargando}
        >
          <article className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <p className="text-sm text-gray-500">Ingresos por ventas</p>
            <p className="mt-2 text-2xl font-bold text-[#1e3a8a]">
              {cargando ? 'Cargando…' : formatoMoneda(resumen?.ingresos)}
            </p>
          </article>

          <article className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <p className="text-sm text-gray-500">Costo de ventas</p>
            <p className="mt-2 text-2xl font-bold text-[#1e3a8a]">
              {cargando ? 'Cargando…' : formatoMoneda(resumen?.costos)}
            </p>
          </article>

          <article className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <p className="text-sm text-gray-500">Utilidad bruta</p>
            <p
              className={`mt-2 text-2xl font-bold ${
                cargando || !resumen
                  ? 'text-gray-500'
                  : utilidadBruta >= 0
                    ? 'text-green-700'
                    : 'text-red-700'
              }`}
            >
              {cargando ? 'Cargando…' : formatoMoneda(resumen?.utilidad_bruta)}
            </p>
          </article>

          <article className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <p className="text-sm text-gray-500">Margen bruto</p>
            <p className="mt-2 text-2xl font-bold text-[#1e3a8a]">
              {cargando
                ? 'Cargando…'
                : margenBruto === null
                  ? '—'
                  : formatoPorcentaje(margenBruto)}
            </p>
          </article>
        </section>

        <section className="mb-6 rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
          <h2 className="text-lg font-bold text-gray-800">
            Resultado del período
          </h2>

          <div className="mt-4 space-y-4">
            <div className="flex flex-col justify-between gap-1 border-b border-gray-100 pb-3 sm:flex-row">
              <span className="text-gray-700">Ingresos por ventas</span>
              <span className="font-semibold text-gray-900">
                {formatoMoneda(resumen?.ingresos)}
              </span>
            </div>

            <div className="flex flex-col justify-between gap-1 border-b border-gray-100 pb-3 sm:flex-row">
              <span className="text-gray-700">Menos: costo de ventas</span>
              <span className="font-semibold text-gray-900">
                {resumen ? `(${formatoMoneda(resumen.costos)})` : '—'}
              </span>
            </div>

            <div className="flex flex-col justify-between gap-1 rounded-lg bg-blue-50 px-4 py-3 sm:flex-row">
              <span className="font-bold text-[#1e3a8a]">
                {resumen && utilidadBruta < 0
                  ? 'Pérdida bruta del período'
                  : 'Utilidad bruta del período'}
              </span>
              <span className="font-bold text-[#1e3a8a]">
                {formatoMoneda(resumen?.utilidad_bruta)}
              </span>
            </div>
          </div>

          <p className="mt-4 text-xs text-gray-500">
            Incluye únicamente asientos de venta y de costo de ventas en estado
            registrado. Los gastos operativos no forman parte de este alcance.
          </p>
        </section>

        <section className="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
          <div className="border-b border-gray-200 px-5 py-4">
            <h2 className="text-lg font-bold text-gray-800">
              Detalle mensual
            </h2>
            <p className="mt-1 text-sm text-gray-500">
              Ingresos, costos y utilidad bruta por mes dentro del período.
            </p>
          </div>

          {cargando && (
            <p className="px-5 py-12 text-center text-sm text-gray-500">
              Cargando estado de resultados…
            </p>
          )}

          {!cargando && !error && !hayMovimientos && (
            <p className="px-5 py-12 text-center text-sm text-gray-500">
              No hay asientos de venta ni de costo registrados en este período.
            </p>
          )}

          {!cargando && hayMovimientos && (
            <>
              <div className="space-y-3 p-3 md:hidden">
                {mensual.map((fila) => (
                  <article
                    key={fila.periodo}
                    className="rounded-lg border border-gray-200 bg-white p-4"
                  >
                    <h3 className="font-semibold text-[#1e3a8a]">
                      {formatoPeriodo(fila.periodo)}
                    </h3>

                    <dl className="mt-3 space-y-2 text-sm">
                      <div className="flex justify-between gap-3">
                        <dt className="text-gray-500">Ingresos</dt>
                        <dd className="font-semibold text-gray-900">
                          {formatoMoneda(fila.ingresos)}
                        </dd>
                      </div>
                      <div className="flex justify-between gap-3">
                        <dt className="text-gray-500">Costos</dt>
                        <dd className="font-semibold text-gray-900">
                          {formatoMoneda(fila.costos)}
                        </dd>
                      </div>
                      <div className="flex justify-between gap-3 border-t border-gray-100 pt-2">
                        <dt className="text-gray-500">Utilidad bruta</dt>
                        <dd
                          className={`font-semibold ${
                            Number(fila.utilidad_bruta) >= 0
                              ? 'text-green-700'
                              : 'text-red-700'
                          }`}
                        >
                          {formatoMoneda(fila.utilidad_bruta)}
                        </dd>
                      </div>
                    </dl>
                  </article>
                ))}
              </div>

              <div className="hidden md:block">
                <table className="w-full text-left text-sm">
                  <thead className="bg-[#1e3a8a] text-white">
                    <tr>
                      <th scope="col" className="px-4 py-4 font-semibold">
                        Mes
                      </th>
                      <th scope="col" className="px-4 py-4 text-right font-semibold">
                        Ingresos
                      </th>
                      <th scope="col" className="px-4 py-4 text-right font-semibold">
                        Costos
                      </th>
                      <th scope="col" className="px-4 py-4 text-right font-semibold">
                        Utilidad bruta
                      </th>
                    </tr>
                  </thead>

                  <tbody className="divide-y divide-gray-100">
                    {mensual.map((fila) => (
                      <tr key={fila.periodo} className="transition hover:bg-blue-50">
                        <th
                          scope="row"
                          className="whitespace-nowrap px-4 py-4 font-semibold text-[#1e3a8a]"
                        >
                          {formatoPeriodo(fila.periodo)}
                        </th>
                        <td className="whitespace-nowrap px-4 py-4 text-right text-gray-800">
                          {formatoMoneda(fila.ingresos)}
                        </td>
                        <td className="whitespace-nowrap px-4 py-4 text-right text-gray-800">
                          {formatoMoneda(fila.costos)}
                        </td>
                        <td
                          className={`whitespace-nowrap px-4 py-4 text-right font-semibold ${
                            Number(fila.utilidad_bruta) >= 0
                              ? 'text-green-700'
                              : 'text-red-700'
                          }`}
                        >
                          {formatoMoneda(fila.utilidad_bruta)}
                        </td>
                      </tr>
                    ))}
                  </tbody>

                  <tfoot className="border-t-2 border-gray-200 bg-gray-50">
                    <tr>
                      <th scope="row" className="px-4 py-4 font-bold text-gray-900">
                        Total del período
                      </th>
                      <td className="whitespace-nowrap px-4 py-4 text-right font-bold text-gray-900">
                        {formatoMoneda(resumen?.ingresos)}
                      </td>
                      <td className="whitespace-nowrap px-4 py-4 text-right font-bold text-gray-900">
                        {formatoMoneda(resumen?.costos)}
                      </td>
                      <td className="whitespace-nowrap px-4 py-4 text-right font-bold text-gray-900">
                        {formatoMoneda(resumen?.utilidad_bruta)}
                      </td>
                    </tr>
                  </tfoot>
                </table>
              </div>
            </>
          )}
        </section>
      </main>

      {reporte && (
        <section className="m7-print-area">
          <h1>Estado de resultados</h1>

          <p>Pacíficos Online</p>
          <p>
            Período: {formatoFecha(reporte.fecha_desde)} al{' '}
            {formatoFecha(reporte.fecha_hasta)}
          </p>

          <h2>Resultado del período</h2>

          <table>
            <thead>
              <tr>
                <th>Concepto</th>
                <th>Monto</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>Ingresos por ventas</td>
                <td>{formatoMoneda(resumen?.ingresos)}</td>
              </tr>
              <tr>
                <td>Menos: costo de ventas</td>
                <td>{formatoMoneda(resumen?.costos)}</td>
              </tr>
              <tr>
                <td>Utilidad bruta</td>
                <td>{formatoMoneda(resumen?.utilidad_bruta)}</td>
              </tr>
              <tr>
                <td>Margen bruto</td>
                <td>
                  {margenBruto === null
                    ? '—'
                    : formatoPorcentaje(margenBruto)}
                </td>
              </tr>
            </tbody>
          </table>

          <h2>Detalle mensual</h2>

          <table>
            <thead>
              <tr>
                <th>Mes</th>
                <th>Ingresos</th>
                <th>Costos</th>
                <th>Utilidad bruta</th>
              </tr>
            </thead>
            <tbody>
              {mensual.map((fila) => (
                <tr key={fila.periodo}>
                  <td>{formatoPeriodo(fila.periodo)}</td>
                  <td>{formatoMoneda(fila.ingresos)}</td>
                  <td>{formatoMoneda(fila.costos)}</td>
                  <td>{formatoMoneda(fila.utilidad_bruta)}</td>
                </tr>
              ))}
              <tr>
                <td>Total del período</td>
                <td>{formatoMoneda(resumen?.ingresos)}</td>
                <td>{formatoMoneda(resumen?.costos)}</td>
                <td>{formatoMoneda(resumen?.utilidad_bruta)}</td>
              </tr>
            </tbody>
          </table>

          <p>
            Incluye únicamente ingresos por ventas y costo de ventas.
            No contempla gastos operativos.
          </p>

          <p className="print-footer">
            Reporte generado desde el módulo M7 de Pacíficos Online.
          </p>
        </section>
      )}
    </div>
  );
}
