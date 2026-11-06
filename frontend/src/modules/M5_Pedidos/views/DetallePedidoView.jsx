import { useCallback, useEffect, useRef, useState } from 'react';
import {
  ArrowLeft, Ban, Check, CircleX, CreditCard, History, LoaderCircle, MapPin, Package, RefreshCw, Store, Truck,
} from 'lucide-react';
import { Alerta, Boton, Encabezado, Total } from '../components/componentes';
import PasarelaPagoSimulada, { esPasarelaSimulada } from '../components/PasarelaPagoSimulada';
import { colones } from '../services/calculos';
import { etiquetaEstado, formatearFecha } from '../services/estados';
import { cancelarPedido, obtenerPedido } from '../services/m5Api';
import { EtiquetaEstado } from './MisPedidosView';

// Los montos llegan como texto decimal ("35400.00") por los casts del modelo.
const monto = (valor) => colones(Number(valor ?? 0));
const normalizar = (estado) => String(estado ?? '').toLowerCase();

// Línea de estados del subpedido. 'confirmado' (respaldo del servicio) equivale a pendiente.
const PASOS = ['pendiente', 'pagado', 'en_preparacion', 'despachado', 'entregado'];
const indicePaso = (estado) => {
  const e = normalizar(estado);
  return PASOS.indexOf(e === 'confirmado' ? 'pendiente' : e);
};

// RN-M5-05: no se cancela el pedido completo si algún subpedido ya avanzó a estos estados.
const ESTADOS_NO_CANCELABLES = ['en_preparacion', 'despachado', 'entregado'];

// Solo se puede pagar un pedido pendiente de pago ('confirmado' es el respaldo del servicio).
const ESTADOS_PAGABLES = ['pendiente', 'confirmado'];

function motivoNoCancelable(pedido) {
  if (normalizar(pedido.estado_pedido) === 'cancelado') return 'Este pedido ya fue cancelado.';
  const avanzado = (pedido.subpedidos ?? []).find((s) => ESTADOS_NO_CANCELABLES.includes(normalizar(s.estado_subpedido)));
  if (avanzado) {
    return `No se puede cancelar: el subpedido #${avanzado.id_subpedido} ya está ${etiquetaEstado(avanzado.estado_subpedido).toLowerCase()}.`;
  }
  return null;
}

function LineaEstados({ estado }) {
  const actual = indicePaso(estado);
  return (
    <ol className="grid grid-cols-5 gap-1" aria-label="Avance del subpedido">
      {PASOS.map((paso, i) => {
        const hecho = actual >= 0 && i < actual;
        const esActual = i === actual;
        return (
          <li key={paso} aria-current={esActual ? 'step' : undefined} className="flex flex-col items-center text-center gap-1 min-w-0">
            <span className="relative w-full flex justify-center">
              {i > 0 && (
                <span aria-hidden="true" className={`absolute top-1/2 right-1/2 w-full h-0.5 -translate-y-1/2 ${i <= actual ? 'bg-brandGreen' : 'bg-gray-200'}`} />
              )}
              <span className={`relative z-10 h-7 w-7 rounded-full flex items-center justify-center border-2 ${
                hecho ? 'bg-brandGreen border-brandGreen text-white'
                  : esActual ? 'bg-white border-brandDarkBlue text-brandDarkBlue ring-4 ring-blue-100'
                    : 'bg-white border-gray-200 text-gray-300'
              }`}>
                {hecho ? <Check className="w-3.5 h-3.5" aria-hidden="true" /> : <span className="text-xs font-bold">{i + 1}</span>}
              </span>
            </span>
            <span className={`text-[11px] leading-tight break-words ${esActual ? 'font-bold text-brandDarkBlue' : hecho ? 'text-gray-700' : 'text-gray-400'}`}>
              {etiquetaEstado(paso)}
              {esActual && <span className="sr-only"> (estado actual)</span>}
            </span>
          </li>
        );
      })}
    </ol>
  );
}

