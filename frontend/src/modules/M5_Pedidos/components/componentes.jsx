import { createContext, useContext, useEffect, useRef, useState } from 'react';
import {
  ArrowLeft, Check, CircleAlert, ClipboardList, CircleCheck, CircleX, Heart, Info, LoaderCircle, Lock, Minus, Package, Plus, Store, Trash2, TriangleAlert,
} from 'lucide-react';
import { colones } from '../services/calculos';
import logoImg from '../../../assets/Logo.jpeg';

// Navegación compartida de M5 (la provee M5App): { abrirMisPedidos, enMisPedidos, abrirPanelComercio, enPanelComercio, abrirDeseos, enDeseos }.
// Permite que cualquier vista muestre los accesos del encabezado sin pasar props.
export const NavegacionM5 = createContext(null);

export function Encabezado({ titulo, paso, onVolver, etiquetaVolver = 'Volver al carrito' }) {
  const nav = useContext(NavegacionM5);
  const mostrarMisPedidos = nav?.abrirMisPedidos && !nav.enMisPedidos;
  const mostrarDeseos = nav?.abrirDeseos && !nav.enDeseos;
  const mostrarPanel = Boolean(
    nav?.abrirPanelComercio &&
    !nav.enPanelComercio &&
    typeof window !== 'undefined' &&
    new URLSearchParams(window.location.search).has('demo')
  );
  return (
    <header className="sticky top-0 z-20 bg-white shadow-xs border-b border-gray-100">
      <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-3 flex items-center justify-between gap-3">
        <div className="flex items-center gap-3 min-w-0">
          {onVolver && (
            <button onClick={onVolver} aria-label={etiquetaVolver}
              className="h-10 w-10 -ml-2 shrink-0 rounded-full flex items-center justify-center text-brandDarkBlue hover:bg-gray-100 transition cursor-pointer">
              <ArrowLeft className="w-5 h-5" aria-hidden="true" />
            </button>
          )}
          <a href="/app.html" aria-label="Ir al portal de Pacíficos Online" className="flex items-center gap-2.5 shrink-0 hover:opacity-90 transition">
            <img src={logoImg} alt="Pacíficos Online" className="w-9 h-9 sm:w-10 sm:h-10 rounded-full object-cover shadow-xs border border-blue-100" />
            <span className="hidden sm:inline-block font-extrabold text-brandDarkBlue tracking-tight text-lg">
              Pacíficos <span className="text-brandBlue font-semibold">Online</span>
            </span>
          </a>
          <div className="h-5 w-px bg-gray-200 hidden sm:block mx-1" aria-hidden="true"></div>
          <h1 className="min-w-0 truncate text-base sm:text-lg font-bold text-gray-800 tracking-tight">{titulo}</h1>
        </div>
        <div className="flex items-center gap-2 shrink-0">
          {paso && (
            <span className="shrink-0 text-xs sm:text-sm font-semibold text-gray-500 bg-gray-100 px-3 py-1 rounded-full border border-gray-200">
              {paso}
            </span>
          )}
          {mostrarDeseos && (
            <button type="button" onClick={nav.abrirDeseos} aria-label="Lista de deseos"
              className="min-h-11 min-w-11 inline-flex items-center justify-center gap-1.5 rounded-lg px-2 sm:px-3 text-sm font-semibold text-brandDarkBlue hover:bg-gray-100 transition cursor-pointer">
              <Heart className="w-5 h-5 text-rose-500" aria-hidden="true" />
              <span className="hidden sm:inline">Deseos</span>
            </button>
          )}
          {mostrarMisPedidos && (
            <button type="button" onClick={nav.abrirMisPedidos} aria-label="Mis pedidos"
              className="min-h-11 min-w-11 inline-flex items-center justify-center gap-1.5 rounded-lg px-2 sm:px-3 text-sm font-semibold text-brandDarkBlue hover:bg-gray-100 transition cursor-pointer">
              <ClipboardList className="w-5 h-5" aria-hidden="true" />
              <span className="hidden sm:inline">Mis pedidos</span>
            </button>
          )}
          {mostrarPanel && (
            <button type="button" onClick={nav.abrirPanelComercio} aria-label="Panel del comercio (demo)"
              className="min-h-11 min-w-11 inline-flex items-center justify-center gap-1.5 rounded-lg px-2 sm:px-3 text-sm font-semibold text-brandDarkBlue hover:bg-gray-100 transition cursor-pointer">
              <Store className="w-5 h-5" aria-hidden="true" />
              <span className="hidden md:inline">Panel del comercio (demo)</span>
            </button>
          )}
        </div>
      </div>
    </header>
  );
}

