import { useState } from 'react';
import { FileText, Sparkles, Store } from 'lucide-react';
import SolicitudesPlataforma from '../components/SolicitudesPlataforma';
import ComerciosPlataforma from '../components/ComerciosPlataforma';
import DestacadosPlataforma from '../components/DestacadosPlataforma';

const PESTANAS = [
  { clave: 'solicitudes', nombre: 'Solicitudes', icono: FileText, Vista: SolicitudesPlataforma },
  { clave: 'comercios', nombre: 'Comercios', icono: Store, Vista: ComerciosPlataforma },
  { clave: 'destacados', nombre: 'Destacados', icono: Sparkles, Vista: DestacadosPlataforma },
];

export default function PanelPlataforma() {
  const [pestana, setPestana] = useState('solicitudes');
  const { Vista } = PESTANAS.find((p) => p.clave === pestana);

  return (
    <div>
      <div role="tablist" aria-label="Panel de la plataforma" className="mb-4 inline-flex rounded-xl border border-slate-200 bg-white p-1">
        {PESTANAS.map(({ clave, nombre, icono: Icono }) => (
          <button key={clave} type="button" role="tab" aria-selected={pestana === clave} onClick={() => setPestana(clave)}
            className={`flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-bold transition-colors ${
              pestana === clave ? 'bg-[#1E3A8A] text-white' : 'text-slate-600 hover:bg-slate-100'}`}>
            <Icono size={16} /> {nombre}
          </button>
        ))}
      </div>
      <Vista />
    </div>
  );
}