import { colones } from './calculos';

export function Encabezado({ titulo, paso, onVolver }) {
  return (
    <header className="sticky top-0 z-10 bg-white border-b border-slate-200 px-4 py-3 flex items-center gap-3">
      {onVolver && (
        <button onClick={onVolver} aria-label="Volver al carrito" className="h-11 w-11 -ml-2 shrink-0 rounded-full text-xl text-slate-700 hover:bg-slate-100">←</button>
      )}
      <h1 className="min-w-0 flex-1 truncate text-lg font-semibold text-slate-900">{titulo}</h1>
      {paso && <span className="shrink-0 text-xs font-medium text-slate-500">{paso}</span>}
    </header>
  );
}

export function Alerta({ tipo = 'error', children }) {
  const estilos = tipo === 'error' ? 'bg-red-50 border-red-300 text-red-800' : tipo === 'aviso' ? 'bg-amber-50 border-amber-300 text-amber-900' : 'bg-emerald-50 border-emerald-300 text-emerald-800';
  return <div role="alert" className={`rounded-lg border px-3 py-2 text-sm break-words ${estilos}`}>{children}</div>;
}

export function Linea({ etiqueta, valor, fuerte }) {
  return (
    <div className={`flex justify-between gap-2 ${fuerte ? 'text-base font-bold text-slate-900' : 'text-slate-600'}`}>
      <dt>{etiqueta}</dt><dd className="shrink-0">{colones(valor)}</dd>
    </div>
  );
}

export function FilaItem({ item, editable, deshabilitado, onCantidad, onQuitar }) {
  const enMaximo = item.cantidad >= item.stock;
  return (
    <li className={`py-3 border-b border-slate-100 last:border-0 ${deshabilitado ? 'opacity-50' : ''}`}>
      <div className="flex gap-3">
        <div aria-hidden className="h-16 w-16 shrink-0 rounded-lg bg-slate-200 grid place-items-center text-slate-400 text-xs">foto</div>
        <div className="min-w-0 flex-1">
          <p className="font-medium text-slate-900 break-words">{item.nombre}</p>
          <p className="text-sm text-slate-500 break-words">{item.variacion}</p>
          <p className="text-sm text-slate-700 mt-0.5">{colones(item.precio_unitario)} c/u</p>
          {!editable && <p className="text-sm text-slate-600 mt-1">Cantidad: {item.cantidad}</p>}
        </div>
        <p className="shrink-0 font-semibold text-slate-900">{colones(item.precio_unitario * item.cantidad)}</p>
      </div>
      {editable && (
        <div className="mt-2 flex items-center justify-between gap-2">
          <div className="inline-flex items-center rounded-lg border border-slate-300">
            <button aria-label={`Disminuir cantidad de ${item.nombre}`} disabled={deshabilitado || item.cantidad <= 1}
              onClick={() => onCantidad(item.id_item_carrito, item.cantidad - 1)} className="h-11 w-11 text-lg disabled:text-slate-300">−</button>
            <span aria-live="polite" className="w-8 text-center font-medium">{item.cantidad}</span>
            <button aria-label={`Aumentar cantidad de ${item.nombre}`} disabled={deshabilitado}
              onClick={() => onCantidad(item.id_item_carrito, item.cantidad + 1)} className="h-11 w-11 text-lg disabled:text-slate-300">+</button>
          </div>
          <button onClick={() => onQuitar(item.id_item_carrito)} className="h-11 px-3 text-sm font-medium text-red-700 underline">Quitar</button>
        </div>
      )}
      {editable && item.aviso && <p className="mt-1 text-xs text-amber-800">{item.aviso}</p>}
      {editable && !item.aviso && enMaximo && !deshabilitado && <p className="mt-1 text-xs text-amber-800">Cantidad máxima disponible: {item.stock}.</p>}
    </li>
  );
}

export function TarjetaComercio({ grupo, editable, suspendido, onCantidad, onQuitar }) {
  return (
    <section className="rounded-xl bg-white border border-slate-200 p-4">
      <h2 className="text-sm font-semibold text-slate-600 uppercase tracking-wide break-words">{grupo.comercio.nombre}</h2>
      {suspendido && <p className="mt-1 text-xs text-red-700">Membresía suspendida: sus productos están deshabilitados.</p>}
      <ul>{grupo.items.map((i) => <FilaItem key={i.id_item_carrito} item={i} editable={editable} deshabilitado={suspendido} onCantidad={onCantidad} onQuitar={onQuitar} />)}</ul>
      <dl className="mt-2 pt-2 border-t border-slate-100 text-sm space-y-1">
        <Linea etiqueta="Subtotal" valor={grupo.subtotal} />
        <Linea etiqueta="Impuestos" valor={grupo.impuesto} />
        <Linea etiqueta="Envío" valor={grupo.costo_envio} />
      </dl>
    </section>
  );
}

export function Campo({ id, etiqueta, error, children }) {
  return (
    <div>
      <label htmlFor={id} className="block text-sm font-medium text-slate-800 mb-1">{etiqueta}</label>
      {children}
      {error && <p role="alert" className="mt-1 text-xs text-red-700">{error}</p>}
    </div>
  );
}

export const clasesInput = (error) =>
  `w-full min-h-[44px] rounded-lg border px-3 py-2 text-base focus:outline-none focus:ring-2 focus:ring-sky-500 ${error ? 'border-red-500' : 'border-slate-300'}`;
