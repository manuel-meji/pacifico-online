import { useCallback, useEffect, useMemo, useState } from 'react';
import { Boxes, FileText, Store, ShieldCheck } from 'lucide-react';
import { cerrarSesion, obtenerUsuario } from '../Nucleo/services/sesionApi';
import { AvisoError, C, Cargando, Encabezado, SesionContexto, textoDeError } from './components/comunes';
import SolicitudIncorporacion from './views/SolicitudIncorporacion';
import PanelComercio from './views/PanelComercio';
import PanelPlataforma from './views/PanelPlataforma';

const VISTAS = [
  { clave: 'solicitud', nombre: 'Solicitud de incorporación', corto: 'Solicitud', icono: FileText, Vista: SolicitudIncorporacion, rol: null },
  { clave: 'comercio', nombre: 'Mi comercio', corto: 'Mi comercio', icono: Store, Vista: PanelComercio, rol: 'encargado_comercio' },
  { clave: 'plataforma', nombre: 'Panel de la plataforma', corto: 'Plataforma', icono: ShieldCheck, Vista: PanelPlataforma, rol: 'encargado_plataforma' },
];
// Inicio de sesión único: los tres roles ingresan por la pantalla de M1.
const URL_INGRESO = '/app.html?pantalla=ingresar';

const vistaInicial =(usuario) =>
  usuario?.rol === 'encargado_plataforma' ? 'plataforma' : usuario?.rol === 'encargado_comercio' ? 'comercio' : 'solicitud';

export default function M2App() {
  const [usuario, setUsuario] = useState(undefined); 
  const [vista, setVista] = useState('solicitud');
  const [errorCierre, setErrorCierre] = useState('');

  useEffect(() => {
    obtenerUsuario().then((r) => {
      const user = r.ok ? r.datos?.user ?? null : null;
      setUsuario(user);
      setVista(vistaInicial(user));
    });
  }, []);

  const salir = async () => {
    setErrorCierre('');
    const respuesta = await cerrarSesion();
    // Con ok o 401 (ya no había sesión) se va al inicio de sesión único. Si falló por conexión se conserva la sesión.
    if (respuesta.ok || respuesta.estado === 401) window.location.href = URL_INGRESO;
    else setErrorCierre('No se pudo cerrar la sesión. Intente de nuevo.');
  };

 
  const mensajeDeError = useCallback((resultado) => {
    if (resultado && !resultado.ok && resultado.estado === 401) {
      window.location.href = URL_INGRESO;
    }
    return textoDeError(resultado);
  }, []);

  const contexto = useMemo(() => ({ usuario: usuario ?? null, mensajeDeError }), [usuario, mensajeDeError]);

  const visibles = VISTAS.filter((v) => v.rol === null || v.rol === usuario?.rol);
  // Sin sesión (o sin vista propia del rol) se ve la solicitud de incorporación, la única pública.
  const actual = visibles.find((v) => v.clave === vista) ?? visibles[0];

  let contenido;
  if (usuario === undefined) {
    contenido = <Cargando texto="Verificando la sesión…" />;
  } else {
    const { Vista } = actual;
    contenido = <Vista />;
  }

  return (
    <SesionContexto.Provider value={contexto}>
      <div className="min-h-screen bg-slate-50 text-slate-800">
        <Encabezado usuario={usuario} onIngresar={() => { window.location.href = URL_INGRESO; }} onSalir={salir} />
        <nav className={C.marino} aria-label="Secciones de comercios">
          <div className="mx-auto flex max-w-6xl gap-1 overflow-x-auto px-2">
            {visibles.map(({ clave, corto, icono: Icono }) => (
              <button key={clave} type="button" onClick={() => setVista(clave)}
                aria-current={clave === vista ? 'page' : undefined}
                className={`flex shrink-0 items-center gap-2 border-b-4 px-4 py-3 text-sm font-bold text-white transition-colors focus:outline-none focus-visible:bg-white/10 ${
                  clave === vista ? 'border-[#9DBB45]' : 'border-transparent opacity-80 hover:opacity-100'}`}>
                <Icono size={18} /> {corto}
              </button>
            ))}
            {/* Enlace al inventario de M4 (/m4.html): el encargado del comercio entra con la misma sesión del núcleo. */}
            {usuario?.rol === 'encargado_comercio' && (
              <a href="/m4.html"
                className="flex shrink-0 items-center gap-2 border-b-4 border-transparent px-4 py-3 text-sm font-bold text-white opacity-80 transition-colors hover:opacity-100 focus:outline-none focus-visible:bg-white/10">
                <Boxes size={18} /> Inventario
              </a>
            )}
          </div>
        </nav>
        <main className="mx-auto max-w-6xl px-4 py-6">
          <p className="mb-4 text-xs text-slate-500">
            Inicio / Comercios / <span className="font-semibold text-slate-700">{actual.nombre}</span>
          </p>
          <AvisoError mensaje={errorCierre} />
          {contenido}
        </main>
      </div>
    </SesionContexto.Provider>
  );
}