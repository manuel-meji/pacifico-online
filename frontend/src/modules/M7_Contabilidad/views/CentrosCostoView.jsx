
import { useMemo, useState } from 'react';
import EncabezadoM7 from '../components/EncabezadoM7';

// Datos ficticios para el prototipo.
// Los costos reales deberán provenir de registros contables validados.
const centrosIniciales = [
  {
    id: 1,
    codigo: 'CC-001',
    nombre: 'Administración',
    responsable: 'Gerencia administrativa',
    tipo: 'Administrativo',
    presupuesto: 500000,
    costo: 350000,
    estado: 'Activo',
  },
  {
    id: 2,
    codigo: 'CC-002',
    nombre: 'Comercialización',
    responsable: 'Coordinación comercial',
    tipo: 'Operativo',
    presupuesto: 300000,
    costo: 180000,
    estado: 'Activo',
  },
  {
    id: 3,
    codigo: 'CC-003',
    nombre: 'Servicios y operaciones',
    responsable: 'Jefatura de operaciones',
    tipo: 'Operativo',
    presupuesto: 150000,
    costo: 95000,
    estado: 'Activo',
  },
  {
    id: 4,
    codigo: 'CC-004',
    nombre: 'Tecnología',
    responsable: 'Coordinación tecnológica',
    tipo: 'Administrativo',
    presupuesto: 250000,
    costo: 275000,
    estado: 'Activo',
  },
  {
    id: 5,
    codigo: 'CC-005',
    nombre: 'Logística',
    responsable: 'Jefatura logística',
    tipo: 'Operativo',
    presupuesto: 400000,
    costo: 320000,
    estado: 'Activo',
  },
  {
    id: 6,
    codigo: 'CC-006',
    nombre: 'Proyectos especiales',
    responsable: 'Dirección de proyectos',
    tipo: 'Proyecto',
    presupuesto: 200000,
    costo: 0,
    estado: 'Inactivo',
  },
  {
    id: 7,
    codigo: 'CC-007',
    nombre: 'Atención al comercio',
    responsable: 'Coordinación de servicio',
    tipo: 'Operativo',
    presupuesto: 180000,
    costo: 120000,
    estado: 'Activo',
  },
  {
    id: 8,
    codigo: 'CC-008',
    nombre: 'Dirección general',
    responsable: 'Dirección general',
    tipo: 'Administrativo',
    presupuesto: 350000,
    costo: 210000,
    estado: 'Activo',
  },
];

const opcionesTipo = ['Administrativo', 'Operativo', 'Proyecto'];
const opcionesEstado = ['Activo', 'Inactivo'];

function formatoMoneda(monto) {
  return new Intl.NumberFormat('es-CR', {
    style: 'currency',
    currency: 'CRC',
    maximumFractionDigits: 2,
  }).format(monto);
}

function porcentajeEjecucion(centro) {
  if (centro.presupuesto <= 0) return 0;
  return (centro.costo / centro.presupuesto) * 100;
}

