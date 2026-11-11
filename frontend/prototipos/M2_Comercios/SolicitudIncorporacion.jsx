import { useState } from 'react';
import { Upload, Send } from 'lucide-react';
import { Aviso, AvisoError, AreaTexto, Boton, Campo, Tarjeta, C } from './comunes';

export default function SolicitudIncorporacion({ onRegistrar }) {
  const [f, setF] = useState({ nombre: '', cedula: '', correo: '', telefono: '', descripcion: '', contribuyente: false });
  const [error, setError] = useState('');
  const [ok, setOk] = useState('');
  const cambiar = (e) => setF({ ...f, [e.target.name]: e.target.type === 'checkbox' ? e.target.checked : e.target.value });

  const enviar = () => {
    setOk('');
    if (!f.nombre || !f.cedula || !f.correo || !f.descripcion) return setError('Revisa los datos marcados: faltan campos obligatorios.');
    if (!f.contribuyente) return setError('Debes estar inscrito como contribuyente ante Hacienda para incorporarte.');
    setError('');
    onRegistrar?.(f);
    setOk('Solicitud enviada. Queda pendiente de revisión por la plataforma.');
  };

  return (
    <Tarjeta className="mx-auto max-w-2xl">
      <h1 className={`text-2xl font-extrabold ${C.marinoTexto}`}>Solicitud de incorporación</h1>
      <p className="mt-1 text-sm text-slate-500">Completa los datos de tu comercio. Los campos con * son obligatorios.</p>
      <div className="grid gap-x-4 sm:grid-cols-2">
        <Campo etiqueta="Nombre comercial *" name="nombre" value={f.nombre} onChange={cambiar} />
        <Campo etiqueta="Cédula jurídica *" name="cedula" placeholder="3-101-123456" value={f.cedula} onChange={cambiar} />
        <Campo etiqueta="Correo *" name="correo" type="email" value={f.correo} onChange={cambiar} />
        <Campo etiqueta="Teléfono" name="telefono" type="tel" value={f.telefono} onChange={cambiar} />
      </div>
      <AreaTexto etiqueta="Descripción *" name="descripcion" value={f.descripcion} onChange={cambiar} />
      <label className="mt-4 block text-sm font-semibold text-slate-700">Documentos (cédula jurídica, patente)
        <span className="mt-1 flex cursor-pointer items-center gap-3 rounded-lg border-2 border-dashed border-slate-300 bg-slate-50 p-4 text-sm font-normal text-slate-500 hover:border-[#1E3A8A]">
          <Upload size={20} className="text-[#1E3A8A]" /> Selecciona los archivos
          <input type="file" multiple className="sr-only" />
        </span>
      </label>
      <label className="mt-4 flex items-center gap-2 text-sm text-slate-700">
        <input type="checkbox" name="contribuyente" checked={f.contribuyente} onChange={cambiar} className="h-4 w-4 accent-[#1E3A8A]" />
        Estoy inscrito como contribuyente ante Hacienda
      </label>
      <AvisoError mensaje={error} />
      <Aviso mensaje={ok} />
      <Boton className="mt-5 w-full py-3 text-base" onClick={enviar}><Send size={18} /> Enviar solicitud</Boton>
    </Tarjeta>
  );
}