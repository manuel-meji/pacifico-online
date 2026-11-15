import { useEffect, useState } from 'react';
import EncabezadoM7 from '../components/EncabezadoM7';
import DetalleMovimientosM7 from '../components/DetalleMovimientosM7';
import SelectorComercioM7 from '../components/SelectorComercioM7';
import { consultarSaldos } from '../services/consultasOperativasApi';

function moneda(valor) {
  return new Intl.NumberFormat('es-CR', { style: 'currency', currency: 'CRC' }).format(Number(valor));
}

export default function CuentasPorCobrarPagarView({ onNavegar, idComercio }) {
  const [datos, setDatos] = useState(null);
  const [error, setError] = useState('');
  const [busqueda, setBusqueda] = useState('');
  const [tipo, setTipo] = useState('Todos');
  const [comercio, setComercio] = useState(idComercio || '');
  const [consulta, setConsulta] = useState({ comercio: idComercio || '' });
  const [pagina, setPagina] = useState(1);
  const [seleccion, setSeleccion] = useState(null);

  useEffect(() => {
    let activo = true;
    consultarSaldos(consulta.comercio).then((resultado) => {
      if (activo) { setDatos(resultado); setError(''); }
    }).catch((fallo) => {
      if (activo) setError(fallo.message || 'No fue posible consultar los saldos.');
    });
    return () => { activo = false; };
  }, [consulta]);

  const cuentas = (datos?.cuentas ?? []).filter((cuenta) =>
    (tipo === 'Todos' || cuenta.tipo === tipo) &&
    `${cuenta.codigo} ${cuenta.nombre}`.toLowerCase().includes(busqueda.trim().toLowerCase())
  );
  const paginas = Math.max(1, Math.ceil(cuentas.length / 10));
  return (
    <div className="min-h-screen bg-[#f5f7fa]">
      <EncabezadoM7 seccionActiva="Cuentas por cobrar y pagar" onNavegar={onNavegar} />
      <main id="contenido" className="m7-print-area m7-print-area-pantalla mx-auto max-w-7xl px-4 py-8 sm:px-6">
        <h1 className="text-2xl font-bold text-[#1e3a8a]">Cuentas por cobrar y pagar</h1>
        <p className="mt-2 text-sm text-gray-600">
          Saldos netos de los asientos registrados del comercio, incluidos fondos por cobrar a plataforma e IVA por pagar.
          No representa documentos, vencimientos, abonos ni liquidaciones.
        </p>
        <form className="no-print mt-4 flex flex-wrap items-end gap-3" onSubmit={(evento) => {
          evento.preventDefault(); setDatos(null); setError(''); setPagina(1); setConsulta({ comercio });
        }}>
          {!idComercio && <SelectorComercioM7 valor={comercio} onCambiar={setComercio} onCargados={(lista) => {
            if (lista.length === 0 || consulta.comercio) return;
            const primero = String(lista[0].id_comercio);
            setComercio(primero); setDatos(null); setError(''); setPagina(1); setConsulta({ comercio: primero });
          }} />}
          <button className="ml-auto rounded bg-[#1e3a8a] p-2 text-white">Actualizar consulta</button>
        </form>
        {error ? <p role="alert" className="mt-4 text-red-700">{error}</p> : !datos ? (
          <p role="status" className="mt-4">Cargando saldos contables...</p>
        ) : (
          <>
            <p className="m7-solo-impresion">Comercio: {datos.id_comercio} · Tipo: {tipo} · Búsqueda: {busqueda.trim() || 'Todas'}</p>
            <section className="my-6 grid gap-4 sm:grid-cols-2" aria-label="Resumen de saldos">
              <article className="rounded-xl bg-white p-5 shadow-sm">
                <h2>Saldo por cobrar</h2>
                <p className="mt-2 text-2xl font-bold text-[#1e3a8a]">{moneda(datos.resumen.por_cobrar)}</p>
              </article>
              <article className="rounded-xl bg-white p-5 shadow-sm">
                <h2>Saldo por pagar</h2>
                <p className="mt-2 text-2xl font-bold text-[#1e3a8a]">{moneda(datos.resumen.por_pagar)}</p>
              </article>
            </section>
            <div className="no-print mb-4 flex flex-wrap gap-4">
              <label className="flex flex-col gap-1">Buscar cuenta
                <input className="rounded border p-2" value={busqueda} onChange={(evento) => { setBusqueda(evento.target.value); setPagina(1); }} />
              </label>
              <label className="flex flex-col gap-1">Tipo
                <select className="rounded border p-2" value={tipo} onChange={(evento) => { setTipo(evento.target.value); setPagina(1); }}>
                  {['Todos', 'Por cobrar', 'Por pagar'].map((opcion) => <option key={opcion}>{opcion}</option>)}
                </select>
              </label>
              <button className="self-end rounded border bg-white p-2" onClick={() => { setBusqueda(''); setTipo('Todos'); setPagina(1); }}>Limpiar filtros</button>
              <button className="self-end rounded bg-[#1e3a8a] p-2 text-white" onClick={() => window.print()}>Imprimir listado</button>
            </div>
            <div className="overflow-x-auto rounded-xl bg-white">
              <table className="w-full text-left text-sm">
                <thead className="bg-[#1e3a8a] text-white"><tr>
                  <th className="p-3">Código</th><th className="p-3">Cuenta</th><th className="p-3">Tipo</th><th className="p-3 text-right">Saldo neto</th><th className="no-print p-3">Detalle</th>
                </tr></thead>
                <tbody>{cuentas.map((cuenta, indice) => <tr key={cuenta.id_cuenta_contable} className={`border-b ${indice < (pagina - 1) * 10 || indice >= pagina * 10 ? 'm7-solo-impresion' : ''}`}>
                  <td className="p-3">{cuenta.codigo}</td><td className="p-3">{cuenta.nombre}</td>
                  <td className="p-3">{cuenta.tipo}</td><td className="p-3 text-right">{moneda(cuenta.saldo)}</td>
                  <td className="no-print p-3"><button className="rounded border border-blue-800 p-2 text-blue-800"
                    aria-label={`Ver movimientos de ${cuenta.codigo}`}
                    onClick={() => setSeleccion({ tipo: 'cuenta', id: cuenta.id_cuenta_contable, idComercio: datos.id_comercio, titulo: `${cuenta.codigo} · ${cuenta.nombre}` })}>Ver movimientos</button></td>
                </tr>)}</tbody>
              </table>
              {cuentas.length === 0 && <p className="p-4">No hay cuentas para los filtros seleccionados.</p>}
            </div>
            <nav aria-label="Paginación de cuentas" className="no-print mt-4 flex items-center gap-3">
              <button className="rounded border p-2 disabled:opacity-50" disabled={pagina === 1} onClick={() => setPagina(pagina - 1)}>Anterior</button>
              <span>Página {pagina} de {paginas}</span>
              <button className="rounded border p-2 disabled:opacity-50" disabled={pagina === paginas} onClick={() => setPagina(pagina + 1)}>Siguiente</button>
            </nav>
            <p className="mt-3 text-xs text-gray-600">Los saldos negativos se conservan para revisión; no se sustituyen por cero. El resumen incluye todas las cuentas consultadas.</p>
          </>
        )}
      </main>
      {seleccion && <DetalleMovimientosM7 seleccion={seleccion} onCerrar={() => setSeleccion(null)} />}
    </div>
  );
}
