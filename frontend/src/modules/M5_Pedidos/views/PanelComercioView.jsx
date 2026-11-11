import { useCallback, useEffect, useMemo, useState } from 'react';
import {
  Boxes, Check, ChevronDown, ChevronUp, ClipboardList, Clock, CreditCard, History,
  LoaderCircle, MapPin, Package, PackageCheck, RefreshCw, Store, Truck, X,
} from 'lucide-react';
import { Alerta, Boton, Encabezado, ModalConCampo } from '../components/componentes';
import {
  cancelarSubpedido, confirmarPago, despacharSubpedido, entregarSubpedido, listarPedidos, prepararSubpedido,
} from '../services/m5Api';
import { colones } from '../services/calculos';
import { formatearFecha } from '../services/estados';
import { EtiquetaEstado } from './MisPedidosView';
import { obtenerUsuario } from '../../Nucleo/services/sesionApi';

// Tabla de transiciones de Subpedido::TRANSICIONES_PERMITIDAS (app/Models/M5_Pedidos/Subpedido.php).
// RN-M5-05: no se puede cancelar si el subpedido ya está en en_preparacion, despachado o entregado.
const TRANSICIONES = {
  pendiente: ['pagado', 'cancelado'],
  confirmado: ['pagado', 'cancelado'],
  pagado: ['en_preparacion', 'despachado', 'cancelado'],
  en_preparacion: ['despachado'],
  despachado: ['entregado'],
  entregado: [],
  cancelado: [],
};

const PASOS_FLUJO = [
  { clave: 'pendiente', etiqueta: 'Recibido' },
  { clave: 'pagado', etiqueta: 'Pagado' },
  { clave: 'en_preparacion', etiqueta: 'En preparación' },
  { clave: 'despachado', etiqueta: 'Despachado' },
  { clave: 'entregado', etiqueta: 'Entregado' },
];

const FILTROS_ESTADO = [
  { clave: 'todos', etiqueta: 'Todos' },
  { clave: 'pendiente_pago', etiqueta: 'Pendientes de pago' },
  { clave: 'pagado', etiqueta: 'Por preparar' },
  { clave: 'en_preparacion', etiqueta: 'En preparación' },
  { clave: 'despachado', etiqueta: 'Despachados' },
  { clave: 'entregado', etiqueta: 'Entregados' },
  { clave: 'cancelado', etiqueta: 'Cancelados' },
];

const puedeTransicionar = (actual, destino) =>
  (TRANSICIONES[String(actual || 'pendiente').toLowerCase()] || []).includes(destino);

const indicePasoFlujo = (estadoRaw) => {
  const est = String(estadoRaw || 'pendiente').toLowerCase();
  if (est === 'confirmado' || est === 'pendiente' || est === 'pendiente_pago') return 0;
  if (est === 'pagado') return 1;
  if (est === 'en_preparacion') return 2;
  if (est === 'despachado') return 3;
  if (est === 'entregado') return 4;
  return -1;
};

function BarraProgresoSubpedido({ estado }) {
  const est = String(estado || 'pendiente').toLowerCase();
  if (est === 'cancelado') {
    return (
      <div className="rounded-lg border border-red-200 bg-red-50/70 px-3 py-2 text-xs font-semibold text-red-700 flex items-center gap-2">
        <X className="w-4 h-4 shrink-0 text-red-600" aria-hidden="true" />
        <span>Subpedido cancelado — ciclo operativo finalizado.</span>
      </div>
    );
  }

  const activoIdx = indicePasoFlujo(est);

  return (
    <div className="rounded-xl border border-gray-100 bg-gray-50/80 px-3 py-2.5">
      <ol className="grid grid-cols-5 gap-1.5 text-[11px]" aria-label="Progreso del subpedido">
        {PASOS_FLUJO.map((paso, idx) => {
          const completado = idx < activoIdx || est === 'entregado';
          const actual = idx === activoIdx && est !== 'entregado';
          return (
            <li key={paso.clave} className="flex flex-col items-center text-center gap-1">
              <div
                className={`h-6 w-6 rounded-full flex items-center justify-center text-[11px] font-bold transition ${
                  completado
                    ? 'bg-brandGreen text-white'
                    : actual
                      ? 'bg-brandDarkBlue text-white ring-2 ring-blue-200'
                      : 'bg-white border border-gray-200 text-gray-400'
                }`}
              >
                {completado ? <Check className="w-3.5 h-3.5" aria-hidden="true" /> : idx + 1}
              </div>
              <span
                className={`leading-tight truncate max-w-full ${
                  actual ? 'font-bold text-brandDarkBlue' : completado ? 'font-semibold text-gray-700' : 'text-gray-400'
                }`}
              >
                {paso.etiqueta}
              </span>
            </li>
          );
        })}
      </ol>
    </div>
  );
}

