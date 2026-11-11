import { useState } from 'react';
import { ArrowLeft, CheckCircle2, XCircle, PlusCircle, Ban, RotateCcw, Check, X, Info } from 'lucide-react';
import { Aviso, AvisoError, Boton, Etiqueta, Tarjeta, SOLICITUDES, C } from './comunes';
import SolicitudIncorporacion from './SolicitudIncorporacion';

export default function PanelPlataforma() {
  const [items, setItems] = useState(SOLICITUDES);
  const [selId, setSelId] = useState(null);
  const [accion, setAccion] = useState(null); // 'rechazar' | 'suspender'
  const [motivo, setMotivo] = useState('');
  const [error, setError] = useState('');
  const [ok, setOk] = useState('');
  const [registrando, setRegistrando] = useState(false);
  const sel = items.find((i) => i.id === selId);

  const cerrar = () => { setAccion(null); setMotivo(''); setError(''); };
  const cambiar = (estado, extra = {}) => {
    setItems(items.map((i) => (i.id === selId ? { ...i, estado, ...extra } : i)));
    setOk(`${sel.comercio}: ${estado}.`);
    cerrar();
  };
  const aprobar = () => {
    if (!sel.completa) return setError('La información de la solicitud está incompleta.');
    if (!sel.contribuyente) return setError('El comercio no está inscrito como contribuyente ante Hacienda.');
    if (sel.docs.some((d) => !d.ok)) return setError('Hay documentos incompletos o vencidos. Solicita la corrección.');
    cambiar('Activo');
  };
  const confirmar = () => {
    if (!motivo.trim()) return setError('El motivo es obligatorio.');
    cambiar(accion === 'rechazar' ? 'Rechazado' : 'Suspendido', { motivo });
  };
  const registrar = (f) => {
    setItems([...items, { id: Date.now(), comercio: f.nombre, cedula: f.cedula, completa: true, contribuyente: f.contribuyente, estado: 'Pendiente', docs: [{ nombre: 'Cédula jurídica', ok: true }] }]);
    setOk(`Solicitud de ${f.nombre} registrada como Pendiente.`);
    setRegistrando(false);
  };
  const Volver = ({ onClick }) => (
    <button className={`mb-4 inline-flex items-center gap-1 text-sm font-semibold ${C.marinoTexto} hover:underline`} onClick={onClick}>
      <ArrowLeft size={16} /> Volver al listado
    </button>
  );

  if (registrando) {
    return (<div><Volver onClick={() => setRegistrando(false)} /><SolicitudIncorporacion onRegistrar={registrar} /></div>);
  }

  if (!sel) {
    return (
      <Tarjeta>
        <div className="flex flex-wrap items-center justify-between gap-3">
          <h1 className={`text-2xl font-extrabold ${C.marinoTexto}`}>Solicitudes y comercios</h1>
          <Boton variante="secundario" onClick={() => { setRegistrando(true); setOk(''); }}><PlusCircle size={18} /> Registrar solicitud</Boton>
        </div>
        <Aviso mensaje={ok} />
        <ul className="mt-4 divide-y divide-slate-200 rounded-xl border border-slate-200">
          {items.map((i) => (
            <li key={i.id} className="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
              <div className="min-w-0">
                <p className="font-semibold text-slate-800">{i.comercio}</p>
                <p className="text-xs text-slate-500">Cédula jurídica {i.cedula}</p>
              </div>
              <div className="flex shrink-0 items-center justify-between gap-3 sm:justify-end">
                <Etiqueta estado={i.estado} />
                <Boton variante="neutro" className="py-2" onClick={() => { setSelId(i.id); setOk(''); cerrar(); }}>Ver</Boton>
              </div>
            </li>
          ))}
        </ul>
      </Tarjeta>
    );
  }

  return (
    <div>
      <Volver onClick={() => { setSelId(null); cerrar(); }} />
      <div className="grid gap-5 lg:grid-cols-3">
        <Tarjeta className="lg:col-span-2">
          <div className="flex flex-wrap items-center justify-between gap-2">
            <h1 className={`text-2xl font-extrabold ${C.marinoTexto}`}>{sel.comercio}</h1>
            <Etiqueta estado={sel.estado} />
          </div>
          <p className="text-sm text-slate-500">Cédula jurídica {sel.cedula}</p>
          <h2 className="mt-5 text-sm font-bold text-slate-700">Documentos</h2>
          <ul className="mt-2 space-y-2">
            {sel.docs.map((d) => (
              <li key={d.nombre} className={`flex items-center gap-2 rounded-lg p-2.5 text-sm ${d.ok ? 'bg-green-50 text-green-800' : 'bg-red-50 text-red-700'}`}>
                {d.ok ? <CheckCircle2 size={18} /> : <XCircle size={18} />} {d.nombre}
              </li>
            ))}
          </ul>
          {sel.motivo && <p className="mt-4 text-sm text-slate-700"><span className="font-semibold">Motivo registrado:</span> {sel.motivo}</p>}
        </Tarjeta>

        <Tarjeta>
          <h2 className="text-sm font-bold text-slate-700">Acciones</h2>
          <Aviso mensaje={ok} />
          {!accion && sel.estado === 'Pendiente' && (
            <div className="mt-3 grid gap-2">
              <Boton className="py-3" onClick={aprobar}><Check size={18} /> Aprobar</Boton>
              <Boton variante="peligro" className="py-3" onClick={() => { setAccion('rechazar'); setError(''); }}><X size={18} /> Rechazar</Boton>
            </div>
          )}
          {!accion && sel.estado === 'Activo' && (
            <Boton variante="peligro" className="mt-3 w-full py-3" onClick={() => { setAccion('suspender'); setError(''); }}><Ban size={18} /> Suspender comercio</Boton>
          )}
          {!accion && sel.estado === 'Suspendido' && (
            <Boton variante="secundario" className="mt-3 w-full py-3" onClick={() => cambiar('Activo', { motivo: undefined })}><RotateCcw size={18} /> Reactivar comercio</Boton>
          )}
          {!accion && sel.estado === 'Rechazado' && (
            <p className="mt-3 text-sm text-slate-500">La solicitud fue rechazada. El comercio puede corregirla y volver a enviarla.</p>
          )}

          {accion && (
            <div className="mt-3">
              {accion === 'suspender' && (
                <p className="mb-3 flex gap-2 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                  <Info size={18} className="mt-0.5 shrink-0" />
                  Hay {sel.pedidosEnCurso ?? 0} pedidos en curso. Los ya pagados se completan; no entrarán pedidos nuevos.
                </p>
              )}
              <label className="block text-sm font-semibold text-slate-700">Motivo (obligatorio)
                <textarea rows="3" value={motivo} onChange={(e) => setMotivo(e.target.value)}
                  className="mt-1 w-full rounded-lg border border-slate-300 p-2.5 text-base font-normal focus:border-[#1E3A8A] focus:outline-none focus:ring-2 focus:ring-[#1E3A8A]/20" />
              </label>
              <div className="mt-3 grid grid-cols-2 gap-2">
                <Boton variante={accion === 'suspender' ? 'peligro' : 'primario'} onClick={confirmar}>Confirmar</Boton>
                <Boton variante="neutro" onClick={cerrar}>Cancelar</Boton>
              </div>
            </div>
          )}
          <AvisoError mensaje={error} />
        </Tarjeta>
      </div>
    </div>
  );
}