
import { useState } from 'react';
import EncabezadoM7 from '../components/EncabezadoM7';

// Datos demostrativos. No están conectados todavía con el backend.
const asientosIniciales = [
  {
    numero: 'ASI-0004',
    fecha: '2026-10-18',
    descripcion: 'Registro automático de venta',
    tipo: 'Venta automática',
    estado: 'Registrado',
    referencia: 'Pedido DEMO-004',
    lineas: [
      { codigo: '1.2.0', cuenta: 'Cuentas por cobrar', debe: 125000, haber: 0 },
      { codigo: '4.0.0', cuenta: 'Ingresos por ventas', debe: 0, haber: 125000 },
    ],
  },
  {
    numero: 'ASI-0003',
    fecha: '2026-10-17',
    descripcion: 'Registro automático del costo de ventas',
    tipo: 'Costo de ventas',
    estado: 'Registrado',
    referencia: 'Pedido DEMO-003',
    lineas: [
      { codigo: '5.0.0', cuenta: 'Costo de ventas', debe: 72000, haber: 0 },
      { codigo: '1.3.0', cuenta: 'Inventario', debe: 0, haber: 72000 },
    ],
  },
  {
    numero: 'ASI-0002',
    fecha: '2026-10-16',
    descripcion: 'Ajuste contable de demostración',
    tipo: 'Ajuste manual',
    estado: 'Registrado',
    referencia: 'Ajuste DEMO-002',
    lineas: [
      { codigo: '6.0.0', cuenta: 'Gastos operativos', debe: 15000, haber: 0 },
      { codigo: '1.1.0', cuenta: 'Caja y bancos', debe: 0, haber: 15000 },
    ],
  },
  {
    numero: 'ASI-0001',
    fecha: '2026-10-15',
    descripcion: 'Registro de venta de demostración',
    tipo: 'Venta automática',
    estado: 'Anulado',
    referencia: 'Pedido DEMO-001',
    lineas: [
      { codigo: '1.2.0', cuenta: 'Cuentas por cobrar', debe: 90000, haber: 0 },
      { codigo: '4.0.0', cuenta: 'Ingresos por ventas', debe: 0, haber: 90000 },
    ],
  },
];

const tiposAsiento = [
  'Venta automática',
  'Costo de ventas',
  'Ajuste manual',
];

function formatoMoneda(monto) {
  return new Intl.NumberFormat('es-CR', {
    style: 'currency',
    currency: 'CRC',
    maximumFractionDigits: 2,
  }).format(monto);
}

function formatoFecha(fecha) {
  const [anio, mes, dia] = fecha.split('-');
  return `${dia}/${mes}/${anio}`;
}