function TarjetaSubpedido({
  sub,
  enCurso,
  onConfirmarPago,
  onPreparar,
  onAbrirDespacho,
  onEntregar,
  onAbrirCancelacion,
}) {
  const [verHistorial, setVerHistorial] = useState(false);
  const estado = String(sub.estado_subpedido || 'pendiente').toLowerCase();
  const guia = sub.guia_seguimiento || sub.guia_transporte;
  const lineas = sub.lineas || [];
  const historial = sub.estados || sub.historial_estados || [];
  const total = sub.total ?? Number(sub.subtotal || 0) + Number(sub.impuesto || 0) + Number(sub.costo_envio || 0);

  const ocupadoPago = enCurso === `pago-${sub.id_pedido}`;
  const ocupadoPrep = enCurso === `prep-${sub.id_subpedido}`;
  const ocupadoDesp = enCurso === `desp-${sub.id_subpedido}`;
  const ocupadoEntr = enCurso === `entr-${sub.id_subpedido}`;
  const ocupadoCanc = enCurso === `canc-${sub.id_subpedido}`;
  const bloqueado = Boolean(enCurso);

  const puedePagar = puedeTransicionar(estado, 'pagado');
  const puedePreparar = puedeTransicionar(estado, 'en_preparacion');
  const puedeDespachar = puedeTransicionar(estado, 'despachado');
  const puedeEntregar = puedeTransicionar(estado, 'entregado');
  const puedeCancelar = puedeTransicionar(estado, 'cancelado');

  return (
    <article className="bg-white rounded-xl shadow-xs border border-gray-200 p-4 sm:p-5 space-y-4 transition hover:border-gray-300">
      <div className="flex flex-wrap items-start justify-between gap-2 pb-3 border-b border-gray-100">
        <div className="space-y-1">
          <div className="flex flex-wrap items-center gap-2">
            <span className="text-sm sm:text-base font-extrabold text-brandDarkBlue">
              Subpedido #{sub.id_subpedido}
            </span>
            <span className="text-xs font-semibold text-gray-500 bg-gray-100 px-2 py-0.5 rounded-md">
              Pedido #{sub.id_pedido}
            </span>
            <span className="inline-flex items-center gap-1 text-xs font-semibold text-brandDarkBlue bg-blue-50 border border-blue-100 px-2.5 py-0.5 rounded-md">
              <Store className="w-3.5 h-3.5 text-brandBlue" aria-hidden="true" />
              {sub.nombre_comercio}
            </span>
          </div>
          <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-500">
            <span>Recibido: {formatearFecha(sub.fecha_pedido)}</span>
            {sub.direccion_envio && (
              <span className="inline-flex items-center gap-1 text-gray-600">
                <MapPin className="w-3.5 h-3.5 text-brandBlue shrink-0" aria-hidden="true" />
                {sub.direccion_envio}
              </span>
            )}
          </div>
        </div>
        <EtiquetaEstado estado={estado} />
      </div>

      {/* Progreso visual del ciclo de vida en BD */}
      <BarraProgresoSubpedido estado={estado} />

      {guia && (
        <div className="flex flex-wrap items-center justify-between gap-2 text-xs text-indigo-900 bg-indigo-50 border border-indigo-200 rounded-lg px-3 py-2">
          <span className="inline-flex items-center gap-1.5">
            <Truck className="w-4 h-4 shrink-0 text-indigo-600" aria-hidden="true" />
            <span>Guía de seguimiento: <strong className="font-bold">{guia}</strong></span>
          </span>
          {sub.fecha_despacho && (
            <span className="text-indigo-700 font-medium">
              Despachado: {formatearFecha(sub.fecha_despacho)}
            </span>
          )}
        </div>
      )}

      {/* Líneas de productos del subpedido */}
      {lineas.length > 0 && (
        <div className="rounded-lg border border-gray-100 bg-gray-50/50 p-3">
          <p className="text-xs font-bold uppercase tracking-wider text-gray-500 mb-2">
            Productos del subpedido ({lineas.length})
          </p>
          <ul className="divide-y divide-gray-100 text-xs sm:text-sm">
            {lineas.map((l, i) => {
              const id = l.id_linea ?? l.id_variacion ?? i;
              const nombre = l.nombre || l.nombre_producto || `Variación #${l.id_variacion}`;
              const subLinea = l.subtotal_linea ?? l.precio_unitario * l.cantidad;
              return (
                <li key={id} className="py-1.5 flex items-center justify-between gap-2 text-gray-700">
                  <span className="flex items-center gap-2 min-w-0">
                    <span className="min-w-6 h-5 px-1.5 bg-white border border-gray-200 rounded text-center font-bold text-brandDarkBlue text-xs">
                      {l.cantidad}x
                    </span>
                    <span className="truncate font-medium">{nombre}</span>
                  </span>
                  <span className="shrink-0 font-bold text-gray-900">{colones(subLinea)}</span>
                </li>
              );
            })}
          </ul>
        </div>
      )}

      {/* Resumen financiero del subpedido */}
      <dl className="grid grid-cols-2 sm:grid-cols-4 gap-2 text-xs bg-gray-50 rounded-lg p-3 border border-gray-100">
        <div>
          <dt className="text-gray-500">Subtotal</dt>
          <dd className="font-bold text-gray-800">{colones(sub.subtotal)}</dd>
        </div>
        <div>
          <dt className="text-gray-500">Impuestos</dt>
          <dd className="font-bold text-gray-800">{colones(sub.impuesto)}</dd>
        </div>
        <div>
          <dt className="text-gray-500">Envío</dt>
          <dd className="font-bold text-gray-800">{colones(sub.costo_envio)}</dd>
        </div>
        <div>
          <dt className="text-gray-500">Total comercio</dt>
          <dd className="font-extrabold text-brandDarkBlue text-sm">{colones(total)}</dd>
        </div>
      </dl>

      {/* Acciones operativas de transición de estado */}
      <div className="pt-2 border-t border-gray-100 flex flex-wrap items-center justify-between gap-2">
        <div className="flex flex-wrap gap-2">
          {puedePagar && (
            <Boton
              variante="primario"
              icono={ocupadoPago ? LoaderCircle : CreditCard}
              disabled={bloqueado}
              onClick={() => onConfirmarPago(sub)}
              className="text-xs px-3.5 py-2"
            >
              {ocupadoPago ? 'Confirmando…' : 'Confirmar pago recibido'}
            </Boton>
          )}

          {puedePreparar && (
            <Boton
              variante="primario"
              icono={ocupadoPrep ? LoaderCircle : Package}
              disabled={bloqueado}
              onClick={() => onPreparar(sub)}
              className="text-xs px-3.5 py-2 bg-amber-600 hover:bg-amber-700"
            >
              {ocupadoPrep ? 'Actualizando…' : 'Iniciar preparación / Empacar'}
            </Boton>
          )}

          {puedeDespachar && (
            <Boton
              variante="primario"
              icono={ocupadoDesp ? LoaderCircle : Truck}
              disabled={bloqueado}
              onClick={() => onAbrirDespacho(sub)}
              className="text-xs px-3.5 py-2"
            >
              {ocupadoDesp ? 'Despachando…' : 'Despachar pedido'}
            </Boton>
          )}

          {puedeEntregar && (
            <Boton
              variante="primario"
              icono={ocupadoEntr ? LoaderCircle : PackageCheck}
              disabled={bloqueado}
              onClick={() => onEntregar(sub)}
              className="text-xs px-3.5 py-2 bg-green-700 hover:bg-green-800"
            >
              {ocupadoEntr ? 'Confirmando…' : 'Marcar entregado'}
            </Boton>
          )}

          {puedeCancelar && (
            <Boton
              variante="peligro"
              icono={ocupadoCanc ? LoaderCircle : X}
              disabled={bloqueado}
              onClick={() => onAbrirCancelacion(sub)}
              className="text-xs px-3.5 py-2"
            >
              {ocupadoCanc ? 'Cancelando…' : 'Cancelar subpedido'}
            </Boton>
          )}

          {!puedePagar && !puedePreparar && !puedeDespachar && !puedeEntregar && !puedeCancelar && (
            <span className="inline-flex items-center gap-1.5 text-xs text-gray-500 font-medium py-1">
              <Check className="w-4 h-4 text-brandGreen" aria-hidden="true" />
              Estado final alcanzado ({estado}). No hay transiciones pendientes.
            </span>
          )}
        </div>

        {historial.length > 0 && (
          <button
            type="button"
            onClick={() => setVerHistorial((v) => !v)}
            className="inline-flex items-center gap-1 text-xs font-semibold text-brandDarkBlue hover:text-brandBlue transition cursor-pointer"
          >
            <History className="w-3.5 h-3.5" aria-hidden="true" />
            <span>Historial ({historial.length})</span>
            {verHistorial ? <ChevronUp className="w-3.5 h-3.5" /> : <ChevronDown className="w-3.5 h-3.5" />}
          </button>
        )}
      </div>

      {/* Historial de estados en BD (estados_pedido) */}
      {verHistorial && historial.length > 0 && (
        <div className="rounded-lg border border-gray-200 bg-gray-50 p-3 space-y-2 text-xs">
          <p className="font-bold text-gray-700">Bitácora de estados del subpedido</p>
          <ol className="space-y-1.5">
            {historial.map((h, idx) => (
              <li key={h.id_estado_pedido ?? idx} className="flex flex-wrap items-center justify-between gap-2 text-gray-600 border-b border-gray-100 last:border-0 pb-1 last:pb-0">
                <span>
                  <strong className="font-semibold text-gray-800 uppercase">{h.estado}</strong>
                  {h.estado_anterior ? ` (desde ${h.estado_anterior})` : ''}
                  {h.motivo ? ` — ${h.motivo}` : ''}
                </span>
                <span className="text-[11px] text-gray-400">{formatearFecha(h.fecha_estado || h.created_at)}</span>
              </li>
            ))}
          </ol>
        </div>
      )}
    </article>
  );
}

