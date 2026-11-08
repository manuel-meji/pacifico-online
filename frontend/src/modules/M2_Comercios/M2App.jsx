import { useCallback, useEffect, useMemo, useState } from 'react';
import { FileText, Store, ShieldCheck } from 'lucide-react';
import { cerrarSesion, obtenerUsuario } from '../Nucleo/services/sesionApi';
import { C, Cargando, Encabezado, SesionContexto, textoDeError } from './components/comunes';
import Ingreso from './views/Ingreso';
import SolicitudIncorporacion from './views/SolicitudIncorporacion';
import PanelComercio from './views/PanelComercio';
import PanelPlataforma from './views/PanelPlataforma';

const VISTAS = [
  { clave: 'solicitud', nombre: 'Solicitud de incorporación', corto: 'Solicitud', icono: FileText, Vista: SolicitudIncorporacion, rol: null },
  { clave: 'comercio', nombre: 'Mi comercio', corto: 'Mi comercio', icono: Store, Vista: PanelComercio, rol: 'encargado_comercio' },
  { clave: 'plataforma', nombre: 'Panel de la plataforma', corto: 'Plataforma', icono: ShieldCheck, Vista: PanelPlataforma, rol: 'encargado_plataforma' },
];


const vistaInicial = (usuario) =>
  usuario?.rol === 'encargado_plataforma' ? 'plataforma' : usuario?.rol === 'encargado_comercio' ? 'comercio' : 'solicitud';

export default function M2App() {
  const [usuario, setUsuario] = useState(undefined); 
  const [vista, setVista] = useState('solicitud');

  useEffect(() => {
    obtenerUsuario().then((r) => {
      const user = r.ok ? r.datos?.user ?? null : null;
      setUsuario(user);
      setVista(vistaInicial(user));
    });
  }, []);

  const alIngresar = (user) => {
    setUsuario(user);
    setVista(vistaInicial(user));
  };

  const salir = async () => {
    await cerrarSesion();
    setUsuario(null);
    setVista('solicitud');
  };

 
  const mensajeDeError = useCallback((resultado) => {
    if (resultado && !resultado.ok && resultado.estado === 401) {
      setUsuario(null);
      setVista('ingreso');
    }
    return textoDeError(resultado);
  }, []);

  const contexto = useMemo(() => ({ usuario: usuario ?? null, mensajeDeError }), [usuario, mensajeDeError]);

  const visibles = VISTAS.filter((v) => v.rol === null || v.rol === usuario?.rol);
  const actual = visibles.find((v) => v.clave === vista);

  let contenido;
  if (usuario === undefined) {
    contenido = <Cargando texto="Verificando la sesión…" />;
  } else if (vista === 'ingreso' || !actual) {
    contenido = <Ingreso onIngreso={alIngresar} />;
  } else {
    const { Vista } = actual;
    contenido = <Vista />;
  }

  return (
    <SesionContexto.Provider value={contexto}>
      <div className="min-h-screen bg-slate-50 text-slate-800">
        <Encabezado usuario={usuario} onIngresar={() => setVista('ingreso')} onSalir={salir} />
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
          </div>
        </nav>
        <main className="mx-auto max-w-6xl px-4 py-6">
          <p className="mb-4 text-xs text-slate-500">
            Inicio / Comercios / <span className="font-semibold text-slate-700">{actual && vista !== 'ingreso' ? actual.nombre : 'Ingresar'}</span>
          </p>
          {contenido}
        </main>
      </div>
    </SesionContexto.Provider>
  );
}