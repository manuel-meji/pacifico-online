import { useEffect, useMemo, useState } from 'react';
import EncabezadoM7 from '../components/EncabezadoM7';
import {
  listarCuentasMayor,
  consultarLibroMayor,
} from '../services/libroMayorApi';

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
  const [cuentas, setCuentas] = useState([]);
  const [cuentaSeleccionada, setCuentaSeleccionada] = useState('');
  const [fechaDesde, setFechaDesde] = useState('');
  const [fechaHasta, setFechaHasta] = useState('');
  const [incluirAnulados, setIncluirAnulados] = useState(false);
  const [busqueda, setBusqueda] = useState('');

  const [cargandoCuentas, setCargandoCuentas] = useState(true);
  const [errorCuentas, setErrorCuentas] = useState('');

  const [movimientos, setMovimientos] = useState([]);
  const [resumen, setResumen] = useState(null);
  const [cargandoMovimientos, setCargandoMovimientos] = useState(false);
  const [errorMovimientos, setErrorMovimientos] = useState('');


  useEffect(() => {
    let componenteActivo = true;

    async function cargarCuentas() {
      try {
        setCargandoCuentas(true);
        setErrorCuentas('');

        const resultado = await listarCuentasMayor();

        if (!componenteActivo) return;

        const listaCuentas = resultado?.cuentas || [];

        setCuentas(listaCuentas);

        if (listaCuentas.length > 0) {
          setCuentaSeleccionada(
            String(listaCuentas[0].id_cuenta_contable)
          );
        }
      } catch (error) {
        if (componenteActivo) {
          setErrorCuentas(error.message);
        }
      } finally {
        if (componenteActivo) {
          setCargandoCuentas(false);
        }
      }
    }

    cargarCuentas();

    return () => {
      componenteActivo = false;
    };
  }, []);

  useEffect(() => {
    let componenteActivo = true;

    async function cargarMovimientos() {
      if (!cuentaSeleccionada) {
        setMovimientos([]);
        setResumen(null);
        setCargandoMovimientos(false);
        return;
      }

      try {
        setCargandoMovimientos(true);
        setErrorMovimientos('');

        const resultado = await consultarLibroMayor({
          id_cuenta_contable: cuentaSeleccionada,
          fecha_desde: fechaDesde,
          fecha_hasta: fechaHasta,
          incluirAnulados: incluirAnulados,
        });

        if (!componenteActivo) return;

        setMovimientos(resultado?.movimientos || []);
        setResumen(resultado || null);
      } catch (error) {
        if (componenteActivo) {
          setErrorMovimientos(error.message);
        }
      } finally {
        if (componenteActivo) {
          setCargandoMovimientos(false);
        }
      }
    }

    cargarMovimientos();

    return () => {
      componenteActivo = false;
    };
  }, [cuentaSeleccionada, fechaDesde, fechaHasta, incluirAnulados]);

  const movimientosCuenta = useMemo(() => {
    const textoBusqueda = busqueda.trim().toLowerCase();

    return movimientos.filter((movimiento) => {
      if (!textoBusqueda) return true;

      return [
        movimiento.asiento,
        movimiento.descripcion,
        movimiento.tipo,
        movimiento.estado,
      ].some((valor) =>
        String(valor ?? '').toLowerCase().includes(textoBusqueda)
      );
    });
  }, [movimientos, busqueda]);

  const cuentaActual = cuentas.find(
    (cuenta) =>
      String(cuenta.id_cuenta_contable) === cuentaSeleccionada
  );

  const resumenVista = {
    cuenta: cuentaActual,
    saldoInicial: resumen?.saldo_inicial ?? 0,
    totalDebe: resumen?.total_debe ?? 0,
    totalHaber: resumen?.total_haber ?? 0,
    saldoNeto: resumen?.saldo_final ?? 0,
  };

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
                {cuentas.map((cuenta) => (
                  <option
                    key={cuenta.id_cuenta_contable}
                    value={String(cuenta.id_cuenta_contable)}
                  >
                    {cuenta.codigo} - {cuenta.nombre}
                  </option>
                ))}
              </select>

              {cargandoCuentas && (
                <p className="mt-2 text-sm text-gray-500">
                  Cargando cuentas contables...
                </p>
              )}

              {errorCuentas && (
                <p className="mt-2 text-sm text-red-600">
                  {errorCuentas}
                </p>
              )}

              {!cargandoCuentas && !errorCuentas && cuentas.length === 0 && (
                <p className="mt-2 text-sm text-gray-500">
                  No hay cuentas contables disponibles.
                </p>
              )}
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
              {resumenVista.cuenta?.codigo}
            </p>
            <p className="mt-1 text-sm text-gray-600">
              {resumenVista.cuenta?.nombre}
            </p>
          </article>

          <article className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <p className="text-sm text-gray-500">Saldo inicial</p>
            <p className="mt-2 text-xl font-bold text-[#1e3a8a]">
              {formatoMoneda(resumenVista.saldoInicial)}
            </p>
          </article>

          <article className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <p className="text-sm text-gray-500">Total debe</p>
            <p className="mt-2 text-xl font-bold text-[#1e3a8a]">
              {formatoMoneda(resumenVista.totalDebe)}
            </p>
          </article>

          <article className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <p className="text-sm text-gray-500">Total haber</p>
            <p className="mt-2 text-xl font-bold text-[#1e3a8a]">
              {formatoMoneda(resumenVista.totalHaber)}
            </p>
          </article>

          <article className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <p className="text-sm text-gray-500">Saldo final</p>
            <p className="mt-2 text-xl font-bold text-[#1e3a8a]">
              {formatoMoneda(resumenVista.saldoNeto)}
            </p>
            <p className="mt-1 text-xs text-gray-500">
              {resumenVista.cuenta?.naturaleza === 'ACREEDORA'
                ? 'Acreedora'
                : 'Deudora'}
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
                key={`${movimiento.id_asiento}-${movimiento.numero_linea}`}
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
                    className={`shrink-0 rounded-full px-3 py-1 text-xs font-semibold ${movimiento.estado === 'REGISTRADO'
                      ? 'bg-green-100 text-green-800'
                      : 'bg-gray-100 text-gray-700'
                      }`}
                  >
                    {movimiento.estado === 'REGISTRADO' ? 'Registrado' : 'Anulado'}
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

          {cargandoMovimientos && (
            <div className="py-6 text-center text-sm text-gray-500">
              Cargando movimientos del Libro Mayor...
            </div>
          )}

          {errorMovimientos && (
            <div className="py-6 text-center text-sm text-red-600">
              {errorMovimientos}
            </div>
          )}

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
                  <tr key={`${movimiento.id_asiento}-${movimiento.numero_linea}`}
                    className="transition hover:bg-blue-50"
                  >                    <td className="whitespace-nowrap px-4 py-4 text-gray-600">
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
                        className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold ${movimiento.estado === 'REGISTRADO'
                          ? 'bg-green-100 text-green-800'
                          : 'bg-gray-100 text-gray-700'
                          }`}
                      >
                        {movimiento.estado === 'REGISTRADO' ? 'Registrado' : 'Anulado'}
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
