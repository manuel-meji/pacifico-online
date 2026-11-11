// Ficha de inventario y Kardex de una variación (M4). Realiza CU-M4-08 «Consultar Kardex de variación» con
// listarExistencias y consultarKardex (/api/m4/v1). Solo lectura (RN-M4-003). Filtros: ubicación y periodo.
import { useEffect, useMemo, useState } from 'react';
import { IconoCandado, IconoUsuario } from '../components/iconos';
import { Aviso, InsigniaTipo, Tarjeta, claseEntrada, formatoColones, formatoFecha } from '../components/componentes';
import { disponible, nombreSede, nombreVariacion } from '../services/inventarioApi';

// Tamaño de página del Kardex en el móvil. La API entrega del más reciente al más antiguo.
const POR_PAGINA = 5;

export default function FichaInventarioKardex({ api, existencias, sedes, version }) {
  const variaciones = useMemo(() => [...new Map(existencias.map((e) => [e.id_variacion, e])).values()], [existencias]);
  const [idVariacion, setIdVariacion] = useState(null);
  const [idUbicacion, setIdUbicacion] = useState('');
  const [periodo, setPeriodo] = useState({ fecha_inicio: '', fecha_fin: '' });
  const [kardex, setKardex] = useState(null);
  const [paginacion, setPaginacion] = useState(null);
  const [error, setError] = useState(null);
  const [cargandoMas, setCargandoMas] = useState(false);

  useEffect(() => { if (idVariacion === null && variaciones[0]) setIdVariacion(variaciones[0].id_variacion); }, [variaciones, idVariacion]);

  const filtros = (pagina) => ({ id_variacion: idVariacion, id_ubicacion: idUbicacion, ...periodo, pagina, por_pagina: POR_PAGINA });

  useEffect(() => {
    if (idVariacion === null) return undefined;
    let vigente = true;
    setKardex(null);
    setError(null);
    api.consultarKardex(filtros(1))
      .then((r) => { if (vigente) { setKardex(r.datos); setPaginacion(r.paginacion); } })
      .catch((falla) => vigente && setError(falla.error));
    return () => { vigente = false; };
  }, [api, idVariacion, idUbicacion, periodo, version]); // eslint-disable-line react-hooks/exhaustive-deps

  async function verAnteriores() {
    setCargandoMas(true);
    try {
      const r = await api.consultarKardex(filtros(paginacion.pagina + 1));
      setKardex((k) => [...k, ...r.datos]);
      setPaginacion(r.paginacion);
    } catch (falla) {
      setError(falla.error);
    } finally {
      setCargandoMas(false);
    }
  }

  if (variaciones.length === 0) return <Tarjeta><p className="text-sm text-gray-600">Todavía no hay existencias con movimientos.</p></Tarjeta>;

  const filas = existencias.filter((e) => e.id_variacion === idVariacion);
  const total = filas.reduce((s, e) => s + disponible(e), 0);

  return (
    <div className="space-y-4">
      <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,2fr)] lg:items-start">
      <Tarjeta>
        <label htmlFor="ficha-variacion" className="text-xs font-semibold uppercase tracking-wide text-gray-500">Variación</label>
        <select id="ficha-variacion" value={idVariacion ?? ''} onChange={(e) => { setIdVariacion(Number(e.target.value)); setIdUbicacion(''); }}
          className="mt-1 block w-full min-w-0 rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-base font-semibold text-gray-900 focus:border-brandBlue focus:outline-none focus:ring-2 focus:ring-brandBlue/40">
          {variaciones.map((v) => <option key={v.id_variacion} value={v.id_variacion}>{nombreVariacion(v)}</option>)}
        </select>
        <p className="mt-2 text-sm text-gray-800">Disponible en el comercio: <span className="text-lg font-extrabold text-brandDarkBlue">{total}</span></p>
      </Tarjeta>

      <section aria-labelledby="titulo-ubicaciones" className="space-y-2">
        <h2 id="titulo-ubicaciones" className="text-base font-bold text-gray-900">Existencia por ubicación</h2>
        <div className="grid gap-2 sm:grid-cols-2">
        {filas.map((e) => (
          <button key={e.id_existencia} type="button" onClick={() => setIdUbicacion(idUbicacion === e.id_ubicacion ? '' : e.id_ubicacion)} aria-pressed={idUbicacion === e.id_ubicacion}
            className={`w-full rounded-xl border bg-white p-3 text-left shadow-sm transition ${idUbicacion === e.id_ubicacion ? 'border-brandBlue ring-2 ring-brandBlue/30' : 'border-gray-100 hover:shadow-md'}`}>
            <span className="font-semibold text-gray-900">{nombreSede(sedes, e.id_ubicacion)}</span>
            <p className="mt-1 text-sm text-gray-600">Física {e.cantidad_fisica} − reservada {e.cantidad_reservada} = <span className="font-bold text-gray-900">{disponible(e)} disponibles</span>{e.bajo_minimo && <span className="text-amber-800"> · bajo el mínimo ({e.stock_minimo})</span>}</p>
          </button>
        ))}
        </div>
      </section>
      </div>

      <section aria-labelledby="titulo-kardex" className="space-y-2">
        <div className="flex flex-wrap items-center justify-between gap-2">
          <h2 id="titulo-kardex" className="text-base font-bold text-gray-900">Kardex · {idUbicacion ? nombreSede(sedes, idUbicacion) : 'todas las ubicaciones'}</h2>
          <span className="inline-flex items-center gap-1 text-xs font-medium text-gray-500"><IconoCandado className="h-3.5 w-3.5" />Solo lectura</span>
        </div>
        <Tarjeta className="grid grid-cols-2 gap-2 md:max-w-xl">
          {[['fecha_inicio', 'Desde'], ['fecha_fin', 'Hasta']].map(([campo, etiqueta]) => (
            <label key={campo} className="min-w-0 text-xs font-semibold text-gray-700" htmlFor={campo}>
              {etiqueta}
              <input id={campo} type="date" className={claseEntrada(error?.codigo === 'ERR-M4-012')} value={periodo[campo]} onChange={(e) => setPeriodo({ ...periodo, [campo]: e.target.value })} />
            </label>
          ))}
        </Tarjeta>
        {error && <Aviso tipo="error" titulo="No se pudo consultar el Kardex" codigo={error.codigo}>{error.mensaje}</Aviso>}
        {kardex === null && !error && <p className="text-sm text-gray-500">Cargando movimientos…</p>}
        {kardex?.length === 0 && <Tarjeta><p className="text-sm text-gray-600">No hay movimientos con estos filtros.</p></Tarjeta>}
        <ol className="grid gap-2 lg:grid-cols-2">
          {kardex?.map((k) => (
            <li key={k.id_kardex} className="rounded-xl border border-gray-100 bg-white p-3 shadow-sm">
              <div className="flex flex-wrap items-center justify-between gap-2">
                <InsigniaTipo tipo={k.tipo_movimiento} />
                <time className="text-xs text-gray-500" dateTime={k.fecha_movimiento}>{formatoFecha(k.fecha_movimiento)}</time>
              </div>
              <p className="mt-2 break-words text-sm text-gray-800">{k.justificacion}</p>
              <dl className="mt-2 grid grid-cols-3 gap-2 text-xs">
                <div><dt className="text-gray-500">Cantidad</dt><dd className="font-semibold text-gray-900">{k.efecto_stock === 'disminuye' ? '−' : k.efecto_stock === 'aumenta' ? '+' : ''}{k.cantidad}</dd></div>
                <div><dt className="text-gray-500">Saldo físico</dt><dd className="font-semibold text-gray-900">{k.saldo_cantidad}</dd></div>
                <div><dt className="text-gray-500">Disponible</dt><dd className="font-semibold text-gray-900">{k.saldo_disponible}</dd></div>
                <div className="col-span-2"><dt className="text-gray-500">Costo unitario · monto</dt><dd className="font-semibold text-gray-900">{k.costo_unitario == null ? 'Sin efecto en costo' : `${formatoColones(k.costo_unitario)} · ${formatoColones(k.monto_total)}`}</dd></div>
                <div><dt className="text-gray-500">Saldo valorizado</dt><dd className="font-semibold text-gray-900">{formatoColones(k.saldo_valorizado)}</dd></div>
              </dl>
              <p className="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-500">
                <span className="inline-flex items-center gap-1"><IconoUsuario className="h-3.5 w-3.5" />{k.id_usuario === 0 ? 'Proceso del sistema' : `Usuario ${k.id_usuario}`}</span>
                {k.id_autorizador && <span>Autorizó: usuario {k.id_autorizador}</span>}
                {!idUbicacion && <span>{nombreSede(sedes, k.id_ubicacion)}</span>}
              </p>
            </li>
          ))}
        </ol>
        {paginacion && kardex?.length > 0 && (
          <div className="space-y-2 text-center">
            <p className="text-xs text-gray-500" aria-live="polite">Mostrando {kardex.length} de {paginacion.total} movimientos, del más reciente al más antiguo.</p>
            {paginacion.pagina < paginacion.total_paginas && (
              <button type="button" onClick={verAnteriores} disabled={cargandoMas}
                className="w-full rounded-lg border border-brandBlue px-4 py-2 text-sm font-semibold text-brandBlue transition hover:bg-blue-50 disabled:cursor-not-allowed disabled:opacity-60">
                {cargandoMas ? 'Cargando…' : 'Ver movimientos anteriores'}
              </button>
            )}
          </div>
        )}
      </section>
    </div>
  );
}
