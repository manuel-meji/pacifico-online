
import { useMemo, useState } from 'react';
import EncabezadoM7 from '../components/EncabezadoM7';

// Datos ficticios para el prototipo. No provienen de la base de datos.
const partidasIniciales = [
  {
    id: 1,
    codigo: '4.0.0',
    nombre: 'Ingresos por ventas',
    categoria: 'Ingresos',
    monto: 2450000,
  },
  {
    id: 2,
    codigo: '4.1.0',
    nombre: 'Devoluciones y descuentos',
    categoria: 'Ingresos',
    monto: 0,
  },
  {
    id: 3,
    codigo: '5.0.0',
    nombre: 'Costo de ventas',
    categoria: 'Costos',
    monto: 1420000,
  },
  {
    id: 4,
    codigo: '6.1.0',
    nombre: 'Gastos administrativos',
    categoria: 'Gastos operativos',
    monto: 350000,
  },
  {
    id: 5,
    codigo: '6.2.0',
    nombre: 'Gastos de comercialización',
    categoria: 'Gastos operativos',
    monto: 180000,
  },
  {
    id: 6,
    codigo: '6.3.0',
    nombre: 'Gastos de servicios',
    categoria: 'Gastos operativos',
    monto: 95000,
  },
];

const categorias = ['Ingresos', 'Costos', 'Gastos operativos'];

function formatoMoneda(monto) {
  return new Intl.NumberFormat('es-CR', {
    style: 'currency',
    currency: 'CRC',
    maximumFractionDigits: 2,
  }).format(monto);
}

