
import { useMemo, useState } from 'react';
import EncabezadoM7 from '../components/EncabezadoM7';

// Datos demostrativos. Se sustituirán por movimientos contables reales.
// Los movimientos se muestran cronológicamente para calcular el saldo.
const movimientosIniciales = [
  {
    id: 1,
    fecha: '2026-10-15',
    asiento: 'ASI-0001',
    codigo: '1.2.0',
    cuenta: 'Cuentas por cobrar',
    descripcion: 'Venta de demostración',
    debe: 90000,
    haber: 0,
    estado: 'Anulado',
  },
  {
    id: 2,
    fecha: '2026-10-16',
    asiento: 'ASI-0002',
    codigo: '6.0.0',
    cuenta: 'Gastos operativos',
    descripcion: 'Ajuste contable de demostración',
    debe: 15000,
    haber: 0,
    estado: 'Registrado',
  },
  {
    id: 3,
    fecha: '2026-10-16',
    asiento: 'ASI-0002',
    codigo: '1.1.0',
    cuenta: 'Caja y bancos',
    descripcion: 'Ajuste contable de demostración',
    debe: 0,
    haber: 15000,
    estado: 'Registrado',
  },
  {
    id: 4,
    fecha: '2026-10-17',
    asiento: 'ASI-0003',
    codigo: '5.0.0',
    cuenta: 'Costo de ventas',
    descripcion: 'Costo de productos vendidos',
    debe: 72000,
    haber: 0,
    estado: 'Registrado',
  },
  {
    id: 5,
    fecha: '2026-10-17',
    asiento: 'ASI-0003',
    codigo: '1.3.0',
    cuenta: 'Inventario',
    descripcion: 'Salida de inventario por venta',
    debe: 0,
    haber: 72000,
    estado: 'Registrado',
  },
  {
    id: 6,
    fecha: '2026-10-18',
    asiento: 'ASI-0004',
    codigo: '1.2.0',
    cuenta: 'Cuentas por cobrar',
    descripcion: 'Venta automática de demostración',
    debe: 125000,
    haber: 0,
    estado: 'Registrado',
  },
  {
    id: 7,
    fecha: '2026-10-18',
    asiento: 'ASI-0004',
    codigo: '4.0.0',
    cuenta: 'Ingresos por ventas',
    descripcion: 'Venta automática de demostración',
    debe: 0,
    haber: 125000,
    estado: 'Registrado',
  },
];

