// Contenedor del prototipo de M4: encabezado, las tres pantallas y navegación inferior sticky (hallazgo H6).
// Mobile-first: una sola columna de ancho máximo 672 px (max-w-2xl). Estado de existencias en memoria (doble de la API).
// simular='suspendido' fuerza ERR-M4-015 y simular='m2' fuerza ERR-006 para validar los estados de error (sección 8.1).
import { useMemo, useRef, useState } from 'react';
import { IconoHistorial, IconoPaquete, IconoPortapapeles } from './components/iconos';
import GestionOperacionesInventario from './views/GestionOperacionesInventario';
import FichaInventarioKardex from './views/FichaInventarioKardex';
import ReservasPedidos from './views/ReservasPedidos';
import { crearApi } from './services/inventarioApi';
import { existenciasIniciales } from './services/datosMock';

const PANTALLAS = [
  { id: 'operaciones', nombre: 'Operaciones', Icono: IconoPortapapeles },
  { id: 'reservas', nombre: 'Reservas', Icono: IconoPaquete },
  { id: 'ficha', nombre: 'Ficha y Kardex', Icono: IconoHistorial },
];

export default function M4App({ simular = null }) {
  const api = useMemo(() => crearApi({ simular }), [simular]);
  const existencias = useRef(existenciasIniciales.map((e) => ({ ...e })));
  const [version, setVersion] = useState(0); // sube cada vez que una operación cambia las existencias
  const [pantalla, setPantalla] = useState('operaciones');
  const alCambiar = () => setVersion((v) => v + 1);

  return (
    <div className="mx-auto flex min-h-screen w-full max-w-2xl flex-col bg-white shadow-sm">
      <header className="bg-brandDarkBlue px-4 py-3 text-white">
        <p className="text-xs text-white/70">Pacíficos Online · Comercio 104</p>
        <h1 className="text-lg font-bold">Inventario</h1>
      </header>

      <main className="flex-1 bg-brandLightBg p-4">
        {pantalla === 'operaciones' && <GestionOperacionesInventario api={api} existencias={existencias.current} alCambiar={alCambiar} />}
        {pantalla === 'reservas' && <ReservasPedidos api={api} existencias={existencias.current} version={version} alCambiar={alCambiar} />}
        {pantalla === 'ficha' && <FichaInventarioKardex api={api} existencias={existencias.current} version={version} />}
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