// ---------- Alertas: informacion, exito, advertencia y error ----------
const ALERTAS = {
  info: { caja: 'bg-blue-50 border-blue-200 text-blue-900', Icono: Info, icono: 'text-brandBlue', titulo: 'text-brandDarkBlue', texto: 'text-blue-800' },
  exito: { caja: 'bg-green-50 border-green-200 text-green-900', Icono: CircleCheck, icono: 'text-green-600', titulo: 'text-green-800', texto: 'text-green-700' },
  aviso: { caja: 'bg-amber-50 border-amber-200 text-amber-900', Icono: TriangleAlert, icono: 'text-amber-600', titulo: 'text-amber-800', texto: 'text-amber-700' },
  error: { caja: 'bg-red-50 border-red-200 text-red-900', Icono: CircleX, icono: 'text-red-600', titulo: 'text-red-800', texto: 'text-red-700' },
};
export function Alerta({ tipo = 'error', titulo, children }) {
  const a = ALERTAS[tipo];
  return (
    <div role="alert" className={`border rounded-xl p-4 flex items-start gap-3 ${a.caja}`}>
      <a.Icono className={`w-5 h-5 shrink-0 mt-0.5 ${a.icono}`} aria-hidden="true" />
      <div className="min-w-0">
        {titulo && <h4 className={`text-sm font-bold ${a.titulo}`}>{titulo}</h4>}
        <p className={`text-xs mt-0.5 break-words ${a.texto}`}>{children}</p>
      </div>
    </div>
  );
}

// ---------- Botones del sistema ----------
const BASE = 'inline-flex items-center justify-center gap-2 rounded-lg transition min-h-11 py-2.5 px-5';
const VARIANTES = {
  primario: 'bg-brandDarkBlue hover:bg-brandBlue text-white font-bold shadow-xs',
  peligro: 'border border-red-200 text-red-600 hover:bg-red-50 font-semibold px-4',
  oscuro: 'bg-gray-800 hover:bg-black text-white text-xs font-semibold px-3',
};
const DESHABILITADO = 'bg-gray-200 text-gray-400 font-semibold cursor-not-allowed';
export function Boton({ variante = 'primario', icono: Icono, className = '', children, disabled, ...props }) {
  const estilo = disabled ? DESHABILITADO : VARIANTES[variante];
  return (
    <button disabled={disabled} className={`${BASE} ${estilo} ${className}`} {...props}>
      {Icono && <Icono className="w-4 h-4" aria-hidden="true" />}
      <span>{children}</span>
    </button>
  );
}

// ---------- Selector de cantidad ----------
export function SelectorCantidad({ nombre, valor, min = 1, deshabilitado, onMenos, onMas }) {
  const btn = 'h-11 w-11 flex items-center justify-center text-gray-600 hover:bg-gray-100 transition disabled:text-gray-300 disabled:hover:bg-transparent';
  return (
    <div className="inline-flex items-center border border-gray-200 rounded-lg overflow-hidden">
      <button aria-label={`Disminuir cantidad de ${nombre}`} disabled={deshabilitado || valor <= min} onClick={onMenos} className={btn}>
        <Minus className="w-3.5 h-3.5" aria-hidden="true" />
      </button>
      <input type="text" value={valor} readOnly aria-label={`Cantidad de ${nombre}`}
        className="w-12 text-center text-sm font-bold text-gray-800 border-x border-gray-200 py-1.5 focus:outline-none bg-white" />
      <button aria-label={`Aumentar cantidad de ${nombre}`} disabled={deshabilitado} onClick={onMas} className={btn}>
        <Plus className="w-3.5 h-3.5" aria-hidden="true" />
      </button>
    </div>
  );
}

