import { useEffect, useState } from 'react';
import EncabezadoM7 from '../components/EncabezadoM7';
import DetalleMovimientosM7 from '../components/DetalleMovimientosM7';
import SelectorComercioM7 from '../components/SelectorComercioM7';
import { consultarCentrosCosto } from '../services/consultasOperativasApi';

function moneda(valor) {
  return new Intl.NumberFormat('es-CR', { style: 'currency', currency: 'CRC' }).format(Number(valor));
}

function porcentaje(valor) {
  return valor === null || valor === undefined ? '—' : `${new Intl.NumberFormat('es-CR', { minimumFractionDigits: 2 }).format(Number(valor))} %`;
}

function claseUtilidad(valor) {
  return Number(valor) < 0 ? 'text-red-700' : 'text-gray-900';
}

function textoPeriodo(desde, hasta) {
  if (!desde && !hasta) return 'Todo el historial';
  if (!desde) return `Hasta ${hasta}`;
  if (!hasta) return `Desde ${desde}`;
  return `Del ${desde} al ${hasta}`;
}

function ColumnasRentabilidad({ fila }) {
  return (
    <>
      <td className="p-3 text-right">{moneda(fila.ingresos)}</td>
      <td className="p-3 text-right">{moneda(fila.costos)}</td>
      <td className={`p-3 text-right font-medium ${claseUtilidad(fila.utilidad)}`}>{moneda(fila.utilidad)}</td>
      <td className="p-3 text-right">{porcentaje(fila.margen)}</td>
    </>
  );
}

