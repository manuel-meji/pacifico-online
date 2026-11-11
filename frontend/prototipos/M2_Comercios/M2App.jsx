import { useState } from 'react';
import { FileText, Store, ShieldCheck } from 'lucide-react';
import { C, Encabezado } from './comunes';
import SolicitudIncorporacion from './SolicitudIncorporacion';
import PanelComercio from './PanelComercio';
import PanelPlataforma from './PanelPlataforma';

const VISTAS = [
  { nombre: 'Solicitud de incorporación', corto: 'Solicitud', icono: FileText, Vista: SolicitudIncorporacion },
  { nombre: 'Mi comercio', corto: 'Mi comercio', icono: Store, Vista: PanelComercio },
  { nombre: 'Panel de la plataforma', corto: 'Plataforma', icono: ShieldCheck, Vista: PanelPlataforma },
];

// Prototipo M2 (SG2). Navegación por pestañas con estado local; sin backend.
export default function M2Prototipo() {
  const [i, setI] = useState(0);
  const { Vista, nombre } = VISTAS[i];
  return (
    <div className="min-h-screen bg-slate-50 text-slate-800">
      <Encabezado />
      <nav className={`${C.marino}`}>
        <div className="mx-auto flex max-w-6xl gap-1 overflow-x-auto px-2">
          {VISTAS.map(({ corto, icono: Icono }, k) => (
            <button key={corto} onClick={() => setI(k)}
              className={`flex shrink-0 items-center gap-2 border-b-4 px-4 py-3 text-sm font-bold text-white transition-colors ${
                k === i ? 'border-[#9DBB45]' : 'border-transparent opacity-80 hover:opacity-100'}`}>
              <Icono size={18} /> {corto}
            </button>
          ))}
        </div>
      </nav>
      <main className="mx-auto max-w-6xl px-4 py-6">
        <p className="mb-4 text-xs text-slate-500">Inicio / Comercios / <span className="font-semibold text-slate-700">{nombre}</span></p>
        <Vista />
      </main>
    </div>
  );
}