// Vista de producción para la Gestión de Pedidos del Comercio (integrada con el portal de M2_Comercios).
export default function PanelComercioView({ onVolver }) {
  const [usuario, setUsuario] = useState(undefined);
  const [pedidos, setPedidos] = useState([]);
  const [cargando, setCargando] = useState(true);
  const [error, setError] = useState(null);
  const [aviso, setAviso] = useState(null);
  const [comercioFiltro, setComercioFiltro] = useState('todos');
  const [estadoFiltro, setEstadoFiltro] = useState('todos');
  const [enCurso, setEnCurso] = useState(null);
  const [modalDespacho, setModalDespacho] = useState(null);
  const [modalCancelacion, setModalCancelacion] = useState(null);

  useEffect(() => {
    let activo = true;
    obtenerUsuario()
      .then((r) => {
        if (!activo) return;
        const user = r?.ok ? r?.datos?.user ?? null : null;
        setUsuario(user);
        if (user?.id_comercio) {
          setComercioFiltro(String(user.id_comercio));
        }
      })
      .catch(() => {
        if (activo) setUsuario(null);
      });
    return () => { activo = false; };
  }, []);

  const cargar = useCallback(async () => {
    if (usuario === undefined) return;
    setCargando(true);
    setError(null);
    try {
      const idComercioSesion = usuario?.id_comercio ? Number(usuario.id_comercio) : undefined;
      const resp = await listarPedidos({
        por_pagina: 50,
        modo_comercio: 1,
        id_comercio: idComercioSesion,
      });
      setPedidos(resp.pedidos || []);
    } catch (e) {
      setError(e);
    } finally {
      setCargando(false);
    }
  }, [usuario]);

  useEffect(() => {
    if (usuario !== undefined) {
      cargar();
    }
  }, [usuario, cargar]);

  // Aplana pedidos → subpedidos conservando los datos del pedido maestro.
  const { subpedidos, comercios } = useMemo(() => {
    const lista = [];
    const mapaComercios = new Map();
    pedidos.forEach((p) => {
      (p.subpedidos || []).forEach((s) => {
        const idComercio = s.id_comercio ?? 0;
        const nombreComercio = s.nombre_comercio || s.comercio?.nombre || `Comercio #${idComercio}`;
        mapaComercios.set(String(idComercio), nombreComercio);
        lista.push({
          ...s,
          id_pedido: p.id_pedido,
          estado_pedido: p.estado_pedido,
          fecha_pedido: p.fecha_creacion || p.created_at,
          direccion_envio: p.direccion_envio_texto || null,
          nombre_comercio: nombreComercio,
        });
      });
    });
    return {
      subpedidos: lista,
      comercios: Array.from(mapaComercios, ([id, nombre]) => ({ id, nombre })),
    };
  }, [pedidos]);

  const subpedidosPorComercio = useMemo(
    () => (comercioFiltro === 'todos'
      ? subpedidos
      : subpedidos.filter((s) => String(s.id_comercio) === comercioFiltro)),
    [subpedidos, comercioFiltro]
  );

  const visibles = useMemo(() => {
    if (estadoFiltro === 'todos') return subpedidosPorComercio;
    return subpedidosPorComercio.filter((s) => {
      const est = String(s.estado_subpedido || 'pendiente').toLowerCase();
      if (estadoFiltro === 'pendiente_pago') {
        return est === 'pendiente' || est === 'confirmado' || est === 'pendiente_pago';
      }
      return est === estadoFiltro;
    });
  }, [subpedidosPorComercio, estadoFiltro]);

  // Resumen de métricas operativas del comercio
  const resumenEstados = useMemo(() => {
    const conteo = { total: subpedidosPorComercio.length, pagado: 0, en_preparacion: 0, despachado: 0, entregado: 0 };
    subpedidosPorComercio.forEach((s) => {
      const est = String(s.estado_subpedido || 'pendiente').toLowerCase();
      if (conteo[est] !== undefined) conteo[est] += 1;
    });
    return conteo;
  }, [subpedidosPorComercio]);

  async function ejecutar(clave, fn, mensajeExito) {
    setEnCurso(clave);
    setAviso(null);
    try {
      await fn();
      await cargar();
      setAviso({ tipo: 'exito', titulo: 'Estado actualizado', texto: mensajeExito });
      return true;
    } catch (e) {
      setAviso({
        tipo: 'error',
        titulo: 'No se pudo completar la operación',
        texto: `${e.mensaje || e.message}${e.codigo ? ` (${e.codigo})` : ''}`,
      });
      return false;
    } finally {
      setEnCurso(null);
    }
  }

  const handleConfirmarPago = (sub) =>
    ejecutar(
      `pago-${sub.id_pedido}`,
      () => confirmarPago(sub.id_pedido),
      `Pago confirmado para el pedido #${sub.id_pedido}. Sus subpedidos pasaron a estado «Pagado».`
    );

  const handlePreparar = (sub) =>
    ejecutar(
      `prep-${sub.id_subpedido}`,
      () => prepararSubpedido(sub.id_subpedido),
      `Subpedido #${sub.id_subpedido} marcado «En preparación». Ya puede empacarse y despacharse.`
    );

  const handleEntregar = (sub) =>
    ejecutar(
      `entr-${sub.id_subpedido}`,
      () => entregarSubpedido(sub.id_subpedido),
      `Subpedido #${sub.id_subpedido} marcado como «Entregado».`
    );

  async function confirmarDespacho(guia) {
    const sub = modalDespacho;
    if (!sub) return;
    const ok = await ejecutar(
      `desp-${sub.id_subpedido}`,
      () => despacharSubpedido(sub.id_subpedido, { guia_transporte: guia || null }),
      `Subpedido #${sub.id_subpedido} despachado${guia ? ` con guía ${guia}` : ''}.`
    );
    if (ok) setModalDespacho(null);
  }

  async function confirmarCancelacion(motivo) {
    const sub = modalCancelacion;
    if (!sub) return;
    const ok = await ejecutar(
      `canc-${sub.id_subpedido}`,
      () => cancelarSubpedido(sub.id_subpedido, { motivo }),
      `Subpedido #${sub.id_subpedido} cancelado.`
    );
    if (ok) setModalCancelacion(null);
  }

  const tieneComercioFijo = Boolean(usuario?.id_comercio);
  const nombreComercioSesion = comercios.find((c) => c.id === String(usuario?.id_comercio))?.nombre
    || (usuario?.id_comercio ? `Comercio #${usuario.id_comercio}` : null);

  return (
    <div className="min-h-screen flex flex-col">
      <Encabezado
        titulo="Gestión de Pedidos del Comercio"
        paso={`${visibles.length} ${visibles.length === 1 ? 'subpedido' : 'subpedidos'}`}
        onVolver={onVolver}
        etiquetaVolver="Volver al carrito"
      />

      {/* Barra de integración directa con el Portal de Comercio M2 e Inventario M4 */}
      <div className="bg-brandDarkBlue text-white border-b border-blue-900">
        <div className="max-w-6xl mx-auto px-4 py-3 flex flex-wrap items-center justify-between gap-3">
          <div className="flex items-center gap-3 min-w-0">
            <div className="h-9 w-9 rounded-lg bg-white/10 flex items-center justify-center shrink-0">
              <Store className="w-5 h-5 text-brandGreen" aria-hidden="true" />
            </div>
            <div className="min-w-0">
              <p className="text-xs text-blue-200 font-medium truncate">
                Portal de Comercios · {usuario?.nombre ? `Sesión: ${usuario.nombre}` : 'Operación de pedidos y despachos'}
              </p>
              <p className="text-sm sm:text-base font-bold truncate">
                {nombreComercioSesion || 'Panel de Gestión de Subpedidos y Estados'}
              </p>
            </div>
          </div>

          <div className="flex flex-wrap items-center gap-2">
            <a
              href="/m2.html"
              className="inline-flex items-center gap-1.5 rounded-lg border border-white/30 bg-white/10 hover:bg-white/20 px-3 py-2 text-xs font-bold text-white transition"
            >
              <Store className="w-4 h-4" aria-hidden="true" />
              <span>Mi comercio (M2)</span>
            </a>
            <a
              href="/m4.html"
              className="inline-flex items-center gap-1.5 rounded-lg border border-white/30 bg-white/10 hover:bg-white/20 px-3 py-2 text-xs font-bold text-white transition"
            >
              <Boxes className="w-4 h-4" aria-hidden="true" />
              <span>Inventario (M4)</span>
            </a>
            <button
              type="button"
              onClick={cargar}
              disabled={cargando}
              className="inline-flex items-center gap-1.5 rounded-lg bg-brandGreen hover:opacity-95 px-3 py-2 text-xs font-bold text-white transition cursor-pointer disabled:opacity-50"
            >
              <RefreshCw className={`w-3.5 h-3.5 ${cargando ? 'animate-spin' : ''}`} aria-hidden="true" />
              <span>Actualizar</span>
            </button>
          </div>
        </div>
      </div>

      <main className="flex-1 max-w-6xl w-full mx-auto px-4 py-6 space-y-5">
        {/* Indicadores rápidos de operación */}
        <section className="grid grid-cols-2 sm:grid-cols-5 gap-3" aria-label="Resumen de estados">
          <div className="bg-white rounded-xl border border-gray-200 p-3.5 shadow-2xs">
            <p className="text-xs font-semibold text-gray-500">Total subpedidos</p>
            <p className="mt-1 text-xl font-extrabold text-brandDarkBlue">{resumenEstados.total}</p>
          </div>
          <div className="bg-white rounded-xl border border-teal-200 p-3.5 shadow-2xs">
            <p className="text-xs font-semibold text-teal-700">Por preparar (Pagados)</p>
            <p className="mt-1 text-xl font-extrabold text-teal-800">{resumenEstados.pagado}</p>
          </div>
          <div className="bg-white rounded-xl border border-amber-200 p-3.5 shadow-2xs">
            <p className="text-xs font-semibold text-amber-700">En preparación</p>
            <p className="mt-1 text-xl font-extrabold text-amber-800">{resumenEstados.en_preparacion}</p>
          </div>
          <div className="bg-white rounded-xl border border-indigo-200 p-3.5 shadow-2xs">
            <p className="text-xs font-semibold text-indigo-700">Despachados</p>
            <p className="mt-1 text-xl font-extrabold text-indigo-800">{resumenEstados.despachado}</p>
          </div>
          <div className="col-span-2 sm:col-span-1 bg-white rounded-xl border border-green-200 p-3.5 shadow-2xs">
            <p className="text-xs font-semibold text-green-700">Entregados</p>
            <p className="mt-1 text-xl font-extrabold text-green-800">{resumenEstados.entregado}</p>
          </div>
        </section>

        {/* Controles de filtrado por estado y comercio */}
        <div className="bg-white rounded-xl border border-gray-200 p-3.5 flex flex-wrap items-center justify-between gap-3 shadow-2xs">
          <div className="flex flex-wrap items-center gap-1.5" role="tablist" aria-label="Filtrar por estado">
            {FILTROS_ESTADO.map((f) => {
              const activo = estadoFiltro === f.clave;
              return (
                <button
                  key={f.clave}
                  type="button"
                  role="tab"
                  aria-selected={activo}
                  onClick={() => setEstadoFiltro(f.clave)}
                  className={`px-3 py-1.5 rounded-lg text-xs font-bold transition cursor-pointer ${
                    activo
                      ? 'bg-brandDarkBlue text-white shadow-2xs'
                      : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                  }`}
                >
                  {f.etiqueta}
                </button>
              );
            })}
          </div>

          {!tieneComercioFijo && comercios.length > 1 && (
            <div className="flex items-center gap-2">
              <label htmlFor="filtro-comercio" className="text-xs font-semibold text-gray-600">
                Comercio:
              </label>
              <select
                id="filtro-comercio"
                value={comercioFiltro}
                onChange={(e) => setComercioFiltro(e.target.value)}
                className="min-h-9 border border-gray-200 rounded-lg py-1.5 px-3 text-xs font-semibold text-gray-800 bg-white focus:border-brandBlue focus:outline-none"
              >
                <option value="todos">Todos los comercios ({subpedidos.length})</option>
                {comercios.map((c) => (
                  <option key={c.id} value={c.id}>{c.nombre}</option>
                ))}
              </select>
            </div>
          )}
        </div>

        {aviso && <Alerta tipo={aviso.tipo} titulo={aviso.titulo}>{aviso.texto}</Alerta>}

        {cargando && subpedidos.length === 0 && (
          <div role="status" className="bg-white rounded-xl border border-gray-200 p-10 text-center space-y-3">
            <LoaderCircle className="w-8 h-8 mx-auto text-brandBlue animate-spin" aria-hidden="true" />
            <p className="text-sm font-semibold text-gray-600">Cargando pedidos del comercio…</p>
          </div>
        )}

        {error && !cargando && (
          <div className="space-y-3">
            <Alerta tipo="error" titulo="No se pudieron cargar los pedidos del comercio">
              {error.mensaje || error.message}
            </Alerta>
            <Boton variante="primario" onClick={cargar}>Reintentar</Boton>
          </div>
        )}

        {!cargando && !error && visibles.length === 0 && (
          <div className="bg-white rounded-xl shadow-xs border border-gray-200 p-8 sm:p-10 text-center space-y-3">
            <ClipboardList className="w-12 h-12 mx-auto stroke-1 text-gray-400" aria-hidden="true" />
            <h2 className="text-base sm:text-lg font-bold text-gray-800">
              {estadoFiltro === 'todos'
                ? 'Aún no hay subpedidos registrados para este comercio'
                : 'No hay subpedidos en el estado seleccionado'}
            </h2>
            <p className="text-xs sm:text-sm text-gray-500 max-w-md mx-auto">
              {estadoFiltro === 'todos'
                ? 'Cuando los clientes confirmen sus compras en el portal, sus subpedidos aparecerán aquí para prepararlos, empacarlos y despacharlos.'
                : 'Seleccione «Todos» en la barra de filtros para ver los demás subpedidos del comercio.'}
            </p>
            {estadoFiltro !== 'todos' && (
              <Boton variante="primario" onClick={() => setEstadoFiltro('todos')}>
                Ver todos los subpedidos
              </Boton>
            )}
          </div>
        )}

        {visibles.length > 0 && (
          <div className="space-y-4">
            {visibles.map((sub) => (
              <TarjetaSubpedido
                key={sub.id_subpedido}
                sub={sub}
                enCurso={enCurso}
                onConfirmarPago={handleConfirmarPago}
                onPreparar={handlePreparar}
                onAbrirDespacho={setModalDespacho}
                onEntregar={handleEntregar}
                onAbrirCancelacion={setModalCancelacion}
              />
            ))}
          </div>
        )}
      </main>

      <ModalConCampo
        abierto={Boolean(modalDespacho)}
        titulo={modalDespacho ? `Despachar subpedido #${modalDespacho.id_subpedido}` : ''}
        descripcion="Ingrese el número de guía o referencia del transportista para el seguimiento del cliente."
        etiquetaCampo="Guía de seguimiento"
        placeholder="Ej. CR-CORREOS-00123"
        obligatorio={false}
        textoConfirmar="Confirmar despacho"
        textoOcupado="Despachando…"
        icono={Truck}
        ocupado={Boolean(modalDespacho && enCurso === `desp-${modalDespacho.id_subpedido}`)}
        onConfirmar={confirmarDespacho}
        onCerrar={() => setModalDespacho(null)}
      />

      <ModalConCampo
        abierto={Boolean(modalCancelacion)}
        titulo={modalCancelacion ? `Cancelar subpedido #${modalCancelacion.id_subpedido}` : ''}
        descripcion="Esta acción cancelará las líneas de este comercio y liberará las reservas de inventario asociadas."
        etiquetaCampo="Motivo de la cancelación"
        placeholder="Indique la razón (p. ej. sin existencias verificadas en bodega)"
        obligatorio
        multilinea
        peligro
        textoConfirmar="Confirmar cancelación"
        textoOcupado="Cancelando…"
        icono={X}
        ocupado={Boolean(modalCancelacion && enCurso === `canc-${modalCancelacion.id_subpedido}`)}
        onConfirmar={confirmarCancelacion}
        onCerrar={() => setModalCancelacion(null)}
      />
    </div>
  );
}
