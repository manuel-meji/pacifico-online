import { useCallback, useEffect, useMemo, useState } from 'react';
import {
  Boxes, Calendar, Check, ChevronDown, ChevronUp, ClipboardList, Clock, CreditCard, History, Home,
  LayoutGrid, LoaderCircle, LogOut, MapPin, Megaphone, Package, PackageCheck, RefreshCw, RotateCcw,
  Search, ShieldCheck, Store, Truck, User, Wallet, X,
} from 'lucide-react';
import { Alerta, Boton, ModalConCampo } from '../components/componentes';
import {
  cancelarSubpedido, confirmarPago, despacharSubpedido, entregarSubpedido, listarPedidos, prepararSubpedido,
} from '../services/m5Api';
import { colones } from '../services/calculos';
import { formatearFecha } from '../services/estados';
import { EtiquetaEstado } from './MisPedidosView';
import { cerrarSesion, obtenerUsuario } from '../../Nucleo/services/sesionApi';
import logoPacificos from '../../../assets/logo-pacificos.png';

const URL_INGRESO = '/app.html?pantalla=ingresar';

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
  const devolucionSolicitada = (sub.cancelaciones || []).find((c) =>
    String(c.motivo || '').startsWith('[DEVOLUCIÓN]')
  );
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

        {devolucionSolicitada && (
          <div className="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5 text-xs text-amber-900 flex items-start gap-2">
            <RotateCcw className="w-4 h-4 shrink-0 mt-0.5 text-amber-700" aria-hidden="true" />
            <div className="min-w-0 space-y-0.5">
              <p className="font-bold">Devolución solicitada por el cliente</p>
              <p className="text-amber-800 break-words">
                Motivo: {String(devolucionSolicitada.motivo || '').replace(/^\[DEVOLUCIÓN\]\s*/, '')}
              </p>
              <p className="text-amber-700">
                El reembolso se gestiona en Pagos (M6). Al recibir físicamente el producto, decide si reingresarlo en Inventario (M4).
              </p>
            </div>
          </div>
        )}

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

