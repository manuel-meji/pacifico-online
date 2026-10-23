// Reservas de pedidos (M4). Muestra cómo se ven en M4 los casos de uso de la sección 3, que inicia M5 y no una persona:
// CU-M4-06 «Reservar existencias para pedido» y CU-M4-07 «Liberar o confirmar reserva», incluida la salida por despacho.
// M4 no tiene una pantalla para crear reservas: M5 llama a reservarExistencias, confirmarReserva, liberarReserva y
// confirmarSalidaPorReserva por InventarioModuleInterface. El encargado ve aquí el estado de cada reserva y su efecto
// en el disponible; el panel «Simular llamadas de M5» reproduce esas llamadas para la validación y no existe en producción.
import { useEffect, useRef, useState } from 'react';
import { Aviso, ESTADO_RESERVA, Tarjeta, claseEntrada, formatoColones, formatoFecha } from '../components/componentes';
import { IconoCargando } from '../components/iconos';
import { pedidos, ubicaciones, variaciones } from '../services/datosMock';
import { disponible } from '../services/inventarioApi';

const nombreUbicacion = (id) => ubicaciones.find((u) => u.id_ubicacion === id)?.nombre ?? `Ubicación ${id}`;
const nombreVariacion = (id) => {
  const v = variaciones.find((x) => x.id_variacion === id);
  return v ? `${v.sku} · ${v.atributos}` : `Variación ${id}`;
};

function BotonSecundario({ children, ...props }) {
  return (
    <button type="button" {...props}
      className="min-h-[40px] rounded-lg border border-brandBlue px-3 py-1.5 text-sm font-semibold text-brandBlue transition hover:bg-blue-50 disabled:cursor-not-allowed disabled:border-gray-200 disabled:text-gray-400">
      {children}
    </button>
  );
}

