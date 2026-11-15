import { createContext, useContext } from 'react';
import EncabezadoPortal from './EncabezadoPortal';

// Lo que M1App comparte con las vistas del portal: navegación, menú de categorías y persona con sesión.
//   ir(pantalla, parametros) → cambia de pantalla y deja los parámetros en la URL
//   cantidadCarrito → líneas del carrito de M5, o null si no hay sesión de cliente o M5 no respondió
export const PortalContext = createContext({ ir: () => {}, categorias: [], usuario: null, cargandoUsuario: false, cantidadCarrito: null });

export const usePortal = () => useContext(PortalContext);

// Marco común de inicio, búsqueda, ficha de producto y micrositio: encabezado, contenido y pie.
// `tipoBusqueda` deja el selector del buscador en 'productos' o 'comercios'.
// `barraPanel` ({ texto, href, etiqueta }) cambia el encabezado del portal por una franja azul con un
// solo botón de regreso: la usan las pantallas de encargados (vista previa del micrositio y panel de gestión).
export default function PaginaPortal({ terminoBusqueda = '', tipoBusqueda = 'productos', vistaPrevia = false, barraPanel = null, children }) {
  const { ir, categorias, usuario, cargandoUsuario, cantidadCarrito } = usePortal();
  const esEncargado = ['encargado_comercio', 'encargado_plataforma'].includes(usuario?.rol);
  // "Vendé en Pacíficos Online" no se muestra a quien ya es encargado, ni mientras se consulta la sesión
  // (para que no aparezca un instante antes de saber el rol).
  const mostrarVender = !cargandoUsuario && !esEncargado;

  const barra = barraPanel ?? (vistaPrevia
    ? { texto: 'Vista previa de tu comercio en el portal', href: '/m2.html', etiqueta: 'Volver a Mi comercio' }
    : null);

  if (barra) {
    return (
      <div className="min-h-screen overflow-x-hidden bg-brandLightBg font-sans text-gray-800 antialiased">
        <div className="border-b border-blue-900 bg-brandDarkBlue text-white">
          <div className="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-4 py-3">
            <p className="text-sm font-semibold">
              {barra.texto}
            </p>
            <a
              href={barra.href}
              className="inline-flex items-center gap-2 rounded-lg bg-[#9DBB45] px-4 py-2 text-sm font-bold text-white transition-colors hover:bg-[#8AA83A] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"
            >
              ← {barra.etiqueta}
            </a>
          </div>
        </div>
        <main className="mx-auto w-full max-w-7xl px-4 pb-12 pt-4">{children}</main>
      </div>
    );
  }

  return (
    <div className="min-h-screen overflow-x-hidden bg-brandLightBg font-sans text-gray-800 antialiased">
      <EncabezadoPortal
        categorias={categorias}
        terminoInicial={terminoBusqueda}
        tipoInicial={tipoBusqueda}
        conSesion={!!usuario}
        cantidadCarrito={cantidadCarrito}
        onInicio={() => ir('inicio')}
        onBuscar={(q, tipo) => ir(tipo === 'comercios' ? 'comercios' : 'busqueda', { q })}
        onCategoria={(idCategoria) => ir('busqueda', { id_categoria: idCategoria })}
        onMiCuenta={() => ir(usuario ? 'perfil' : 'ingresar')}
        mostrarVender={mostrarVender}
        onGestion={esEncargado ? () => { window.location.href = '/m2.html'; } : null}
      />
      <main className="mx-auto w-full max-w-7xl px-4 pb-12">{children}</main>
      <footer className="border-t border-gray-200 bg-white px-4 py-6 text-center text-xs text-gray-500">
        {/* La franja superior se oculta en celular; aquí el enlace queda visible en todos los tamaños. */}
        {mostrarVender && (
          <p className="mb-2">
            <a href="/m2.html" className="inline-flex min-h-[44px] items-center rounded-sm px-2 text-sm font-semibold text-brandBlue hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brandBlue">
              Vendé en Pacíficos Online
            </a>
          </p>
        )}
        Pacíficos Online · Portal público
      </footer>
    </div>
  );
}