function TarjetaSubpedido({ subpedido, numero }) {
  const cancelado = normalizar(subpedido.estado_subpedido) === 'cancelado';
  const lineas = subpedido.lineas ?? [];
  return (
    <section className="bg-white rounded-xl shadow-xs border border-gray-200 p-4 sm:p-5 space-y-4">
      <div className="flex items-center justify-between gap-2 pb-3 border-b border-gray-100">
        <div className="flex items-center gap-2 min-w-0">
          <Store className="w-4 h-4 shrink-0 text-brandDarkBlue" aria-hidden="true" />
          <h3 className="text-sm sm:text-base font-bold text-gray-900 truncate">Comercio #{subpedido.id_comercio}</h3>
        </div>
        <span className="shrink-0 text-xs font-semibold text-gray-500">Subpedido {numero} · #{subpedido.id_subpedido}</span>
      </div>

      {cancelado ? (
        <p className="flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm font-semibold text-red-700">
          <CircleX className="w-4 h-4 shrink-0" aria-hidden="true" /> Subpedido cancelado
        </p>
      ) : (
        <LineaEstados estado={subpedido.estado_subpedido} />
      )}

      {subpedido.guia_seguimiento && (
        <p className="flex items-center gap-2 text-xs sm:text-sm text-gray-700">
          <Truck className="w-4 h-4 shrink-0 text-brandBlue" aria-hidden="true" />
          Guía de seguimiento: <span className="font-mono font-semibold break-all">{subpedido.guia_seguimiento}</span>
          {subpedido.fecha_despacho && <span className="text-gray-500">· {formatearFecha(subpedido.fecha_despacho)}</span>}
        </p>
      )}

      <ul className="divide-y divide-gray-100">
        {lineas.map((l) => {
          const precio = Number(l.precio_unitario ?? 0);
          return (
            <li key={l.id_linea} className="py-2.5 flex items-center gap-3 text-xs sm:text-sm">
              <div aria-hidden="true" className="h-10 w-10 shrink-0 rounded-lg bg-gray-50 border border-gray-100 flex items-center justify-center">
                <Package className="w-5 h-5 stroke-1 text-gray-400" />
              </div>
              <div className="min-w-0 flex-1">
                <p className="font-semibold text-gray-900">{l.nombre ?? `Variación #${l.id_variacion}`}</p>
                <p className="text-gray-500">{l.cantidad} × {colones(precio)}</p>
              </div>
              <p className="shrink-0 font-bold text-gray-900">{colones(precio * Number(l.cantidad ?? 0))}</p>
            </li>
          );
        })}
      </ul>

      <dl className="pt-3 border-t border-gray-100 space-y-1.5 text-xs sm:text-sm text-gray-600">
        <div className="flex justify-between"><dt>Subtotal</dt><dd className="font-medium">{monto(subpedido.subtotal)}</dd></div>
        <div className="flex justify-between"><dt>IVA</dt><dd className="font-medium">{monto(subpedido.impuesto)}</dd></div>
        <div className="flex justify-between"><dt>Envío</dt><dd className="font-medium">{monto(subpedido.costo_envio)}</dd></div>
        {Number(subpedido.descuento) > 0 && (
          <div className="flex justify-between"><dt>Descuento</dt><dd className="font-medium">−{monto(subpedido.descuento)}</dd></div>
        )}
        <div className="flex justify-between pt-1 text-gray-900">
          <dt className="font-bold">Total del subpedido</dt><dd className="font-extrabold">{monto(subpedido.total)}</dd>
        </div>
      </dl>
    </section>
  );
}

