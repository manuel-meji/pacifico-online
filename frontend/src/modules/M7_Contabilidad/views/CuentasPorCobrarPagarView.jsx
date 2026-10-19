
import { useMemo, useState } from 'react';
import EncabezadoM7 from '../components/EncabezadoM7';

// Datos ficticios para el prototipo.
// No representan saldos reales ni están conectados con M2 o M6.
const documentosIniciales = [
  {
    id: 1,
    referencia: 'CXC-0001',
    tercero: 'Comercio Demo Norte',
    tipo: 'Por cobrar',
    fechaEmision: '2026-10-10',
    fechaVencimiento: '2026-10-25',
    monto: 125000,
    abonado: 0,
    estado: 'Pendiente',
  },
  {
    id: 2,
    referencia: 'CXC-0002',
    tercero: 'Comercio Demo Centro',
    tipo: 'Por cobrar',
    fechaEmision: '2026-10-12',
    fechaVencimiento: '2026-10-18',
    monto: 180000,
    abonado: 50000,
    estado: 'Pendiente',
  },
  {
    id: 3,
    referencia: 'CXC-0003',
    tercero: 'Comercio Demo Sur',
    tipo: 'Por cobrar',
    fechaEmision: '2026-10-14',
    fechaVencimiento: '2026-10-16',
    monto: 80000,
    abonado: 80000,
    estado: 'Pagado',
  },
  {
    id: 4,
    referencia: 'CXP-0001',
    tercero: 'Proveedor Demo A',
    tipo: 'Por pagar',
    fechaEmision: '2026-10-08',
    fechaVencimiento: '2026-10-20',
    monto: 95000,
    abonado: 0,
    estado: 'Pendiente',
  },
  {
    id: 5,
    referencia: 'CXP-0002',
    tercero: 'Proveedor Demo B',
    tipo: 'Por pagar',
    fechaEmision: '2026-10-11',
    fechaVencimiento: '2026-10-30',
    monto: 150000,
    abonado: 40000,
    estado: 'Pendiente',
  },
  {
    id: 6,
    referencia: 'CXP-0003',
    tercero: 'Proveedor Demo C',
    tipo: 'Por pagar',
    fechaEmision: '2026-10-13',
    fechaVencimiento: '2026-10-17',
    monto: 60000,
    abonado: 60000,
    estado: 'Pagado',
  },
];

const opcionesTipo = ['Por cobrar', 'Por pagar'];
const opcionesEstado = ['Pendiente', 'Pagado'];

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

function saldoPendiente(documento) {
  return Math.max(0, documento.monto - documento.abonado);
}

function estaVencido(documento) {
  // La fecha se compara con el día actual del navegador.
  // Un documento pagado no se considera vencido.
  const hoy = new Date();
  hoy.setHours(0, 0, 0, 0);

  const vencimiento = new Date(`${documento.fechaVencimiento}T00:00:00`);

  return documento.estado !== 'Pagado' &&
    saldoPendiente(documento) > 0 &&
    vencimiento < hoy;
}