// ---------- Miniatura del producto (ícono si la API no trae imagen) ----------
export function Miniatura({ imagen, nombre }) {
  // Guarda la URL que falló: si `imagen` cambia, deja de coincidir y se reintenta la carga.
  const [urlFallida, setUrlFallida] = useState(null);
  const mostrarImagen = Boolean(imagen) && urlFallida !== imagen;
  return (
    <div className="h-16 w-16 sm:h-20 sm:w-20 shrink-0 rounded-lg bg-gray-50 border border-gray-100 flex items-center justify-center overflow-hidden">
      {mostrarImagen
        ? <img src={imagen} alt={nombre} className="h-full w-full object-cover" onError={() => setUrlFallida(imagen)} />
        : <Package className="w-8 h-8 sm:w-9 sm:h-9 stroke-1 text-gray-400" aria-hidden="true" />}
    </div>
  );
}

// ---------- Linea de item editable (carrito) ----------
// Los controles de cantidad y "Quitar" quedan deshabilitados si no se pasan onCantidad / onQuitar.
// `pendientes` (Set) marca las peticiones en curso: 'cantidad-<id>' y 'quitar-<id>'.
// Un comercio suspendido (`deshabilitado`) bloquea la cantidad, pero deja "Quitar" activo para poder resolverlo.
export function FilaItem({ item, deshabilitado, pendientes, onCantidad, onQuitar }) {
  const cambiando = pendientes?.has(`cantidad-${item.id_item_carrito}`) ?? false;
  const quitando = pendientes?.has(`quitar-${item.id_item_carrito}`) ?? false;
  const conStock = item.stock != null;
  const enMaximo = conStock && item.cantidad >= item.stock;
  const subtotal = item.subtotal ?? item.precio_unitario * item.cantidad;
  const detalle = item.variacion ?? (item.sku ? `SKU: ${item.sku}` : null);
  return (
    <li className={`py-3.5 border-b border-gray-100 last:border-0 ${deshabilitado ? 'opacity-50' : ''}`}>
      <div className="flex items-start sm:items-center gap-3">
        <Miniatura imagen={item.imagen} nombre={item.nombre} />
        <div className="min-w-0 flex-1">
          <p className="text-sm sm:text-base font-bold text-gray-900 break-words">{item.nombre}</p>
          {detalle && <p className="text-xs text-gray-500 font-normal break-words">{detalle}</p>}
          <p className="text-xs text-gray-600 font-medium mt-0.5">{colones(item.precio_unitario)} c/u · Cantidad: {item.cantidad}</p>
          {conStock && !deshabilitado && !enMaximo && (
            <span className="inline-flex items-center gap-1 text-xs text-green-700 font-medium mt-1">
              <Check className="w-3 h-3" aria-hidden="true" /> En Stock ({item.stock} disp.)
            </span>
          )}
        </div>
        <p className="shrink-0 text-sm sm:text-base font-extrabold text-brandDarkBlue">{colones(subtotal)}</p>
      </div>
      <div className="mt-2.5 flex items-center justify-between sm:justify-end gap-3">
        <SelectorCantidad nombre={item.nombre} valor={item.cantidad} deshabilitado={deshabilitado || !onCantidad || cambiando || quitando}
          onMenos={() => onCantidad?.(item.id_item_carrito, item.cantidad - 1)} onMas={() => onCantidad?.(item.id_item_carrito, item.cantidad + 1)} />
        <Boton variante="peligro" icono={quitando ? LoaderCircle : Trash2} disabled={!onQuitar || quitando || cambiando}
          aria-label={`Quitar ${item.nombre} del carrito`} onClick={() => onQuitar?.(item.id_item_carrito)}>
          {quitando ? 'Quitando…' : 'Quitar'}
        </Boton>
      </div>
      {item.aviso && (
        <p className="mt-2 inline-flex items-center gap-1.5 text-xs font-semibold text-red-700"><CircleAlert className="w-3.5 h-3.5" aria-hidden="true" />{item.aviso}</p>
      )}
      {!item.aviso && enMaximo && !deshabilitado && (
        <p className="mt-2 inline-flex items-center gap-1.5 text-xs font-semibold text-amber-700"><TriangleAlert className="w-3.5 h-3.5" aria-hidden="true" />Cantidad máxima disponible: {item.stock}.</p>
      )}
    </li>
  );
}

