
import { useState } from 'react';
import EncabezadoM7 from '../components/EncabezadoM7';

// Datos de demostración. No representan todavía el catálogo definitivo.
// "permiteMovimientos" distingue cuentas de agrupación y de movimiento.
const cuentasIniciales = [
  {
    codigo: '1.0.0',
    nombre: 'Activos',
    tipo: 'Activo',
    naturaleza: 'Deudora',
    cuentaPadre: null,
    nivel: 1,
    permiteMovimientos: false,
    estado: 'Activa',
  },
  {
    codigo: '1.1.0',
    nombre: 'Caja y bancos',
    tipo: 'Activo',
    naturaleza: 'Deudora',
    cuentaPadre: '1.0.0',
    nivel: 2,
    permiteMovimientos: true,
    estado: 'Activa',
  },
  {
    codigo: '1.2.0',
    nombre: 'Cuentas por cobrar',
    tipo: 'Activo',
    naturaleza: 'Deudora',
    cuentaPadre: '1.0.0',
    nivel: 2,
    permiteMovimientos: true,
    estado: 'Activa',
  },
  {
    codigo: '1.3.0',
    nombre: 'Inventario',
    tipo: 'Activo',
    naturaleza: 'Deudora',
    cuentaPadre: '1.0.0',
    nivel: 2,
    permiteMovimientos: true,
    estado: 'Activa',
  },
  {
    codigo: '2.0.0',
    nombre: 'Pasivos',
    tipo: 'Pasivo',
    naturaleza: 'Acreedora',
    cuentaPadre: null,
    nivel: 1,
    permiteMovimientos: false,
    estado: 'Activa',
  },
  {
    codigo: '2.1.0',
    nombre: 'Cuentas por pagar',
    tipo: 'Pasivo',
    naturaleza: 'Acreedora',
    cuentaPadre: '2.0.0',
    nivel: 2,
    permiteMovimientos: true,
    estado: 'Activa',
  },
  {
    codigo: '3.0.0',
    nombre: 'Patrimonio',
    tipo: 'Patrimonio',
    naturaleza: 'Acreedora',
    cuentaPadre: null,
    nivel: 1,
    permiteMovimientos: false,
    estado: 'Activa',
  },
  {
    codigo: '4.0.0',
    nombre: 'Ingresos por ventas',
    tipo: 'Ingreso',
    naturaleza: 'Acreedora',
    cuentaPadre: null,
    nivel: 1,
    permiteMovimientos: true,
    estado: 'Activa',
  },
  {
    codigo: '5.0.0',
    nombre: 'Costo de ventas',
    tipo: 'Costo',
    naturaleza: 'Deudora',
    cuentaPadre: null,
    nivel: 1,
    permiteMovimientos: true,
    estado: 'Activa',
  },
  {
    codigo: '6.0.0',
    nombre: 'Gastos operativos',
    tipo: 'Gasto',
    naturaleza: 'Deudora',
    cuentaPadre: null,
    nivel: 1,
    permiteMovimientos: true,
    estado: 'Activa',
  },
];

const tiposCuenta = [
  'Activo',
  'Pasivo',
  'Patrimonio',
  'Ingreso',
  'Costo',
  'Gasto',
];

