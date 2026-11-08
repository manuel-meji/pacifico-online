import { useEffect, useState } from 'react';
import logo from '../assets/logo-pacificos.png';
import Icono from './IconoComercio';
import Imagen from './Imagen';

// Encabezado del portal: franja superior, logotipo, buscador, cuenta, carrito y menú de categorías.
// `categorias` es la respuesta de GET /categorias (CategoriaPortal: id_categoria, nombre, icono_url, subcategorias).
// La validación del término (RN-M1-08) la hace la vista de búsqueda, para mostrar el aviso junto a los resultados.
export default function EncabezadoPortal({ categorias = [], terminoInicial = '', conSesion = false, onInicio, onBuscar, onCategoria, onMiCuenta }) {
  const [termino, setTermino] = useState(terminoInicial);

  useEffect(() => { setTermino(terminoInicial); }, [terminoInicial]);

  return (
    <>
      <div className="hidden border-b border-blue-900 bg-brandDarkBlue px-4 py-2 text-xs text-white sm:block">
        <div className="mx-auto flex max-w-7xl items-center justify-between">
          <div className="flex items-center gap-4">
            <span className="flex items-center gap-1.5"><Icono nombre="ubicacion" className="h-4 w-4 text-brandGreen" /> Envíos rápidos a todo Costa Rica</span>
            <span className="flex items-center gap-1.5"><Icono nombre="telefono" className="h-4 w-4 text-brandGreen" /> Soporte y Ventas: +506 2000-0000</span>
          </div>
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

          <form role="search" onSubmit={(e) => { e.preventDefault(); onBuscar?.(termino.trim()); }}
            className="relative order-3 flex basis-full items-center sm:order-none sm:mx-4 sm:max-w-2xl sm:flex-1 sm:basis-0">
            <label className="sr-only" htmlFor="buscar-portal">Buscar productos</label>
            <input id="buscar-portal" type="search" value={termino} maxLength={100} onChange={(e) => setTermino(e.target.value)}
              placeholder="Buscar productos..."
              className="w-full min-w-0 rounded-full border-2 border-gray-200 py-2.5 pl-5 pr-12 text-sm transition focus:border-brandBlue focus:outline-none" />
            <button className="absolute right-1 rounded-full bg-brandDarkBlue p-2 text-white transition hover:bg-brandBlue" aria-label="Buscar">
              <Icono nombre="buscar" className="h-4 w-4" />
            </button>
          </form>

          <nav className="ml-auto flex shrink-0 items-center gap-5 text-sm font-medium text-gray-700 sm:ml-0" aria-label="Cuenta y carrito">
            <button type="button" onClick={onMiCuenta} className="flex min-h-[44px] items-center gap-1 transition hover:text-brandBlue">
              <Icono nombre="usuario" className="h-5 w-5" />
              <span className="hidden sm:inline">{conSesion ? 'Mi cuenta' : 'Ingresar'}</span>
            </button>
            {/* El carrito es de M5 (app.html). */}
            <a href="/app.html" className="flex min-h-[44px] items-center gap-1 transition hover:text-brandBlue" aria-label="Carrito">
              <Icono nombre="carrito" className="h-6 w-6" />
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