// ---------- Linea compacta de ítem (resumen del checkout) ----------
export function FilaResumen({ item }) {
  return (
    <li className="flex items-center justify-between gap-2 text-xs sm:text-sm">
      <div className="flex items-center gap-2 min-w-0">
        <span className="min-w-5 h-5 px-1 bg-gray-100 rounded flex items-center justify-center font-bold text-gray-700 text-xs">{item.cantidad}x</span>
        <span className="text-gray-800 font-medium break-words">{item.nombre}</span>
      </div>
      <span className="shrink-0 font-bold text-gray-900">{colones(item.subtotal ?? item.precio_unitario * item.cantidad)}</span>
    </li>
  );
}

// ---------- Tarjeta de subpedido (un comercio) ----------
export function TarjetaComercio({ grupo, numero, editable, suspendido, pendientes, onCantidad, onQuitar }) {
  return (
    <section className="bg-white rounded-xl shadow-xs border border-gray-200 p-4 sm:p-5">
      <div className="flex items-center justify-between gap-2 pb-3 border-b border-gray-100">
        <div className="flex items-center gap-2 min-w-0">
          <Store className="w-4 h-4 shrink-0 text-brandDarkBlue" aria-hidden="true" />
          <h2 className="text-sm sm:text-base font-bold text-gray-900 truncate">{grupo.comercio?.nombre ?? grupo.nombre_comercio}</h2>
        </div>
        <span className="shrink-0 text-xs font-semibold text-brandGreen bg-green-50 px-2.5 py-0.5 rounded border border-green-200">Subpedido {numero}</span>
      </div>
      {suspendido && (
        <span className="mt-3 inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-red-50 text-red-700 border border-red-200">
          <CircleAlert className="w-3.5 h-3.5 text-red-600" aria-hidden="true" /> Membresía suspendida: productos deshabilitados
        </span>
      )}
      {editable ? (
        <ul>{grupo.items.map((i) => <FilaItem key={i.id_item_carrito} item={i} deshabilitado={suspendido} pendientes={pendientes} onCantidad={onCantidad} onQuitar={onQuitar} />)}</ul>
      ) : (
        <ul className="py-3 space-y-3">{grupo.items.map((i) => <FilaResumen key={i.id_item_carrito} item={i} />)}</ul>
      )}
      <dl className="pt-3 border-t border-gray-100 space-y-2">
        <div className="flex justify-between text-sm sm:text-base">
          <dt className="font-bold text-gray-800">Subtotal Subpedido:</dt>
          <dd className="font-extrabold text-brandDarkBlue text-base sm:text-lg">{colones(grupo.subtotal)}</dd>
        </div>
        <div className="flex justify-between text-xs sm:text-sm text-gray-500"><dt>Impuestos:</dt><dd className="font-medium">{colones(grupo.impuesto)}</dd></div>
        <div className="flex justify-between text-xs sm:text-sm text-gray-500"><dt>Costo de Envío:</dt><dd className="font-medium">{colones(grupo.costo_envio)}</dd></div>
      </dl>
    </section>
  );
}