export default function PlanCuentasView({ onNavegar }) {
  const [busqueda, setBusqueda] = useState('');
  const [tipoSeleccionado, setTipoSeleccionado] = useState('Todos');
  const [estadoSeleccionado, setEstadoSeleccionado] = useState('Todos');
  const [tamanoPagina, setTamanoPagina] = useState(10);
  const [pagina, setPagina] = useState(1);

  const textoBusqueda = busqueda.trim().toLowerCase();

  const cuentasFiltradas = cuentasIniciales.filter((cuenta) => {
    const coincideTexto =
      cuenta.codigo.toLowerCase().includes(textoBusqueda) ||
      cuenta.nombre.toLowerCase().includes(textoBusqueda);

    const coincideTipo =
      tipoSeleccionado === 'Todos' ||
      cuenta.tipo === tipoSeleccionado;

    const coincideEstado =
      estadoSeleccionado === 'Todos' ||
      cuenta.estado === estadoSeleccionado;

    return coincideTexto && coincideTipo && coincideEstado;
  });

  const totalCuentas = cuentasFiltradas.length;
  const totalPaginas = Math.max(1, Math.ceil(totalCuentas / tamanoPagina));
  const paginaActual = Math.min(pagina, totalPaginas);
  const indiceInicial = (paginaActual - 1) * tamanoPagina;
  const cuentasVisibles = cuentasFiltradas.slice(
    indiceInicial,
    indiceInicial + tamanoPagina
  );

  const cuentasDeudoras = cuentasIniciales.filter(
    (cuenta) => cuenta.naturaleza === 'Deudora'
  ).length;

  const cuentasAcreedoras = cuentasIniciales.filter(
    (cuenta) => cuenta.naturaleza === 'Acreedora'
  ).length;

  function limpiarFiltros() {
    setBusqueda('');
    setTipoSeleccionado('Todos');
    setEstadoSeleccionado('Todos');
    setPagina(1);
  }

  return (
    <div className="min-h-screen bg-[#f5f7fa]">
      <EncabezadoM7
        seccionActiva="Plan de cuentas"
        onNavegar={onNavegar}
      />

      <main id="contenido" className="mx-auto max-w-7xl px-4 py-8 sm:px-6">
        <div className="mb-6">
          <p className="mb-2 text-sm text-gray-500">
            Contabilidad / Plan de cuentas
          </p>

          <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
            <div>
              <h1 className="text-2xl font-bold text-[#1e3a8a] sm:text-3xl">
                Plan de cuentas
              </h1>
              <p className="mt-2 text-sm text-gray-600">
                Catálogo y clasificación de cuentas contables.
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

        {/* Indicadores del catálogo de demostración */}
        <section className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
          <article className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <p className="text-sm text-gray-500">Cuentas registradas</p>
            <p className="mt-2 text-3xl font-bold text-[#1e3a8a]">
              {cuentasIniciales.length}
            </p>
            <p className="mt-1 text-xs text-gray-500">
              Total del catálogo de ejemplo
            </p>
          </article>

          <article className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <p className="text-sm text-gray-500">Naturaleza deudora</p>
            <p className="mt-2 text-3xl font-bold text-[#1e3a8a]">
              {cuentasDeudoras}
            </p>
            <p className="mt-1 text-xs text-gray-500">
              Cuentas de ejemplo
            </p>
          </article>

          <article className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <p className="text-sm text-gray-500">Naturaleza acreedora</p>
            <p className="mt-2 text-3xl font-bold text-[#1e3a8a]">
              {cuentasAcreedoras}
            </p>
            <p className="mt-1 text-xs text-gray-500">
              Cuentas de ejemplo
            </p>
          </article>
        </section>

        <section className="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
          <div className="border-b border-gray-200 px-5 py-4">
            <h2 className="text-lg font-bold text-gray-800">
              Catálogo contable
            </h2>
            <p className="mt-1 text-sm text-gray-500">
              Busca y filtra las cuentas registradas.
            </p>
          </div>

          {/* Búsqueda y filtros */}
          <div className="grid grid-cols-1 gap-4 border-b border-gray-200 p-5 md:grid-cols-2 xl:grid-cols-4">
            <div className="xl:col-span-2">
              <label
                htmlFor="buscar-cuenta"
                className="mb-2 block text-sm font-medium text-gray-700"
              >
                Buscar cuenta
              </label>
              <div className="relative">
                <span className="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-400">
                  <svg
                    aria-hidden="true"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="2"
                    className="h-5 w-5"
                  >
                    <circle cx="11" cy="11" r="7" />
                    <path d="m16 16 4 4" />
                  </svg>
                </span>
                <input
                  id="buscar-cuenta"
                  type="search"
                  value={busqueda}
                  onChange={(e) => {
                    setBusqueda(e.target.value);
                    setPagina(1);
                  }}
                  placeholder="Código o nombre de la cuenta..."
                  className="w-full rounded-lg border border-gray-300 py-2.5 pl-10 pr-3 text-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                />
              </div>
            </div>

            <div>
              <label
                htmlFor="filtrar-tipo"
                className="mb-2 block text-sm font-medium text-gray-700"
              >
                Tipo de cuenta
              </label>
              <select
                id="filtrar-tipo"
                value={tipoSeleccionado}
                onChange={(e) => {
                  setTipoSeleccionado(e.target.value);
                  setPagina(1);
                }}
                className="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
              >
                <option value="Todos">Todos los tipos</option>
                {tiposCuenta.map((tipo) => (
                  <option key={tipo} value={tipo}>
                    {tipo}
                  </option>
                ))}
              </select>
            </div>

            <div>
              <label
                htmlFor="filtrar-estado"
                className="mb-2 block text-sm font-medium text-gray-700"
              >
                Estado
              </label>
              <select
                id="filtrar-estado"
                value={estadoSeleccionado}
                onChange={(e) => {
                  setEstadoSeleccionado(e.target.value);
                  setPagina(1);
                }}
                className="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
              >
                <option value="Todos">Todos los estados</option>
                <option value="Activa">Activas</option>
                <option value="Inactiva">Inactivas</option>
              </select>
            </div>

            <div className="flex items-end md:col-span-2 xl:col-span-4">
              <button
                type="button"
                onClick={limpiarFiltros}
                className="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50"
              >
                Limpiar filtros
              </button>
            </div>
          </div>

          {/* Tamaño de página y resultado de búsqueda */}
          <div className="flex flex-col gap-3 border-b border-gray-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <p className="text-sm text-gray-600" aria-live="polite">
              {totalCuentas === 0
                ? 'No se encontraron cuentas.'
                : `Mostrando ${indiceInicial + 1}–${Math.min(
                    indiceInicial + tamanoPagina,
                    totalCuentas
                  )} de ${totalCuentas} cuentas`}
            </p>

            <div className="flex items-center gap-2">
              <label
                htmlFor="tamano-pagina"
                className="text-sm text-gray-600"
              >
                Filas por página
              </label>
              <select
                id="tamano-pagina"
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


          {/* Vista móvil: cuentas como tarjetas */}
          <div className="space-y-3 p-3 md:hidden">
            {cuentasVisibles.map((cuenta) => (
              <article
                key={cuenta.codigo}
                className="rounded-lg border border-gray-200 bg-white p-4"
              >
                <div className="flex items-start justify-between gap-3">
                  <div className="min-w-0">
                    <p className="text-xs font-semibold uppercase tracking-wide text-gray-500">
                      Código
                    </p>
                    <p className="break-words font-bold text-[#1e3a8a]">
                      {cuenta.codigo}
                    </p>
                  </div>

                  <span
                    className={`shrink-0 rounded-full px-3 py-1 text-xs font-semibold ${
                      cuenta.estado === 'Activa'
                        ? 'bg-green-100 text-green-800'
                        : 'bg-gray-100 text-gray-700'
                    }`}
                  >
                    {cuenta.estado}
                  </span>
                </div>

                <h3 className="mt-3 break-words font-semibold text-gray-900">
                  {cuenta.nivel > 1 && (
                    <span className="mr-1 text-gray-400">↳</span>
                  )}
                  {cuenta.nombre}
                </h3>

                <dl className="mt-4 grid grid-cols-2 gap-4 border-t border-gray-100 pt-3 text-sm">
                  <div>
                    <dt className="text-gray-500">Tipo</dt>
                    <dd className="break-words font-medium text-gray-800">
                      {cuenta.tipo}
                    </dd>
                  </div>

                  <div>
                    <dt className="text-gray-500">Naturaleza</dt>
                    <dd>
                      <span
                        className={`inline-flex rounded-full px-2 py-1 text-xs font-semibold ${
                          cuenta.naturaleza === 'Deudora'
                            ? 'bg-blue-100 text-blue-800'
                            : 'bg-lime-100 text-lime-800'
                        }`}
                      >
                        {cuenta.naturaleza}
                      </span>
                    </dd>
                  </div>

                  <div>
                    <dt className="text-gray-500">Cuenta padre</dt>
                    <dd className="break-words font-medium text-gray-800">
                      {cuenta.cuentaPadre ?? '—'}
                    </dd>
                  </div>

                  <div>
                    <dt className="text-gray-500">Nivel</dt>
                    <dd className="font-medium text-gray-800">
                      {cuenta.nivel}
                    </dd>
                  </div>

                  <div>
                    <dt className="text-gray-500">Permite movimientos</dt>
                    <dd className="font-medium text-gray-800">
                      {cuenta.permiteMovimientos ? 'Sí' : 'No'}
                    </dd>
                  </div>
                </dl>
              </article>
            ))}

            {cuentasVisibles.length === 0 && (
              <div className="rounded-lg border border-gray-200 p-6 text-center text-sm text-gray-500">
                No hay cuentas que coincidan con los filtros.
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
                  <th scope="col" className="px-4 py-4 font-semibold">Nombre de la cuenta</th>
                  <th scope="col" className="px-4 py-4 font-semibold">Tipo</th>
                  <th scope="col" className="px-4 py-4 font-semibold">Naturaleza</th>
                  <th scope="col" className="px-4 py-4 font-semibold">Cuenta padre</th>
                  <th scope="col" className="px-4 py-4 font-semibold">Nivel</th>
                  <th scope="col" className="px-4 py-4 font-semibold">Movimientos</th>
                  <th scope="col" className="px-4 py-4 font-semibold">Estado</th>
                </tr>
              </thead>

              <tbody className="divide-y divide-gray-100">
                {cuentasVisibles.map((cuenta) => (
                  <tr key={cuenta.codigo} className="transition hover:bg-blue-50">
                    <td className="whitespace-nowrap px-4 py-4 font-semibold text-[#1e3a8a]">
                      {cuenta.codigo}
                    </td>
                    <td className="px-4 py-4 font-medium text-gray-800">
                      {cuenta.nivel > 1 && (
                        <span className="mr-1 text-gray-400">↳</span>
                      )}
                      {cuenta.nombre}
                    </td>
                    <td className="whitespace-nowrap px-4 py-4 text-gray-600">
                      {cuenta.tipo}
                    </td>
                    <td className="whitespace-nowrap px-4 py-4">
                      <span
                        className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold ${
                          cuenta.naturaleza === 'Deudora'
                            ? 'bg-blue-100 text-blue-800'
                            : 'bg-lime-100 text-lime-800'
                        }`}
                      >
                        {cuenta.naturaleza}
                      </span>
                    </td>
                    <td className="whitespace-nowrap px-4 py-4 text-gray-600">
                      {cuenta.cuentaPadre ?? '—'}
                    </td>
                    <td className="px-4 py-4 text-gray-600">{cuenta.nivel}</td>
                    <td className="px-4 py-4">
                      {cuenta.permiteMovimientos ? 'Sí' : 'No'}
                    </td>
                    <td className="px-4 py-4">
                      <span
                        className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold ${
                          cuenta.estado === 'Activa'
                            ? 'bg-green-100 text-green-800'
                            : 'bg-gray-100 text-gray-700'
                        }`}
                      >
                        {cuenta.estado}
                      </span>
                    </td>
                  </tr>
                ))}

                {cuentasVisibles.length === 0 && (
                  <tr>
                    <td colSpan={8} className="px-5 py-12 text-center text-gray-500">
                      No hay cuentas que coincidan con los filtros.
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


          {/* Navegación entre páginas */}
          <div className="flex flex-col gap-3 border-t border-gray-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <p className="text-sm text-gray-600">
              Página {paginaActual} de {totalPaginas}
            </p>

            <div className="flex items-center gap-2">
              <button
                type="button"
                onClick={() => setPagina(1)}
                disabled={paginaActual === 1}
                className="rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40"
                aria-label="Primera página"
              >
                «
              </button>

              <button
                type="button"
                onClick={() => setPagina((actual) => Math.max(1, actual - 1))}
                disabled={paginaActual === 1}
                className="rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40"
              >
                Anterior
              </button>

              <button
                type="button"
                onClick={() =>
                  setPagina((actual) => Math.min(totalPaginas, actual + 1))
                }
                disabled={paginaActual === totalPaginas || totalCuentas === 0}
                className="rounded-lg bg-[#1e3a8a] px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-40"
              >
                Siguiente
              </button>

              <button
                type="button"
                onClick={() => setPagina(totalPaginas)}
                disabled={paginaActual === totalPaginas || totalCuentas === 0}
                className="rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40"
                aria-label="Última página"
              >
                »
              </button>
            </div>
          </div>
        </section>

        <p className="mt-5 text-xs text-gray-500">
          Datos demostrativos. El catálogo definitivo, su jerarquía y las reglas
          contables deberán validarse con los requisitos del proyecto.
        </p>
      </main>
    </div>
  );
}
