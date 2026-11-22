import { useState } from 'react';
import { CheckCircle2, Circle, Clock, RefreshCw, XCircle } from 'lucide-react';
import { AvisoError, AvisoInfo, Boton, C, Etiqueta, Tarjeta, fechaCorta } from '../components/comunes';
import { CorregirMiSolicitud } from './SolicitudIncorporacion';

// Lo único que ve la persona encargada mientras su comercio no está aprobado: el estado de la
// revisión y, si se rechazó, el motivo y el formulario para corregirla. No puede administrar el
// comercio (el backend también lo impide) hasta que la plataforma lo apruebe.
//
// Hallazgo 19: la tarjeta va centrada, con la etiqueta del sistema y los pasos de la revisión con sus
// fechas. Hallazgo 10: «Actualizar estado» dice qué pasó al consultar.

const hora = () => new Date().toLocaleTimeString('es-CR', { hour: 'numeric', minute: '2-digit' });

function Paso({ estado, titulo, detalle }) {
  const iconos = {
    hecho: <CheckCircle2 size={20} className="text-green-600" />,
    actual: <Clock size={20} className="text-amber-600" />,
    rechazado: <XCircle size={20} className="text-red-600" />,
    pendiente: <Circle size={20} className="text-slate-300" />,
  };
  return (
    <li className="flex items-start gap-3">
      <span className="mt-0.5 shrink-0">{iconos[estado]}</span>
      <span>
        <span className={`block text-sm font-bold ${estado === 'pendiente' ? 'text-slate-400' : 'text-slate-800'}`}>{titulo}</span>
        {detalle && <span className="block text-xs text-slate-500">{detalle}</span>}
      </span>
    </li>
  );
}

export default function EstadoSolicitud({ datos, onActualizar }) {
  const solicitud = datos?.solicitud;
  const rechazada = solicitud?.estado === 'Rechazada';
  const [consultando, setConsultando] = useState(false);
  const [resultado, setResultado] = useState({ info: '', error: '' });

  const actualizar = async () => {
    setConsultando(true);
    const r = await onActualizar?.();
    setConsultando(false);
    if (r && !r.ok) setResultado({ info: '', error: 'No se pudo consultar el estado. Revisá tu conexión e intentá de nuevo.' });
    // Si ya se aprobó, el panel completo reemplaza esta pantalla; si no, se dice que sigue igual.
    else setResultado({ info: `Consultado a las ${hora()}: ${rechazada ? 'tu solicitud sigue rechazada, corregila abajo' : 'tu solicitud sigue en revisión'}.`, error: '' });
  };

  const numero = solicitud?.id_solicitud ? ` #${solicitud.id_solicitud}` : '';

  return (
    <div className="mx-auto max-w-3xl space-y-6">
      <Tarjeta>
        <div className="flex flex-wrap items-center justify-between gap-2">
          <h2 className={`text-xl font-extrabold ${C.marinoTexto}`}>{datos?.nombre_comercial}</h2>
          <Etiqueta estado={rechazada ? 'Rechazada' : 'Pendiente'} />
        </div>
        <p className="mt-1 text-sm text-slate-500">Solicitud{numero}</p>

        <ol className="mt-5 space-y-3" aria-label="Pasos de la revisión">
          <Paso estado="hecho" titulo="Solicitud enviada" detalle={solicitud?.created_at ? `El ${fechaCorta(solicitud.created_at)}` : null} />
          {rechazada ? (
            <Paso estado="rechazado" titulo="Rechazada por la plataforma"
              detalle={solicitud?.fecha_revision ? `El ${fechaCorta(solicitud.fecha_revision)}` : null} />
          ) : (
            <Paso estado="actual" titulo="En revisión"
              detalle={solicitud?.fecha_reenvio ? `Corregida y reenviada el ${fechaCorta(solicitud.fecha_reenvio)}` : 'La plataforma revisa tus datos y documentos.'} />
          )}
          <Paso estado="pendiente" titulo="Aprobada"
            detalle="Cuando la plataforma apruebe tu comercio, aquí mismo vas a poder administrarlo: micrositio, catálogo, inventario y pedidos." />
        </ol>

        {rechazada && (
          <div className="mt-5 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-900">
            <p className="font-bold">Motivo del rechazo</p>
            <p className="mt-1">{solicitud?.motivo_rechazo}</p>
            <p className="mt-2 text-xs">Corregí los datos abajo y enviala de nuevo: vuelve a quedar en revisión.</p>
          </div>
        )}

        <Boton variante="neutro" className="mt-5" onClick={actualizar} disabled={consultando}>
          <RefreshCw size={16} className={consultando ? 'animate-spin' : ''} /> {consultando ? 'Consultando…' : 'Actualizar estado'}
        </Boton>
        <AvisoInfo mensaje={resultado.info} />
        <AvisoError mensaje={resultado.error} />
      </Tarjeta>
      {rechazada && <CorregirMiSolicitud revision={datos} onCorregida={onActualizar} />}
    </div>
  );
}