// ---------- Total (barra inferior) ----------
export function Total({ etiqueta, valor }) {
  return (
    <dl className="flex items-center justify-between gap-2">
      <dt className="text-sm font-bold text-gray-800">{etiqueta}</dt>
      <dd className="text-xl font-extrabold text-brandDarkBlue">{colones(valor)}</dd>
    </dl>
  );
}

// ---------- Modal de confirmación (reemplaza window.confirm) ----------
export function ModalConfirmacion({ abierto, titulo, children, textoConfirmar = 'Confirmar', ocupado, onConfirmar, onCancelar }) {
  const cancelarRef = useRef(null);

  useEffect(() => {
    if (!abierto) return undefined;
    cancelarRef.current?.focus();
    const alTeclear = (e) => { if (e.key === 'Escape' && !ocupado) onCancelar(); };
    document.addEventListener('keydown', alTeclear);
    return () => document.removeEventListener('keydown', alTeclear);
  }, [abierto, ocupado, onCancelar]);

  if (!abierto) return null;
  return (
    <div className="fixed inset-0 z-40 flex items-center justify-center p-4">
      <div className="absolute inset-0 bg-gray-900/50" aria-hidden="true" onClick={() => !ocupado && onCancelar()} />
      <div role="alertdialog" aria-modal="true" aria-labelledby="modal-titulo" aria-describedby="modal-texto"
        className="relative bg-white rounded-2xl shadow-lg border border-gray-200 w-full max-w-sm p-6 space-y-4">
        <div className="flex items-start gap-3">
          <div className="h-10 w-10 shrink-0 rounded-full bg-red-50 flex items-center justify-center">
            <TriangleAlert className="w-5 h-5 text-red-600" aria-hidden="true" />
          </div>
          <div className="min-w-0">
            <h2 id="modal-titulo" className="text-base font-bold text-gray-900">{titulo}</h2>
            <p id="modal-texto" className="mt-1 text-sm text-gray-600">{children}</p>
          </div>
        </div>
        <div className="flex flex-col-reverse sm:flex-row sm:justify-end gap-2">
          <button ref={cancelarRef} type="button" disabled={ocupado} onClick={onCancelar}
            className="min-h-11 px-5 rounded-lg border border-gray-200 text-sm font-semibold text-gray-700 hover:bg-gray-50 transition disabled:opacity-50">
            Cancelar
          </button>
          <button type="button" disabled={ocupado} onClick={onConfirmar}
            className="min-h-11 px-5 rounded-lg bg-red-600 hover:bg-red-700 text-white text-sm font-bold transition inline-flex items-center justify-center gap-2 disabled:opacity-70">
            {ocupado ? <LoaderCircle className="w-4 h-4 animate-spin" aria-hidden="true" /> : <Trash2 className="w-4 h-4" aria-hidden="true" />}
            {ocupado ? 'Procesando…' : textoConfirmar}
          </button>
        </div>
      </div>
    </div>
  );
}