export default function LibroDiarioView({ onNavegar }) {
  const [busqueda, setBusqueda] = useState('');
  const [tipoSeleccionado, setTipoSeleccionado] = useState('Todos');
  const [estadoSeleccionado, setEstadoSeleccionado] = useState('Todos');
  const [fechaDesde, setFechaDesde] = useState('');
  const [fechaHasta, setFechaHasta] = useState('');
  const [tamanoPagina, setTamanoPagina] = useState(10);
  const [pagina, setPagina] = useState(1);
  const [asientoSeleccionado, setAsientoSeleccionado] = useState(null);

  const texto = busqueda.trim().toLowerCase();

  const asientosFiltrados = asientosIniciales.filter((asiento) => {
    const coincideBusqueda = [
      asiento.numero,
      asiento.descripcion,
      asiento.referencia,
      asiento.tipo,
    ].some((valor) => valor.toLowerCase().includes(texto));

    const coincideTipo =
      tipoSeleccionado === 'Todos' || asiento.tipo === tipoSeleccionado;

    const coincideEstado =
      estadoSeleccionado === 'Todos' ||
      asiento.estado === estadoSeleccionado;

    const coincideFechaDesde =
      !fechaDesde || asiento.fecha >= fechaDesde;

    const coincideFechaHasta =
      !fechaHasta || asiento.fecha <= fechaHasta;

    return (
      coincideBusqueda &&
      coincideTipo &&
      coincideEstado &&
      coincideFechaDesde &&
      coincideFechaHasta
    );
  });

  const totalAsientos = asientosFiltrados.length;
  const totalPaginas = Math.max(1, Math.ceil(totalAsientos / tamanoPagina));
  const paginaActual = Math.min(pagina, totalPaginas);
  const indiceInicial = (paginaActual - 1) * tamanoPagina;
  const asientosVisibles = asientosFiltrados.slice(
    indiceInicial,
    indiceInicial + tamanoPagina
  );

  function limpiarFiltros() {
    setBusqueda('');
    setTipoSeleccionado('Todos');
    setEstadoSeleccionado('Todos');
    setFechaDesde('');
    setFechaHasta('');
    setPagina(1);
  }

  return (
    <div className="min-h-screen bg-[#f5f7fa]">
      <EncabezadoM7
        seccionActiva="Libro diario"
        onNavegar={onNavegar}
      />

      <main id="contenido" className="mx-auto max-w-7xl px-4 py-8 sm:px-6">
        <div className="mb-6">
          <p className="mb-2 text-sm text-gray-500">
            Contabilidad / Libro diario
          </p>

          <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
            <div>
              <h1 className="text-2xl font-bold text-[#1e3a8a] sm:text-3xl">
                Libro diario
              </h1>
              <p className="mt-2 text-sm text-gray-600">
                Consulta de asientos y sus movimientos contables.
              </p>
            </div>

            <button
              type="button"
              onClick={() => window.print()}
              className="rounded-lg bg-[#1e3a8a] px-5 py-3 text-sm font-semibold text-white transition hover:bg-blue-800"
            >
              Imprimir listado
            </button>
          </div>
        </div>

        <section className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
          <article className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <p className="text-sm text-gray-500">Asientos encontrados</p>
            <p className="mt-2 text-3xl font-bold text-[#1e3a8a]">
              {totalAsientos}
            </p>
            <p className="mt-1 text-xs text-gray-500">
              Según los filtros actuales
            </p>
          </article>

          <article className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <p className="text-sm text-gray-500">Asientos registrados</p>
            <p className="mt-2 text-3xl font-bold text-[#1e3a8a]">
              {asientosIniciales.filter(
                (asiento) => asiento.estado === 'Registrado'
              ).length}
            </p>
            <p className="mt-1 text-xs text-gray-500">
              Datos de demostración
            </p>
          </article>

          <article className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <p className="text-sm text-gray-500">Asientos anulados</p>
            <p className="mt-2 text-3xl font-bold text-[#1e3a8a]">
              {asientosIniciales.filter(
                (asiento) => asiento.estado === 'Anulado'
              ).length}
            </p>
            <p className="mt-1 text-xs text-gray-500">
              Datos de demostración
            </p>
          </article>
        </section>

        <section className="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
          <div className="border-b border-gray-200 px-5 py-4">
            <h2 className="text-lg font-bold text-gray-800">
              Registro de asientos
            </h2>
            <p className="mt-1 text-sm text-gray-500">
              Busca por número, descripción o referencia y filtra los resultados.
            </p>
          </div>

          <div className="grid grid-cols-1 gap-4 border-b border-gray-200 p-5 md:grid-cols-2 xl:grid-cols-3">
            <div>
              <label htmlFor="buscar-asiento" className="mb-2 block text-sm font-medium text-gray-700">
                Buscar asiento
              </label>
              <input
                id="buscar-asiento"
                type="search"
                value={busqueda}
                onChange={(e) => {
                  setBusqueda(e.target.value);
                  setPagina(1);
                }}
                placeholder="Número, descripción o referencia..."
                className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
              />
            </div>

            <div>
              <label htmlFor="tipo-asiento" className="mb-2 block text-sm font-medium text-gray-700">
                Tipo de asiento
              </label>
              <select
                id="tipo-asiento"
                value={tipoSeleccionado}
                onChange={(e) => {
                  setTipoSeleccionado(e.target.value);
                  setPagina(1);
                }}
                className="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-blue-500"
              >
                <option value="Todos">Todos los tipos</option>
                {tiposAsiento.map((tipo) => (
                  <option key={tipo} value={tipo}>{tipo}</option>
                ))}
              </select>
            </div>

            <div>
              <label htmlFor="estado-asiento" className="mb-2 block text-sm font-medium text-gray-700">
                Estado
              </label>
              <select
                id="estado-asiento"
                value={estadoSeleccionado}
                onChange={(e) => {
                  setEstadoSeleccionado(e.target.value);
                  setPagina(1);
                }}
                className="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-blue-500"
              >
                <option value="Todos">Todos los estados</option>
                <option value="Registrado">Registrados</option>
                <option value="Anulado">Anulados</option>
              </select>
            </div>

            <div>
              <label htmlFor="fecha-desde" className="mb-2 block text-sm font-medium text-gray-700">
                Fecha desde
              </label>
              <input
                id="fecha-desde"
                type="date"
                value={fechaDesde}
                max={fechaHasta || undefined}
                onChange={(e) => {
                  setFechaDesde(e.target.value);
                  setPagina(1);
                }}
                className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm outline-none focus:border-blue-500"
              />
            </div>

            <div>
              <label htmlFor="fecha-hasta" className="mb-2 block text-sm font-medium text-gray-700">
                Fecha hasta
              </label>
              <input
                id="fecha-hasta"
                type="date"
                value={fechaHasta}
                min={fechaDesde || undefined}
                onChange={(e) => {
                  setFechaHasta(e.target.value);
                  setPagina(1);
                }}
                className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm outline-none focus:border-blue-500"
              />
            </div>

            <div className="flex items-end">
              <button
                type="button"
                onClick={limpiarFiltros}
                className="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50"
              >
                Limpiar filtros
              </button>
            </div>
          </div>

          <div className="flex flex-col gap-3 border-b border-gray-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <p className="text-sm text-gray-600" aria-live="polite">
              {totalAsientos === 0
                ? 'No se encontraron asientos.'
                : `Mostrando ${indiceInicial + 1}–${Math.min(
                    indiceInicial + tamanoPagina,
                    totalAsientos
                  )} de ${totalAsientos} asientos`}
            </p>

            <div className="flex items-center gap-2">
              <label htmlFor="filas-asientos" className="text-sm text-gray-600">
                Filas por página
              </label>
              <select
                id="filas-asientos"
                value={tamanoPagina}
                onChange={(e) => {
                  setTamanoPagina(Number(e.target.value));
                  setPagina(1);
                }}
                className="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500"
              >
                <option value={10}>10</option>
                <option value={25}>25</option>
                <option value={50}>50</option>
              </select>
            </div>
          </div>

          <div className="overflow-x-auto">
            <table className="w-full min-w-[950px] text-left text-sm">
              <thead className="bg-[#1e3a8a] text-white">
                <tr>
                  <th scope="col" className="px-4 py-4 font-semibold">Número</th>
                  <th scope="col" className="px-4 py-4 font-semibold">Fecha</th>
                  <th scope="col" className="px-4 py-4 font-semibold">Descripción</th>
                  <th scope="col" className="px-4 py-4 font-semibold">Tipo</th>
                  <th scope="col" className="px-4 py-4 font-semibold">Estado</th>
                  <th scope="col" className="px-4 py-4 text-right font-semibold">Total debe</th>
                  <th scope="col" className="px-4 py-4 text-right font-semibold">Total haber</th>
                  <th scope="col" className="px-4 py-4 font-semibold">Detalle</th>
                </tr>
              </thead>

              <tbody className="divide-y divide-gray-100">
                {asientosVisibles.map((asiento) => {
                  const totalDebe = asiento.lineas.reduce(
                    (total, linea) => total + linea.debe,
                    0
                  );
                  const totalHaber = asiento.lineas.reduce(
                    (total, linea) => total + linea.haber,
                    0
                  );

                  return (
                    <tr key={asiento.numero} className="transition hover:bg-blue-50">
                      <td className="whitespace-nowrap px-4 py-4 font-semibold text-[#1e3a8a]">
                        {asiento.numero}
                      </td>
                      <td className="whitespace-nowrap px-4 py-4 text-gray-600">
                        {formatoFecha(asiento.fecha)}
                      </td>
                      <td className="px-4 py-4 font-medium text-gray-800">
                        {asiento.descripcion}
                        <p className="mt-1 text-xs font-normal text-gray-500">
                          {asiento.referencia}
                        </p>
                      </td>
                      <td className="whitespace-nowrap px-4 py-4 text-gray-600">
                        {asiento.tipo}
                      </td>
                      <td className="px-4 py-4">
                        <span
                          className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold ${
                            asiento.estado === 'Registrado'
                              ? 'bg-green-100 text-green-800'
                              : 'bg-gray-100 text-gray-700'
                          }`}
                        >
                          {asiento.estado}
                        </span>
                      </td>
                      <td className="whitespace-nowrap px-4 py-4 text-right text-gray-700">
                        {formatoMoneda(totalDebe)}
                      </td>
                      <td className="whitespace-nowrap px-4 py-4 text-right text-gray-700">
                        {formatoMoneda(totalHaber)}
                      </td>
                      <td className="px-4 py-4">
                        <button
                          type="button"
                          onClick={() => setAsientoSeleccionado(asiento)}
                          className="font-semibold text-[#1e3a8a] underline decoration-transparent underline-offset-4 transition hover:decoration-current"
                        >
                          Ver detalle
                        </button>
                      </td>
                    </tr>
                  );
                })}

                {asientosVisibles.length === 0 && (
                  <tr>
                    <td colSpan={8} className="px-5 py-12 text-center text-gray-500">
                      No hay asientos que coincidan con los filtros.
                      <div className="mt-3">
                        <button
                          type="button"
                          onClick={limpiarFiltros}
                          className="font-semibold text-[#1e3a8a] underline"
                        >
                          Limpiar búsqueda y filtros
                        </button>
                      </div>
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>

          <div className="flex flex-col gap-3 border-t border-gray-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <p className="text-sm text-gray-600">
              Página {paginaActual} de {totalPaginas}
            </p>

            <div className="flex items-center gap-2">
              <button
                type="button"
                onClick={() => setPagina(1)}
                disabled={paginaActual === 1}
                aria-label="Primera página"
                className="rounded-lg border border-gray-300 px-3 py-2 text-sm disabled:opacity-40"
              >
                «
              </button>
              <button
                type="button"
                onClick={() => setPagina((actual) => Math.max(1, actual - 1))}
                disabled={paginaActual === 1}
                className="rounded-lg border border-gray-300 px-3 py-2 text-sm disabled:opacity-40"
              >
                Anterior
              </button>
              <button
                type="button"
                onClick={() => setPagina((actual) => Math.min(totalPaginas, actual + 1))}
                disabled={paginaActual === totalPaginas || totalAsientos === 0}
                className="rounded-lg bg-[#1e3a8a] px-4 py-2 text-sm font-semibold text-white disabled:opacity-40"
              >
                Siguiente
              </button>
              <button
                type="button"
                onClick={() => setPagina(totalPaginas)}
                disabled={paginaActual === totalPaginas || totalAsientos === 0}
                aria-label="Última página"
                className="rounded-lg border border-gray-300 px-3 py-2 text-sm disabled:opacity-40"
              >
                »
              </button>
            </div>
          </div>
        </section>

        <p className="mt-5 text-xs text-gray-500">
          Los asientos son ejemplos de interfaz. No representan registros
          contables reales ni están conectados a pedidos o pagos.
        </p>
      </main>

      {/* Ventana de detalle del asiento */}
      {asientoSeleccionado && (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
          onMouseDown={(e) => {
            if (e.target === e.currentTarget) {
              setAsientoSeleccionado(null);
            }
          }}
        >
          <section
            role="dialog"
            aria-modal="true"
            aria-labelledby="titulo-detalle-asiento"
            className="max-h-[90vh] w-full max-w-4xl overflow-y-auto rounded-xl bg-white shadow-2xl"
          >
            <div className="flex items-start justify-between gap-4 border-b border-gray-200 p-5">
              <div>
                <p className="text-sm text-gray-500">Detalle del asiento</p>
                <h2
                  id="titulo-detalle-asiento"
                  className="mt-1 text-xl font-bold text-[#1e3a8a]"
                >
                  {asientoSeleccionado.numero}
                </h2>
                <p className="mt-1 text-sm text-gray-600">
                  {asientoSeleccionado.descripcion}
                </p>
              </div>
              <button
                type="button"
                onClick={() => setAsientoSeleccionado(null)}
                aria-label="Cerrar detalle"
                className="rounded-lg border border-gray-300 px-3 py-2 text-gray-600 hover:bg-gray-50"
              >
                Cerrar
              </button>
            </div>

            <div className="grid grid-cols-1 gap-4 p-5 sm:grid-cols-3">
              <div>
                <p className="text-xs text-gray-500">Fecha</p>
                <p className="mt-1 font-medium">{formatoFecha(asientoSeleccionado.fecha)}</p>
              </div>
              <div>
                <p className="text-xs text-gray-500">Tipo</p>
                <p className="mt-1 font-medium">{asientoSeleccionado.tipo}</p>
              </div>
              <div>
                <p className="text-xs text-gray-500">Referencia</p>
                <p className="mt-1 font-medium">{asientoSeleccionado.referencia}</p>
              </div>
            </div>

            <div className="overflow-x-auto px-5 pb-5">
              <table className="w-full min-w-[550px] text-left text-sm">
                <thead className="bg-[#1e3a8a] text-white">
                  <tr>
                    <th className="px-4 py-3">Código</th>
                    <th className="px-4 py-3">Cuenta contable</th>
                    <th className="px-4 py-3 text-right">Debe</th>
                    <th className="px-4 py-3 text-right">Haber</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-100">
                  {asientoSeleccionado.lineas.map((linea, indice) => (
                    <tr key={`${linea.codigo}-${indice}`}>
                      <td className="px-4 py-3 font-semibold text-[#1e3a8a]">
                        {linea.codigo}
                      </td>
                      <td className="px-4 py-3">{linea.cuenta}</td>
                      <td className="whitespace-nowrap px-4 py-3 text-right">
                        {linea.debe ? formatoMoneda(linea.debe) : '—'}
                      </td>
                      <td className="whitespace-nowrap px-4 py-3 text-right">
                        {linea.haber ? formatoMoneda(linea.haber) : '—'}
                      </td>
                    </tr>
                  ))}
                </tbody>
                <tfoot className="border-t-2 border-gray-200 bg-gray-50 font-bold">
                  <tr>
                    <td colSpan={2} className="px-4 py-3">Totales</td>
                    <td className="whitespace-nowrap px-4 py-3 text-right">
                      {formatoMoneda(
                        asientoSeleccionado.lineas.reduce((total, linea) => total + linea.debe, 0)
                      )}
                    </td>
                    <td className="whitespace-nowrap px-4 py-3 text-right">
                      {formatoMoneda(
                        asientoSeleccionado.lineas.reduce((total, linea) => total + linea.haber, 0)
                      )}
                    </td>
                  </tr>
                </tfoot>
              </table>
            </div>

            <div className="border-t border-gray-200 px-5 py-4 text-xs text-gray-500">
              Detalle de demostración. Los importes y referencias deberán
              sustituirse por datos contables validados.
            </div>
          </section>
        </div>
      )}
    </div>
  );
}