const cuentasDisponibles = [
  { codigo: '1.1.0', nombre: 'Caja y bancos', naturaleza: 'Deudora' },
  { codigo: '1.2.0', nombre: 'Cuentas por cobrar', naturaleza: 'Deudora' },
  { codigo: '1.3.0', nombre: 'Inventario', naturaleza: 'Deudora' },
  { codigo: '2.1.0', nombre: 'Cuentas por pagar', naturaleza: 'Acreedora' },
  { codigo: '4.0.0', nombre: 'Ingresos por ventas', naturaleza: 'Acreedora' },
  { codigo: '5.0.0', nombre: 'Costo de ventas', naturaleza: 'Deudora' },
  { codigo: '6.0.0', nombre: 'Gastos operativos', naturaleza: 'Deudora' },
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

export default function LibroMayorView({ onNavegar }) {
  const [cuentaSeleccionada, setCuentaSeleccionada] = useState('1.2.0');
  const [fechaDesde, setFechaDesde] = useState('');
  const [fechaHasta, setFechaHasta] = useState('');
  const [incluirAnulados, setIncluirAnulados] = useState(false);
  const [busqueda, setBusqueda] = useState('');

  const movimientosCuenta = useMemo(() => {
    return movimientosIniciales
      .filter((movimiento) => {
        const coincideCuenta = movimiento.codigo === cuentaSeleccionada;
        const coincideFechaDesde =
          !fechaDesde || movimiento.fecha >= fechaDesde;
        const coincideFechaHasta =
          !fechaHasta || movimiento.fecha <= fechaHasta;
        const coincideEstado =
          incluirAnulados || movimiento.estado !== 'Anulado';
        const coincideBusqueda = [
          movimiento.asiento,
          movimiento.descripcion,
          movimiento.cuenta,
        ].some((valor) =>
          valor.toLowerCase().includes(busqueda.trim().toLowerCase())
        );

        return (
          coincideCuenta &&
          coincideFechaDesde &&
          coincideFechaHasta &&
          coincideEstado &&
          coincideBusqueda
        );
      })
      .sort((a, b) => {
        const porFecha = a.fecha.localeCompare(b.fecha);
        return porFecha || a.id - b.id;
      });
  }, [
    cuentaSeleccionada,
    fechaDesde,
    fechaHasta,
    incluirAnulados,
    busqueda,
  ]);

  const resumen = useMemo(() => {
    const cuenta = cuentasDisponibles.find(
      (item) => item.codigo === cuentaSeleccionada
    );

    const totalDebe = movimientosCuenta.reduce(
      (total, movimiento) => total + movimiento.debe,
      0
    );

    const totalHaber = movimientosCuenta.reduce(
      (total, movimiento) => total + movimiento.haber,
      0
    );

    // Saldo neto del período. Para una cuenta deudora, debe - haber;
    // para una acreedora, haber - debe.
    const saldoNeto =
      cuenta?.naturaleza === 'Acreedora'
        ? totalHaber - totalDebe
        : totalDebe - totalHaber;

    return { cuenta, totalDebe, totalHaber, saldoNeto };
  }, [cuentaSeleccionada, movimientosCuenta]);

  function limpiarFiltros() {
    setFechaDesde('');
    setFechaHasta('');
    setBusqueda('');
    setIncluirAnulados(false);
  }

  return (
    <div className="min-h-screen bg-[#f5f7fa]">
      <EncabezadoM7
        seccionActiva="Libro mayor"
        onNavegar={onNavegar}
      />

      <main id="contenido" className="mx-auto max-w-7xl px-4 py-8 sm:px-6">
        <div className="mb-6">
          <p className="mb-2 text-sm text-gray-500">
            Contabilidad / Libro mayor
          </p>

          <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
            <div>
              <h1 className="text-2xl font-bold text-[#1e3a8a] sm:text-3xl">
                Libro mayor
              </h1>
              <p className="mt-2 text-sm text-gray-600">
                Movimientos y saldo por cuenta contable.
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

        <section className="mb-6 rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
          <h2 className="text-lg font-bold text-gray-800">
            Parámetros de consulta
          </h2>

          <div className="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div className="xl:col-span-2">
              <label
                htmlFor="cuenta-mayor"
                className="mb-2 block text-sm font-medium text-gray-700"
              >
                Cuenta contable
              </label>
              <select
                id="cuenta-mayor"
                value={cuentaSeleccionada}
                onChange={(e) => setCuentaSeleccionada(e.target.value)}
                className="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-blue-500"
              >
                {cuentasDisponibles.map((cuenta) => (
                  <option key={cuenta.codigo} value={cuenta.codigo}>
                    {cuenta.codigo} — {cuenta.nombre}
                  </option>
                ))}
              </select>
            </div>

            <div>
              <label
                htmlFor="mayor-desde"
                className="mb-2 block text-sm font-medium text-gray-700"
              >
                Fecha desde
              </label>
              <input
                id="mayor-desde"
                type="date"
                value={fechaDesde}
                max={fechaHasta || undefined}
                onChange={(e) => setFechaDesde(e.target.value)}
                className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm outline-none focus:border-blue-500"
              />
            </div>

            <div>
              <label
                htmlFor="mayor-hasta"
                className="mb-2 block text-sm font-medium text-gray-700"
              >
                Fecha hasta
              </label>
              <input
                id="mayor-hasta"
                type="date"
                value={fechaHasta}
                min={fechaDesde || undefined}
                onChange={(e) => setFechaHasta(e.target.value)}
                className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm outline-none focus:border-blue-500"
              />
            </div>

            <div className="md:col-span-2 xl:col-span-3">
              <label
                htmlFor="buscar-movimiento"
                className="mb-2 block text-sm font-medium text-gray-700"
              >
                Buscar movimiento
              </label>
              <input
                id="buscar-movimiento"
                type="search"
                value={busqueda}
                onChange={(e) => setBusqueda(e.target.value)}
                placeholder="Número de asiento o descripción..."
                className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm outline-none focus:border-blue-500"
              />
            </div>

            <div className="flex flex-col justify-end gap-3">
              <label className="flex items-center gap-2 text-sm text-gray-700">
                <input
                  type="checkbox"
                  checked={incluirAnulados}
                  onChange={(e) => setIncluirAnulados(e.target.checked)}
                  className="h-4 w-4 accent-[#1e3a8a]"
                />
                Incluir anulados
              </label>
              <button
                type="button"
                onClick={limpiarFiltros}
                className="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50"
              >
                Limpiar filtros
              </button>
            </div>
          </div>
        </section>

        <section className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <article className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <p className="text-sm text-gray-500">Cuenta seleccionada</p>
            <p className="mt-2 font-bold text-[#1e3a8a]">
              {resumen.cuenta?.codigo}
            </p>
            <p className="mt-1 text-sm text-gray-600">
              {resumen.cuenta?.nombre}
            </p>
          </article>

          <article className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <p className="text-sm text-gray-500">Total debe</p>
            <p className="mt-2 text-xl font-bold text-[#1e3a8a]">
              {formatoMoneda(resumen.totalDebe)}
            </p>
          </article>

          <article className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <p className="text-sm text-gray-500">Total haber</p>
            <p className="mt-2 text-xl font-bold text-[#1e3a8a]">
              {formatoMoneda(resumen.totalHaber)}
            </p>
          </article>

          <article className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <p className="text-sm text-gray-500">Saldo neto del período</p>
            <p className="mt-2 text-xl font-bold text-[#1e3a8a]">
              {formatoMoneda(resumen.saldoNeto)}
            </p>
            <p className="mt-1 text-xs text-gray-500">
              {resumen.cuenta?.naturaleza}
            </p>
          </article>
        </section>

        <section className="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
          <div className="border-b border-gray-200 px-5 py-4">
            <h2 className="text-lg font-bold text-gray-800">
              Movimientos de la cuenta
            </h2>
            <p className="mt-1 text-sm text-gray-500">
              {movimientosCuenta.length} movimiento(s) según los filtros actuales.
            </p>
          </div>


          {/* Vista móvil: movimientos como tarjetas */}
          <div className="space-y-3 p-3 md:hidden">
            {movimientosCuenta.map((movimiento) => (
              <article
                key={movimiento.id}
                className="rounded-lg border border-gray-200 bg-white p-4"
              >
                <div className="flex items-start justify-between gap-3">
                  <div className="min-w-0">
                    <p className="text-xs font-semibold uppercase tracking-wide text-gray-500">
                      Asiento
                    </p>
                    <p className="break-words font-bold text-[#1e3a8a]">
                      {movimiento.asiento}
                    </p>
                  </div>

                  <span
                    className={`shrink-0 rounded-full px-3 py-1 text-xs font-semibold ${
                      movimiento.estado === 'Registrado'
                        ? 'bg-green-100 text-green-800'
                        : 'bg-gray-100 text-gray-700'
                    }`}
                  >
                    {movimiento.estado}
                  </span>
                </div>

                <p className="mt-3 text-sm text-gray-500">Descripción</p>
                <p className="break-words font-medium text-gray-900">
                  {movimiento.descripcion}
                </p>

                <dl className="mt-4 grid grid-cols-2 gap-4 border-t border-gray-100 pt-3 text-sm">
                  <div>
                    <dt className="text-gray-500">Fecha</dt>
                    <dd className="break-words font-medium text-gray-800">
                      {formatoFecha(movimiento.fecha)}
                    </dd>
                  </div>
                  <div>
                    <dt className="text-gray-500">Debe</dt>
                    <dd className="break-words font-semibold text-gray-900">
                      {movimiento.debe
                        ? formatoMoneda(movimiento.debe)
                        : '—'}
                    </dd>
                  </div>
                  <div className="col-span-2">
                    <dt className="text-gray-500">Haber</dt>
                    <dd className="break-words font-semibold text-gray-900">
                      {movimiento.haber
                        ? formatoMoneda(movimiento.haber)
                        : '—'}
                    </dd>
                  </div>
                </dl>
              </article>
            ))}

            {movimientosCuenta.length === 0 && (
              <div className="rounded-lg border border-gray-200 p-6 text-center text-sm text-gray-500">
                No hay movimientos que coincidan con la cuenta y los filtros.
              </div>
            )}
          </div>

          {/* Vista de escritorio: tabla original */}
          <div className="hidden md:block">
            <table className="w-full text-left text-sm">
              <thead className="bg-[#1e3a8a] text-white">
                <tr>
                  <th scope="col" className="px-4 py-4 font-semibold">Fecha</th>
                  <th scope="col" className="px-4 py-4 font-semibold">Asiento</th>
                  <th scope="col" className="px-4 py-4 font-semibold">Descripción</th>
                  <th scope="col" className="px-4 py-4 text-right font-semibold">Debe</th>
                  <th scope="col" className="px-4 py-4 text-right font-semibold">Haber</th>
                  <th scope="col" className="px-4 py-4 font-semibold">Estado</th>
                </tr>
              </thead>

              <tbody className="divide-y divide-gray-100">
                {movimientosCuenta.map((movimiento) => (
                  <tr key={movimiento.id} className="transition hover:bg-blue-50">
                    <td className="whitespace-nowrap px-4 py-4 text-gray-600">
                      {formatoFecha(movimiento.fecha)}
                    </td>
                    <td className="whitespace-nowrap px-4 py-4 font-semibold text-[#1e3a8a]">
                      {movimiento.asiento}
                    </td>
                    <td className="px-4 py-4 text-gray-800">
                      {movimiento.descripcion}
                    </td>
                    <td className="whitespace-nowrap px-4 py-4 text-right">
                      {movimiento.debe ? formatoMoneda(movimiento.debe) : '—'}
                    </td>
                    <td className="whitespace-nowrap px-4 py-4 text-right">
                      {movimiento.haber ? formatoMoneda(movimiento.haber) : '—'}
                    </td>
                    <td className="px-4 py-4">
                      <span
                        className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold ${
                          movimiento.estado === 'Registrado'
                            ? 'bg-green-100 text-green-800'
                            : 'bg-gray-100 text-gray-700'
                        }`}
                      >
                        {movimiento.estado}
                      </span>
                    </td>
                  </tr>
                ))}

                {movimientosCuenta.length === 0 && (
                  <tr>
                    <td colSpan={6} className="px-5 py-12 text-center text-gray-500">
                      No hay movimientos que coincidan con la cuenta y los filtros.
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>


          <div className="border-t border-gray-200 px-5 py-4 text-sm text-gray-500">
            Saldo calculado a partir de los movimientos visibles del período.
            No representa el saldo histórico de una cuenta real.
          </div>
        </section>

        <p className="mt-5 text-xs text-gray-500">
          Datos demostrativos. La consulta definitiva deberá utilizar los
          asientos contables registrados y las reglas de contabilización de M7.
        </p>
      </main>
    </div>
  );
}
