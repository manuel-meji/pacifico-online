
import { useEffect, useMemo, useState } from 'react';
import EncabezadoM7 from '../components/EncabezadoM7';
import { consultarBalanceGeneral } from '../services/balanceGeneralApi';

const tiposDisponibles = ['Activo', 'Pasivo', 'Patrimonio'];

const nombresTipos = {
  ACTIVO: 'Activo',
  PASIVO: 'Pasivo',
  PATRIMONIO: 'Patrimonio',
};

function obtenerFechaActual() {
  const fecha = new Date();
  const anio = fecha.getFullYear();
  const mes = String(fecha.getMonth() + 1).padStart(2, '0');
  const dia = String(fecha.getDate()).padStart(2, '0');

  return `${anio}-${mes}-${dia}`;
}

function formatoMoneda(monto) {
  if (monto === null || monto === undefined || !Number.isFinite(monto)) {
    return '—';
  }

  return new Intl.NumberFormat('es-CR', {
    style: 'currency',
    currency: 'CRC',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(monto);
}

function convertirMoneda(centimos) {
  return Number(centimos ?? 0) / 100;
}

function obtenerGrupo(codigo) {
  const partes = String(codigo).split('.');
  return partes.length >= 2 ? partes.slice(0, 2).join('.') : partes[0];
}

export default function BalanceGeneralView({ onNavegar }) {
  const [fechaCorte, setFechaCorte] = useState(obtenerFechaActual);
  const [balance, setBalance] = useState(null);
  const [cargando, setCargando] = useState(true);
  const [error, setError] = useState('');
  const [busqueda, setBusqueda] = useState('');
  const [tipoSeleccionado, setTipoSeleccionado] = useState('Todos');
  const [grupoSeleccionado, setGrupoSeleccionado] = useState('Todos');

  useEffect(() => {
    let activo = true;

    async function cargarBalance() {
      setCargando(true);
      setError('');
      setBalance(null);

      try {
        const resultado = await consultarBalanceGeneral(fechaCorte);

        if (activo) {
          setBalance(resultado);
        }
      } catch (errorConsulta) {
        if (activo) {
          setError(errorConsulta.message || 'No se pudo consultar el Balance general.');
        }
      } finally {
        if (activo) {
          setCargando(false);
        }
      }
    }

    if (fechaCorte) {
      cargarBalance();
    } else {
      setBalance(null);
      setCargando(false);
    }

    return () => {
      activo = false;
    };
  }, [fechaCorte]);

  const cuentasBalance = useMemo(() => {
    if (!Array.isArray(balance?.cuentas)) {
      return [];
    }

    return balance.cuentas
      .filter((cuenta) => nombresTipos[String(cuenta.tipo).toUpperCase()])
      .map((cuenta) => ({
        id: cuenta.id_cuenta_contable,
        codigo: String(cuenta.codigo),
        nombre: cuenta.nombre,
        tipo: nombresTipos[String(cuenta.tipo).toUpperCase()],
        grupo: obtenerGrupo(cuenta.codigo),
        saldo: convertirMoneda(cuenta.saldo_centimos),
      }));
  }, [balance]);

  const cuentasFiltradas = useMemo(() => {
    const texto = busqueda.trim().toLowerCase();

    return cuentasBalance.filter((cuenta) => {
      const coincideBusqueda =
        cuenta.codigo.toLowerCase().includes(texto) ||
        cuenta.nombre.toLowerCase().includes(texto);

      const coincideTipo =
        tipoSeleccionado === 'Todos' ||
        cuenta.tipo === tipoSeleccionado;

      const coincideGrupo =
        grupoSeleccionado === 'Todos' ||
        cuenta.grupo === grupoSeleccionado;

      return coincideBusqueda && coincideTipo && coincideGrupo;
    });
  }, [cuentasBalance, busqueda, tipoSeleccionado, grupoSeleccionado]);

  const gruposDisponibles = useMemo(
    () => [...new Set(cuentasBalance.map((cuenta) => cuenta.grupo))].sort(),
    [cuentasBalance]
  );

  const activos = balance ? convertirMoneda(balance.activos_centimos) : null;
  const pasivos = balance ? convertirMoneda(balance.pasivos_centimos) : null;
  const patrimonio = balance ? convertirMoneda(balance.patrimonio_centimos) : null;
  const resultadoAcumulado = balance
    ? convertirMoneda(balance.resultado_acumulado_centimos)
    : null;

  const totalPasivoPatrimonio =
    balance ? pasivos + patrimonio : null;

  const diferencia =
    balance ? activos - totalPasivoPatrimonio : null;

  const balanceCuadrado = balance ? balance.cuadra === true : null;

  function limpiarFiltros() {
    setBusqueda('');
    setTipoSeleccionado('Todos');
    setGrupoSeleccionado('Todos');
  }

  return (
    <div className="min-h-screen bg-[#f5f7fa]">
      <EncabezadoM7
        seccionActiva="Balance general"
        onNavegar={onNavegar}
      />

      <main id="contenido" className="mx-auto max-w-7xl px-4 py-8 sm:px-6">
        <div className="mb-6">
          <p className="mb-2 text-sm text-gray-500">
            Contabilidad / Balance general
          </p>

          <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
            <div>
              <h1 className="text-2xl font-bold text-[#1e3a8a] sm:text-3xl">
                Balance general
              </h1>
              <p className="mt-2 text-sm text-gray-600">
                Estado de situación financiera de Pacíficos Online.
              </p>
            </div>

            <button
              type="button"
              onClick={() => window.print()}
              disabled={!balance || cargando}
              className="rounded-lg bg-[#1e3a8a] px-5 py-3 text-sm font-semibold text-white transition hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-50"
            >
              Imprimir balance
            </button>
          </div>
        </div>

        <section className="mb-6 rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
          <label
            htmlFor="fecha-corte-balance"
            className="mb-2 block text-sm font-medium text-gray-700"
          >
            Fecha de corte
          </label>
          <input
            id="fecha-corte-balance"
            type="date"
            value={fechaCorte}
            onChange={(e) => setFechaCorte(e.target.value)}
            className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm outline-none focus:border-blue-500 sm:w-auto"
          />
          <p className="mt-2 text-sm text-gray-500">
            El balance incluye los movimientos contables registrados hasta la fecha seleccionada.
          </p>
        </section>

        {cargando && (
          <div
            role="status"
            className="mb-6 rounded-xl border border-gray-200 bg-white p-5 text-sm text-gray-600"
          >
            Consultando información contable...
          </div>
        )}

        {error && !cargando && (
          <div
            role="alert"
            className="mb-6 rounded-xl border border-red-200 bg-red-50 p-5 text-sm text-red-700"
          >
            {error}
          </div>
        )}

        <section className="mb-6 grid grid-cols-1 gap-4 md:grid-cols-3">
          <article className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <p className="text-sm text-gray-500">Total activos</p>
            <p className="mt-2 text-2xl font-bold text-[#1e3a8a]">
              {formatoMoneda(activos)}
            </p>
          </article>

          <article className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <p className="text-sm text-gray-500">Total pasivos</p>
            <p className="mt-2 text-2xl font-bold text-[#1e3a8a]">
              {formatoMoneda(pasivos)}
            </p>
          </article>

          <article className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <p className="text-sm text-gray-500">Total patrimonio</p>
            <p className="mt-2 text-2xl font-bold text-[#1e3a8a]">
              {formatoMoneda(patrimonio)}
            </p>
          </article>
        </section>

        <section
          className="mb-6 rounded-xl border border-gray-200 bg-white p-5 shadow-sm"
          aria-live="polite"
        >
          <h2 className="font-bold text-gray-800">
            Comprobación de la ecuación contable
          </h2>

          <p className="mt-1 text-sm text-gray-600">
            Activos = Pasivos + Patrimonio
          </p>

          {balance ? (
            <div className="mt-3 flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
              <p className="text-sm text-gray-700">
                {formatoMoneda(activos)} = {formatoMoneda(pasivos)} + {formatoMoneda(patrimonio)}
              </p>

              <div className="sm:text-right">
                <span className="text-sm font-semibold text-gray-700">
                  {balanceCuadrado ? 'Balance cuadrado' : 'Diferencia detectada'}
                </span>
                <p className="mt-2 text-sm text-gray-700">
                  Diferencia: {formatoMoneda(diferencia)}
                </p>
              </div>
            </div>
          ) : (
            <p className="mt-3 text-sm text-gray-500">
              Sin información disponible para comprobar la ecuación contable.
            </p>
          )}
        </section>

        <section className="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
          <div className="border-b border-gray-200 px-5 py-4">
            <h2 className="text-lg font-bold text-gray-800">
              Cuentas del balance
            </h2>
            <p className="mt-1 text-sm text-gray-500">
              Cuentas contables con movimientos registrados hasta la fecha de corte.
            </p>
          </div>

          <div className="grid grid-cols-1 gap-4 border-b border-gray-200 p-5 md:grid-cols-3">
            <div>
              <label
                htmlFor="buscar-balance"
                className="mb-2 block text-sm font-medium text-gray-700"
              >
                Buscar cuenta
              </label>
              <input
                id="buscar-balance"
                type="search"
                value={busqueda}
                onChange={(e) => setBusqueda(e.target.value)}
                placeholder="Código o nombre..."
                className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm outline-none focus:border-blue-500"
              />
            </div>

            <div>
              <label
                htmlFor="tipo-balance"
                className="mb-2 block text-sm font-medium text-gray-700"
              >
                Tipo de cuenta
              </label>
              <select
                id="tipo-balance"
                value={tipoSeleccionado}
                onChange={(e) => setTipoSeleccionado(e.target.value)}
                className="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-blue-500"
              >
                <option value="Todos">Todos los tipos</option>
                {tiposDisponibles.map((tipo) => (
                  <option key={tipo} value={tipo}>
                    {tipo}
                  </option>
                ))}
              </select>
            </div>

            <div>
              <label
                htmlFor="grupo-balance"
                className="mb-2 block text-sm font-medium text-gray-700"
              >
                Grupo contable
              </label>
              <select
                id="grupo-balance"
                value={grupoSeleccionado}
                onChange={(e) => setGrupoSeleccionado(e.target.value)}
                className="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-blue-500"
              >
                <option value="Todos">Todos los grupos</option>
                {gruposDisponibles.map((grupo) => (
                  <option key={grupo} value={grupo}>
                    {grupo}
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
              Mostrando {cuentasFiltradas.length} de {cuentasBalance.length} cuentas.
              Los totales del balance no cambian con los filtros.
            </p>
          </div>

          <div className="space-y-3 p-3 md:hidden">
            {cuentasFiltradas.map((cuenta) => (
              <article
                key={cuenta.id}
                className="rounded-lg border border-gray-200 bg-white p-4"
              >
                <div className="mb-3 flex items-start justify-between gap-3">
                  <div className="min-w-0">
                    <p className="text-xs font-semibold uppercase tracking-wide text-gray-500">
                      Código
                    </p>
                    <p className="break-words font-bold text-[#1e3a8a]">
                      {cuenta.codigo}
                    </p>
                  </div>
                </div>

                <h3 className="break-words font-semibold text-gray-900">
                  {cuenta.nombre}
                </h3>

                <dl className="mt-3 grid grid-cols-2 gap-3 border-t border-gray-100 pt-3 text-sm">
                  <div>
                    <dt className="text-gray-500">Tipo</dt>
                    <dd className="break-words font-medium text-gray-800">
                      {cuenta.tipo}
                    </dd>
                  </div>
                  <div>
                    <dt className="text-gray-500">Grupo</dt>
                    <dd className="break-words font-medium text-gray-800">
                      {cuenta.grupo}
                    </dd>
                  </div>
                  <div className="col-span-2">
                    <dt className="text-gray-500">Saldo</dt>
                    <dd className="font-semibold text-gray-900">
                      {formatoMoneda(cuenta.saldo)}
                    </dd>
                  </div>
                </dl>
              </article>
            ))}

            {cuentasFiltradas.length === 0 && (
              <div className="rounded-lg border border-gray-200 p-6 text-center text-sm text-gray-500">
                {cargando
                  ? 'Cargando cuentas...'
                  : error
                    ? 'No fue posible consultar las cuentas.'
                    : 'No hay cuentas que coincidan con los filtros.'}

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

          <div className="hidden md:block">
            <table className="w-full text-left text-sm">
              <thead className="bg-[#1e3a8a] text-white">
                <tr>
                  <th scope="col" className="px-4 py-4 font-semibold">Código</th>
                  <th scope="col" className="px-4 py-4 font-semibold">Cuenta</th>
                  <th scope="col" className="px-4 py-4 font-semibold">Tipo</th>
                  <th scope="col" className="px-4 py-4 font-semibold">Grupo</th>
                  <th scope="col" className="px-4 py-4 text-right font-semibold">Saldo</th>
                </tr>
              </thead>

              <tbody className="divide-y divide-gray-100">
                {cuentasFiltradas.map((cuenta) => (
                  <tr key={cuenta.id} className="transition hover:bg-blue-50">
                    <td className="whitespace-nowrap px-4 py-4 font-semibold text-[#1e3a8a]">
                      {cuenta.codigo}
                    </td>
                    <td className="px-4 py-4 font-medium text-gray-800">
                      {cuenta.nombre}
                    </td>
                    <td className="px-4 py-4 text-gray-600">
                      {cuenta.tipo}
                    </td>
                    <td className="px-4 py-4 text-gray-600">
                      {cuenta.grupo}
                    </td>
                    <td className="whitespace-nowrap px-4 py-4 text-right font-medium text-gray-800">
                      {formatoMoneda(cuenta.saldo)}
                    </td>
                  </tr>
                ))}

                {cuentasFiltradas.length === 0 && (
                  <tr>
                    <td colSpan={5} className="px-5 py-12 text-center text-gray-500">
                      {cargando
                        ? 'Cargando cuentas...'
                        : error
                          ? 'No fue posible consultar las cuentas.'
                          : 'No hay cuentas que coincidan con los filtros.'}

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

          <div className="border-t border-gray-200 px-5 py-4">
            <div className="flex flex-col gap-2 sm:flex-row sm:justify-between">
              <span className="text-sm text-gray-600">
                Total de activos: {formatoMoneda(activos)}
              </span>
              <span className="text-sm text-gray-600">
                Total pasivos + patrimonio: {formatoMoneda(totalPasivoPatrimonio)}
              </span>
            </div>
          </div>
        </section>

        <p className="mt-5 text-xs text-gray-500">
          {balance
            ? `Balance consultado al ${balance.fecha_corte}.`
            : 'Los saldos se mostrarán cuando esté disponible la información contable.'}
        </p>
      </main>

      {balance && (
        <section className="m7-print-area">
          <h1>Balance general</h1>

          <p>Pacíficos Online</p>
          <p>Fecha de corte: {balance.fecha_corte}</p>

          <h2>Estado de situación financiera</h2>

          <table>
            <thead>
              <tr>
                <th>Concepto</th>
                <th>Monto</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>Total activos</td>
                <td>{formatoMoneda(activos)}</td>
              </tr>
              <tr>
                <td>Total pasivos</td>
                <td>{formatoMoneda(pasivos)}</td>
              </tr>
              <tr>
                <td>Total patrimonio</td>
                <td>{formatoMoneda(patrimonio)}</td>
              </tr>
              <tr>
                <td>Resultado acumulado incluido en patrimonio</td>
                <td>{formatoMoneda(resultadoAcumulado)}</td>
              </tr>
              <tr>
                <td>Total pasivos + patrimonio</td>
                <td>{formatoMoneda(totalPasivoPatrimonio)}</td>
              </tr>
              <tr>
                <td>Diferencia</td>
                <td>{formatoMoneda(diferencia)}</td>
              </tr>
            </tbody>
          </table>

          <p>
            Estado del balance: {balanceCuadrado
              ? 'Balance cuadrado'
              : 'Diferencia detectada'}
          </p>

          <h2>Detalle de cuentas contables</h2>

          <table>
            <thead>
              <tr>
                <th>Código</th>
                <th>Cuenta</th>
                <th>Tipo</th>
                <th>Grupo</th>
                <th>Saldo</th>
              </tr>
            </thead>
            <tbody>
              {cuentasBalance.map((cuenta) => (
                <tr key={cuenta.id}>
                  <td>{cuenta.codigo}</td>
                  <td>{cuenta.nombre}</td>
                  <td>{cuenta.tipo}</td>
                  <td>{cuenta.grupo}</td>
                  <td>{formatoMoneda(cuenta.saldo)}</td>
                </tr>
              ))}

              <tr>
                <td colSpan={4}>Total activos</td>
                <td>{formatoMoneda(activos)}</td>
              </tr>
              <tr>
                <td colSpan={4}>Total pasivos + patrimonio</td>
                <td>{formatoMoneda(totalPasivoPatrimonio)}</td>
              </tr>
            </tbody>
          </table>

          <p className="print-footer">
            Reporte generado desde el módulo M7 de Pacíficos Online.
          </p>
        </section>
      )}
    </div>
  );
}
