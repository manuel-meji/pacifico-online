import logo from '../assets/logo-pacificos.png';

// Encabezado del portal, con la misma estructura que el prototipo de ficha de producto (Royland):
// franja superior azul oscuro, encabezado blanco con logotipo, buscador, cuenta y carrito, y barra de categorías.
// Es común a todas las pantallas de M1; se unifica en un solo componente cuando se junten las pantallas.
export default function EncabezadoPortal({ categorias = [], cantidadCarrito = 0, onCategoria }) {
  return (
    <>
      <div className="hidden border-b border-blue-900 bg-brandDarkBlue px-4 py-2 text-xs text-white sm:block">
        <div className="mx-auto flex max-w-7xl items-center justify-between">
          <div className="flex items-center gap-4">
            <span>📍 Envíos rápidos a todo Costa Rica</span>
            <span>📞 Soporte y Ventas: +506 2000-0000</span>
          </div>
          <div className="flex gap-4">
            <a href="#" className="hover:underline">Comercios</a>
            <a href="#" className="hover:underline">Ayuda</a>
          </div>
        </div>
      </div>

      <header className="sticky top-0 z-20 bg-white shadow-sm">
        <div className="mx-auto flex max-w-7xl flex-wrap items-center gap-3 px-4 py-3 sm:gap-4">
          <a href="#" className="flex shrink-0 items-center gap-3" aria-label="Pacíficos Online, ir al inicio">
            <img src={logo} alt="" className="h-11 w-11 rounded-full" />
            <span className="leading-tight">
              <span className="block text-xl font-extrabold tracking-tight text-brandDarkBlue">Pacíficos</span>
              <span className="-mt-1 block text-xs font-bold tracking-widest text-brandGreen">ONLINE</span>
            </span>
          </a>

          <form role="search" onSubmit={(e) => e.preventDefault()} className="relative order-3 flex basis-full items-center sm:order-none sm:mx-4 sm:max-w-2xl sm:flex-1 sm:basis-0">
            <label className="sr-only" htmlFor="buscar-portal">Buscar productos, marcas o comercios</label>
            <input id="buscar-portal" type="search" placeholder="Buscar productos, marcas o comercios..."
              className="w-full rounded-full border-2 border-gray-200 py-2.5 pl-5 pr-12 text-sm transition focus:border-brandBlue focus:outline-none" />
            <button className="absolute right-1 rounded-full bg-brandDarkBlue p-2 text-white transition hover:bg-brandBlue" aria-label="Buscar">
              <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
            </button>
          </form>

          <nav className="ml-auto flex shrink-0 items-center gap-5 text-sm font-medium text-gray-700 sm:ml-0">
            <a href="#" className="flex items-center gap-1 transition hover:text-brandBlue">
              <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
              <span className="hidden sm:inline">Mi Cuenta</span>
            </a>
            <a href="#" className="relative flex items-center gap-1 transition hover:text-brandBlue" aria-label={`Carrito, ${cantidadCarrito} productos`}>
              <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z" /></svg>
              <span className="absolute -top-2 left-4 flex h-5 w-5 items-center justify-center rounded-full bg-brandGreen text-xs font-bold text-white">{cantidadCarrito}</span>
              <span className="ml-2 hidden sm:inline">Carrito</span>
            </a>
          </nav>
        </div>

        <nav className="border-t border-blue-900 bg-brandDarkBlue text-sm font-medium text-white" aria-label="Categorías">
          <div className="mx-auto flex max-w-7xl gap-8 overflow-x-auto px-4 py-2.5">
            {categorias.map((c) => (
              <button key={c.idCategoria} onClick={() => onCategoria?.(c.idCategoria)} className="whitespace-nowrap hover:text-brandGreen">
                {c.icono} {c.nombre}
              </button>
            ))}
          </div>
        </nav>
      </header>
    </>
  );
}