export default function CuentasPorCobrarPagarView({ onNavegar }) {
  const [busqueda, setBusqueda] = useState('');
  const [tipoSeleccionado, setTipoSeleccionado] = useState('Todos');
  const [estadoSeleccionado, setEstadoSeleccionado] = useState('Todos');
  const [vencimientoSeleccionado, setVencimientoSeleccionado] =
    useState('Todos');
  const [pagina, setPagina] = useState(1);
  const [filasPorPagina, setFilasPorPagina] = useState(5);

  const documentosFiltrados = useMemo(() => {
    const texto = busqueda.trim().toLowerCase();

    return documentosIniciales.filter((documento) => {
      const coincideTexto = [
        documento.referencia,
        documento.tercero,
      ].some((valor) => valor.toLowerCase().includes(texto));

      const coincideTipo =
        tipoSeleccionado === 'Todos' ||
        documento.tipo === tipoSeleccionado;

      const coincideEstado =
        estadoSeleccionado === 'Todos' ||
        documento.estado === estadoSeleccionado;

      const vencido = estaVencido(documento);

      const coincideVencimiento =
        vencimientoSeleccionado === 'Todos' ||
        (vencimientoSeleccionado === 'Vencidos' && vencido) ||
        (vencimientoSeleccionado === 'Vigentes' && !vencido &&
          documento.estado !== 'Pagado' &&
          saldoPendiente(documento) > 0);

      return (
        coincideTexto &&
        coincideTipo &&
        coincideEstado &&
        coincideVencimiento
      );
    });
  }, [
    busqueda,
    tipoSeleccionado,
    estadoSeleccionado,
    vencimientoSeleccionado,
  ]);

  const resumen = useMemo(() => {
    const porCobrar = documentosIniciales
      .filter((documento) => documento.tipo === 'Por cobrar')
      .reduce((total, documento) => total + saldoPendiente(documento), 0);

    const porPagar = documentosIniciales
      .filter((documento) => documento.tipo === 'Por pagar')
      .reduce((total, documento) => total + saldoPendiente(documento), 0);

    const vencidoPorCobrar = documentosIniciales
      .filter((documento) =>
        documento.tipo === 'Por cobrar' && estaVencido(documento)
      )
      .reduce((total, documento) => total + saldoPendiente(documento), 0);

    const vencidoPorPagar = documentosIniciales
      .filter((documento) =>
        documento.tipo === 'Por pagar' && estaVencido(documento)
      )
      .reduce((total, documento) => total + saldoPendiente(documento), 0);

    return { porCobrar, porPagar, vencidoPorCobrar, vencidoPorPagar };
  }, []);

  const totalPaginas = Math.max(
    1,
    Math.ceil(documentosFiltrados.length / filasPorPagina)
  );

  const paginaActual = Math.min(pagina, totalPaginas);
  const inicio = (paginaActual - 1) * filasPorPagina;
  const documentosVisibles = documentosFiltrados.slice(
    inicio,
    inicio + filasPorPagina
  );

  function cambiarFiltro(callback, valor) {
    callback(valor);
    setPagina(1);
  }

  function limpiarFiltros() {
    setBusqueda('');
    setTipoSeleccionado('Todos');
    setEstadoSeleccionado('Todos');
    setVencimientoSeleccionado('Todos');
    setPagina(1);
    setFilasPorPagina(5);
  }

  return (
    <div className="min-h-screen bg-[#f5f7fa]">
      <EncabezadoM7
        seccionActiva="Cuentas por cobrar y pagar"
        onNavegar={onNavegar}
      />

      <main id="contenido" className="mx-auto max-w-7xl px-4 py-8 sm:px-6">
        <div className="mb-6">
          <p className="mb-2 text-sm text-gray-500">
            Contabilidad / Cuentas por cobrar y pagar
          </p>

          <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
            <div>
              <h1 className="text-2xl font-bold text-[#1e3a8a] sm:text-3xl">
                Cuentas por cobrar y pagar
              </h1>
              <p className="mt-2 text-sm text-gray-600">
                Consulta de saldos pendientes y fechas de vencimiento.
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

        <section className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <article className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <p className="text-sm text-gray-500">Saldo por cobrar</p>
            <p className="mt-2 text-2xl font-bold text-[#1e3a8a]">
              {formatoMoneda(resumen.porCobrar)}
            </p>
            <p className="mt-1 text-xs text-gray-500">
              Pendiente de clientes
            </p>
          </article>

          <article className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <p className="text-sm text-gray-500">Saldo por pagar</p>
            <p className="mt-2 text-2xl font-bold text-[#1e3a8a]">
              {formatoMoneda(resumen.porPagar)}
            </p>
            <p className="mt-1 text-xs text-gray-500">
              Pendiente con proveedores
            </p>
          </article>

          <article className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <p className="text-sm text-gray-500">Por cobrar vencido</p>
            <p className="mt-2 text-2xl font-bold text-red-700">
              {formatoMoneda(resumen.vencidoPorCobrar)}
            </p>
            <p className="mt-1 text-xs text-gray-500">
              Según la fecha actual
            </p>
          </article>

          <article className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <p className="text-sm text-gray-500">Por pagar vencido</p>
            <p className="mt-2 text-2xl font-bold text-red-700">
              {formatoMoneda(resumen.vencidoPorPagar)}
            </p>
            <p className="mt-1 text-xs text-gray-500">
              Según la fecha actual
            </p>
          </article>
        </section>

        <section className="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
          <div className="border-b border-gray-200 px-5 py-4">
            <h2 className="text-lg font-bold text-gray-800">
              Registro de cuentas pendientes
            </h2>
            <p className="mt-1 text-sm text-gray-500">
              Busca por referencia o tercero y filtra los resultados.
            </p>
          </div>

          <div className="grid grid-cols-1 gap-4 border-b border-gray-200 p-5 md:grid-cols-2 xl:grid-cols-4">
            <div className="xl:col-span-2">
              <label
                htmlFor="buscar-cuentas-pendientes"
                className="mb-2 block text-sm font-medium text-gray-700"
              >
                Buscar documento o tercero
              </label>
              <input
                id="buscar-cuentas-pendientes"
                type="search"
                value={busqueda}
                onChange={(e) => cambiarFiltro(setBusqueda, e.target.value)}
                placeholder="Referencia o nombre..."
                className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm outline-none focus:border-blue-500"
              />
            </div>

            <div>
              <label
                htmlFor="tipo-cuenta-pendiente"
                className="mb-2 block text-sm font-medium text-gray-700"
              >
                Tipo de cuenta
              </label>
              <select
                id="tipo-cuenta-pendiente"
                value={tipoSeleccionado}
                onChange={(e) =>
                  cambiarFiltro(setTipoSeleccionado, e.target.value)
                }
                className="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-blue-500"
              >
                <option value="Todos">Por cobrar y pagar</option>
                {opcionesTipo.map((tipo) => (
                  <option key={tipo} value={tipo}>{tipo}</option>
                ))}
              </select>
            </div>

            <div>
              <label
                htmlFor="estado-cuenta-pendiente"
                className="mb-2 block text-sm font-medium text-gray-700"
              >
                Estado
              </label>
              <select
                id="estado-cuenta-pendiente"
                value={estadoSeleccionado}
                onChange={(e) =>
                  cambiarFiltro(setEstadoSeleccionado, e.target.value)
                }
                className="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-blue-500"
              >
                <option value="Todos">Todos los estados</option>
                {opcionesEstado.map((estado) => (
                  <option key={estado} value={estado}>{estado}</option>
                ))}
              </select>
            </div>

            <div>
              <label
                htmlFor="vencimiento-cuenta-pendiente"
                className="mb-2 block text-sm font-medium text-gray-700"
              >
                Vencimiento
              </label>
              <select
                id="vencimiento-cuenta-pendiente"
                value={vencimientoSeleccionado}
                onChange={(e) =>
                  cambiarFiltro(setVencimientoSeleccionado, e.target.value)
                }
                className="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-blue-500"
              >
                <option value="Todos">Todos los vencimientos</option>
                <option value="Vencidos">Vencidos</option>
                <option value="Vigentes">Pendientes vigentes</option>
              </select>
            </div>

            <div className="flex items-end">
              <button
                type="button"
                onClick={limpiarFiltros}
                className="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50"
              >
                Limpiar filtros
              </button>
            </div>
          </div>

          <div className="flex flex-col justify-between gap-3 border-b border-gray-200 px-5 py-4 sm:flex-row sm:items-center">
            <p className="text-sm text-gray-600" aria-live="polite">
              {documentosFiltrados.length === 0
                ? 'No hay documentos que coincidan con los filtros.'
                : `Mostrando ${inicio + 1}–${Math.min(
                    inicio + filasPorPagina,
                    documentosFiltrados.length
                  )} de ${documentosFiltrados.length} documentos`}
            </p>

            <label className="flex items-center gap-2 text-sm text-gray-600">
              Filas por página
              <select
                value={filasPorPagina}
                onChange={(e) => {
                  setFilasPorPagina(Number(e.target.value));
                  setPagina(1);
                }}
                className="rounded-lg border border-gray-300 bg-white px-3 py-2"
              >
                <option value={5}>5</option>
                <option value={10}>10</option>
                <option value={20}>20</option>
              </select>
            </label>
          </div>

          <div className="overflow-x-auto">
            <table className="w-full min-w-[1050px] text-left text-sm">
              <thead className="bg-[#1e3a8a] text-white">
                <tr>
                  <th scope="col" className="px-4 py-4 font-semibold">Referencia</th>
                  <th scope="col" className="px-4 py-4 font-semibold">Tercero</th>
                  <th scope="col" className="px-4 py-4 font-semibold">Tipo</th>
                  <th scope="col" className="px-4 py-4 font-semibold">Emisión</th>
                  <th scope="col" className="px-4 py-4 font-semibold">Vencimiento</th>
                  <th scope="col" className="px-4 py-4 text-right font-semibold">Monto</th>
                  <th scope="col" className="px-4 py-4 text-right font-semibold">Pendiente</th>
                  <th scope="col" className="px-4 py-4 font-semibold">Estado</th>
                </tr>
              </thead>

              <tbody className="divide-y divide-gray-100">
                {documentosVisibles.map((documento) => {
                  const vencido = estaVencido(documento);
                  const pendiente = saldoPendiente(documento);

                  return (
                    <tr key={documento.id} className="transition hover:bg-blue-50">
                      <td className="whitespace-nowrap px-4 py-4 font-semibold text-[#1e3a8a]">
                        {documento.referencia}
                      </td>
                      <td className="px-4 py-4 font-medium text-gray-800">
                        {documento.tercero}
                      </td>
                      <td className="px-4 py-4 text-gray-600">
                        {documento.tipo}
                      </td>
                      <td className="whitespace-nowrap px-4 py-4 text-gray-600">
                        {formatoFecha(documento.fechaEmision)}
                      </td>
                      <td className="whitespace-nowrap px-4 py-4">
                        <span className={vencido ? 'font-semibold text-red-700' : 'text-gray-600'}>
                          {formatoFecha(documento.fechaVencimiento)}
                        </span>
                        {vencido && (
                          <span className="mt-1 block text-xs font-semibold text-red-700">
                            Vencido
                          </span>
                        )}
                      </td>
                      <td className="whitespace-nowrap px-4 py-4 text-right">
                        {formatoMoneda(documento.monto)}
                      </td>
                      <td className="whitespace-nowrap px-4 py-4 text-right font-semibold">
                        {formatoMoneda(pendiente)}
                      </td>
                      <td className="px-4 py-4">
                        <span className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold ${
                          documento.estado === 'Pagado'
                            ? 'bg-green-100 text-green-800'
                            : 'bg-amber-100 text-amber-800'
                        }`}>
                          {documento.estado}
                        </span>
                      </td>
                    </tr>
                  );
                })}

                {documentosVisibles.length === 0 && (
                  <tr>
                    <td colSpan={8} className="px-5 py-12 text-center text-gray-500">
                      No hay documentos que coincidan con los filtros.
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>

          <div className="flex flex-col justify-between gap-3 border-t border-gray-200 px-5 py-4 sm:flex-row sm:items-center">
            <p className="text-sm text-gray-500">
              Página {paginaActual} de {totalPaginas}
            </p>

            <div className="flex items-center gap-2">
              <button
                type="button"
                onClick={() => setPagina(1)}
                disabled={paginaActual === 1}
                className="rounded-lg border border-gray-300 px-3 py-2 text-sm disabled:cursor-not-allowed disabled:opacity-40"
                aria-label="Primera página"
              >
                «
              </button>
              <button
                type="button"
                onClick={() => setPagina((actual) => Math.max(1, actual - 1))}
                disabled={paginaActual === 1}
                className="rounded-lg border border-gray-300 px-4 py-2 text-sm disabled:cursor-not-allowed disabled:opacity-40"
              >
                Anterior
              </button>
              <button
                type="button"
                onClick={() =>
                  setPagina((actual) => Math.min(totalPaginas, actual + 1))
                }
                disabled={paginaActual === totalPaginas}
                className="rounded-lg bg-[#1e3a8a] px-4 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-40"
              >
                Siguiente
              </button>
              <button
                type="button"
                onClick={() => setPagina(totalPaginas)}
                disabled={paginaActual === totalPaginas}
                className="rounded-lg border border-gray-300 px-3 py-2 text-sm disabled:cursor-not-allowed disabled:opacity-40"
                aria-label="Última página"
              >
                »
              </button>
            </div>
          </div>
        </section>

        <p className="mt-5 text-xs text-gray-500">
          Información ficticia para el prototipo. En la versión definitiva,
          los saldos deberán provenir de los registros contables y de contratos
          de integración con los módulos responsables.
        </p>
      </main>
    </div>
  );
}