export default function ReservasPedidos({ api, existencias, version, alCambiar }) {
  const [reservas, setReservas] = useState(null);
  const [form, setForm] = useState({ id_variacion: 501, id_ubicacion: '', cantidad: 1 });
  const [vinculo, setVinculo] = useState(0); // índice en pedidos
  const [aviso, setAviso] = useState(null);
  const [ocupado, setOcupado] = useState(null);
  const avisoRef = useRef(null);
  // Igual que H1: el resultado se lleva a la vista a 360 px.
  useEffect(() => { if (aviso) avisoRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' }); }, [aviso]);

  useEffect(() => {
    let vigente = true;
    api.listarReservas(existencias).then((r) => vigente && setReservas(r));
    return () => { vigente = false; };
  }, [api, existencias, version]);

  async function llamar(clave, accion, exito) {
    setAviso(null);
    setOcupado(clave);
    try {
      const r = await accion();
      setAviso({ tipo: 'exito', titulo: exito(r) });
      alCambiar();
    } catch (falla) {
      const e = falla.error ?? { codigo: 'ERR-006', mensaje: 'No se pudo completar la operación.' };
      setAviso({ tipo: 'error', titulo: 'M4 rechazó la llamada', mensaje: e.mensaje, codigo: e.codigo });
    } finally {
      setOcupado(null);
    }
  }

  const p = pedidos[vinculo];
  const v = { id_pedido: p.id_pedido, id_subpedido: p.id_subpedido };

  return (
    <div className="space-y-4">
      <Aviso tipo="info" titulo="Las reservas las crea M5, no el encargado">
        Cuando un cliente llega al checkout, M5 (Carrito y Pedidos) reserva las unidades; al pagarse el pedido las confirma y al
        despacharlo registra la salida. Las unidades reservadas no se pueden vender, sacar ni transferir. Aquí se consulta cada reserva y su efecto en el disponible.
      </Aviso>

      <div ref={avisoRef} className="scroll-mt-4" aria-live="polite">
        {aviso && <Aviso tipo={aviso.tipo} titulo={aviso.titulo} codigo={aviso.codigo}>{aviso.mensaje}</Aviso>}
      </div>

      <section aria-labelledby="titulo-reservas" className="space-y-2">
        <h2 id="titulo-reservas" className="text-base font-bold text-gray-900">Reservas del comercio</h2>
        {reservas === null && <p className="text-sm text-gray-500">Cargando reservas…</p>}
        <ol className="space-y-2">
          {reservas?.map((r) => {
            const e = existencias.find((x) => x.id_existencia === r.id_existencia);
            const estado = ESTADO_RESERVA[r.estado_reserva];
            const abierta = r.estado_reserva === 'activa' || r.estado_reserva === 'confirmada';
            return (
              <li key={r.id_reserva} className="rounded-xl border border-gray-100 bg-white p-3 shadow-sm">
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <span className={`inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold ${estado.estilo}`}>{estado.nombre}</span>
                  <span className="text-xs text-gray-500">{r.id_pedido ? `Pedido ${r.id_pedido} · subpedido ${r.id_subpedido}` : 'Sin pedido vinculado'}</span>
                </div>
                <p className="mt-2 text-sm font-semibold text-gray-900">{r.cantidad} × {nombreVariacion(r.id_variacion)}</p>
                <p className="text-xs text-gray-600">{nombreUbicacion(r.id_ubicacion)} · creada {formatoFecha(r.fecha_creacion)}
                  {r.estado_reserva === 'activa' && ` · vence ${formatoFecha(r.fecha_expiracion)}`}
                  {r.fecha_resolucion && ` · cerrada ${formatoFecha(r.fecha_resolucion)}`}</p>
                {e && abierta && <p className="mt-1 text-xs text-gray-500">En esa ubicación quedan {disponible(e)} disponibles ({e.cantidad_reservada} reservadas).</p>}
                <p className="mt-1 break-all text-[11px] text-gray-400">id_reserva {r.id_reserva}</p>

                <div className="mt-3 border-t border-dashed border-gray-200 pt-2">
                  <p className="mb-1 text-[11px] font-semibold uppercase tracking-wide text-gray-400">Simular llamada de M5</p>
                  <div className="flex flex-wrap gap-2">
                    <BotonSecundario disabled={!!ocupado || r.estado_reserva !== 'activa'}
                      onClick={() => llamar(`c-${r.id_reserva}`, () => api.confirmarReserva(existencias, r.id_reserva, v), () => `Reserva confirmada para el pedido ${v.id_pedido}`)}>
                      Confirmar ({p.etiqueta.split(' · ')[0]})
                    </BotonSecundario>
                    <BotonSecundario disabled={!!ocupado || r.estado_reserva !== 'confirmada'}
                      onClick={() => llamar(`s-${r.id_reserva}`, () => api.confirmarSalidaPorReserva(existencias, r.id_reserva, { id_pedido: r.id_pedido, id_subpedido: r.id_subpedido }),
                        (s) => `Salida por despacho registrada: ${s.cantidad} unidades a ${formatoColones(s.costo_unitario)} (${formatoColones(s.monto_total)})`)}>
                      Despachar
                    </BotonSecundario>
                    <BotonSecundario disabled={!!ocupado || !abierta}
                      onClick={() => llamar(`l-${r.id_reserva}`, () => api.liberarReserva(existencias, r.id_reserva, { motivo: 'pedido_cancelado' }), () => 'Reserva liberada: las unidades vuelven a estar disponibles')}>
                      Liberar (pedido cancelado)
                    </BotonSecundario>
                  </div>
                </div>
              </li>
            );
          })}
        </ol>
      </section>

      <Tarjeta className="border-dashed">
        <h2 className="text-sm font-bold text-gray-900">Simular llamadas de M5</h2>
        <p className="mt-1 text-xs text-gray-500">Solo para la validación del prototipo: reproduce lo que M5 envía por la interfaz.</p>
        <form className="mt-3 space-y-3" onSubmit={(ev) => {
          ev.preventDefault();
          const s = { id_comercio: 104, id_variacion: Number(form.id_variacion), cantidad: Number(form.cantidad), expiracion_minutos: 15,
            ...(form.id_ubicacion && { id_ubicacion: Number(form.id_ubicacion) }) };
          llamar('reservar', () => api.reservarExistencias(existencias, s), (r) => `Reserva creada en ${nombreUbicacion(r.id_ubicacion)}: vence en 15 minutos si M5 no la confirma`);
        }}>
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <label className="block text-xs font-semibold text-gray-700">Variación
              <select className={claseEntrada(false)} value={form.id_variacion} onChange={(e) => setForm({ ...form, id_variacion: e.target.value })}>
                {variaciones.map((x) => <option key={x.id_variacion} value={x.id_variacion}>{x.sku} · {x.atributos}</option>)}
              </select>
            </label>
            <label className="block text-xs font-semibold text-gray-700">Ubicación
              <select className={claseEntrada(false)} value={form.id_ubicacion} onChange={(e) => setForm({ ...form, id_ubicacion: e.target.value })}>
                <option value="">M4 elige</option>
                {ubicaciones.map((u) => <option key={u.id_ubicacion} value={u.id_ubicacion}>{u.nombre}</option>)}
              </select>
            </label>
            <label className="block text-xs font-semibold text-gray-700">Cantidad
              <select className={claseEntrada(false)} value={form.cantidad} onChange={(e) => setForm({ ...form, cantidad: e.target.value })}>
                {[1, 2, 5, 10, 50].map((n) => <option key={n} value={n}>{n}</option>)}
              </select>
            </label>
          </div>
          <label className="block text-xs font-semibold text-gray-700">Pedido que usa «Confirmar»
            <select className={claseEntrada(false)} value={vinculo} onChange={(e) => setVinculo(Number(e.target.value))}>
              {pedidos.map((x, i) => <option key={x.id_pedido} value={i}>{x.etiqueta} (subpedido {x.id_subpedido})</option>)}
            </select>
          </label>
          <button type="submit" disabled={!!ocupado}
            className="flex w-full items-center justify-center gap-2 rounded-lg bg-brandDarkBlue px-4 py-2.5 text-sm font-bold text-white transition hover:bg-brandBlue disabled:cursor-not-allowed disabled:bg-gray-300">
            {ocupado === 'reservar' && <IconoCargando className="h-4 w-4 animate-spin" />}
            M5 reserva existencias
          </button>
        </form>
      </Tarjeta>
    </div>
  );
}
