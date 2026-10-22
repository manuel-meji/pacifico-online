import {
  ArrowLeft, Check, CircleAlert, CircleCheck, CircleX, Info, Lock, Minus, Package, Plus, Store, Trash2, TriangleAlert,
} from 'lucide-react';
import { colones } from './calculos';

// ---------- Encabezado con isotipo "P" (sistema de diseño) ----------
export function Encabezado({ titulo, paso, onVolver }) {
  return (
    <header className="sticky top-0 z-10 bg-white shadow-xs border-b border-gray-100 px-4 py-3 flex items-center gap-3">
      {onVolver && (
        <button onClick={onVolver} aria-label="Volver al carrito"
          className="h-11 w-11 -ml-2 shrink-0 rounded-full flex items-center justify-center text-brandDarkBlue hover:bg-gray-100 transition">
          <ArrowLeft className="w-5 h-5" aria-hidden="true" />
        </button>
      )}
      <div aria-hidden="true" className="w-9 h-9 shrink-0 rounded-full bg-brandGreen flex items-center justify-center font-extrabold text-white text-xl shadow-xs">P</div>
      <h1 className="min-w-0 flex-1 truncate text-lg font-extrabold text-brandDarkBlue tracking-tight">{titulo}</h1>
      {paso && <span className="shrink-0 text-xs font-medium text-gray-500">{paso}</span>}
    </header>
  );
}

// ---------- Alertas: información, éxito, advertencia y error ----------
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

// ---------- Línea de ítem editable (carrito) ----------
export function FilaItem({ item, deshabilitado, onCantidad, onQuitar }) {
  const enMaximo = item.cantidad >= item.stock;
  return (
    <li className={`py-3 border-b border-gray-100 last:border-0 ${deshabilitado ? 'opacity-50' : ''}`}>
      <div className="flex gap-3">
        <div aria-hidden="true" className="h-16 w-16 shrink-0 rounded-lg bg-gray-50 border border-gray-100 flex items-center justify-center">
          <Package className="w-8 h-8 stroke-1 text-gray-400" />
        </div>
        <div className="min-w-0 flex-1">
          <p className="text-sm font-bold text-gray-900 break-words">{item.nombre}</p>
          <p className="text-xs text-gray-500 font-normal break-words">{item.variacion}</p>
          <p className="text-xs text-gray-600 font-medium mt-0.5">{colones(item.precio_unitario)} c/u</p>
          {!deshabilitado && !enMaximo && (
            <span className="inline-flex items-center gap-1 text-xs text-green-700 font-medium mt-1">
              <Check className="w-3 h-3" aria-hidden="true" /> En Stock ({item.stock} disp.)
            </span>
          )}
        </div>
        <p className="shrink-0 text-sm font-extrabold text-brandDarkBlue">{colones(item.precio_unitario * item.cantidad)}</p>
      </div>
      <div className="mt-2 flex items-center justify-between gap-2">
        <SelectorCantidad nombre={item.nombre} valor={item.cantidad} deshabilitado={deshabilitado}
          onMenos={() => onCantidad(item.id_item_carrito, item.cantidad - 1)} onMas={() => onCantidad(item.id_item_carrito, item.cantidad + 1)} />
        <Boton variante="peligro" icono={Trash2} onClick={() => onQuitar(item.id_item_carrito)}>Quitar</Boton>
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

// ---------- Línea compacta de ítem (resumen del checkout) ----------
export function FilaResumen({ item }) {
  return (
    <li className="flex items-center justify-between gap-2 text-xs">
      <div className="flex items-center gap-2 min-w-0">
        <span className="min-w-5 h-5 px-1 bg-gray-100 rounded flex items-center justify-center font-bold text-gray-700">{item.cantidad}x</span>
        <span className="text-gray-800 font-medium break-words">{item.nombre}</span>
      </div>
      <span className="shrink-0 font-bold text-gray-900">{colones(item.precio_unitario * item.cantidad)}</span>
    </li>
  );
}

// ---------- Tarjeta de subpedido (un comercio) ----------
export function TarjetaComercio({ grupo, numero, editable, suspendido, onCantidad, onQuitar }) {
  return (
    <section className="bg-white rounded-xl shadow-xs border border-gray-200 p-5">
      <div className="flex items-center justify-between gap-2 pb-3 border-b border-gray-100">
        <div className="flex items-center gap-2 min-w-0">
          <Store className="w-4 h-4 shrink-0 text-brandDarkBlue" aria-hidden="true" />
          <h2 className="text-sm font-bold text-gray-900 truncate">{grupo.comercio.nombre}</h2>
        </div>
        <span className="shrink-0 text-xs font-semibold text-brandGreen bg-green-50 px-2 py-0.5 rounded border border-green-200">Subpedido {numero}</span>
      </div>
      {suspendido && (
        <span className="mt-3 inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-red-50 text-red-700 border border-red-200">
          <CircleAlert className="w-3.5 h-3.5 text-red-600" aria-hidden="true" /> Membresía suspendida: productos deshabilitados
        </span>
      )}
      {editable ? (
        <ul>{grupo.items.map((i) => <FilaItem key={i.id_item_carrito} item={i} deshabilitado={suspendido} onCantidad={onCantidad} onQuitar={onQuitar} />)}</ul>
      ) : (
        <ul className="py-3 space-y-3">{grupo.items.map((i) => <FilaResumen key={i.id_item_carrito} item={i} />)}</ul>
      )}
      <dl className="pt-3 border-t border-gray-100 space-y-2">
        <div className="flex justify-between text-sm">
          <dt className="font-bold text-gray-800">Subtotal Subpedido:</dt>
          <dd className="font-extrabold text-brandDarkBlue text-base">{colones(grupo.subtotal)}</dd>
        </div>
        <div className="flex justify-between text-xs text-gray-500"><dt>Impuestos:</dt><dd className="font-medium">{colones(grupo.impuesto)}</dd></div>
        <div className="flex justify-between text-xs text-gray-500"><dt>Costo de Envío:</dt><dd className="font-medium">{colones(grupo.costo_envio)}</dd></div>
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
