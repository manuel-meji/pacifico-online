import { useState } from 'react';
import { Image as ImagenIcono, Images, ScrollText, PanelTop, Save, Info } from 'lucide-react';
import { Aviso, AreaTexto, Boton, Campo, Etiqueta, Tarjeta, C } from './comunes';

const Espacio = ({ icono: Icono, texto }) => (
  <div className="flex h-24 flex-col items-center justify-center gap-1 rounded-xl border-2 border-dashed border-slate-300 bg-slate-50 text-xs font-semibold text-slate-500">
    <Icono size={22} className="text-[#1E3A8A]" /> {texto}
  </div>
);

export default function PanelComercio() {
  const [estado, setEstado] = useState('Activo');
  const [motivo] = useState('Incumplimiento de políticas de la plataforma');
  const [f, setF] = useState({ descripcion: 'Pan artesanal y repostería', horario: 'Lun a Sáb, 6:00 a 18:00', facebook: 'facebook.com/panaderiadonarosa' });
  const [ok, setOk] = useState('');
  const cambiar = (e) => setF({ ...f, [e.target.name]: e.target.value });
  const editable = estado === 'Activo';

  return (
    <div className="grid gap-5 lg:grid-cols-3">
      <Tarjeta className="lg:col-span-1 lg:self-start">
        <p className="text-xs font-semibold text-slate-500">Comercio</p>
        <div className="mt-1 flex items-center justify-between gap-2">
          <h1 className={`text-xl font-extrabold ${C.marinoTexto}`}>Panadería Doña Rosa</h1>
          <Etiqueta estado={estado} />
        </div>
        <p className="text-sm text-slate-500">Cédula jurídica 3-101-999888</p>
        <label className="mt-5 block text-xs font-semibold text-slate-500">Simular estado (solo prototipo)
          <select value={estado} onChange={(e) => { setEstado(e.target.value); setOk(''); }}
            className="mt-1 w-full rounded-lg border border-slate-300 bg-white p-2.5 text-base font-normal text-slate-800">
            <option>Activo</option><option>Suspendido</option><option>Pendiente</option>
          </select>
        </label>
        {estado === 'Suspendido' && (
          <p className="mt-4 flex gap-2 rounded-lg border border-slate-300 bg-slate-50 p-3 text-sm text-slate-700">
            <Info size={18} className="mt-0.5 shrink-0" /> Tu comercio está suspendido. Motivo: {motivo}
          </p>
        )}
        {estado === 'Pendiente' && (
          <p className="mt-4 flex gap-2 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
            <Info size={18} className="mt-0.5 shrink-0" /> Tu solicitud está pendiente de revisión. Podrás editar el micrositio cuando sea aprobada.
          </p>
        )}
      </Tarjeta>

      <Tarjeta className="lg:col-span-2">
        <h2 className={`text-lg font-extrabold ${C.marinoTexto}`}>Micrositio</h2>
        <div className="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
          <Espacio icono={ImagenIcono} texto="Logotipo" />
          <Espacio icono={PanelTop} texto="Banner" />
          <Espacio icono={Images} texto="Galería" />
          <Espacio icono={ScrollText} texto="Políticas" />
        </div>
        <fieldset disabled={!editable} className="disabled:opacity-60">
          <AreaTexto etiqueta="Descripción" name="descripcion" value={f.descripcion} onChange={cambiar} />
          <div className="grid gap-x-4 sm:grid-cols-2">
            <Campo etiqueta="Horario de atención" name="horario" value={f.horario} onChange={cambiar} />
            <Campo etiqueta="Red social" name="facebook" value={f.facebook} onChange={cambiar} />
          </div>
        </fieldset>
        <Aviso mensaje={ok} />
        <Boton className="mt-5 w-full py-3 sm:w-auto" disabled={!editable} onClick={() => setOk('Cambios guardados.')}>
          <Save size={18} /> Guardar cambios
        </Boton>
      </Tarjeta>
    </div>
  );
}