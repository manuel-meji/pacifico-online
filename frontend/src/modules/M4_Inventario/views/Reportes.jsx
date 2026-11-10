// Reportes (M4): CU-M4-11 y RF-M4-008, kardex valorizado del periodo con GET /api/m4/v1/kardex/valorizado
// (obtenerKardexValorizado). Solo lectura. Por defecto, el mes actual. Los filtros de variación y ubicación se
// aplican sobre las líneas que entrega el servidor; los montos los calcula el servidor (costo promedio ponderado).
import { useEffect, useMemo, useState } from 'react';
import { IconoCandado, IconoEntrada, IconoGrafico, IconoSalida, IconoTransferir } from '../components/iconos';
import { Aviso, InsigniaTipo, Tarjeta, claseEntrada, formatoColones, formatoFecha } from '../components/componentes';
import { nombreSede, nombreVariacion } from '../services/inventarioApi';

const TRANSFERENCIAS = ['transferencia_salida', 'transferencia_entrada'];

function fechaLocal(fecha) {
  const d = new Date(fecha.getTime() - fecha.getTimezoneOffset() * 60000);
  return d.toISOString().slice(0, 10);
}

function mesActual() {
  const hoy = new Date();
  return { fecha_inicio: fechaLocal(new Date(hoy.getFullYear(), hoy.getMonth(), 1)), fecha_fin: fechaLocal(hoy) };
}

function Indicador({ titulo, valor, detalle, Icono, tono }) {
  return (
    <Tarjeta className="min-w-0">
      <div className="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-gray-500">
        <span className={`flex h-7 w-7 shrink-0 items-center justify-center rounded-lg ${tono}`}><Icono className="h-4 w-4" /></span>
        <span className="truncate">{titulo}</span>
      </div>
      <p className="mt-2 break-words text-xl font-extrabold text-gray-900 lg:text-2xl">{valor}</p>
      <p className="mt-0.5 text-xs text-gray-500">{detalle}</p>
    </Tarjeta>
  );
}

