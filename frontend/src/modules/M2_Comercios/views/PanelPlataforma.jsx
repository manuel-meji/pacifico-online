import { useState } from 'react';
import { SECCIONES_PLATAFORMA } from './gestion/secciones';

// Panel de gestión de la plataforma: las pestañas se registran en ./gestion/secciones.js,
// así otros módulos (por ejemplo, las noticias de M1) agregan la suya sin tocar este archivo.
export default function PanelPlataforma() {
  const [pestana, setPestana] = useState(SECCIONES_PLATAFORMA[0].clave);
  const { Vista } = SECCIONES_PLATAFORMA.find((p) => p.clave === pestana) ?? SECCIONES_PLATAFORMA[0];

  return (
    <div>
      <div className="mb-4 max-w-full overflow-x-auto">
        <div role="tablist" aria-label="Panel de gestión de la plataforma" className="inline-flex rounded-xl border border-slate-200 bg-white p-1">
          {SECCIONES_PLATAFORMA.map(({ clave, nombre, icono: Icono }) => (
            <button key={clave} type="button" role="tab" aria-selected={pestana === clave} onClick={() => setPestana(clave)}
              className={`flex shrink-0 items-center gap-2 rounded-lg px-4 py-2 text-sm font-bold transition-colors ${
                pestana === clave ? 'bg-[#1E3A8A] text-white' : 'text-slate-600 hover:bg-slate-100'}`}>
              <Icono size={16} /> {nombre}
            </button>
          ))}
        </div>
      </div>
      <Vista />
    </div>
  );
}
