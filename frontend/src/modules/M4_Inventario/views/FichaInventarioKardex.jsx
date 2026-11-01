// Ficha de inventario y Kardex de una variación (M4). Realiza CU-M4-08 «Consultar Kardex de variación» y
// muestra la existencia por ubicación (listarExistencias). Solo lectura: no ofrece acciones de edición (RN-M4-003).
// Usa las operaciones listarExistencias y consultarKardex del contrato v1.2.0 (en el PC4, vía Inertia).
import { useEffect, useState } from 'react';

// Tamaño de página del Kardex en el móvil. La API entrega del más reciente al más antiguo; la vista pide la
// página siguiente con «Ver movimientos anteriores» y conserva la paginación que devuelve el contrato.
const POR_PAGINA = 5;
import { IconoCandado, IconoCirculoCheck, IconoTrianguloAlerta, IconoUsuario } from '../components/iconos';
import { InsigniaTipo, Tarjeta, formatoColones, formatoFecha } from '../components/componentes';
import { ubicaciones, usuarios, variaciones } from '../services/datosMock';
import { disponible } from '../services/inventarioApi';

export default function FichaInventarioKardex({ api, existencias, version }) {
  const [idVariacion, setIdVariacion] = useState(501);
  const [idUbicacion, setIdUbicacion] = useState(12);
  const [kardex, setKardex] = useState(null);
  const [paginacion, setPaginacion] = useState(null);
  const [cargandoMas, setCargandoMas] = useState(false);

  useEffect(() => {
    let vigente = true;
    setKardex(null);
    setPaginacion(null);
    api.consultarKardex({ id_variacion: idVariacion, id_ubicacion: idUbicacion, pagina: 1, por_pagina: POR_PAGINA })
      .then((r) => { if (vigente) { setKardex(r.datos); setPaginacion(r.paginacion); } });
    return () => { vigente = false; };
  }, [api, idVariacion, idUbicacion, version]);

  async function verAnteriores() {
    setCargandoMas(true);
    const r = await api.consultarKardex({ id_variacion: idVariacion, id_ubicacion: idUbicacion, pagina: paginacion.pagina + 1, por_pagina: POR_PAGINA });
    setKardex((k) => [...k, ...r.datos]);
    setPaginacion(r.paginacion);
    setCargandoMas(false);
  }

  const variacion = variaciones.find((v) => v.id_variacion === idVariacion);
  const filas = existencias.filter((e) => e.id_variacion === idVariacion);
  const total = filas.reduce((s, e) => s + disponible(e), 0);

  return (
    <div className="space-y-4">
      <Tarjeta>
        <label htmlFor="ficha-variacion" className="text-xs font-semibold uppercase tracking-wide text-gray-500">Variación</label>
        <select id="ficha-variacion" value={idVariacion} onChange={(e) => setIdVariacion(Number(e.target.value))}
          className="mt-1 block w-full min-w-0 rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-base font-semibold text-gray-900 focus:border-brandBlue focus:outline-none focus:ring-2 focus:ring-brandBlue/40">
          {variaciones.map((v) => <option key={v.id_variacion} value={v.id_variacion}>{v.sku} · {v.atributos}</option>)}
        </select>
        <p className="mt-2 text-sm text-gray-600">{variacion.nombre} · {variacion.atributos}</p>
        <p className="mt-1 text-sm text-gray-800">Disponible en el comercio: <span className="text-lg font-extrabold text-brandDarkBlue">{total}</span></p>
      </Tarjeta>

      <section aria-labelledby="titulo-ubicaciones" className="space-y-2">
        <h2 id="titulo-ubicaciones" className="text-base font-bold text-gray-900">Existencia por ubicación</h2>
        {filas.map((e) => {
          const u = ubicaciones.find((x) => x.id_ubicacion === e.id_ubicacion);
          const d = disponible(e);
          const bajo = d <= e.stock_minimo;
          const seleccionada = e.id_ubicacion === idUbicacion;
          return (
            <button key={e.id_existencia} type="button" onClick={() => setIdUbicacion(e.id_ubicacion)} aria-pressed={seleccionada}
              className={`w-full rounded-xl border bg-white p-3 text-left shadow-sm transition ${seleccionada ? 'border-brandBlue ring-2 ring-brandBlue/30' : 'border-gray-100 hover:shadow-md'}`}>
              <div className="flex flex-wrap items-center justify-between gap-2">
                <span className="min-w-0 font-semibold text-gray-900">{u.nombre}</span>
                {d === 0
                  ? <span className="inline-flex items-center gap-1 rounded-full border border-red-200 bg-red-50 px-2.5 py-0.5 text-xs font-semibold text-red-700"><IconoTrianguloAlerta className="h-3.5 w-3.5" />Agotado</span>
                  : bajo
                    ? <span className="inline-flex items-center gap-1 rounded-full border border-amber-200 bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-800"><IconoTrianguloAlerta className="h-3.5 w-3.5" />Bajo mínimo ({e.stock_minimo})</span>
                    : <span className="inline-flex items-center gap-1 rounded-full border border-green-200 bg-green-50 px-2.5 py-0.5 text-xs font-semibold text-green-700"><IconoCirculoCheck className="h-3.5 w-3.5" />Disponible</span>}
              </div>
              <p className="mt-2 text-sm text-gray-600">
                Física {e.cantidad_fisica} − reservada {e.cantidad_reservada} − defectuosa {e.cantidad_defectuosa} = <span className="font-bold text-gray-900">{d} disponibles</span>
              </p>
              <p className="mt-1 text-xs text-gray-500">Costo promedio {formatoColones(e.costo_promedio)} · Valor {formatoColones(e.cantidad_fisica * e.costo_promedio)}</p>
            </button>
          );
        })}
      </section>

      <section aria-labelledby="titulo-kardex" className="space-y-2">
        <div className="flex flex-wrap items-center justify-between gap-2">
          <h2 id="titulo-kardex" className="text-base font-bold text-gray-900">Kardex · {ubicaciones.find((u) => u.id_ubicacion === idUbicacion)?.nombre}</h2>
          <span className="inline-flex items-center gap-1 text-xs font-medium text-gray-500"><IconoCandado className="h-3.5 w-3.5" />Solo lectura</span>
        </div>
        {kardex === null && <p className="text-sm text-gray-500">Cargando movimientos…</p>}
        {kardex?.length === 0 && <Tarjeta><p className="text-sm text-gray-600">Esta ubicación no tiene movimientos registrados.</p></Tarjeta>}
        <ol className="space-y-2">
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
                <span className="inline-flex items-center gap-1"><IconoUsuario className="h-3.5 w-3.5" />{usuarios[k.id_usuario] ?? `Usuario ${k.id_usuario}`}</span>
                {k.id_autorizador && <span>Autorizó: {usuarios[k.id_autorizador] ?? `Usuario ${k.id_autorizador}`}</span>}
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
