// Interfaz de M4 (PC4): pantallas del encargado del comercio conectadas a la API /api/m4/v1.
// Mobile-first: una sola columna de ancho máximo 672 px (max-w-2xl), operable desde 360 px.
// Sin sesión del núcleo se muestra el inicio de sesión; el comercio sale de la sesión (RN-12), nunca de la URL.
import { useCallback, useEffect, useMemo, useState } from 'react';
import { IconoHistorial, IconoPaquete, IconoPortapapeles } from './components/iconos';
import { Aviso } from './components/componentes';
import GestionOperacionesInventario from './views/GestionOperacionesInventario';
import ExistenciasMinimos from './views/ExistenciasMinimos';
import FichaInventarioKardex from './views/FichaInventarioKardex';
import IniciarSesion from './views/IniciarSesion';
import { cerrarSesion, crearApi, usuarioActual } from './services/inventarioApi';

const PANTALLAS = [
  { id: 'operaciones', nombre: 'Operaciones', Icono: IconoPortapapeles },
  { id: 'existencias', nombre: 'Existencias', Icono: IconoPaquete },
  { id: 'kardex', nombre: 'Kardex', Icono: IconoHistorial },
];

export default function M4App() {
  const [usuario, setUsuario] = useState(undefined); // undefined: cargando; null: sin sesión
  const [error, setError] = useState(null);
  const [pantalla, setPantalla] = useState('operaciones');
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

  async function salir() {
    await cerrarSesion().catch(() => null);
    setUsuario(null);
  }

  if (usuario === undefined) return <p className="p-4 text-sm text-gray-500">Cargando…</p>;
  if (usuario === null) return <IniciarSesion alIniciar={setUsuario} />;

  return (
    <div className="mx-auto flex min-h-screen w-full max-w-2xl flex-col bg-white shadow-sm">
      <header className="flex items-center justify-between gap-2 bg-brandDarkBlue px-4 py-3 text-white">
        <div className="min-w-0">
          <p className="truncate text-xs text-white/70">Pacíficos Online · Comercio {usuario.id_comercio ?? '—'} · {usuario.nombre}</p>
          <h1 className="text-lg font-bold">Inventario</h1>
        </div>
        <button type="button" onClick={salir} className="shrink-0 rounded-lg border border-white/40 px-3 py-1.5 text-xs font-semibold hover:bg-white/10">Salir</button>
      </header>

      <main className="flex-1 space-y-3 bg-brandLightBg p-4">
        {!api && (
          <Aviso tipo="error" titulo="Sin comercio en la sesión" codigo="ERR-002">
            Esta pantalla es para el encargado de un comercio. Su usuario no tiene un comercio asignado.
          </Aviso>
        )}
        {error && <Aviso tipo="error" titulo="No se pudieron cargar las existencias" codigo={error.codigo}>{error.mensaje}</Aviso>}
        {api && pantalla === 'operaciones' && <GestionOperacionesInventario api={api} existencias={existencias} sedes={sedes} alCambiar={alCambiar} />}
        {api && pantalla === 'existencias' && <ExistenciasMinimos api={api} existencias={existencias} sedes={sedes} alCambiar={alCambiar} />}
        {api && pantalla === 'kardex' && <FichaInventarioKardex api={api} existencias={existencias} sedes={sedes} version={version} />}
      </main>

      <nav aria-label="Pantallas de inventario" className="sticky bottom-0 grid grid-cols-3 border-t border-gray-200 bg-white">
        {PANTALLAS.map(({ id, nombre, Icono }) => (
          <button key={id} type="button" onClick={() => setPantalla(id)} aria-current={pantalla === id ? 'page' : undefined}
            className={`flex min-w-0 flex-col items-center gap-0.5 px-2 py-2.5 text-xs font-semibold ${pantalla === id ? 'text-brandDarkBlue' : 'text-gray-500 hover:text-brandBlue'}`}>
            <Icono className="h-5 w-5" />
            <span className="truncate">{nombre}</span>
          </button>
        ))}
      </nav>
    </div>
  );
}
