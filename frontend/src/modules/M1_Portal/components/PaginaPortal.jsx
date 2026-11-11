import { createContext, useContext } from 'react';
import EncabezadoPortal from './EncabezadoPortal';

// Lo que M1App comparte con las vistas del portal: navegación, menú de categorías y persona con sesión.
//   ir(pantalla, parametros) → cambia de pantalla y deja los parámetros en la URL
//   cantidadCarrito → unidades del carrito de M5, o null si no hay sesión de cliente o M5 no respondió
export const PortalContext = createContext({ ir: () => {}, categorias: [], usuario: null, cargandoUsuario: false, cantidadCarrito: null });

export const usePortal = () => useContext(PortalContext);

// Marco común de inicio, búsqueda, ficha de producto y micrositio: encabezado, contenido y pie.
export default function PaginaPortal({ terminoBusqueda = '', children }) {
  const { ir, categorias, usuario, cantidadCarrito } = usePortal();

  return (
    <div className="min-h-screen overflow-x-hidden bg-brandLightBg font-sans text-gray-800 antialiased">
      <EncabezadoPortal
        categorias={categorias}
        terminoInicial={terminoBusqueda}
        conSesion={!!usuario}
        cantidadCarrito={cantidadCarrito}
        onInicio={() => ir('inicio')}
        onBuscar={(q) => ir('busqueda', { q })}
        onCategoria={(idCategoria) => ir('busqueda', { id_categoria: idCategoria })}
        onMiCuenta={() => ir(usuario ? 'perfil' : 'ingresar')}
        onGestion={['encargado_comercio', 'encargado_plataforma'].includes(usuario?.rol) ? () => ir('gestion') : null}
      />
      <main className="mx-auto w-full max-w-7xl px-4 pb-12">{children}</main>
      <footer className="border-t border-gray-200 bg-white py-6 text-center text-xs text-gray-500">
        Pacíficos Online · Portal público
      </footer>
    </div>
  );
}