export default function Reportes({ api, existencias, sedes }) {
  const variaciones = useMemo(() => [...new Map(existencias.map((e) => [e.id_variacion, e])).values()], [existencias]);
  const ubicaciones = useMemo(() => [...new Set(existencias.map((e) => e.id_ubicacion))].sort((a, b) => a - b), [existencias]);
  const [periodo, setPeriodo] = useState(mesActual);
  const [filtro, setFiltro] = useState({ id_variacion: '', id_ubicacion: '' });
  const [lineas, setLineas] = useState(null);
  const [truncado, setTruncado] = useState(false);
  const [error, setError] = useState(null);

  useEffect(() => {
    if (!periodo.fecha_inicio || !periodo.fecha_fin) return undefined;
    let vigente = true;
    setLineas(null);
    setError(null);
    api.obtenerKardexValorizado(periodo)
      .then((r) => { if (vigente) { setLineas(r.datos); setTruncado(!!r.paginacion?.truncado); } })
      .catch((falla) => vigente && setError(falla.error));
    return () => { vigente = false; };
  }, [api, periodo]);

  const filtradas = useMemo(() => (lineas ?? []).filter((k) =>
    (!filtro.id_variacion || k.id_variacion === Number(filtro.id_variacion))
    && (!filtro.id_ubicacion || k.id_ubicacion === Number(filtro.id_ubicacion))), [lineas, filtro]);

  const totales = useMemo(() => {
    const t = { entradas: 0, unidadesEntrada: 0, salidas: 0, unidadesSalida: 0, transferidas: 0 };
    filtradas.forEach((k) => {
      if (k.monto_total == null) return; // reservas: sin efecto en el costo
      if (TRANSFERENCIAS.includes(k.tipo_movimiento)) {
        if (k.tipo_movimiento === 'transferencia_salida') t.transferidas += k.cantidad;
        return;
      }
      if (k.efecto_stock === 'aumenta') { t.entradas += Number(k.monto_total); t.unidadesEntrada += k.cantidad; }
      if (k.efecto_stock === 'disminuye') { t.salidas += Number(k.monto_total); t.unidadesSalida += k.cantidad; }
    });
    return t;
  }, [filtradas]);

  const valorInventario = existencias
    .filter((e) => (!filtro.id_variacion || e.id_variacion === Number(filtro.id_variacion)) && (!filtro.id_ubicacion || e.id_ubicacion === Number(filtro.id_ubicacion)))
    .reduce((s, e) => s + Number(e.valor_inventario ?? 0), 0);

  const variacionDe = (id) => variaciones.find((v) => v.id_variacion === id) ?? { id_variacion: id };
  const signo = (k) => (k.efecto_stock === 'disminuye' ? '−' : k.efecto_stock === 'aumenta' ? '+' : '');

  return (
    <div className="space-y-4">
      <Tarjeta>
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          {[['fecha_inicio', 'Desde'], ['fecha_fin', 'Hasta']].map(([campo, etiqueta]) => (
            <label key={campo} htmlFor={`rep-${campo}`} className="min-w-0 text-xs font-semibold text-gray-700">
              {etiqueta}
              <input id={`rep-${campo}`} type="date" className={claseEntrada(error?.codigo === 'ERR-M4-012')} value={periodo[campo]} onChange={(e) => setPeriodo({ ...periodo, [campo]: e.target.value })} />
            </label>
          ))}
          <label htmlFor="rep-variacion" className="min-w-0 text-xs font-semibold text-gray-700">
            Variación
            <select id="rep-variacion" className={claseEntrada(false)} value={filtro.id_variacion} onChange={(e) => setFiltro({ ...filtro, id_variacion: e.target.value })}>
              <option value="">Todas</option>
              {variaciones.map((v) => <option key={v.id_variacion} value={v.id_variacion}>{nombreVariacion(v)}</option>)}
            </select>
          </label>
          <label htmlFor="rep-ubicacion" className="min-w-0 text-xs font-semibold text-gray-700">
            Ubicación
            <select id="rep-ubicacion" className={claseEntrada(false)} value={filtro.id_ubicacion} onChange={(e) => setFiltro({ ...filtro, id_ubicacion: e.target.value })}>
              <option value="">Todas</option>
              {ubicaciones.map((id) => <option key={id} value={id}>{nombreSede(sedes, id)}</option>)}
            </select>
          </label>
        </div>
      </Tarjeta>

      {error && <Aviso tipo="error" titulo="No se pudo generar el reporte" codigo={error.codigo}>{error.mensaje}</Aviso>}
      {truncado && <Aviso tipo="aviso" titulo="Periodo muy largo">Se muestran las primeras 5000 líneas. Acorte el rango de fechas para ver el total exacto.</Aviso>}

      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <Indicador titulo="Entradas" valor={formatoColones(totales.entradas)} detalle={`${totales.unidadesEntrada} unidades en el periodo`} Icono={IconoEntrada} tono="bg-green-50 text-green-700" />
        <Indicador titulo="Salidas" valor={formatoColones(totales.salidas)} detalle={`${totales.unidadesSalida} unidades al costo promedio`} Icono={IconoSalida} tono="bg-red-50 text-red-700" />
        <Indicador titulo="Transferencias" valor={`${totales.transferidas} u.`} detalle="Entre sedes, sin efecto en el valor total" Icono={IconoTransferir} tono="bg-blue-50 text-brandDarkBlue" />
        <Indicador titulo="Valor del inventario" valor={formatoColones(valorInventario)} detalle="Actual, con los filtros elegidos" Icono={IconoGrafico} tono="bg-amber-50 text-amber-800" />
      </div>

      <section aria-labelledby="titulo-detalle" className="space-y-2">
        <div className="flex flex-wrap items-center justify-between gap-2">
          <h2 id="titulo-detalle" className="text-base font-bold text-gray-900">Detalle del kardex valorizado</h2>
          <span className="inline-flex items-center gap-1 text-xs font-medium text-gray-500"><IconoCandado className="h-3.5 w-3.5" />Solo lectura · {filtradas.length} líneas</span>
        </div>

        {lineas === null && !error && <p className="text-sm text-gray-500">Cargando el reporte…</p>}
        {lineas !== null && filtradas.length === 0 && <Tarjeta><p className="text-sm text-gray-600">No hay movimientos en el periodo con estos filtros.</p></Tarjeta>}

        {/* Celular: tarjetas */}
        {filtradas.length > 0 && (
          <ol className="space-y-2 md:hidden">
            {filtradas.map((k) => (
              <li key={k.id_kardex} className="rounded-xl border border-gray-100 bg-white p-3 shadow-sm">
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <InsigniaTipo tipo={k.tipo_movimiento} />
                  <time className="text-xs text-gray-500" dateTime={k.fecha_movimiento}>{formatoFecha(k.fecha_movimiento)}</time>
                </div>
                <p className="mt-1 break-words text-sm font-semibold text-gray-900">{nombreVariacion(variacionDe(k.id_variacion))}</p>
                <p className="text-xs text-gray-500">{nombreSede(sedes, k.id_ubicacion)}</p>
                <dl className="mt-2 grid grid-cols-3 gap-2 text-xs">
                  <div><dt className="text-gray-500">Cantidad</dt><dd className="font-semibold text-gray-900">{signo(k)}{k.cantidad}</dd></div>
                  <div><dt className="text-gray-500">Monto</dt><dd className="font-semibold text-gray-900">{formatoColones(k.monto_total)}</dd></div>
                  <div><dt className="text-gray-500">Saldo valorizado</dt><dd className="font-semibold text-gray-900">{formatoColones(k.saldo_valorizado)}</dd></div>
                </dl>
              </li>
            ))}
          </ol>
        )}

        {/* Tablet y computadora: tabla con desplazamiento horizontal propio */}
        {filtradas.length > 0 && (
          <div className="hidden overflow-x-auto rounded-xl border border-gray-100 bg-white shadow-sm md:block">
            <table className="w-full min-w-[720px] text-left text-sm">
              <thead className="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                <tr>
                  <th scope="col" className="px-3 py-2 font-semibold">Fecha</th>
                  <th scope="col" className="px-3 py-2 font-semibold">Tipo</th>
                  <th scope="col" className="px-3 py-2 font-semibold">Variación</th>
                  <th scope="col" className="px-3 py-2 font-semibold">Ubicación</th>
                  <th scope="col" className="px-3 py-2 text-right font-semibold">Cantidad</th>
                  <th scope="col" className="px-3 py-2 text-right font-semibold">Costo unit.</th>
                  <th scope="col" className="px-3 py-2 text-right font-semibold">Monto</th>
                  <th scope="col" className="px-3 py-2 text-right font-semibold">Saldo</th>
                  <th scope="col" className="px-3 py-2 text-right font-semibold">Saldo valorizado</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100">
                {filtradas.map((k) => (
                  <tr key={k.id_kardex} className="hover:bg-gray-50">
                    <td className="whitespace-nowrap px-3 py-2 text-gray-600">{formatoFecha(k.fecha_movimiento)}</td>
                    <td className="px-3 py-2"><InsigniaTipo tipo={k.tipo_movimiento} /></td>
                    <td className="px-3 py-2 text-gray-900">{nombreVariacion(variacionDe(k.id_variacion))}</td>
                    <td className="px-3 py-2 text-gray-600">{nombreSede(sedes, k.id_ubicacion)}</td>
                    <td className="px-3 py-2 text-right font-semibold text-gray-900">{signo(k)}{k.cantidad}</td>
                    <td className="whitespace-nowrap px-3 py-2 text-right text-gray-600">{formatoColones(k.costo_unitario)}</td>
                    <td className="whitespace-nowrap px-3 py-2 text-right text-gray-900">{formatoColones(k.monto_total)}</td>
                    <td className="px-3 py-2 text-right text-gray-600">{k.saldo_cantidad}</td>
                    <td className="whitespace-nowrap px-3 py-2 text-right font-semibold text-gray-900">{formatoColones(k.saldo_valorizado)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </section>
    </div>
  );
}