function normalizarTexto(valor) {
  return String(valor || '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase();
}

function fechaLocalISO(fechaValor) {
  if (!fechaValor) return null;
  const d = new Date(fechaValor);
  if (Number.isNaN(d.getTime())) return null;
  const yyyy = d.getFullYear();
  const mm = String(d.getMonth() + 1).padStart(2, '0');
  const dd = String(d.getDate()).padStart(2, '0');
  return `${yyyy}-${mm}-${dd}`;
}

function textoBuscableSubpedido(sub) {
  const guia = sub.guia_seguimiento || sub.guia_transporte || '';
  const lineasTexto = (sub.lineas || []).map((l) => [
    l.nombre,
    l.nombre_producto,
    l.nombre_variacion,
    l.variacion,
    l.sku,
    l.id_variacion,
  ].filter(Boolean).join(' ')).join(' ');

  return normalizarTexto([
    sub.id_subpedido,
    `#${sub.id_subpedido}`,
    `subpedido ${sub.id_subpedido}`,
    sub.id_pedido,
    `#${sub.id_pedido}`,
    `pedido ${sub.id_pedido}`,
    sub.nombre_comercio,
    sub.direccion_envio,
    guia,
    sub.estado_subpedido,
    sub.motivo_cancelacion,
    formatearFecha(sub.fecha_pedido),
    lineasTexto,
  ].filter(Boolean).join(' '));
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
  const [busqueda, setBusqueda] = useState('');
  const [fechaDesde, setFechaDesde] = useState('');
  const [fechaHasta, setFechaHasta] = useState('');
  const [orden, setOrden] = useState('recientes');
  const [enCurso, setEnCurso] = useState(null);
  const [modalDespacho, setModalDespacho] = useState(null);
  const [modalCancelacion, setModalCancelacion] = useState(null);
  const [errorModal, setErrorModal] = useState(null);

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
    const terminos = normalizarTexto(busqueda).trim().split(/\s+/).filter(Boolean);

    const filtrados = subpedidosPorComercio.filter((s) => {
      if (estadoFiltro !== 'todos') {
        const est = String(s.estado_subpedido || 'pendiente').toLowerCase();
        if (estadoFiltro === 'pendiente_pago') {
          if (est !== 'pendiente' && est !== 'confirmado' && est !== 'pendiente_pago') return false;
        } else if (est !== estadoFiltro) {
          return false;
        }
      }

      if (fechaDesde || fechaHasta) {
        const fechaSub = fechaLocalISO(s.fecha_pedido);
        if (!fechaSub) return false;
        if (fechaDesde && fechaSub < fechaDesde) return false;
        if (fechaHasta && fechaSub > fechaHasta) return false;
      }

      if (terminos.length > 0) {
        const texto = textoBuscableSubpedido(s);
        if (!terminos.every((t) => texto.includes(t))) return false;
      }

      return true;
    });

    const montoDe = (s) => Number(s.total ?? Number(s.subtotal || 0) + Number(s.impuesto || 0) + Number(s.costo_envio || 0));
    const fechaDe = (s) => (s.fecha_pedido ? new Date(s.fecha_pedido).getTime() : 0);

    return [...filtrados].sort((a, b) => {
      if (orden === 'antiguos') return fechaDe(a) - fechaDe(b);
      if (orden === 'mayor_monto') return montoDe(b) - montoDe(a);
      if (orden === 'menor_monto') return montoDe(a) - montoDe(b);
      return fechaDe(b) - fechaDe(a);
    });
  }, [subpedidosPorComercio, estadoFiltro, fechaDesde, fechaHasta, busqueda, orden]);

  const tieneComercioFijo = Boolean(usuario?.id_comercio);
  const hayFiltrosActivos = Boolean(
    estadoFiltro !== 'todos'
    || busqueda.trim()
    || fechaDesde
    || fechaHasta
    || orden !== 'recientes'
    || (!tieneComercioFijo && comercioFiltro !== 'todos')
  );

  function limpiarFiltros() {
    setEstadoFiltro('todos');
    setBusqueda('');
    setFechaDesde('');
    setFechaHasta('');
    setOrden('recientes');
    if (!tieneComercioFijo) setComercioFiltro('todos');
  }

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
    setErrorModal(null);
    try {
      await fn();
      await cargar();
      setAviso({ tipo: 'exito', titulo: 'Estado actualizado', texto: mensajeExito });
      return true;
    } catch (e) {
      const textoError = `${e.mensaje || e.message}${e.codigo ? ` (${e.codigo})` : ''}`;
      setAviso({
        tipo: 'error',
        titulo: 'No se pudo completar la operación',
        texto: textoError,
      });
      setErrorModal(textoError);
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

  const nombreComercioSesion = comercios.find((c) => c.id === String(usuario?.id_comercio))?.nombre
    || (usuario?.id_comercio ? `Comercio #${usuario.id_comercio}` : null);

  const salir = async () => {
    setAviso(null);
    try {
      const respuesta = await cerrarSesion();
      if (respuesta.ok || respuesta.estado === 401) {
        window.location.href = URL_INGRESO;
      } else {
        setAviso({ tipo: 'error', titulo: 'Error al salir', texto: 'No se pudo cerrar la sesión. Intente de nuevo.' });
      }
    } catch {
      setAviso({ tipo: 'error', titulo: 'Error al salir', texto: 'No se pudo cerrar la sesión. Intente de nuevo.' });
    }
  };

  const esComercio = usuario?.rol === 'encargado_comercio';
  const esPlataforma = usuario?.rol === 'encargado_plataforma';

  const claseTab = (activa) =>
    `flex shrink-0 items-center gap-2 border-b-4 px-4 py-3 text-sm font-bold text-white transition-colors focus:outline-none focus-visible:bg-white/10 ${
      activa ? 'border-[#9DBB45]' : 'border-transparent opacity-80 hover:opacity-100'
    }`;

  const tarjetasResumen = [
    { clave: 'todos', etiqueta: 'Total subpedidos', valor: resumenEstados.total, icono: ClipboardList },
    { clave: 'pagado', etiqueta: 'Por preparar (Pagados)', valor: resumenEstados.pagado, icono: CreditCard },
    { clave: 'en_preparacion', etiqueta: 'En preparación', valor: resumenEstados.en_preparacion, icono: Package },
    { clave: 'despachado', etiqueta: 'Despachados', valor: resumenEstados.despachado, icono: Truck },
    { clave: 'entregado', etiqueta: 'Entregados', valor: resumenEstados.entregado, icono: PackageCheck },
  ];

  return (
    <div className="min-h-screen bg-slate-50 text-slate-800 flex flex-col">
      {/* Encabezado superior idéntico al Portal de Comercios (M2) */}
      <header className="border-b border-slate-200 bg-white">
        <div className="mx-auto flex max-w-6xl items-center justify-between px-4 py-3">
          <a
            href={esComercio ? '/m2.html' : '/m1.html'}
            aria-label={esComercio ? 'Pacíficos Online, ir a Mi comercio' : 'Pacíficos Online, ir al inicio'}
            className="flex items-center gap-2 rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-[#1E3A8A] focus-visible:ring-offset-2"
          >
            <img src={logoPacificos} alt="" className="h-10 w-10 rounded-full" />
            <span className="leading-tight">
              <span className="block text-lg font-extrabold text-[#1E3A8A]">Pacíficos</span>
              <span className="block text-[11px] font-bold tracking-widest text-[#7E9C30]">ONLINE</span>
            </span>
          </a>

          {usuario ? (
            <div className="flex items-center gap-3 text-sm font-semibold text-[#1E3A8A]">
              <span className="hidden items-center gap-1 sm:flex">
                <User size={18} aria-hidden="true" /> {usuario.nombre}
              </span>
              <button
                type="button"
                onClick={salir}
                className="inline-flex items-center justify-center gap-2 rounded-lg bg-white px-4 py-2 text-sm font-bold text-slate-700 ring-1 ring-slate-300 transition-colors hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#1E3A8A] focus-visible:ring-offset-2 cursor-pointer"
              >
                <LogOut size={16} aria-hidden="true" /> Salir
              </button>
            </div>
          ) : usuario === null ? (
            <button
              type="button"
              onClick={() => { window.location.href = URL_INGRESO; }}
              className="inline-flex items-center justify-center gap-2 rounded-lg bg-[#1E3A8A] px-4 py-2 text-sm font-bold text-white transition-colors hover:bg-[#172E6E] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#1E3A8A] focus-visible:ring-offset-2 cursor-pointer"
            >
              <User size={16} aria-hidden="true" /> Ingresar
            </button>
          ) : null}
        </div>
      </header>

      {/* Barra de navegación de secciones de comercios (idéntica a M2) */}
      <nav className="bg-[#1E3A8A]" aria-label="Secciones de comercios">
        <div className="mx-auto flex max-w-6xl gap-1 overflow-x-auto px-2">
          {!esComercio && (
            <a href="/m1.html" className={claseTab(false)}>
              <Home size={18} aria-hidden="true" /> Portal
            </a>
          )}
          {esComercio && (
            <a href="/m2.html" className={claseTab(false)}>
              <Store size={18} aria-hidden="true" /> Mi comercio
            </a>
          )}
          {esPlataforma && (
            <a href="/m2.html" className={claseTab(false)}>
              <ShieldCheck size={18} aria-hidden="true" /> Plataforma
            </a>
          )}
          {esComercio && (
            <a href="/m3.html" className={claseTab(false)}>
              <LayoutGrid size={18} aria-hidden="true" /> Catálogo
            </a>
          )}
          {esComercio && (
            <a href="/m4.html" className={claseTab(false)}>
              <Boxes size={18} aria-hidden="true" /> Inventario
            </a>
          )}
          <span aria-current="page" className={claseTab(true)}>
            <ClipboardList size={18} aria-hidden="true" /> Pedidos
          </span>
          {esComercio && (
            <a href="/m7.html" className={claseTab(false)}>
              <Wallet size={18} aria-hidden="true" /> Finanzas
            </a>
          )}
          {(esComercio || esPlataforma) && (
            <a
              href="/app.html?pantalla=gestion"
              title="Productos destacados, promociones, cupones y noticias (M1)"
              className={claseTab(false)}
            >
              <Megaphone size={18} aria-hidden="true" /> Gestión del portal
            </a>
          )}
        </div>
      </nav>

      <main className="flex-1 max-w-6xl w-full mx-auto px-4 py-6 space-y-5">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div>
            <p className="mb-2 text-xs text-slate-500">
              <a href="/m2.html" className="hover:underline">Inicio</a> /{' '}
              <span className="font-semibold text-slate-700">Pedidos</span>
            </p>
            <h1 className="text-3xl font-extrabold tracking-tight text-[#1E3A8A]">
              {nombreComercioSesion || 'Gestión de pedidos'}
            </h1>
            <p className="mt-1 text-sm text-slate-600">
              Administrá la preparación, despacho y entrega de los subpedidos de tu comercio.
            </p>
          </div>

          <button
            type="button"
            onClick={cargar}
            disabled={cargando}
            className="inline-flex items-center gap-2 rounded-lg bg-[#9DBB45] hover:bg-[#8AA83A] px-4 py-2.5 text-sm font-bold text-white transition-colors cursor-pointer disabled:opacity-50"
          >
            <RefreshCw className={`w-4 h-4 ${cargando ? 'animate-spin' : ''}`} aria-hidden="true" />
            <span>Actualizar</span>
          </button>
        </div>

        {/* Indicadores rápidos de operación siguiendo el estándar visual de la aplicación */}
        <section className="grid grid-cols-2 gap-3 sm:grid-cols-5" aria-label="Resumen de estados">
          {tarjetasResumen.map((item, idx) => {
            const Icono = item.icono;
            const activa = estadoFiltro === item.clave;
            return (
              <button
                key={item.clave}
                type="button"
                onClick={() => setEstadoFiltro(item.clave)}
                aria-pressed={activa}
                className={`${idx === tarjetasResumen.length - 1 ? 'col-span-2 sm:col-span-1 ' : ''}flex flex-col justify-between rounded-2xl border bg-white p-4 text-left shadow-sm transition cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-[#1E3A8A] ${
                  activa
                    ? 'border-[#1E3A8A] ring-1 ring-[#1E3A8A]/20'
                    : 'border-slate-200 hover:border-slate-300'
                }`}
              >
                <div className="flex items-start justify-between gap-2">
                  <p className="text-xs font-semibold text-slate-500">{item.etiqueta}</p>
                  <span
                    className={`flex h-8 w-8 shrink-0 items-center justify-center rounded-xl ${
                      activa ? 'bg-[#1E3A8A] text-white' : 'bg-slate-100 text-[#1E3A8A]'
                    }`}
                  >
                    <Icono className="h-4 w-4" aria-hidden="true" />
                  </span>
                </div>
                <p className="mt-2 text-2xl font-extrabold text-[#1E3A8A]">{item.valor}</p>
              </button>
            );
          })}
        </section>

        {/* Controles de búsqueda, fechas, estado y comercio */}
        <div className="bg-white rounded-xl border border-gray-200 p-3.5 space-y-3 shadow-2xs">
          <div className="grid grid-cols-1 lg:grid-cols-12 gap-2.5 items-center">
            <div className="lg:col-span-5 relative">
              <Search className="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" aria-hidden="true" />
              <input
                type="search"
                value={busqueda}
                onChange={(e) => setBusqueda(e.target.value)}
                placeholder="Buscar por #subpedido, #pedido, producto, SKU, dirección, guía..."
                aria-label="Buscar subpedidos"
                className="w-full min-h-9 border border-gray-200 rounded-lg py-1.5 pl-9 pr-8 text-xs font-medium text-gray-800 placeholder:text-gray-400 focus:border-brandBlue focus:outline-none"
              />
              {busqueda && (
                <button
                  type="button"
                  onClick={() => setBusqueda('')}
                  aria-label="Limpiar búsqueda"
                  className="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 cursor-pointer"
                >
                  <X className="w-3.5 h-3.5" aria-hidden="true" />
                </button>
              )}
            </div>

            <div className="lg:col-span-5 flex flex-wrap sm:flex-nowrap items-center gap-2">
              <div className="flex items-center gap-1.5 flex-1 min-w-[130px]">
                <Calendar className="w-3.5 h-3.5 text-gray-400 shrink-0" aria-hidden="true" />
                <label htmlFor="filtro-fecha-desde" className="text-[11px] font-semibold text-gray-500 shrink-0">
                  Desde:
                </label>
                <input
                  id="filtro-fecha-desde"
                  type="date"
                  value={fechaDesde}
                  max={fechaHasta || undefined}
                  onChange={(e) => setFechaDesde(e.target.value)}
                  className="w-full min-h-9 border border-gray-200 rounded-lg py-1 px-2 text-xs font-medium text-gray-800 bg-white focus:border-brandBlue focus:outline-none"
                />
              </div>
              <div className="flex items-center gap-1.5 flex-1 min-w-[130px]">
                <label htmlFor="filtro-fecha-hasta" className="text-[11px] font-semibold text-gray-500 shrink-0">
                  Hasta:
                </label>
                <input
                  id="filtro-fecha-hasta"
                  type="date"
                  value={fechaHasta}
                  min={fechaDesde || undefined}
                  onChange={(e) => setFechaHasta(e.target.value)}
                  className="w-full min-h-9 border border-gray-200 rounded-lg py-1 px-2 text-xs font-medium text-gray-800 bg-white focus:border-brandBlue focus:outline-none"
                />
              </div>
            </div>

            <div className="lg:col-span-2">
              <select
                aria-label="Ordenar subpedidos"
                value={orden}
                onChange={(e) => setOrden(e.target.value)}
                className="w-full min-h-9 border border-gray-200 rounded-lg py-1.5 px-2.5 text-xs font-semibold text-gray-800 bg-white focus:border-brandBlue focus:outline-none"
              >
                <option value="recientes">Más recientes</option>
                <option value="antiguos">Más antiguos</option>
                <option value="mayor_monto">Mayor monto</option>
                <option value="menor_monto">Menor monto</option>
              </select>
            </div>
          </div>

          <div className="flex flex-wrap items-center justify-between gap-2.5 pt-2 border-t border-gray-100">
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

            <div className="flex flex-wrap items-center gap-2.5">
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

              <span className="text-xs font-semibold text-gray-500">
                {visibles.length} de {subpedidosPorComercio.length}
              </span>

              {hayFiltrosActivos && (
                <button
                  type="button"
                  onClick={limpiarFiltros}
                  className="inline-flex items-center gap-1 text-xs font-bold text-brandBlue hover:text-brandDarkBlue px-2 py-1 rounded-md hover:bg-blue-50 transition cursor-pointer"
                >
                  <X className="w-3.5 h-3.5" aria-hidden="true" />
                  Limpiar filtros
                </button>
              )}
            </div>
          </div>
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
              {!hayFiltrosActivos
                ? 'Aún no hay subpedidos registrados para este comercio'
                : 'No hay subpedidos que coincidan con los filtros'}
            </h2>
            <p className="text-xs sm:text-sm text-gray-500 max-w-md mx-auto">
              {!hayFiltrosActivos
                ? 'Cuando los clientes confirmen sus compras en el portal, sus subpedidos aparecerán aquí para prepararlos, empacarlos y despacharlos.'
                : 'Pruebe ajustando el texto de búsqueda, el rango de fechas o el estado seleccionado.'}
            </p>
            {hayFiltrosActivos && (
              <Boton variante="primario" onClick={limpiarFiltros}>
                Limpiar filtros
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
                onAbrirDespacho={(s) => { setErrorModal(null); setModalDespacho(s); }}
                onEntregar={handleEntregar}
                onAbrirCancelacion={(s) => { setErrorModal(null); setModalCancelacion(s); }}
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
        error={errorModal}
        onConfirmar={confirmarDespacho}
        onCerrar={() => { setErrorModal(null); setModalDespacho(null); }}
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
        error={errorModal}
        onConfirmar={confirmarCancelacion}
        onCerrar={() => { setErrorModal(null); setModalCancelacion(null); }}
      />
    </div>
  );
}
