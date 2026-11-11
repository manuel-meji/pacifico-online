// Interfaz de M4 (PC4): pantallas del encargado del comercio conectadas a la API /api/m4/v1.
// Diseño adaptable:
//  - Celular (menos de 768 px): una columna y navegación inferior con 4 accesos (Operaciones, Existencias, Kardex
//    y «Más», que abre Transferencias, Inventario físico y Reportes). Operable desde 360 px y con zoom al 200 %.
//  - Tablet y computadora (768 px o más): menú lateral fijo con las 6 pantallas y contenido ancho (max-w-6xl).
// Sin sesión del núcleo se muestra el inicio de sesión; el comercio sale de la sesión (RN-12), nunca de la URL.
import { useCallback, useEffect, useMemo, useState } from 'react';
import {
  IconoCerrarSesion, IconoGrafico, IconoHistorial, IconoMas, IconoPaquete, IconoPortapapeles, IconoPortapapelesCheck,
  IconoTienda, IconoTransferir,
} from './components/iconos';
import { Aviso } from './components/componentes';
import GestionOperacionesInventario from './views/GestionOperacionesInventario';
import ExistenciasMinimos from './views/ExistenciasMinimos';
import FichaInventarioKardex from './views/FichaInventarioKardex';
import Transferencias from './views/Transferencias';
import InventarioFisico from './views/InventarioFisico';
import Reportes from './views/Reportes';
import IniciarSesion from './views/IniciarSesion';
import { cerrarSesion, crearApi, usuarioActual } from './services/inventarioApi';

const PANTALLAS = [
  { id: 'operaciones', nombre: 'Operaciones', descripcion: 'Entradas y salidas manuales', Icono: IconoPortapapeles, principal: true },
  { id: 'existencias', nombre: 'Existencias', descripcion: 'Disponible y stock mínimo', Icono: IconoPaquete, principal: true },
  { id: 'kardex', nombre: 'Kardex', descripcion: 'Movimientos por variación', Icono: IconoHistorial, principal: true },
  { id: 'transferencias', nombre: 'Transferencias', descripcion: 'Mover unidades entre sedes', Icono: IconoTransferir },
  { id: 'fisico', nombre: 'Inventario físico', descripcion: 'Conteo y ajuste autorizado', Icono: IconoPortapapelesCheck },
  { id: 'reportes', nombre: 'Reportes', descripcion: 'Kardex valorizado del periodo', Icono: IconoGrafico },
];
const PRINCIPALES = PANTALLAS.filter((p) => p.principal);
const SECUNDARIAS = PANTALLAS.filter((p) => !p.principal);

const claseBotonEncabezado = 'inline-flex items-center gap-1.5 rounded-lg border border-white/40 px-3 py-1.5 text-xs font-semibold text-white hover:bg-white/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-white/60';