export default function CentrosCostoView({ onNavegar }) {
  const [datos, setDatos] = useState(null);
  const [error, setError] = useState('');
  const [busqueda, setBusqueda] = useState('');
  const [estado, setEstado] = useState('Todos');
  const [comercio, setComercio] = useState('');
  const [desde, setDesde] = useState('');
  const [hasta, setHasta] = useState('');
  const [consulta, setConsulta] = useState({ comercio: '', desde: '', hasta: '' });
  const [pagina, setPagina] = useState(1);
  const [seleccion, setSeleccion] = useState(null);
  useEffect(() => {
    let activo = true;
    consultarCentrosCosto(consulta.comercio, { desde: consulta.desde, hasta: consulta.hasta }).then((resultado) => {
      if (activo) { setDatos(resultado); setError(''); }
    }).catch((fallo) => {
      if (activo) setError(fallo.message || 'No fue posible consultar los centros de costo.');
    });
    return () => { activo = false; };
  }, [consulta]);
  const centros = (datos?.centros ?? []).filter((centro) =>
    (estado === 'Todos' || centro.activo === (estado === 'Activo')) &&
    `${centro.codigo} ${centro.nombre}`.toLowerCase().includes(busqueda.trim().toLowerCase())
  );
  const paginas = Math.max(1, Math.ceil(centros.length / 10));
  const periodo = { desde: consulta.desde, hasta: consulta.hasta };
  const abrirDetalle = (id, titulo) => setSeleccion({ tipo: 'centro', id, idComercio: datos.id_comercio, titulo, periodo });
  const hayFechas = Boolean(desde || hasta || consulta.desde || consulta.hasta);
  const limpiarFechas = () => {
    setDesde(''); setHasta('');
    if (consulta.desde || consulta.hasta) {
      setDatos(null); setError(''); setPagina(1); setConsulta({ comercio, desde: '', hasta: '' });
    }
  };

  return (
    <div className="min-h-screen bg-[#f5f7fa]">
      <EncabezadoM7 seccionActiva="Centros de costo" onNavegar={onNavegar} />
      <main id="contenido" className="m7-print-area m7-print-area-pantalla mx-auto max-w-7xl px-4 py-8 sm:px-6">
        <h1 className="text-2xl font-bold text-[#1e3a8a]">Centros de costo</h1>
        <p className="mt-2 text-sm text-gray-600">Rentabilidad por centro: ingresos y costos netos de asientos registrados en cuentas de ingresos, costos y gastos. No incluye presupuestos ni responsables.</p>
        <form className="no-print mt-4 flex flex-wrap items-end gap-3" onSubmit={(evento) => {
          evento.preventDefault(); setDatos(null); setError(''); setPagina(1); setConsulta({ comercio, desde, hasta });
        }}>
          <SelectorComercioM7 valor={comercio} onCambiar={setComercio} onCargados={(lista) => {
            if (lista.length === 0 || consulta.comercio) return;
            const primero = String(lista[0].id_comercio);
            setComercio(primero); setDatos(null); setError(''); setPagina(1); setConsulta({ comercio: primero, desde, hasta });
          }} />
          <label className="flex flex-col gap-1 text-sm">Desde
            <input type="date" value={desde} max={hasta || undefined} onChange={(evento) => setDesde(evento.target.value)} className="rounded border bg-white p-2" />
          </label>
          <label className="flex flex-col gap-1 text-sm">Hasta
            <input type="date" value={hasta} min={desde || undefined} onChange={(evento) => setHasta(evento.target.value)} className="rounded border bg-white p-2" />
          </label>
          <button type="button" className="rounded border bg-white p-2 disabled:opacity-50" disabled={!hayFechas} onClick={limpiarFechas}>Limpiar filtros</button>
          <button className="ml-auto rounded bg-[#1e3a8a] p-2 text-white">Actualizar consulta</button>
        </form>
        <p className="no-print mt-1 text-xs text-gray-600">Fechas vacías consultan todo el historial.</p>
        {error ? <p role="alert" className="mt-4 text-red-700">{error}</p> : !datos ? (
          <p role="status" className="mt-4">Cargando centros de costo...</p>
        ) : (
          <>
            <p className="m7-solo-impresion">Comercio: {datos.id_comercio} · Estado: {estado} · Búsqueda: {busqueda.trim() || 'Todas'}</p>
            <section className="my-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4" aria-label="Resumen de rentabilidad">
              <article className="rounded-xl bg-white p-5 shadow-sm"><h2>Ingresos</h2><p className="mt-2 text-2xl font-bold text-[#1e3a8a]">{moneda(datos.resumen.ingresos)}</p></article>
              <article className="rounded-xl bg-white p-5 shadow-sm"><h2>Costos</h2><p className="mt-2 text-2xl font-bold text-[#1e3a8a]">{moneda(datos.resumen.costos)}</p></article>
              <article className="rounded-xl bg-white p-5 shadow-sm"><h2>Utilidad</h2><p className={`mt-2 text-2xl font-bold ${Number(datos.resumen.utilidad) < 0 ? 'text-red-700' : 'text-[#1e3a8a]'}`}>{moneda(datos.resumen.utilidad)}</p></article>
              <article className="rounded-xl bg-white p-5 shadow-sm"><h2>Margen</h2><p className="mt-2 text-2xl font-bold text-[#1e3a8a]">{porcentaje(datos.resumen.margen)}</p></article>
            </section>
            <p className="mb-4 text-sm text-gray-600">
              Período: <strong>{textoPeriodo(datos.fecha_desde, datos.fecha_hasta)}</strong> · {datos.resumen.cantidad} centros registrados, {datos.resumen.activos} activos.
            </p>
            {!datos.hay_movimientos && (
              <p role="status" className="mb-4 rounded-lg border border-blue-200 bg-blue-50 p-3 text-sm text-[#1e3a8a]">Sin movimientos en el período consultado.</p>
            )}
            <div className="no-print mb-4 flex flex-wrap gap-4">
              <label className="flex flex-col gap-1">Buscar centro
                <input className="rounded border p-2" value={busqueda} onChange={(evento) => { setBusqueda(evento.target.value); setPagina(1); }} />
              </label>
              <label className="flex flex-col gap-1">Estado
                <select className="rounded border p-2" value={estado} onChange={(evento) => { setEstado(evento.target.value); setPagina(1); }}>
                  {['Todos', 'Activo', 'Inactivo'].map((opcion) => <option key={opcion}>{opcion}</option>)}
                </select>
              </label>
              <button className="self-end rounded border bg-white p-2" onClick={() => { setBusqueda(''); setEstado('Todos'); setPagina(1); }}>Limpiar búsqueda y filtros</button>
              <button className="self-end rounded bg-[#1e3a8a] p-2 text-white" onClick={() => window.print()}>Imprimir reporte</button>
            </div>
            <div className="overflow-x-auto rounded-xl bg-white">
              <table className="w-full text-left text-sm">
                <thead className="bg-[#1e3a8a] text-white"><tr>
                  <th className="p-3">Código</th><th className="p-3">Centro</th><th className="p-3">Estado</th>
                  <th className="p-3 text-right">Ingresos</th><th className="p-3 text-right">Costos</th>
                  <th className="p-3 text-right">Utilidad</th><th className="p-3 text-right">Margen</th><th className="no-print p-3">Detalle</th>
                </tr></thead>
                <tbody>
                  {centros.map((centro, indice) => <tr key={centro.id_centro_costo} className={`border-b ${indice < (pagina - 1) * 10 || indice >= pagina * 10 ? 'm7-solo-impresion' : ''}`}>
                    <td className="p-3">{centro.codigo}</td><td className="p-3">{centro.nombre}</td>
                    <td className="p-3">{centro.activo ? 'Activo' : 'Inactivo'}</td>
                    <ColumnasRentabilidad fila={centro} />
                    <td className="no-print p-3"><button className="rounded border border-blue-800 p-2 text-blue-800"
                      aria-label={`Ver movimientos de ${centro.codigo}`}
                      onClick={() => abrirDetalle(centro.id_centro_costo, `${centro.codigo} · ${centro.nombre}`)}>Ver movimientos</button></td>
                  </tr>)}
                  {datos.sin_centro && <tr className="border-b bg-amber-50">
                    <td className="p-3">—</td><td className="p-3 font-medium">Sin centro de costo asignado</td><td className="p-3">—</td>
                    <ColumnasRentabilidad fila={datos.sin_centro} />
                    <td className="no-print p-3"><button className="rounded border border-blue-800 p-2 text-blue-800"
                      aria-label="Ver movimientos sin centro asignado"
                      onClick={() => abrirDetalle(null, 'Sin centro de costo asignado')}>Ver movimientos</button></td>
                  </tr>}
                </tbody>
              </table>
              {centros.length === 0 && <p className="p-4">No hay centros para los filtros seleccionados.</p>}
            </div>
            <nav aria-label="Paginación de centros" className="no-print mt-4 flex items-center gap-3">
              <button className="rounded border p-2 disabled:opacity-50" disabled={pagina === 1} onClick={() => setPagina(pagina - 1)}>Anterior</button>
              <span>Página {pagina} de {paginas}</span>
              <button className="rounded border p-2 disabled:opacity-50" disabled={pagina === paginas} onClick={() => setPagina(pagina + 1)}>Siguiente</button>
            </nav>
            <p className="mt-3 text-xs text-gray-600">Utilidad = ingresos − costos; margen = utilidad sobre ingresos (no aplica sin ingresos). Los totales incluyen los centros inactivos y las líneas sin centro de costo asignado.</p>
          </>
        )}
      </main>
      {seleccion && <DetalleMovimientosM7 seleccion={seleccion} onCerrar={() => setSeleccion(null)} />}
    </div>
  );
}
