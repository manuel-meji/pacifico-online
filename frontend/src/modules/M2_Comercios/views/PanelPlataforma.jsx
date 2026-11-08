import { C, Tarjeta } from '../components/comunes';

export default function PanelPlataforma() {
  return (
    <Tarjeta>
      <h1 className={`text-2xl font-extrabold ${C.marinoTexto}`}>Panel de la plataforma</h1>
      <p className="mt-2 text-sm text-slate-500">Esta pantalla está en construcción.</p>
    </Tarjeta>
  );
}