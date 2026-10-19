
import { useMemo, useState } from 'react';
import EncabezadoM7 from '../components/EncabezadoM7';

// Datos financieros ficticios para el prototipo.
// No son saldos reales ni provienen de la base de datos.
const cuentasBalance = [
  { codigo: '1.1.0', nombre: 'Caja y bancos', grupo: 'Activo corriente', tipo: 'Activo', saldo: 850000 },
  { codigo: '1.2.0', nombre: 'Cuentas por cobrar', grupo: 'Activo corriente', tipo: 'Activo', saldo: 385000 },
  { codigo: '1.3.0', nombre: 'Inventario', grupo: 'Activo corriente', tipo: 'Activo', saldo: 620000 },
  { codigo: '1.4.0', nombre: 'Equipo y mobiliario', grupo: 'Activo no corriente', tipo: 'Activo', saldo: 950000 },
  { codigo: '2.1.0', nombre: 'Cuentas por pagar', grupo: 'Pasivo corriente', tipo: 'Pasivo', saldo: 425000 },
  { codigo: '2.2.0', nombre: 'Obligaciones a largo plazo', grupo: 'Pasivo no corriente', tipo: 'Pasivo', saldo: 580000 },
  { codigo: '3.1.0', nombre: 'Capital aportado', grupo: 'Patrimonio', tipo: 'Patrimonio', saldo: 1200000 },
  { codigo: '3.2.0', nombre: 'Utilidades acumuladas', grupo: 'Patrimonio', tipo: 'Patrimonio', saldo: 600000 },
];

const tiposDisponibles = ['Activo', 'Pasivo', 'Patrimonio'];

function formatoMoneda(monto) {
  return new Intl.NumberFormat('es-CR', {
    style: 'currency',
    currency: 'CRC',
    maximumFractionDigits: 2,
  }).format(monto);
}

export default function BalanceGeneralView({ onNavegar }) {
  const [busqueda, setBusqueda] = useState('');
  const [tipoSeleccionado, setTipoSeleccionado] = useState('Todos');
  const [grupoSeleccionado, setGrupoSeleccionado] = useState('Todos');

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
  }, [busqueda, tipoSeleccionado, grupoSeleccionado]);

  // Los totales del balance se calculan con todas las cuentas.
  // Los filtros solo modifican las filas visibles.
  const activos = cuentasBalance
    .filter((cuenta) => cuenta.tipo === 'Activo')
    .reduce((total, cuenta) => total + cuenta.saldo, 0);

  const pasivos = cuentasBalance
    .filter((cuenta) => cuenta.tipo === 'Pasivo')
    .reduce((total, cuenta) => total + cuenta.saldo, 0);

  const patrimonio = cuentasBalance
    .filter((cuenta) => cuenta.tipo === 'Patrimonio')
    .reduce((total, cuenta) => total + cuenta.saldo, 0);

  const totalPasivoPatrimonio = pasivos + patrimonio;
  const diferencia = activos - totalPasivoPatrimonio;
  const balanceCuadrado = Math.abs(diferencia) < 0.005;

  const gruposDisponibles = [
    ...new Set(cuentasBalance.map((cuenta) => cuenta.grupo)),
  ];

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
              className="rounded-lg bg-[#1e3a8a] px-5 py-3 text-sm font-semibold text-white transition hover:bg-blue-800"
            >
              Imprimir balance
            </button>
          </div>
        </div>

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
          className={`mb-6 rounded-xl border p-5 shadow-sm ${
            balanceCuadrado
              ? 'border-green-200 bg-green-50'
              : 'border-amber-200 bg-amber-50'
          }`}
          aria-live="polite"
        >
          <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
            <div>
              <h2 className="font-bold text-gray-800">
                Comprobación de la ecuación contable
              </h2>
              <p className="mt-1 text-sm text-gray-600">
                Activos = Pasivos + Patrimonio
              </p>
              <p className="mt-2 text-sm text-gray-700">
                {formatoMoneda(activos)} = {formatoMoneda(pasivos)} + {formatoMoneda(patrimonio)}
              </p>
            </div>

            <div className="sm:text-right">
              <span
                className={`inline-flex rounded-full px-3 py-1 text-sm font-semibold ${
                  balanceCuadrado
                    ? 'bg-green-100 text-green-800'
                    : 'bg-amber-100 text-amber-800'
                }`}
              >
                {balanceCuadrado ? 'Balance cuadrado' : 'Diferencia detectada'}
              </span>
              <p className="mt-2 text-sm text-gray-700">
                Diferencia: {formatoMoneda(diferencia)}
              </p>
            </div>
          </div>
        </section>

        <section className="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
          <div className="border-b border-gray-200 px-5 py-4">
            <h2 className="text-lg font-bold text-gray-800">
              Cuentas del balance
            </h2>
            <p className="mt-1 text-sm text-gray-500">
              Datos demostrativos clasificados por tipo y grupo.
            </p>
          </div>

          <div className="grid grid-cols-1 gap-4 border-b border-gray-200 p-5 md:grid-cols-3">
            <div>
              <label htmlFor="buscar-balance" className="mb-2 block text-sm font-medium text-gray-700">
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
              <label htmlFor="tipo-balance" className="mb-2 block text-sm font-medium text-gray-700">
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
                  <option key={tipo} value={tipo}>{tipo}</option>
                ))}
              </select>
            </div>

            <div>
              <label htmlFor="grupo-balance" className="mb-2 block text-sm font-medium text-gray-700">
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
                  <option key={grupo} value={grupo}>{grupo}</option>
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

          <div className="overflow-x-auto">
            <table className="w-full min-w-[850px] text-left text-sm">
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
                  <tr key={cuenta.codigo} className="transition hover:bg-blue-50">
                    <td className="whitespace-nowrap px-4 py-4 font-semibold text-[#1e3a8a]">
                      {cuenta.codigo}
                    </td>
                    <td className="px-4 py-4 font-medium text-gray-800">
                      {cuenta.nombre}
                    </td>
                    <td className="px-4 py-4 text-gray-600">{cuenta.tipo}</td>
                    <td className="px-4 py-4 text-gray-600">{cuenta.grupo}</td>
                    <td className="whitespace-nowrap px-4 py-4 text-right font-medium text-gray-800">
                      {formatoMoneda(cuenta.saldo)}
                    </td>
                  </tr>
                ))}

                {cuentasFiltradas.length === 0 && (
                  <tr>
                    <td colSpan={5} className="px-5 py-12 text-center text-gray-500">
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
          Datos ficticios para el prototipo. El balance definitivo deberá
          generarse a partir de los saldos contables validados.
        </p>
      </main>
    </div>
  );
}