function ModalCancelar({ abierto, idPedido, ocupado, error, onConfirmar, onCerrar }) {
  const [motivo, setMotivo] = useState('');
  const [intentado, setIntentado] = useState(false);
  const campoRef = useRef(null);

  useEffect(() => {
    if (!abierto) return undefined;
    setMotivo('');
    setIntentado(false);
    campoRef.current?.focus();
    return undefined;
  }, [abierto]);

  useEffect(() => {
    if (!abierto) return undefined;
    const alTeclear = (e) => { if (e.key === 'Escape' && !ocupado) onCerrar(); };
    document.addEventListener('keydown', alTeclear);
    return () => document.removeEventListener('keydown', alTeclear);
  }, [abierto, ocupado, onCerrar]);

  if (!abierto) return null;
  const vacio = motivo.trim() === '';

  const enviar = (e) => {
    e.preventDefault();
    setIntentado(true);
    if (vacio || ocupado) { campoRef.current?.focus(); return; }
    onConfirmar(motivo.trim());
  };

  return (
    <div className="fixed inset-0 z-40 flex items-center justify-center p-4">
      <div className="absolute inset-0 bg-gray-900/50" aria-hidden="true" onClick={() => !ocupado && onCerrar()} />
      <form onSubmit={enviar} noValidate role="dialog" aria-modal="true" aria-labelledby="cancelar-titulo"
        className="relative bg-white rounded-2xl shadow-lg border border-gray-200 w-full max-w-md p-6 space-y-4">
        <div className="flex items-start gap-3">
          <div className="h-10 w-10 shrink-0 rounded-full bg-red-50 flex items-center justify-center">
            <Ban className="w-5 h-5 text-red-600" aria-hidden="true" />
          </div>
          <div className="min-w-0">
            <h2 id="cancelar-titulo" className="text-base font-bold text-gray-900">¿Cancelar el pedido #{idPedido}?</h2>
            <p className="mt-1 text-sm text-gray-600">Se cancelarán todos sus subpedidos y se liberarán las reservas. Si ya estaba pagado, se solicitará el reembolso.</p>
          </div>
        </div>

        <div>
          <label htmlFor="motivo-cancelacion" className="text-xs font-medium text-gray-600 block mb-1">Motivo de la cancelación (obligatorio)</label>
          <textarea
            id="motivo-cancelacion"
            ref={campoRef}
            rows={3}
            value={motivo}
            onChange={(e) => setMotivo(e.target.value)}
            disabled={ocupado}
            aria-invalid={intentado && vacio}
            aria-describedby={intentado && vacio ? 'motivo-error' : undefined}
            className={`w-full border rounded-lg py-2 px-3 text-sm focus:outline-none ${intentado && vacio ? 'border-red-400 focus:border-red-600' : 'border-gray-200 focus:border-brandBlue'}`}
            placeholder="Ej.: Me equivoqué de dirección"
          />
          {intentado && vacio && <p id="motivo-error" role="alert" className="mt-1 text-xs text-red-600">Indique el motivo de la cancelación.</p>}
        </div>

        {error && <Alerta tipo="error" titulo="No se pudo cancelar el pedido">{error}</Alerta>}

        <div className="flex flex-col-reverse sm:flex-row sm:justify-end gap-2">
          <button type="button" disabled={ocupado} onClick={onCerrar}
            className="min-h-11 px-5 rounded-lg border border-gray-200 text-sm font-semibold text-gray-700 hover:bg-gray-50 transition disabled:opacity-50">
            Volver
          </button>
          <button type="submit" disabled={ocupado}
            className="min-h-11 px-5 rounded-lg bg-red-600 hover:bg-red-700 text-white text-sm font-bold transition inline-flex items-center justify-center gap-2 disabled:opacity-70">
            {ocupado ? <LoaderCircle className="w-4 h-4 animate-spin" aria-hidden="true" /> : <Ban className="w-4 h-4" aria-hidden="true" />}
            {ocupado ? 'Cancelando…' : 'Cancelar pedido'}
          </button>
        </div>
      </form>
    </div>
  );
}