// ---------- Modal con un campo de texto (guía de seguimiento, motivo, etc.) ----------
// onConfirmar(valor) recibe el texto sin espacios extremos. Si `obligatorio`, no se envía vacío.
export function ModalConCampo({
  abierto, titulo, descripcion, etiquetaCampo, placeholder, obligatorio = false, multilinea = false,
  textoConfirmar = 'Confirmar', textoOcupado = 'Procesando…', peligro = false, icono: Icono = Check, ocupado, onConfirmar, onCerrar,
}) {
  const [valor, setValor] = useState('');
  const [intentado, setIntentado] = useState(false);
  const campoRef = useRef(null);

  useEffect(() => {
    if (!abierto) return;
    setValor('');
    setIntentado(false);
    campoRef.current?.focus();
  }, [abierto]);

  useEffect(() => {
    if (!abierto) return undefined;
    const alTeclear = (e) => { if (e.key === 'Escape' && !ocupado) onCerrar(); };
    document.addEventListener('keydown', alTeclear);
    return () => document.removeEventListener('keydown', alTeclear);
  }, [abierto, ocupado, onCerrar]);

  if (!abierto) return null;
  const invalido = obligatorio && valor.trim() === '';
  const mostrarError = intentado && invalido;

  const enviar = (e) => {
    e.preventDefault();
    setIntentado(true);
    if (invalido || ocupado) { campoRef.current?.focus(); return; }
    onConfirmar(valor.trim());
  };

  const Control = multilinea ? 'textarea' : 'input';
  return (
    <div className="fixed inset-0 z-40 flex items-center justify-center p-4">
      <div className="absolute inset-0 bg-gray-900/50" aria-hidden="true" onClick={() => !ocupado && onCerrar()} />
      <form onSubmit={enviar} noValidate role="dialog" aria-modal="true" aria-labelledby="modal-campo-titulo"
        className="relative bg-white rounded-2xl shadow-lg border border-gray-200 w-full max-w-md p-6 space-y-4">
        <div>
          <h2 id="modal-campo-titulo" className="text-base font-bold text-gray-900">{titulo}</h2>
          {descripcion && <p className="mt-1 text-sm text-gray-600">{descripcion}</p>}
        </div>
        <div>
          <label htmlFor="modal-campo" className="text-xs font-medium text-gray-600 block mb-1">
            {etiquetaCampo} {obligatorio ? '(obligatorio)' : '(opcional)'}
          </label>
          <Control
            id="modal-campo"
            ref={campoRef}
            rows={multilinea ? 3 : undefined}
            value={valor}
            onChange={(e) => setValor(e.target.value)}
            disabled={ocupado}
            placeholder={placeholder}
            aria-invalid={mostrarError}
            aria-describedby={mostrarError ? 'modal-campo-error' : undefined}
            className={`w-full min-h-11 border rounded-lg py-2 px-3 text-sm focus:outline-none ${mostrarError ? 'border-red-400 focus:border-red-600' : 'border-gray-200 focus:border-brandBlue'}`}
          />
          {mostrarError && <p id="modal-campo-error" role="alert" className="mt-1 text-xs text-red-600">Este campo es obligatorio.</p>}
        </div>
        <div className="flex flex-col-reverse sm:flex-row sm:justify-end gap-2">
          <button type="button" disabled={ocupado} onClick={onCerrar}
            className="min-h-11 px-5 rounded-lg border border-gray-200 text-sm font-semibold text-gray-700 hover:bg-gray-50 transition disabled:opacity-50">
            Volver
          </button>
          <button type="submit" disabled={ocupado}
            className={`min-h-11 px-5 rounded-lg text-white text-sm font-bold transition inline-flex items-center justify-center gap-2 disabled:opacity-70 ${peligro ? 'bg-red-600 hover:bg-red-700' : 'bg-brandDarkBlue hover:bg-brandBlue'}`}>
            {ocupado ? <LoaderCircle className="w-4 h-4 animate-spin" aria-hidden="true" /> : <Icono className="w-4 h-4" aria-hidden="true" />}
            {ocupado ? textoOcupado : textoConfirmar}
          </button>
        </div>
      </form>
    </div>
  );
}

// ---------- Controles de formulario ----------
export function Campo({ id, etiqueta, error, children }) {
  return (
    <div>
      <label htmlFor={id} className="text-xs font-medium text-gray-600 block mb-1">{etiqueta}</label>
      {children}
      {error && <p role="alert" className="mt-1 text-xs text-red-600">{error}</p>}
    </div>
  );
}
export const clasesInput = (error, conIcono = false) =>
  `w-full min-h-11 border rounded-lg py-2 ${conIcono ? 'pl-9' : 'pl-3'} pr-3 text-xs focus:outline-none ${error ? 'border-red-400 focus:border-red-600' : 'border-gray-200 focus:border-brandBlue'}`;

export { Lock };