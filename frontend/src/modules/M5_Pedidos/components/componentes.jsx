import { createContext, useContext, useEffect, useRef, useState } from 'react';
import {
  ArrowLeft, Check, CircleAlert, ClipboardList, CircleCheck, CircleX, Heart, Info, LoaderCircle, Lock, MapPin, Minus, Package, Phone, Plus, Search, ShoppingCart, Store, Trash2, TriangleAlert, User,
} from 'lucide-react';
import { colones } from '../services/calculos';
import logoPacificos from '../../../assets/logo-pacificos.png';
import { obtenerUsuario } from '../../Nucleo/services/sesionApi';

// Navegación compartida de M5 (la provee M5App): { abrirMisPedidos, enMisPedidos, abrirPanelComercio, enPanelComercio, abrirDeseos, enDeseos }.
// Permite que cualquier vista muestre los accesos del encabezado sin pasar props.
export const NavegacionM5 = createContext(null);

export function Encabezado({ titulo, paso, onVolver, etiquetaVolver = 'Volver al carrito' }) {
  const nav = useContext(NavegacionM5);
  const [termino, setTermino] = useState('');
  const [conSesion, setConSesion] = useState(false);
  const [esComercio, setEsComercio] = useState(false);

  useEffect(() => {
    let activo = true;
    obtenerUsuario()
      .then((r) => {
        if (activo && r?.ok && r?.datos?.user) {
          setConSesion(true);
          setEsComercio(r.datos.user.rol === 'encargado_comercio');
        }
      })
      .catch(() => {});
    return () => { activo = false; };
  }, []);

  const handleBuscar = (e) => {
    e.preventDefault();
    const t = termino.trim();
    if (t) {
      window.location.href = `/app.html?buscar=${encodeURIComponent(t)}`;
    }
  };

  const mostrarMisPedidos = nav?.abrirMisPedidos && !nav.enMisPedidos;
  const mostrarDeseos = nav?.abrirDeseos && !nav.enDeseos;
  const mostrarPanel = Boolean(
    nav?.abrirPanelComercio &&
    !nav.enPanelComercio &&
    (esComercio || (typeof window !== 'undefined' && new URLSearchParams(window.location.search).has('demo')))
  );

  return (
    <>
      {/* Franja superior azul idéntica a M1 */}
      <div className="hidden border-b border-blue-900 bg-brandDarkBlue px-4 py-2 text-xs text-white sm:block">
        <div className="mx-auto flex max-w-7xl items-center justify-between">
          <div className="flex items-center gap-4">
            <span className="flex items-center gap-1.5 font-medium">
              <MapPin className="h-4 w-4 text-brandGreen" aria-hidden="true" />
              Envíos rápidos a todo Costa Rica
            </span>
            <span className="flex items-center gap-1.5 font-medium">
              <Phone className="h-4 w-4 text-brandGreen" aria-hidden="true" />
              Soporte y Ventas: +506 2000-0000
            </span>
          </div>
        </div>
      </div>

      {/* Encabezado principal unificado */}
      <header className="sticky top-0 z-20 bg-white shadow-xs">
        <div className="mx-auto flex max-w-7xl flex-wrap items-center gap-3 px-4 py-3 sm:gap-4">
          {/* Logotipo oficial idéntico a M1 */}
          <a
            href="/app.html"
            aria-label="Pacíficos Online, ir al inicio"
            className="flex shrink-0 items-center gap-3 rounded-lg focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brandBlue"
          >
            <img src={logoPacificos} alt="Pacíficos Online" className="h-11 w-11 rounded-full object-cover" />
            <span className="text-left leading-tight">
              <span className="block text-xl font-extrabold tracking-tight text-brandDarkBlue">Pacíficos</span>
              <span className="-mt-1 block text-xs font-bold tracking-widest text-brandGreen">ONLINE</span>
            </span>
          </a>

          {/* Buscador: redirige a la búsqueda del portal */}
          <form
            role="search"
            onSubmit={handleBuscar}
            className="relative order-3 flex basis-full items-center sm:order-none sm:mx-4 sm:max-w-2xl sm:flex-1 sm:basis-0"
          >
            <label className="sr-only" htmlFor="buscar-m5">Buscar productos</label>
            <input
              id="buscar-m5"
              type="search"
              value={termino}
              maxLength={100}
              onChange={(e) => setTermino(e.target.value)}
              placeholder="Buscar productos..."
              className="w-full min-w-0 rounded-full border-2 border-gray-200 py-2.5 pl-5 pr-12 text-base transition sm:text-sm focus:border-brandBlue focus:outline-none"
            />
            <button
              type="submit"
              className="absolute right-1 grid h-9 w-9 place-items-center rounded-full bg-brandDarkBlue text-white transition hover:bg-brandBlue sm:h-8 sm:w-8 cursor-pointer"
              aria-label="Buscar"
            >
              <Search className="h-4 w-4" aria-hidden="true" />
            </button>
          </form>

          {/* Navegación de cuenta, deseos, pedidos y carrito */}
          <nav className="ml-auto flex shrink-0 items-center gap-4 sm:gap-5 text-sm font-medium text-gray-700 sm:ml-0" aria-label="Cuenta, pedidos y carrito">
            <a
              href={`/app.html?pantalla=${conSesion ? 'cuenta' : 'ingresar'}`}
              aria-label={conSesion ? 'Mi cuenta' : 'Ingresar'}
              className="flex min-h-[44px] min-w-[44px] items-center justify-center gap-1.5 transition hover:text-brandBlue"
            >
              <User className="h-5 w-5" aria-hidden="true" />
              <span className="hidden sm:inline">{conSesion ? 'Mi cuenta' : 'Ingresar'}</span>
            </a>

            {mostrarDeseos && (
              <button
                type="button"
                onClick={nav.abrirDeseos}
                aria-label="Lista de deseos"
                className="flex min-h-[44px] min-w-[44px] items-center justify-center gap-1.5 transition hover:text-brandBlue cursor-pointer"
              >
                <Heart className="h-5 w-5 text-rose-500" aria-hidden="true" />
                <span className="hidden sm:inline">Deseos</span>
              </button>
            )}

            {mostrarMisPedidos && (
              <button
                type="button"
                onClick={nav.abrirMisPedidos}
                aria-label="Mis pedidos"
                className="flex min-h-[44px] min-w-[44px] items-center justify-center gap-1.5 transition hover:text-brandBlue cursor-pointer"
              >
                <ClipboardList className="h-5 w-5" aria-hidden="true" />
                <span className="hidden sm:inline">Mis pedidos</span>
              </button>
            )}

            <a
              href="/carrito.html"
              aria-label="Carrito de compras"
              className="flex min-h-[44px] min-w-[44px] items-center justify-center gap-1.5 font-bold text-brandBlue transition hover:text-brandDarkBlue"
            >
              <span className="relative">
                <ShoppingCart className="h-6 w-6" aria-hidden="true" />
              </span>
              <span className="hidden sm:inline">Carrito</span>
            </a>

            {mostrarPanel && (
              <button
                type="button"
                onClick={nav.abrirPanelComercio}
                aria-label="Gestión de pedidos del comercio"
                className="min-h-11 min-w-11 inline-flex items-center justify-center gap-1.5 rounded-lg border border-blue-100 bg-blue-50/70 px-3 text-xs font-bold text-brandDarkBlue hover:bg-blue-100 transition cursor-pointer"
              >
                <Store className="h-4 w-4 text-brandBlue" aria-hidden="true" />
                <span className="hidden lg:inline">Pedidos comercio</span>
              </button>
            )}
          </nav>
        </div>

        {/* Barra de contexto de la pantalla de M5 (Paso actual / Breadcrumb) */}
        <div className="border-t border-gray-100 bg-gray-50/80 px-4 py-2">
          <div className="mx-auto flex max-w-7xl items-center justify-between gap-3 text-xs sm:text-sm">
            <div className="flex items-center gap-2 min-w-0">
              {onVolver && (
                <button
                  type="button"
                  onClick={onVolver}
                  aria-label={etiquetaVolver}
                  className="inline-flex items-center gap-1 font-semibold text-brandDarkBlue hover:text-brandBlue transition cursor-pointer"
                >
                  <ArrowLeft className="w-4 h-4" aria-hidden="true" />
                  <span>{etiquetaVolver}</span>
                </button>
              )}
              {onVolver && <span className="text-gray-300">/</span>}
              <h1 className="font-bold text-gray-800 truncate">{titulo}</h1>
            </div>
            {paso && (
              <span className="shrink-0 font-semibold text-gray-600 bg-white px-2.5 py-0.5 rounded-full border border-gray-200 shadow-2xs">
                {paso}
              </span>
            )}
          </div>
        </div>
      </header>
    </>
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

  const precioBase = Number(item.precio_base) > 0
    ? Number(item.precio_base)
    : (Number(item.precio_unitario) + (Number(item.descuento_unitario) || 0));
  const precioUnitario = Number(item.precio_unitario) || 0;
  const descuentoUnitario = Number(item.descuento_unitario) > 0
    ? Number(item.descuento_unitario)
    : Math.max(0, precioBase - precioUnitario);
  const tieneDescuento = descuentoUnitario > 0;
  const subtotalOriginal = precioBase * item.cantidad;
  const descuentoTotal = tieneDescuento
    ? (Number(item.descuento) > 0 ? Number(item.descuento) : descuentoUnitario * item.cantidad)
    : 0;
  const subtotal = item.subtotal ?? precioUnitario * item.cantidad;
  const detalle = item.variacion ?? (item.sku ? `SKU: ${item.sku}` : null);

  return (
    <li className={`py-3.5 border-b border-gray-100 last:border-0 ${deshabilitado ? 'opacity-50' : ''}`}>
      <div className="flex items-start sm:items-center gap-3">
        <Miniatura imagen={item.imagen} nombre={item.nombre} />
        <div className="min-w-0 flex-1">
          <p className="text-sm sm:text-base font-bold text-gray-900 break-words">{item.nombre}</p>
          {detalle && <p className="text-xs text-gray-500 font-normal break-words">{detalle}</p>}
          {tieneDescuento ? (
            <div className="mt-1 space-y-0.5 text-xs">
              <p className="text-gray-600">
                <span>Precio original: </span>
                <span className="line-through text-gray-500 font-medium">{colones(precioBase)}</span>
                <span className="text-gray-500"> c/u</span>
              </p>
              <p className="text-red-700 font-semibold flex flex-wrap items-center gap-1">
                <span>Descuento realizado:</span>
                <span className="inline-flex items-center rounded-sm bg-red-50 px-1.5 py-0.5 text-[11px] font-bold text-red-700 border border-red-200">
                  -{colones(descuentoUnitario)} c/u
                </span>
                {item.cantidad > 1 && (
                  <span className="text-[11px] text-gray-500 font-normal">(-{colones(descuentoTotal)} total)</span>
                )}
              </p>
              <p className="text-gray-800 font-medium">
                Nuevo precio: <span className="font-bold text-brandDarkBlue">{colones(precioUnitario)}</span> c/u · Cantidad: {item.cantidad}
              </p>
              <p className="text-gray-900 font-semibold pt-0.5">
                Nuevo total: <span className="font-bold text-brandDarkBlue">{colones(subtotal)}</span>
              </p>
            </div>
          ) : (
            <p className="text-xs text-gray-600 font-medium mt-0.5">{colones(item.precio_unitario)} c/u · Cantidad: {item.cantidad}</p>
          )}
          {conStock && !deshabilitado && !enMaximo && (
            <span className="inline-flex items-center gap-1 text-xs text-green-700 font-medium mt-1">
              <Check className="w-3 h-3" aria-hidden="true" /> En Stock ({item.stock} disp.)
            </span>
          )}
        </div>
        <div className="shrink-0 text-right">
          {tieneDescuento && (
            <p className="text-xs text-gray-400 line-through">{colones(subtotalOriginal)}</p>
          )}
          <p className="text-sm sm:text-base font-extrabold text-brandDarkBlue">{colones(subtotal)}</p>
          {tieneDescuento && (
            <span className="text-[10px] font-semibold text-green-700 block">Nuevo total</span>
          )}
        </div>
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
  const precioBase = Number(item.precio_base) > 0
    ? Number(item.precio_base)
    : (Number(item.precio_unitario) + (Number(item.descuento_unitario) || 0));
  const precioUnitario = Number(item.precio_unitario) || 0;
  const descuentoUnitario = Number(item.descuento_unitario) > 0
    ? Number(item.descuento_unitario)
    : Math.max(0, precioBase - precioUnitario);
  const tieneDescuento = descuentoUnitario > 0;
  const subtotalOriginal = precioBase * item.cantidad;
  const subtotal = item.subtotal ?? precioUnitario * item.cantidad;

  return (
    <li className="flex items-center justify-between gap-2 text-xs sm:text-sm">
      <div className="flex items-center gap-2 min-w-0">
        <span className="min-w-5 h-5 px-1 bg-gray-100 rounded flex items-center justify-center font-bold text-gray-700 text-xs">{item.cantidad}x</span>
        <div className="min-w-0">
          <span className="text-gray-800 font-medium break-words">{item.nombre}</span>
          {tieneDescuento && (
            <p className="text-[11px] text-gray-500">
              <span className="line-through">{colones(precioBase)}</span>
              <span className="text-red-700 font-semibold ml-1.5">(-{colones(descuentoUnitario)})</span>
              <span className="text-gray-700 font-medium ml-1.5">{colones(precioUnitario)} c/u</span>
            </p>
          )}
        </div>
      </div>
      <div className="shrink-0 text-right">
        {tieneDescuento && (
          <span className="block text-[11px] text-gray-400 line-through">{colones(subtotalOriginal)}</span>
        )}
        <span className="font-bold text-gray-900">{colones(subtotal)}</span>
      </div>
    </li>
  );
}

// ---------- Tarjeta de subpedido (un comercio) ----------
export function TarjetaComercio({ grupo, numero, editable, suspendido, pendientes, onCantidad, onQuitar }) {
  const descuentoGrupo = (grupo.items || []).reduce((s, i) => s + (Number(i.descuento) || 0), 0);
  const subtotalBaseGrupo = (grupo.items || []).reduce((s, i) => {
    const base = Number(i.precio_base) > 0 ? Number(i.precio_base) : (Number(i.precio_unitario) + (Number(i.descuento_unitario) || 0));
    return s + base * (Number(i.cantidad) || 1);
  }, 0);
  const totalSubpedido = Number(grupo.total) > 0 ? Number(grupo.total) : (Number(grupo.subtotal || 0) + Number(grupo.impuesto || 0) + Number(grupo.costo_envio || 0));

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
        {descuentoGrupo > 0 && (
          <div className="flex justify-between text-xs sm:text-sm text-gray-500">
            <dt>Subtotal original:</dt>
            <dd className="line-through">{colones(subtotalBaseGrupo)}</dd>
          </div>
        )}
        {descuentoGrupo > 0 && (
          <div className="flex justify-between text-xs sm:text-sm text-red-700 font-semibold">
            <dt>Descuento aplicado:</dt>
            <dd>-{colones(descuentoGrupo)}</dd>
          </div>
        )}
        <div className="flex justify-between text-sm sm:text-base">
          <dt className="font-bold text-gray-800">{descuentoGrupo > 0 ? 'Subtotal con descuento:' : 'Subtotal Subpedido:'}</dt>
          <dd className="font-extrabold text-brandDarkBlue text-base sm:text-lg">{colones(grupo.subtotal)}</dd>
        </div>
        <div className="flex justify-between text-xs sm:text-sm text-gray-500"><dt>Impuestos:</dt><dd className="font-medium">{colones(grupo.impuesto)}</dd></div>
        <div className="flex justify-between text-xs sm:text-sm text-gray-500"><dt>Costo de Envío:</dt><dd className="font-medium">{colones(grupo.costo_envio)}</dd></div>
        <div className="flex justify-between text-sm sm:text-base font-bold text-gray-900 pt-1.5 border-t border-dashed border-gray-200">
          <dt>Total Subpedido:</dt>
          <dd className="font-extrabold text-brandDarkBlue">{colones(totalSubpedido)}</dd>
        </div>
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

// ---------- Modal amigable de inicio de sesion requerido para pagar ----------
export function ModalLoginRequerido({ abierto, onCerrar }) {
  const cerrarRef = useRef(null);

  useEffect(() => {
    if (!abierto) return undefined;
    cerrarRef.current?.focus();
    const alTeclear = (e) => { if (e.key === 'Escape') onCerrar(); };
    document.addEventListener('keydown', alTeclear);
    return () => document.removeEventListener('keydown', alTeclear);
  }, [abierto, onCerrar]);

  if (!abierto) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div className="absolute inset-0 bg-gray-900/60 backdrop-blur-xs" aria-hidden="true" onClick={onCerrar} />
      <div
        role="dialog"
        aria-modal="true"
        aria-labelledby="modal-login-titulo"
        aria-describedby="modal-login-desc"
        className="relative bg-white rounded-2xl shadow-xl border border-gray-200 w-full max-w-md p-6 sm:p-7 space-y-5 animate-in fade-in zoom-in-95 duration-150"
      >
        <div className="flex items-start gap-4">
          <div className="h-12 w-12 shrink-0 rounded-2xl bg-blue-50 text-brandDarkBlue flex items-center justify-center">
            <Lock className="w-6 h-6" aria-hidden="true" />
          </div>
          <div className="min-w-0">
            <h2 id="modal-login-titulo" className="text-lg font-bold text-gray-900 leading-snug">
              Inicie sesión para completar su compra
            </h2>
            <p id="modal-login-desc" className="mt-1.5 text-xs sm:text-sm text-gray-600 leading-relaxed">
              Para calcular los costos de envío a su dirección y asociar su pedido, debe identificarse con su cuenta.
            </p>
          </div>
        </div>

        <div className="bg-blue-50/70 border border-blue-200/80 rounded-xl p-3 text-xs text-blue-900 flex items-center gap-2.5">
          <CircleCheck className="w-4 h-4 text-brandGreen shrink-0" aria-hidden="true" />
          <span className="font-medium">No se preocupe: los productos de su carrito se mantendrán guardados.</span>
        </div>

        <div className="space-y-2 pt-1">
          <a
            href="/app.html?pantalla=ingresar&motivo=checkout"
            className="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-brandDarkBlue hover:bg-brandBlue text-white font-bold py-3 px-5 transition text-sm shadow-xs cursor-pointer"
          >
            Iniciar sesión
          </a>
          <a
            href="/app.html?pantalla=crear&motivo=checkout"
            className="w-full inline-flex items-center justify-center gap-2 rounded-xl border border-gray-300 hover:border-brandBlue hover:text-brandBlue text-gray-700 font-semibold py-2.5 px-5 transition text-sm cursor-pointer"
          >
            Crear una cuenta nueva
          </a>
          <button
            ref={cerrarRef}
            type="button"
            onClick={onCerrar}
            className="w-full py-2 text-xs font-semibold text-gray-500 hover:text-gray-800 transition cursor-pointer"
          >
            Volver al carrito
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