// Detalle de un pedido: GET /api/m5/v1/pedidos/{id_pedido} y POST /pedidos/{id_pedido}/cancelar.
export default function DetallePedidoView({ idPedido, onVolver }) {
  const [pedido, setPedido] = useState(null);
  const [cargando, setCargando] = useState(true);
  const [error, setError] = useState(null);
  const [intento, setIntento] = useState(0);

  const [modalAbierto, setModalAbierto] = useState(false);
  const [cancelando, setCancelando] = useState(false);
  const [errorCancelar, setErrorCancelar] = useState(null);
  const [exito, setExito] = useState(null); // { titulo, texto }
  const cancelandoRef = useRef(false);
  const [pasarelaAbierta, setPasarelaAbierta] = useState(false);

  useEffect(() => {
    let vigente = true;
    setCargando(true);
    setError(null);
    obtenerPedido(idPedido)
      .then((p) => { if (vigente) setPedido(p); })
      .catch((e) => { if (vigente) setError(e); })
      .finally(() => { if (vigente) setCargando(false); });
    return () => { vigente = false; };
  }, [idPedido, intento]);

  const cerrarModal = useCallback(() => setModalAbierto(false), []);
  const cerrarPasarela = useCallback(() => setPasarelaAbierta(false), []);

  // Misma regla que en PedidoPendienteView: sin URL o con la del doble de M6 se usa la pasarela simulada.
  function abrirPago() {
    const url = pedido?.urlPasarelaPago ?? pedido?.url_pasarela_pago;
    setExito(null);
    if (esPasarelaSimulada(url)) {
      setPasarelaAbierta(true);
    } else {
      window.open(url, '_blank', 'noopener,noreferrer');
    }
  }

  function alPagar() {
    setPasarelaAbierta(false);
    setExito({ titulo: 'Pago confirmado', texto: `El pago del pedido #${idPedido} se registró correctamente.` });
    setIntento((n) => n + 1); // Recarga el detalle: pedido y subpedidos quedan en Pagado.
  }

  async function confirmarCancelacion(motivo) {
    if (cancelandoRef.current) return;
    cancelandoRef.current = true;
    setCancelando(true);
    setErrorCancelar(null);
    try {
      await cancelarPedido(idPedido, { motivo, requiere_reembolso: normalizar(pedido?.estado_pedido) === 'pagado' });
      setModalAbierto(false);
      setExito({ titulo: 'Pedido cancelado', texto: 'El pedido se canceló correctamente.' });
      setIntento((n) => n + 1); // Recarga el detalle desde la API.
    } catch (e) {
      // 409 (ERR-M5-003): se muestra el mensaje de la API, que indica qué subpedido lo impide.
      setErrorCancelar(`${e.mensaje || e.message}${e.codigo ? ` (${e.codigo})` : ''}`);
    } finally {
      cancelandoRef.current = false;
      setCancelando(false);
    }
  }

  const encabezado = (
    <Encabezado titulo={`Pedido #${idPedido}`} onVolver={onVolver} etiquetaVolver="Volver a mis pedidos" />
  );
  const botonVolver = <Boton variante="oscuro" icono={ArrowLeft} onClick={onVolver}>Volver a mis pedidos</Boton>;

  if (cargando && !pedido) {
    return (
      <>
        {encabezado}
        <main className="max-w-4xl mx-auto px-4 py-16 flex flex-col items-center gap-3 text-gray-500" aria-live="polite">
          <LoaderCircle className="w-8 h-8 animate-spin text-brandBlue" aria-hidden="true" />
          <p className="text-sm font-medium">Cargando el pedido…</p>
        </main>
      </>
    );
  }

  if (error) {
    return (
      <>
        {encabezado}
        <main className="max-w-xl mx-auto px-4 py-12 space-y-4">
          <Alerta tipo="error" titulo={error.status === 404 ? 'Pedido no encontrado' : 'No se pudo cargar el pedido'}>
            {error.mensaje || error.message}{error.codigo ? ` (${error.codigo})` : ''}
          </Alerta>
          <div className="flex flex-col sm:flex-row gap-3">
            {error.status !== 404 && (
              <Boton variante="oscuro" icono={RefreshCw} onClick={() => setIntento((n) => n + 1)}>Reintentar</Boton>
            )}
            {botonVolver}
          </div>
        </main>
      </>
    );
  }

  const subpedidos = pedido.subpedidos ?? [];
  const historial = [...(pedido.estados ?? pedido.historial_estados ?? [])]
    .sort((a, b) => new Date(a.fecha_estado ?? a.created_at ?? 0) - new Date(b.fecha_estado ?? b.created_at ?? 0));
  const bloqueo = motivoNoCancelable(pedido);
  const pagable = ESTADOS_PAGABLES.includes(normalizar(pedido.estado_pedido));

  return (
    <>
      {encabezado}
      <main className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
        {exito && <Alerta tipo="exito" titulo={exito.titulo}>{exito.texto}</Alerta>}

        {/* Encabezado del pedido y desglose */}
        <section className="bg-white rounded-xl shadow-xs border border-gray-200 p-5 sm:p-6 space-y-4">
          <div className="flex items-start justify-between gap-3 flex-wrap">
            <div>
              <h2 className="text-lg font-bold text-gray-900">Pedido #{pedido.id_pedido}</h2>
              <p className="text-xs text-gray-500">Creado el {formatearFecha(pedido.fecha_creacion)}</p>
            </div>
            <EtiquetaEstado estado={pedido.estado_pedido} />
          </div>

          {pedido.direccion_envio_texto && (
            <p className="flex items-start gap-2 text-xs sm:text-sm text-gray-700">
              <MapPin className="w-4 h-4 shrink-0 mt-0.5 text-brandBlue" aria-hidden="true" />
              <span><span className="font-semibold">Dirección de envío: </span><span className="break-words">{pedido.direccion_envio_texto}</span></span>
            </p>
          )}

          <dl className="space-y-2 text-sm pt-3 border-t border-gray-100">
            <div className="flex justify-between text-gray-600"><dt>Subtotal</dt><dd className="font-semibold text-gray-900">{monto(pedido.subtotal)}</dd></div>
            <div className="flex justify-between text-gray-600"><dt>IVA (13 %)</dt><dd className="font-semibold text-gray-900">{monto(pedido.impuesto)}</dd></div>
            <div className="flex justify-between text-gray-600"><dt>Envío</dt><dd className="font-semibold text-gray-900">{monto(pedido.costo_envio)}</dd></div>
            {Number(pedido.descuento) > 0 && (
              <div className="flex justify-between text-gray-600"><dt>Descuento</dt><dd className="font-semibold text-gray-900">−{monto(pedido.descuento)}</dd></div>
            )}
            <div className="pt-3 border-t border-gray-100"><Total etiqueta="Total" valor={Number(pedido.monto_total ?? 0)} /></div>
          </dl>

          <div className="pt-3 border-t border-gray-100 space-y-2">
            <div className="flex flex-col sm:flex-row gap-2">
              {pagable && (
                <Boton icono={CreditCard} disabled={cargando} onClick={abrirPago}>
                  Pagar pedido
                </Boton>
              )}
              <Boton variante="peligro" icono={Ban} disabled={Boolean(bloqueo) || cargando}
                aria-describedby={bloqueo ? 'cancelar-bloqueo' : undefined}
                onClick={() => { setErrorCancelar(null); setExito(null); setModalAbierto(true); }}>
                Cancelar pedido
              </Boton>
            </div>
            {bloqueo && <p id="cancelar-bloqueo" className="text-xs text-gray-600">{bloqueo}</p>}
          </div>
        </section>

        {/* Subpedidos */}
        <div className="space-y-4">
          <h2 className="text-sm font-bold text-gray-900 uppercase tracking-wider">Subpedidos por comercio</h2>
          {subpedidos.map((s, n) => <TarjetaSubpedido key={s.id_subpedido} subpedido={s} numero={n + 1} />)}
        </div>

        {/* Historial de estados */}
        {historial.length > 0 && (
          <section className="bg-white rounded-xl shadow-xs border border-gray-200 p-5 sm:p-6">
            <h2 className="text-sm font-bold text-gray-900 uppercase tracking-wider flex items-center gap-2 mb-3">
              <History className="w-4 h-4 text-brandBlue" aria-hidden="true" /> Historial de estados
            </h2>
            <ol className="space-y-3">
              {historial.map((h, i) => (
                <li key={h.id_estado_pedido ?? i} className="flex gap-3 text-xs sm:text-sm">
                  <span aria-hidden="true" className="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-brandBlue" />
                  <div className="min-w-0">
                    <p className="font-semibold text-gray-900">
                      {h.estado_anterior ? `${etiquetaEstado(h.estado_anterior)} → ` : ''}{etiquetaEstado(h.estado)}
                      {h.id_subpedido ? <span className="font-normal text-gray-500"> · Subpedido #{h.id_subpedido}</span> : <span className="font-normal text-gray-500"> · Pedido</span>}
                    </p>
                    <p className="text-gray-500">{formatearFecha(h.fecha_estado ?? h.created_at)}</p>
                    {h.motivo && <p className="text-gray-700 break-words">{h.motivo}</p>}
                  </div>
                </li>
              ))}
            </ol>
          </section>
        )}

        {botonVolver}
      </main>

      <ModalCancelar
        abierto={modalAbierto}
        idPedido={pedido.id_pedido}
        ocupado={cancelando}
        error={errorCancelar}
        onConfirmar={confirmarCancelacion}
        onCerrar={cerrarModal}
      />

      <PasarelaPagoSimulada
        abierto={pasarelaAbierta}
        pedido={pedido}
        onCerrar={cerrarPasarela}
        onPagado={alPagar}
      />
    </>
  );
}