export default function EstadoResultadosView({ onNavegar }) {
  const [busqueda, setBusqueda] = useState('');
  const [categoriaSeleccionada, setCategoriaSeleccionada] = useState('Todas');

  const partidasFiltradas = useMemo(() => {
    const texto = busqueda.trim().toLowerCase();

    return partidasIniciales.filter((partida) => {
      const coincideTexto =
        partida.codigo.toLowerCase().includes(texto) ||
        partida.nombre.toLowerCase().includes(texto);

      const coincideCategoria =
        categoriaSeleccionada === 'Todas' ||
        partida.categoria === categoriaSeleccionada;

      return coincideTexto && coincideCategoria;
    });
  }, [busqueda, categoriaSeleccionada]);

  // Los totales se calculan con todas las partidas, no con las filas filtradas.
  const ingresos = partidasIniciales
    .filter((partida) => partida.categoria === 'Ingresos')
    .reduce((total, partida) => total + partida.monto, 0);

  const costos = partidasIniciales
    .filter((partida) => partida.categoria === 'Costos')
    .reduce((total, partida) => total + partida.monto, 0);

  const gastosOperativos = partidasIniciales
    .filter((partida) => partida.categoria === 'Gastos operativos')
    .reduce((total, partida) => total + partida.monto, 0);

  const utilidadBruta = ingresos - costos;
  const utilidadOperativa = utilidadBruta - gastosOperativos;

  function limpiarFiltros() {
    setBusqueda('');
    setCategoriaSeleccionada('Todas');
  }

  return (
    <div className="min-h-screen bg-[#f5f7fa]">
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
                Ingresos, costos y gastos del período.
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

        <section className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <article className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <p className="text-sm text-gray-500">Ingresos netos</p>
            <p className="mt-2 text-2xl font-bold text-[#1e3a8a]">
              {formatoMoneda(ingresos)}
            </p>
          </article>

          <article className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <p className="text-sm text-gray-500">Costo de ventas</p>
            <p className="mt-2 text-2xl font-bold text-[#1e3a8a]">
              {formatoMoneda(costos)}
            </p>
          </article>

          <article className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <p className="text-sm text-gray-500">Utilidad bruta</p>
            <p className="mt-2 text-2xl font-bold text-[#1e3a8a]">
              {formatoMoneda(utilidadBruta)}
            </p>
          </article>

          <article className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <p className="text-sm text-gray-500">Utilidad operativa</p>
            <p className={`mt-2 text-2xl font-bold ${
              utilidadOperativa >= 0 ? 'text-green-700' : 'text-red-700'
            }`}>
              {formatoMoneda(utilidadOperativa)}
            </p>
          </article>
        </section>

        <section className="mb-6 rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
          <h2 className="text-lg font-bold text-gray-800">
            Resultado del período
          </h2>

          <div className="mt-4 space-y-4">
            <div className="flex flex-col justify-between gap-1 border-b border-gray-100 pb-3 sm:flex-row">
              <span className="text-gray-700">Ingresos netos</span>
              <span className="font-semibold text-gray-900">
                {formatoMoneda(ingresos)}
              </span>
            </div>

            <div className="flex flex-col justify-between gap-1 border-b border-gray-100 pb-3 sm:flex-row">
              <span className="text-gray-700">Menos: costo de ventas</span>
              <span className="font-semibold text-gray-900">
                ({formatoMoneda(costos)})
              </span>
            </div>

            <div className="flex flex-col justify-between gap-1 rounded-lg bg-blue-50 px-4 py-3 sm:flex-row">
              <span className="font-bold text-[#1e3a8a]">Utilidad bruta</span>
              <span className="font-bold text-[#1e3a8a]">
                {formatoMoneda(utilidadBruta)}
              </span>
            </div>

            <div className="flex flex-col justify-between gap-1 border-b border-gray-100 pb-3 sm:flex-row">
              <span className="text-gray-700">Menos: gastos operativos</span>
              <span className="font-semibold text-gray-900">
                ({formatoMoneda(gastosOperativos)})
              </span>
            </div>

            <div className={`flex flex-col justify-between gap-1 rounded-lg px-4 py-4 sm:flex-row ${
              utilidadOperativa >= 0 ? 'bg-green-50' : 'bg-red-50'
            }`}>
              <span className="font-bold text-gray-900">
                {utilidadOperativa >= 0
                  ? 'Utilidad operativa del período'
                  : 'Pérdida operativa del período'}
              </span>
              <span className={`text-lg font-bold ${
                utilidadOperativa >= 0 ? 'text-green-800' : 'text-red-800'
              }`}>
                {formatoMoneda(utilidadOperativa)}
              </span>
            </div>
          </div>
        </section>

        <section className="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
          <div className="border-b border-gray-200 px-5 py-4">
            <h2 className="text-lg font-bold text-gray-800">
              Detalle de ingresos, costos y gastos
            </h2>
            <p className="mt-1 text-sm text-gray-500">
              Busca partidas o filtra por categoría.
            </p>
          </div>

          <div className="grid grid-cols-1 gap-4 border-b border-gray-200 p-5 md:grid-cols-3">
            <div className="md:col-span-2">
              <label
                htmlFor="buscar-resultados"
                className="mb-2 block text-sm font-medium text-gray-700"
              >
                Buscar partida
              </label>
              <input
                id="buscar-resultados"
                type="search"
                value={busqueda}
                onChange={(e) => setBusqueda(e.target.value)}
                placeholder="Código o nombre de la cuenta..."
                className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm outline-none focus:border-blue-500"
              />
            </div>

            <div>
              <label
                htmlFor="categoria-resultados"
                className="mb-2 block text-sm font-medium text-gray-700"
              >
                Categoría
              </label>
              <select
                id="categoria-resultados"
                value={categoriaSeleccionada}
                onChange={(e) => setCategoriaSeleccionada(e.target.value)}
                className="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-blue-500"
              >
                <option value="Todas">Todas las categorías</option>
                {categorias.map((categoria) => (
                  <option key={categoria} value={categoria}>
                    {categoria}
                  </option>
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

          <div className="border-b border-gray-200 px-5 py-4">
            <p className="text-sm text-gray-600" aria-live="polite">
              Mostrando {partidasFiltradas.length} de {partidasIniciales.length} partidas.
              Los indicadores no cambian con los filtros.
            </p>
          </div>


          {/* Vista móvil: partidas como tarjetas */}
          <div className="space-y-3 p-3 md:hidden">
            {partidasFiltradas.map((partida) => (
              <article
                key={partida.id}
                className="rounded-lg border border-gray-200 bg-white p-4"
              >
                <div className="flex items-start justify-between gap-3">
                  <div className="min-w-0">
                    <p className="text-xs font-semibold uppercase tracking-wide text-gray-500">
                      Código
                    </p>
                    <p className="break-words font-bold text-[#1e3a8a]">
                      {partida.codigo}
                    </p>
                  </div>

                </div>

                <h3 className="mt-3 break-words font-semibold text-gray-900">
                  {partida.nombre}
                </h3>

                <div className="mt-3 border-t border-gray-100 pt-3">
                  <p className="text-sm text-gray-500">Categoría</p>
                  <p className="break-words text-sm font-medium text-gray-800">
                    {partida.categoria}
                  </p>
                </div>

                <div className="mt-3">
                  <p className="text-sm text-gray-500">Monto</p>
                  <p className="break-words font-semibold text-gray-900">
                    {formatoMoneda(partida.monto)}
                  </p>
                </div>
              </article>
            ))}

            {partidasFiltradas.length === 0 && (
              <div className="rounded-lg border border-gray-200 p-6 text-center text-sm text-gray-500">
                No hay partidas que coincidan con los filtros.
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
                  <th scope="col" className="px-4 py-4 font-semibold">Cuenta</th>
                  <th scope="col" className="px-4 py-4 font-semibold">Categoría</th>
                  <th scope="col" className="px-4 py-4 text-right font-semibold">Monto</th>
                </tr>
              </thead>

              <tbody className="divide-y divide-gray-100">
                {partidasFiltradas.map((partida) => (
                  <tr key={partida.id} className="transition hover:bg-blue-50">
                    <td className="whitespace-nowrap px-4 py-4 font-semibold text-[#1e3a8a]">
                      {partida.codigo}
                    </td>
                    <td className="px-4 py-4 font-medium text-gray-800">
                      {partida.nombre}
                    </td>
                    <td className="px-4 py-4 text-gray-600">
                      {partida.categoria}
                    </td>
                    <td className="whitespace-nowrap px-4 py-4 text-right font-semibold text-gray-800">
                      {formatoMoneda(partida.monto)}
                    </td>
                  </tr>
                ))}

                {partidasFiltradas.length === 0 && (
                  <tr>
                    <td colSpan={4} className="px-5 py-12 text-center text-gray-500">
                      No hay partidas que coincidan con los filtros.
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

          <div className="border-t border-gray-200 px-5 py-4 text-sm text-gray-500">
            Utilidad operativa demostrativa: {formatoMoneda(utilidadOperativa)}.
          </div>
        </section>

        <p className="mt-5 text-xs text-gray-500">
          Datos ficticios para el prototipo. Este reporte no incluye todavía
          impuestos, ingresos financieros ni otros resultados no operativos.
        </p>
      </main>
    </div>
  );
}
