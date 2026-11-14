
import { useEffect, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { Home, Package, PlusCircle } from 'lucide-react';

import '../../index.css';

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

function vistaInicial() {
  return window.location.hash === '#alta' ? 'alta' : 'catalogo';
}

function M3AppPrincipal() {
  const [vista, setVista] = useState(vistaInicial);
  const [usuario, setUsuario] = useState(undefined);
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
        <div className="mx-auto flex max-w-6xl gap-1 overflow-x-auto px-2">

          <a
            href="/m1.html"
            className="flex shrink-0 items-center gap-2 border-b-4 border-transparent px-4 py-3 text-sm font-bold text-white opacity-80 transition-colors hover:opacity-100 focus:outline-none focus-visible:bg-white/10"
          >
            <Home size={18} />
            Portal
          </a>

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

        </div>
      </nav>

      <div className="mx-auto max-w-6xl px-4 pt-5">
        <p className="mb-4 text-xs text-slate-500">
          <a href="/m1.html" className="hover:underline">
            Inicio
          </a>
          {' / '}
          Catálogo
          {' / '}
          <span className="font-semibold text-slate-700">
            {vista === 'catalogo'
              ? 'Consultar productos'
              : 'Registrar producto'}
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

      {vista === 'catalogo' ? (
        <CatalogoView />
      ) : (
        <M3App />
      )}
    </div>
  );
}

createRoot(document.getElementById('root')).render(
  <M3AppPrincipal />
);
