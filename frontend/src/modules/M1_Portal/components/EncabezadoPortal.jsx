import { useEffect, useState } from 'react';
import logo from '../assets/logo-pacificos.png';
import Icono from './IconoComercio';
import Imagen from './Imagen';

export const TOPE_CARRITO = 30;

// Texto de la insignia del carrito: el número, o +30 si lo supera.
export const textoInsignia = (cantidad) => (cantidad > TOPE_CARRITO ? `+${TOPE_CARRITO}` : String(cantidad));

// Encabezado del portal: franja superior, logotipo, buscador, cuenta, carrito y menú de categorías.
// `categorias` es la respuesta de GET /categorias (CategoriaPortal: id_categoria, nombre, icono_url, subcategorias).
// La validación del término (RN-M1-08) la hace la vista de búsqueda, para mostrar el aviso junto a los resultados.
// `cantidadCarrito` son las líneas del carrito de M5 (productos o variaciones distintas). La insignia se muestra
// siempre: 0 sin sesión, sin dato o con el carrito vacío, y de 31 en adelante +30.
// `tipoInicial` ('productos' o 'comercios') elige qué busca el buscador; onBuscar(termino, tipo).
// `onGestion` solo llega para los encargados (comercio o plataforma): muestra el acceso al panel de gestión.
// `mostrarVender` agrega en la franja superior el enlace a la solicitud de incorporación de comercios (M2).
export default function EncabezadoPortal({ categorias = [], terminoInicial = '', tipoInicial = 'productos', conSesion = false, cantidadCarrito = null, mostrarVender = false, onInicio, onBuscar, onCategoria, onMiCuenta, onGestion = null }) {
  const [termino, setTermino] = useState(terminoInicial);
  const [tipo, setTipo] = useState(tipoInicial);
  const cantidad = Number.isInteger(cantidadCarrito) && cantidadCarrito > 0 ? cantidadCarrito : 0;
  const etiquetaCarrito = `Carrito, ${cantidad > TOPE_CARRITO ? `más de ${TOPE_CARRITO}` : cantidad} ${cantidad === 1 ? 'producto' : 'productos'}`;

  useEffect(() => { setTermino(terminoInicial); }, [terminoInicial]);
  useEffect(() => { setTipo(tipoInicial); }, [tipoInicial]);

  return (
    <>
      <div className="hidden border-b border-blue-900 bg-brandDarkBlue px-4 py-2 text-xs text-white sm:block">
        <div className="mx-auto flex max-w-7xl items-center justify-between">
          <div className="flex items-center gap-4">
            <span className="flex items-center gap-1.5"><Icono nombre="ubicacion" className="h-4 w-4 text-brandGreen" /> Envíos rápidos a todo Costa Rica</span>
            <span className="flex items-center gap-1.5"><Icono nombre="telefono" className="h-4 w-4 text-brandGreen" /> Soporte y Ventas: +506 2000-0000</span>
          </div>
          {/* La solicitud de incorporación es de M2 (m2.html). El margen negativo da 44 px de área táctil sin engrosar la franja. */}
          {mostrarVender && (
            <a href="/m2.html" className="-my-3.5 ml-4 flex min-h-[44px] shrink-0 items-center gap-1.5 rounded-sm font-semibold transition hover:text-brandGreen hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white">
              <Icono nombre="tienda" className="h-4 w-4 text-brandGreen" /> Vendé en Pacíficos Online
            </a>
          )}
        </div>
      </div>

      <header className="sticky top-0 z-20 bg-white shadow-sm">
        <div className="mx-auto flex max-w-7xl flex-wrap items-center gap-3 px-4 py-3 sm:gap-4">
          <button type="button" onClick={onInicio} className="flex shrink-0 items-center gap-3 rounded-lg focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brandBlue" aria-label="Pacíficos Online, ir al inicio">
            <img src={logo} alt="" className="h-11 w-11 rounded-full" />
            <span className="text-left leading-tight">
              <span className="block text-xl font-extrabold tracking-tight text-brandDarkBlue">Pacíficos</span>
              <span className="-mt-1 block text-xs font-bold tracking-widest text-brandGreen">ONLINE</span>
            </span>
          </button>

          <form role="search" onSubmit={(e) => { e.preventDefault(); onBuscar?.(termino.trim(), tipo); }}
            className="relative order-3 flex basis-full items-center rounded-full border-2 border-gray-200 bg-white transition focus-within:border-brandBlue sm:order-none sm:mx-4 sm:max-w-2xl sm:flex-1 sm:basis-0">
            <label className="sr-only" htmlFor="tipo-busqueda">Buscar en</label>
            <span className="relative shrink-0">
              <select id="tipo-busqueda" value={tipo} onChange={(e) => setTipo(e.target.value)}
                className="min-h-[40px] cursor-pointer appearance-none rounded-l-full border-r border-gray-200 bg-gray-50 py-2 pl-4 pr-8 text-sm font-semibold text-brandDarkBlue focus:outline-none">
                <option value="productos">Productos</option>
                <option value="comercios">Comercios</option>
              </select>
              <Icono nombre="abajo" className="pointer-events-none absolute right-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-500" />
            </span>
            <label className="sr-only" htmlFor="buscar-portal">{tipo === 'comercios' ? 'Buscar comercios' : 'Buscar productos'}</label>
            <input id="buscar-portal" type="search" value={termino} maxLength={100} onChange={(e) => setTermino(e.target.value)}
              placeholder={tipo === 'comercios' ? 'Buscar comercios...' : 'Buscar productos...'}
              className="w-full min-w-0 rounded-r-full border-0 bg-transparent py-2 pl-3 pr-12 text-base focus:outline-none sm:text-sm" />
            <button className="absolute right-1 grid h-9 w-9 place-items-center rounded-full bg-brandDarkBlue text-white transition hover:bg-brandBlue sm:h-8 sm:w-8" aria-label="Buscar">
              <Icono nombre="buscar" className="h-4 w-4" />
            </button>
          </form>

          <nav className="ml-auto flex shrink-0 items-center gap-5 text-sm font-medium text-gray-700 sm:ml-0" aria-label="Cuenta y carrito">
            {onGestion && (
              <button type="button" onClick={onGestion} aria-label="Panel de gestión"
                className="flex min-h-[44px] min-w-[44px] items-center justify-center gap-1 transition hover:text-brandBlue">
                <Icono nombre="panel" className="h-5 w-5" />
                <span className="hidden sm:inline">Gestión</span>
              </button>
            )}
            <button type="button" onClick={onMiCuenta} aria-label={conSesion ? 'Mi cuenta' : 'Ingresar'}
              className="flex min-h-[44px] min-w-[44px] items-center justify-center gap-1 transition hover:text-brandBlue">
              <Icono nombre="usuario" className="h-5 w-5" />
              <span className="hidden sm:inline">{conSesion ? 'Mi cuenta' : 'Ingresar'}</span>
            </button>
            {/* El carrito es de M5 (carrito.html). */}
            <a href="/carrito.html" className="flex min-h-[44px] min-w-[44px] items-center justify-center gap-1 transition hover:text-brandBlue" aria-label={etiquetaCarrito}>
              <span className="relative mr-2.5">
                <Icono nombre="carrito" className="h-6 w-6" />
                <span aria-hidden="true" data-testid="insignia-carrito"
                  className={`absolute -right-2.5 -top-2 flex h-5 min-w-5 items-center justify-center rounded-full px-1 text-[11px] font-bold leading-none text-white ring-2 ring-white ${cantidad > 0 ? 'bg-brandDarkBlue' : 'bg-gray-400'}`}>
                  {textoInsignia(cantidad)}
                </span>
              </span>
              <span className="hidden sm:inline">Carrito</span>
            </a>
          </nav>
        </div>

        {categorias.length > 0 && (
          <nav className="border-t border-blue-900 bg-brandDarkBlue text-sm font-medium text-white" aria-label="Categorías">
            <div className="mx-auto flex max-w-7xl gap-6 overflow-x-auto px-4 py-2.5">
              {categorias.map((c) => (
                <button key={c.id_categoria} type="button" onClick={() => onCategoria?.(c.id_categoria)} className="flex shrink-0 items-center gap-2 whitespace-nowrap hover:text-brandGreen">
                  <Imagen src={c.icono_url} className="h-4 w-4" claseIcono="h-4 w-4" />
                  {c.nombre}
                </button>
              ))}
            </div>
          </nav>
        )}
      </header>
    </>
  );
}