export default function M4App() {
  const [usuario, setUsuario] = useState(undefined); // undefined: cargando; null: sin sesión
  const [error, setError] = useState(null);
  const [pantalla, setPantalla] = useState('operaciones');
  const [menuMas, setMenuMas] = useState(false);
  const [existencias, setExistencias] = useState([]);
  const [sedes, setSedes] = useState([]);
  const [version, setVersion] = useState(0);

  useEffect(() => {
    usuarioActual().then(setUsuario).catch((f) => { setUsuario(null); setError(f.error); });
  }, []);

  const api = useMemo(() => (usuario?.id_comercio ? crearApi(usuario.id_comercio) : null), [usuario]);

  const recargar = useCallback(async () => {
    if (!api) return;
    try {
      const [respuesta, listaSedes] = await Promise.all([api.listarExistencias(), api.consultarSedes()]);
      setExistencias(respuesta.datos);
      setSedes(listaSedes);
      setError(null);
    } catch (falla) {
      setError(falla.error);
    }
  }, [api]);

  useEffect(() => { recargar(); }, [recargar, version]);
  const alCambiar = () => setVersion((v) => v + 1);

  // Cierra el menú «Más» con Escape.
  useEffect(() => {
    if (!menuMas) return undefined;
    const alTeclear = (e) => { if (e.key === 'Escape') setMenuMas(false); };
    window.addEventListener('keydown', alTeclear);
    return () => window.removeEventListener('keydown', alTeclear);
  }, [menuMas]);

  function ir(id) {
    setPantalla(id);
    setMenuMas(false);
    window.scrollTo({ top: 0 });
  }

  async function salir() {
    await cerrarSesion().catch(() => null);
    setUsuario(null);
  }

  if (usuario === undefined) return <p className="p-4 text-sm text-gray-500">Cargando…</p>;
  if (usuario === null) return <IniciarSesion alIniciar={setUsuario} />;

  const actual = PANTALLAS.find((p) => p.id === pantalla);
  const secundariaActiva = SECUNDARIAS.some((p) => p.id === pantalla);
  const props = { api, existencias, sedes, alCambiar };

  return (
    <div className="min-h-screen bg-brandLightBg md:flex">
      {/* Menú lateral (768 px o más) */}
      <aside className="hidden md:sticky md:top-0 md:flex md:h-screen md:w-60 md:shrink-0 md:flex-col md:bg-brandDarkBlue md:text-white lg:w-64">
        <div className="border-b border-white/10 px-5 py-5">
          <p className="text-xs font-semibold uppercase tracking-wider text-white/60">Pacíficos Online</p>
          <p className="mt-1 text-xl font-bold">Inventario</p>
        </div>
        <nav aria-label="Pantallas de inventario" className="flex-1 space-y-1 overflow-y-auto p-3">
          {PANTALLAS.map(({ id, nombre, descripcion, Icono }) => (
            <button key={id} type="button" onClick={() => ir(id)} aria-current={pantalla === id ? 'page' : undefined}
              className={`flex w-full items-start gap-3 rounded-lg px-3 py-2.5 text-left transition focus:outline-none focus-visible:ring-2 focus-visible:ring-white/60 ${pantalla === id ? 'bg-white text-brandDarkBlue shadow-sm' : 'text-white/85 hover:bg-white/10 hover:text-white'}`}>
              <Icono className="mt-0.5 h-5 w-5 shrink-0" />
              <span className="min-w-0">
                <span className="block text-sm font-semibold">{nombre}</span>
                <span className={`block text-xs ${pantalla === id ? 'text-gray-500' : 'text-white/60'}`}>{descripcion}</span>
              </span>
            </button>
          ))}
        </nav>
        <div className="border-t border-white/10 p-3 text-xs text-white/70">
          <p className="truncate">{usuario.nombre}</p>
          <p>Comercio {usuario.id_comercio ?? '—'}</p>
        </div>
      </aside>

      <div className="flex min-h-screen min-w-0 flex-1 flex-col">
        <header className="sticky top-0 z-20 bg-brandDarkBlue text-white shadow-sm">
          <div className="mx-auto flex w-full max-w-6xl items-center justify-between gap-2 px-4 py-3 md:px-6 lg:px-8">
            <div className="min-w-0">
              <p className="truncate text-xs text-white/70 md:hidden">Pacíficos Online · Comercio {usuario.id_comercio ?? '—'} · {usuario.nombre}</p>
              <h1 className="truncate text-lg font-bold md:text-xl">
                <span className="md:hidden">Inventario</span>
                <span className="hidden md:inline">{actual.nombre}</span>
              </h1>
            </div>
            <div className="flex shrink-0 items-center gap-2">
              {/* Regreso al panel «Mi comercio» de M2 (/m2.html), con la misma sesión del núcleo. */}
              <a href="/m2.html" className={claseBotonEncabezado}><IconoTienda className="hidden h-4 w-4 sm:block" />Mi comercio</a>
              <button type="button" onClick={salir} className={claseBotonEncabezado}><IconoCerrarSesion className="hidden h-4 w-4 sm:block" />Salir</button>
            </div>
          </div>
        </header>

        <main className="mx-auto w-full max-w-6xl flex-1 space-y-3 px-4 pb-24 pt-4 md:px-6 md:pb-8 md:pt-6 lg:px-8">
          <h2 className="text-base font-bold text-gray-900 md:hidden">{actual.nombre}</h2>
          {!api && (
            <Aviso tipo="error" titulo="Sin comercio en la sesión" codigo="ERR-002">
              Esta pantalla es para el encargado de un comercio. Su usuario no tiene un comercio asignado.
            </Aviso>
          )}
          {error && <Aviso tipo="error" titulo="No se pudieron cargar las existencias" codigo={error.codigo}>{error.mensaje}</Aviso>}
          {api && pantalla === 'operaciones' && <GestionOperacionesInventario {...props} />}
          {api && pantalla === 'existencias' && <ExistenciasMinimos {...props} />}
          {api && pantalla === 'kardex' && <FichaInventarioKardex {...props} version={version} />}
          {api && pantalla === 'transferencias' && <Transferencias {...props} />}
          {api && pantalla === 'fisico' && <InventarioFisico {...props} />}
          {api && pantalla === 'reportes' && <Reportes {...props} />}
        </main>
      </div>

      {/* Navegación inferior (menos de 768 px): 3 pantallas y «Más» con las otras 3 */}
      {menuMas && <button type="button" aria-label="Cerrar menú" className="fixed inset-0 z-30 bg-black/30 md:hidden" onClick={() => setMenuMas(false)} />}
      {menuMas && (
        <div id="menu-mas" role="menu" aria-label="Más pantallas" className="fixed inset-x-0 bottom-[calc(4rem+env(safe-area-inset-bottom))] z-40 mx-3 rounded-xl border border-gray-100 bg-white p-2 shadow-lg md:hidden">
          {SECUNDARIAS.map(({ id, nombre, descripcion, Icono }) => (
            <button key={id} type="button" role="menuitem" onClick={() => ir(id)} aria-current={pantalla === id ? 'page' : undefined}
              className={`flex w-full items-center gap-3 rounded-lg px-3 py-3 text-left ${pantalla === id ? 'bg-brandLightBg text-brandDarkBlue' : 'text-gray-800 hover:bg-gray-50'}`}>
              <Icono className="h-5 w-5 shrink-0" />
              <span className="min-w-0">
                <span className="block text-sm font-semibold">{nombre}</span>
                <span className="block truncate text-xs text-gray-500">{descripcion}</span>
              </span>
            </button>
          ))}
        </div>
      )}
      <nav aria-label="Pantallas de inventario" className="fixed inset-x-0 bottom-0 z-40 grid grid-cols-4 border-t border-gray-200 bg-white pb-[env(safe-area-inset-bottom)] md:hidden">
        {PRINCIPALES.map(({ id, nombre, Icono }) => (
          <button key={id} type="button" onClick={() => ir(id)} aria-current={pantalla === id ? 'page' : undefined}
            className={`flex min-h-16 min-w-0 flex-col items-center justify-center gap-0.5 px-1 py-2 text-xs font-semibold ${pantalla === id ? 'text-brandDarkBlue' : 'text-gray-500 hover:text-brandBlue'}`}>
            <Icono className="h-5 w-5" />
            <span className="max-w-full truncate">{nombre}</span>
          </button>
        ))}
        <button type="button" onClick={() => setMenuMas((m) => !m)} aria-expanded={menuMas} aria-controls="menu-mas" aria-haspopup="menu"
          className={`flex min-h-16 min-w-0 flex-col items-center justify-center gap-0.5 px-1 py-2 text-xs font-semibold ${secundariaActiva || menuMas ? 'text-brandDarkBlue' : 'text-gray-500 hover:text-brandBlue'}`}>
          {secundariaActiva && !menuMas ? <actual.Icono className="h-5 w-5" /> : <IconoMas className="h-5 w-5" />}
          <span className="max-w-full truncate">{secundariaActiva && !menuMas ? actual.nombre : 'Más'}</span>
        </button>
      </nav>
    </div>
  );
}
