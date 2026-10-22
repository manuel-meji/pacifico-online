
export default function FichaProductoView() {
  return (
    <div className="min-h-screen bg-brandLightBg font-sans text-gray-800 antialiased">
    <div className="bg-brandDarkBlue text-white text-xs py-2 px-4 border-b border-blue-900">
      <div className="max-w-7xl mx-auto flex justify-between items-center">
        <div className="flex items-center space-x-4">
          <span>
            Envíos rápidos a todo Costa Rica
          </span>
          <span>
            Soporte y Ventas: +506 2000-0000
          </span>
        </div>
        <div className="flex space-x-4">
          <a href="#" className="hover:underline">
            Sucursales
          </a>
          <a href="#" className="hover:underline">
            Garantías
          </a>
          <a href="#" className="hover:underline">
            Ayuda
          </a>
        </div>
      </div>
    </div>
    <header className="bg-white shadow-sm sticky top-0 z-50">
      <div className="max-w-7xl mx-auto px-4 py-3 flex items-center justify-between gap-4">
        <div className="flex items-center space-x-3 shrink-0">
          <div className="w-11 h-11 rounded-full bg-brandGreen flex items-center justify-center font-extrabold text-white text-2xl shadow">
            P
          </div>
          <div className="leading-tight">
            <span className="text-xl font-extrabold text-brandDarkBlue tracking-tight block">
              Pacíficos
            </span>
            <span className="text-xs text-brandGreen font-bold tracking-widest block -mt-1">
              ONLINE
            </span>
          </div>
        </div>
        <div className="flex-1 max-w-2xl mx-4">
          <div className="relative flex items-center">
            <input type="text" placeholder="Buscar productos, marcas o comercios..." className="w-full border-2 border-gray-200 rounded-full py-2.5 pl-5 pr-12 text-sm focus:outline-none focus:border-brandBlue transition" />
            <button className="absolute right-1 bg-brandDarkBlue hover:bg-brandBlue text-white p-2 rounded-full transition">
              <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
              </svg>
            </button>
          </div>
        </div>
        <div className="flex items-center space-x-6 text-sm font-medium text-gray-700 shrink-0">
          <a href="#" className="flex items-center space-x-1 hover:text-brandBlue transition">
            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
            </svg>
            <span>
              Mi Cuenta
            </span>
          </a>
          <a href="#" className="flex items-center space-x-1 hover:text-brandBlue transition relative">
            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"></path>
            </svg>
            <span className="bg-brandGreen text-white text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center -top-2 -right-3 absolute">
              0
            </span>
            <span className="ml-2">
              Carrito
            </span>
          </a>
        </div>
      </div>
      <nav className="bg-brandDarkBlue text-white border-t border-blue-900 text-sm font-medium">
        <div className="max-w-7xl mx-auto px-4 flex space-x-8 overflow-x-auto py-2.5">
          <a href="#" className="hover:text-brandGreen whitespace-nowrap">
            Laptops
          </a>
          <a href="#" className="hover:text-brandGreen whitespace-nowrap">
            PC Escritorio
          </a>
          <a href="#" className="hover:text-brandGreen whitespace-nowrap">
            Tarjetas Gráficas
          </a>
          <a href="#" className="hover:text-brandGreen whitespace-nowrap">
            Monitores
          </a>
          <a href="#" className="hover:text-brandGreen whitespace-nowrap">
            Periféricos
          </a>
          <a href="#" className="text-brandGreen font-bold whitespace-nowrap">
            Ofertas del Día
          </a>
        </div>
        </nav>  
     </header>
     
<main className="mx-auto max-w-7xl px-4 py-6">
  <section className="mb-8 grid grid-cols-1 gap-8 rounded-xl bg-white p-6 shadow-md md:grid-cols-2 md:p-8">

    {/* Imagen del producto */}
    <div className="flex items-center justify-center rounded-lg border bg-white p-4">
      <img
        src="https://images.unsplash.com/photo-1603302576837-37561b2e2302?auto=format&fit=crop&w=800&q=80"
        alt="Laptop Acer Nitro Lite"
        className="max-h-96 object-contain"
      />
    </div>

    {/* Información del producto */}
    <div>
      <p className="mb-3 text-sm font-semibold text-brandDarkBlue">
        Comercio vendedor: TecnoPacífico
      </p>

      <h1 className="mb-4 text-2xl font-bold text-gray-900">
        Acer Nitro Lite Core i5-13420H / 16GB RAM / RTX 4050 / 512GB SSD
      </h1>

      <div className="mb-6 rounded-lg border-l-4 border-brandGreen bg-gray-50 p-4">
        <span className="text-xs font-semibold uppercase text-gray-500">
          Precio de contado
        </span>

        <p className="text-3xl font-extrabold text-brandDarkBlue">
          ₡549,900
        </p>

        <span className="text-xs text-gray-500">
          IVA incluido
        </span>
      </div>

      <p className="mb-6 text-sm leading-relaxed text-gray-600">
        Laptop de alto rendimiento para gaming y diseño.
        Procesador Intel Core i5 de 13.ª generación,
        gráficos NVIDIA RTX 4050 y pantalla de 144 Hz.
      </p>

      <p className="mb-6 text-sm font-semibold text-green-700">
        Disponible en inventario
      </p>

      <button
        type="button"
        className="w-full rounded-lg bg-brandGreen px-6 py-3 font-bold text-white"
      >
        Agregar al carrito
      </button>
    </div>

  </section>
</main>

    </div>
  );
}