// Pantalla principal de M3 (m3.html): encabezado, pestañas Catálogo / Registrar producto y la vista activa.
// Separada de main.jsx para poder probarla sin montar la aplicación en el documento.
//
// Encargado del comercio con id_comercio en la sesión: el catálogo muestra solo sus productos ("Mis productos")
// y el encabezado ofrece el regreso a su panel de M2 (/m2.html). El comercio sale de la sesión (GET /api/user),
// nunca de la URL ni de un control de la pantalla. Para los demás roles y sin sesión, el catálogo es el público.
import { useEffect, useState } from 'react';
import { Home, Package, PlusCircle, Store } from 'lucide-react';

import M3App from './M3App';
import CatalogoView from './views/CatalogoView';

import {
  cerrarSesion,
  obtenerUsuario,
} from '../Nucleo/services/sesionApi';

import {
  C,
  Encabezado,
} from '../M2_Comercios/components/comunes';

const URL_INGRESO = '/app.html?pantalla=ingresar';
const ROL_ENCARGADO = 'encargado_comercio';

// Mismo estilo que el botón «Mi comercio» del encabezado de M4.
const CLASE_BOTON_ENCABEZADO =
  'inline-flex items-center gap-1.5 rounded-lg border border-white/40 px-3 py-1.5 text-xs font-semibold text-white hover:bg-white/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-white/60';

// Comercio propio del encargado, o null si la persona no es encargada de un comercio.
export function comercioPropio(usuario) {
  return usuario?.rol === ROL_ENCARGADO && usuario.id_comercio
    ? Number(usuario.id_comercio)
    : null;
}

function vistaInicial() {
  return window.location.hash === '#alta' ? 'alta' : 'catalogo';
}

export default function M3Principal() {
  const [vista, setVista] = useState(vistaInicial);
  const [usuario, setUsuario] = useState(undefined); // undefined: cargando; null: sin sesión
  const [errorCierre, setErrorCierre] = useState('');

  useEffect(() => {
    let activo = true;

    obtenerUsuario()
      .then((respuesta) => {
        if (!activo) return;

        const user = respuesta.ok
          ? respuesta.datos?.user ?? null
          : null;

        setUsuario(user);
      })
      .catch(() => {
        if (activo) setUsuario(null);
      });

    const actualizarVista = () => {
      setVista(vistaInicial());
    };

    window.addEventListener('hashchange', actualizarVista);

    return () => {
      activo = false;
      window.removeEventListener('hashchange', actualizarVista);
    };
  }, []);

  function cambiarVista(nuevaVista) {
    setVista(nuevaVista);
    window.location.hash = nuevaVista === 'alta' ? 'alta' : 'catalogo';
  }

  async function salir() {
    setErrorCierre('');

    const respuesta = await cerrarSesion();

    if (respuesta.ok || respuesta.estado === 401) {
      window.location.href = URL_INGRESO;
    } else {
      setErrorCierre(
        'No se pudo cerrar la sesión. Intentá de nuevo.'
      );
    }
  }

  const esEncargado = usuario?.rol === ROL_ENCARGADO;
  const idComercio = comercioPropio(usuario);

  return (
    <div className="min-h-screen bg-slate-50 text-slate-800">
      <Encabezado
        usuario={usuario ?? null}
        onIngresar={() => {
          window.location.href = URL_INGRESO;
        }}
        onSalir={salir}
      />

      <nav
        className={C.marino}
        aria-label="Secciones del catálogo"
      >
        <div className="mx-auto flex max-w-6xl items-center gap-1 overflow-x-auto px-2">

          {usuario !== undefined && !esEncargado && (
            <a
              href="/m1.html"
              className="flex shrink-0 items-center gap-2 border-b-4 border-transparent px-4 py-3 text-sm font-bold text-white opacity-80 transition-colors hover:opacity-100 focus:outline-none focus-visible:bg-white/10"
            >
              <Home size={18} />
              Portal
            </a>
          )}

          <button
            type="button"
            onClick={() => cambiarVista('catalogo')}
            aria-current={vista === 'catalogo' ? 'page' : undefined}
            className={`flex shrink-0 items-center gap-2 border-b-4 px-4 py-3 text-sm font-bold text-white transition-colors focus:outline-none focus-visible:bg-white/10 ${
              vista === 'catalogo'
                ? 'border-[#9DBB45]'
                : 'border-transparent opacity-80 hover:opacity-100'
            }`}
          >
            <Package size={18} />
            Catálogo
          </button>

          <button
            type="button"
            onClick={() => cambiarVista('alta')}
            aria-current={vista === 'alta' ? 'page' : undefined}
            className={`flex shrink-0 items-center gap-2 border-b-4 px-4 py-3 text-sm font-bold text-white transition-colors focus:outline-none focus-visible:bg-white/10 ${
              vista === 'alta'
                ? 'border-[#9DBB45]'
                : 'border-transparent opacity-80 hover:opacity-100'
            }`}
          >
            <PlusCircle size={18} />
            Registrar producto
          </button>

          {/* Regreso al panel «Mi comercio» de M2 (/m2.html), con la misma sesión del núcleo. */}
          {esEncargado && (
            <a href="/m2.html" className={`${CLASE_BOTON_ENCABEZADO} my-2 ml-auto shrink-0`}>
              <Store className="hidden h-4 w-4 sm:block" aria-hidden="true" />
              Mi comercio
            </a>
          )}

        </div>
      </nav>

      <div className="mx-auto max-w-6xl px-4 pt-5">
        <p className="mb-4 text-xs text-slate-500">
          <a href={esEncargado ? '/m2.html' : '/m1.html'} className="hover:underline">
            Inicio
          </a>
          {' / '}
          Catálogo
          {' / '}
          <span className="font-semibold text-slate-700">
            {vista === 'alta'
              ? 'Registrar producto'
              : idComercio !== null
                ? 'Mis productos'
                : 'Consultar productos'}
          </span>
        </p>

        {errorCierre && (
          <div
            role="alert"
            className="mb-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800"
          >
            {errorCierre}
          </div>
        )}
      </div>

      {vista === 'alta' && <M3App />}

      {/* Se espera a la sesión para no mostrar al encargado, ni por un instante, el catálogo de otros comercios. */}
      {vista === 'catalogo' && (
        usuario === undefined
          ? <p className="mx-auto max-w-6xl px-4 text-sm text-slate-500">Cargando…</p>
          : <CatalogoView idComercio={idComercio} />
      )}
    </div>
  );
}