export default function CentrosCostoView({ onNavegar }) {
  const [busqueda, setBusqueda] = useState('');
  const [tipoSeleccionado, setTipoSeleccionado] = useState('Todos');
  const [estadoSeleccionado, setEstadoSeleccionado] = useState('Todos');
  const [pagina, setPagina] = useState(1);
  const [filasPorPagina, setFilasPorPagina] = useState(5);

  const centrosFiltrados = useMemo(() => {
    const texto = busqueda.trim().toLowerCase();

    return centrosIniciales.filter((centro) => {
      const coincideTexto = [
        centro.codigo,
        centro.nombre,
        centro.responsable,
      ].some((valor) => valor.toLowerCase().includes(texto));

      const coincideTipo =
        tipoSeleccionado === 'Todos' ||
        centro.tipo === tipoSeleccionado;

      const coincideEstado =
        estadoSeleccionado === 'Todos' ||
        centro.estado === estadoSeleccionado;

      return coincideTexto && coincideTipo && coincideEstado;
    });
  }, [busqueda, tipoSeleccionado, estadoSeleccionado]);

  const resumen = useMemo(() => {
    const activos = centrosIniciales.filter(
      (centro) => centro.estado === 'Activo'
    ).length;

    const presupuesto = centrosIniciales
      .filter((centro) => centro.estado === 'Activo')
      .reduce((total, centro) => total + centro.presupuesto, 0);

    const costo = centrosIniciales
      .filter((centro) => centro.estado === 'Activo')
      .reduce((total, centro) => total + centro.costo, 0);

    return {
      cantidad: centrosIniciales.length,
      activos,
      presupuesto,
      costo,
      disponible: presupuesto - costo,
    };
  }, []);

  const totalPaginas = Math.max(
    1,
    Math.ceil(centrosFiltrados.length / filasPorPagina)
  );

  const paginaActual = Math.min(pagina, totalPaginas);
  const inicio = (paginaActual - 1) * filasPorPagina;
  const centrosVisibles = centrosFiltrados.slice(
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
    setPagina(1);
    setFilasPorPagina(5);
  }

  return (
    <div className="min-h-screen bg-[#f5f7fa]">
      <EncabezadoM7
        seccionActiva="Centros de costo"
        onNavegar={onNavegar}
      />

      <main id="contenido" className="mx-auto max-w-7xl px-4 py-8 sm:px-6">
        <div className="mb-6">
          <p className="mb-2 text-sm text-gray-500">
            Contabilidad / Centros de costo
          </p>

          <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
            <div>
              <h1 className="text-2xl font-bold text-[#1e3a8a] sm:text-3xl">
                Centros de costo
              </h1>
              <p className="mt-2 text-sm text-gray-600">
                Consulta de presupuestos, costos acumulados y ejecución.
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
            <p className="text-sm text-gray-500">Centros registrados</p>
            <p className="mt-2 text-2xl font-bold text-[#1e3a8a]">
              {resumen.cantidad}
            </p>
            <p className="mt-1 text-xs text-gray-500">
              Total del catálogo demostrativo
            </p>
          </article>

          <article className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <p className="text-sm text-gray-500">Centros activos</p>
            <p className="mt-2 text-2xl font-bold text-[#1e3a8a]">
              {resumen.activos}
            </p>
            <p className="mt-1 text-xs text-gray-500">
              Considerados en el resumen financiero
            </p>
          </article>

          <article className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <p className="text-sm text-gray-500">Presupuesto asignado</p>
            <p className="mt-2 text-2xl font-bold text-[#1e3a8a]">
              {formatoMoneda(resumen.presupuesto)}
            </p>
            <p className="mt-1 text-xs text-gray-500">
              Solo centros activos
            </p>
          </article>

          <article className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <p className="text-sm text-gray-500">Costo acumulado</p>
            <p className="mt-2 text-2xl font-bold text-[#1e3a8a]">
              {formatoMoneda(resumen.costo)}
            </p>
            <p className={`mt-1 text-xs ${
              resumen.disponible >= 0 ? 'text-green-700' : 'text-red-700'
            }`}>
              {resumen.disponible >= 0 ? 'Disponible: ' : 'Exceso: '}
              {formatoMoneda(Math.abs(resumen.disponible))}
            </p>
          </article>
        </section>

        <section className="mb-6 rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
          <h2 className="text-lg font-bold text-gray-800">
            Ejecución presupuestaria
          </h2>
          <p className="mt-1 text-sm text-gray-500">
            Comparación entre el presupuesto y el costo de los centros activos.
          </p>

          <div className="mt-5 grid grid-cols-1 gap-5 md:grid-cols-2">
            {centrosIniciales
              .filter((centro) => centro.estado === 'Activo')
              .slice(0, 4)
              .map((centro) => {
                const porcentaje = porcentajeEjecucion(centro);
                const excedido = porcentaje > 100;

                return (
                  <div key={centro.id}>
                    <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
                      <span className="text-sm font-medium text-gray-700">
                        {centro.nombre}
                      </span>
                      <span className={`text-sm font-semibold ${
                        excedido ? 'text-red-700' : 'text-[#1e3a8a]'
                      }`}>
                        {porcentaje.toLocaleString('es-CR', {
                          maximumFractionDigits: 1,
                        })}%
                      </span>
                    </div>

                    <div
                      className="h-3 overflow-hidden rounded-full bg-gray-100"
                      role="progressbar"
                      aria-label={`Ejecución presupuestaria de ${centro.nombre}`}
                      aria-valuemin={0}
                      aria-valuemax={100}
                      aria-valuenow={Math.min(100, porcentaje)}
                    >
                      <div
                        className={`h-full rounded-full ${
                          excedido ? 'bg-red-600' : 'bg-[#a3be4d]'
                        }`}
                        style={{ width: `${Math.min(100, porcentaje)}%` }}
                      />
                    </div>

                    <p className="mt-1 text-xs text-gray-500">
                      Costo: {formatoMoneda(centro.costo)} de{' '}
                      {formatoMoneda(centro.presupuesto)}
                      {excedido && (
                        <span className="ml-1 font-semibold text-red-700">
                          Presupuesto excedido
                        </span>
                      )}
                    </p>
                  </div>
                );
              })}
          </div>
        </section>

        <section className="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
          <div className="border-b border-gray-200 px-5 py-4">
            <h2 className="text-lg font-bold text-gray-800">
              Registro de centros de costo
            </h2>
            <p className="mt-1 text-sm text-gray-500">
              Busca por código, nombre o responsable y filtra los resultados.
            </p>
          </div>

          <div className="grid grid-cols-1 gap-4 border-b border-gray-200 p-5 md:grid-cols-3">
            <div>
              <label
                htmlFor="buscar-centro-costo"
                className="mb-2 block text-sm font-medium text-gray-700"
              >
                Buscar centro
              </label>
              <input
                id="buscar-centro-costo"
                type="search"
                value={busqueda}
                onChange={(e) => cambiarFiltro(setBusqueda, e.target.value)}
                placeholder="Código, nombre o responsable..."
                className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm outline-none focus:border-blue-500"
              />
            </div>

            <div>
              <label
                htmlFor="tipo-centro-costo"
                className="mb-2 block text-sm font-medium text-gray-700"
              >
                Tipo de centro
              </label>
              <select
                id="tipo-centro-costo"
                value={tipoSeleccionado}
                onChange={(e) =>
                  cambiarFiltro(setTipoSeleccionado, e.target.value)
                }
                className="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-blue-500"
              >
                <option value="Todos">Todos los tipos</option>
                {opcionesTipo.map((tipo) => (
                  <option key={tipo} value={tipo}>{tipo}</option>
                ))}
              </select>
            </div>

            <div>
              <label
                htmlFor="estado-centro-costo"
                className="mb-2 block text-sm font-medium text-gray-700"
              >
                Estado
              </label>
              <select
                id="estado-centro-costo"
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

            <div className="md:col-span-3">
              <button
                type="button"
                onClick={limpiarFiltros}
                className="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50"
              >
                Limpiar filtros
              </button>
            </div>
          </div>

          <div className="flex flex-col justify-between gap-3 border-b border-gray-200 px-5 py-4 sm:flex-row sm:items-center">
            <p className="text-sm text-gray-600" aria-live="polite">
              {centrosFiltrados.length === 0
                ? 'No hay centros que coincidan con los filtros.'
                : `Mostrando ${inicio + 1}–${Math.min(
                    inicio + filasPorPagina,
                    centrosFiltrados.length
                  )} de ${centrosFiltrados.length} centros`}
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


          {/* Vista móvil: tarjetas de centros de costo */}
          <div className="space-y-3 p-3 md:hidden">
            {centrosVisibles.map((centro) => {
              const porcentaje = porcentajeEjecucion(centro);
              const excedido = porcentaje > 100;

              return (
                <article
                  key={centro.id}
                  className="rounded-lg border border-gray-200 bg-white p-4"
                >
                  <div className="flex items-start justify-between gap-3">
                    <div className="min-w-0">
                      <p className="text-xs font-semibold uppercase tracking-wide text-gray-500">
                        Código
                      </p>
                      <p className="break-words font-bold text-[#1e3a8a]">
                        {centro.codigo}
                      </p>
                    </div>

                    <span
                      className={`shrink-0 rounded-full px-3 py-1 text-xs font-semibold ${
                        centro.estado === 'Activo'
                          ? 'bg-green-100 text-green-800'
                          : 'bg-gray-100 text-gray-600'
                      }`}
                    >
                      {centro.estado}
                    </span>
                  </div>

                  <h3 className="mt-3 break-words font-semibold text-gray-900">
                    {centro.nombre}
                  </h3>

                  <dl className="mt-4 grid grid-cols-2 gap-4 border-t border-gray-100 pt-3 text-sm">
                    <div>
                      <dt className="text-gray-500">Tipo</dt>
                      <dd className="break-words font-medium text-gray-800">
                        {centro.tipo}
                      </dd>
                    </div>

                    <div>
                      <dt className="text-gray-500">Responsable</dt>
                      <dd className="break-words font-medium text-gray-800">
                        {centro.responsable}
                      </dd>
                    </div>

                    <div>
                      <dt className="text-gray-500">Presupuesto</dt>
                      <dd className="break-words font-medium text-gray-800">
                        {formatoMoneda(centro.presupuesto)}
                      </dd>
                    </div>

                    <div>
                      <dt className="text-gray-500">Costo acumulado</dt>
                      <dd className="break-words font-semibold text-gray-900">
                        {formatoMoneda(centro.costo)}
                      </dd>
                    </div>

                    <div className="col-span-2">
                      <dt className="text-gray-500">Ejecución presupuestaria</dt>
                      <dd
                        className={`font-semibold ${
                          excedido ? 'text-red-700' : 'text-gray-700'
                        }`}
                      >
                        {porcentaje.toLocaleString('es-CR', {
                          maximumFractionDigits: 1,
                        })}%
                      </dd>
                    </div>
                  </dl>
                </article>
              );
            })}

            {centrosVisibles.length === 0 && (
              <div className="rounded-lg border border-gray-200 p-6 text-center text-sm text-gray-500">
                No hay centros que coincidan con los filtros.
                <button
                  type="button"
                  onClick={limpiarFiltros}
                  className="mt-3 block w-full font-semibold text-[#1e3a8a] underline"
                >
                  Limpiar búsqueda y filtros
                </button>
              </div>
            )}
          </div>

          {/* Vista de escritorio: tabla original */}
          <div className="hidden md:block">
            <table className="w-full text-left text-sm">
              <thead className="bg-[#1e3a8a] text-white">
                <tr>
                  <th scope="col" className="px-4 py-4 font-semibold">Código</th>
                  <th scope="col" className="px-4 py-4 font-semibold">Centro de costo</th>
                  <th scope="col" className="px-4 py-4 font-semibold">Tipo</th>
                  <th scope="col" className="px-4 py-4 font-semibold">Responsable</th>
                  <th scope="col" className="px-4 py-4 text-right font-semibold">Presupuesto</th>
                  <th scope="col" className="px-4 py-4 text-right font-semibold">Costo acumulado</th>
                  <th scope="col" className="px-4 py-4 text-right font-semibold">Ejecución</th>
                  <th scope="col" className="px-4 py-4 font-semibold">Estado</th>
                </tr>
              </thead>

              <tbody className="divide-y divide-gray-100">
                {centrosVisibles.map((centro) => {
                  const porcentaje = porcentajeEjecucion(centro);
                  const excedido = porcentaje > 100;

                  return (
                    <tr key={centro.id} className="transition hover:bg-blue-50">
                      <td className="whitespace-nowrap px-4 py-4 font-semibold text-[#1e3a8a]">
                        {centro.codigo}
                      </td>
                      <td className="px-4 py-4 font-medium text-gray-800">
                        {centro.nombre}
                      </td>
                      <td className="px-4 py-4 text-gray-600">{centro.tipo}</td>
                      <td className="px-4 py-4 text-gray-600">{centro.responsable}</td>
                      <td className="whitespace-nowrap px-4 py-4 text-right">
                        {formatoMoneda(centro.presupuesto)}
                      </td>
                      <td className="whitespace-nowrap px-4 py-4 text-right font-medium">
                        {formatoMoneda(centro.costo)}
                      </td>
                      <td
                        className={`whitespace-nowrap px-4 py-4 text-right font-semibold ${
                          excedido ? 'text-red-700' : 'text-gray-700'
                        }`}
                      >
                        {porcentaje.toLocaleString('es-CR', {
                          maximumFractionDigits: 1,
                        })}%
                      </td>
                      <td className="px-4 py-4">
                        <span
                          className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold ${
                            centro.estado === 'Activo'
                              ? 'bg-green-100 text-green-800'
                              : 'bg-gray-100 text-gray-600'
                          }`}
                        >
                          {centro.estado}
                        </span>
                      </td>
                    </tr>
                  );
                })}

                {centrosVisibles.length === 0 && (
                  <tr>
                    <td colSpan={8} className="px-5 py-12 text-center text-gray-500">
                      No hay centros que coincidan con los filtros.
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
          Datos ficticios para el prototipo. La asignación de costos y el
          presupuesto definitivo deberán validarse con las reglas contables
          y los requisitos del proyecto.
        </p>
      </main>
    </div>
  );
}
