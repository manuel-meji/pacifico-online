import { Boton, C, Tarjeta } from '../components/comunes';
import { CorregirSolicitud } from './SolicitudIncorporacion';

// Lo único que ve la persona encargada mientras su comercio no está aprobado: el estado de la
// revisión y, si se rechazó, el motivo y el formulario para corregirla. No puede administrar el
// comercio (el backend también lo impide) hasta que la plataforma lo apruebe.
export default function EstadoSolicitud({ datos, onActualizar }) {
  const solicitud = datos?.solicitud;
  const rechazada = solicitud?.estado === 'Rechazada';

  return (
    <div className="space-y-6">
      <Tarjeta>
        <h2 className={`text-xl font-extrabold ${C.marinoTexto}`}>{datos?.nombre_comercial}</h2>
        {rechazada ? (
          <>
            <p className="mt-3 inline-block rounded-full bg-red-100 px-3 py-1 text-sm font-bold text-red-800">Solicitud rechazada</p>
            <p className="mt-3 text-slate-700">
              La plataforma rechazó tu solicitud{solicitud?.id_solicitud ? ` #${solicitud.id_solicitud}` : ''} por este motivo:
            </p>
            <p className="mt-2 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-900">{solicitud?.motivo_rechazo}</p>
            <p className="mt-3 text-sm text-slate-600">Corregí los datos abajo y enviala de nuevo: vuelve a quedar en revisión.</p>
          </>
        ) : (
          <>
            <p className="mt-3 inline-block rounded-full bg-amber-100 px-3 py-1 text-sm font-bold text-amber-800">Solicitud en revisión</p>
            <p className="mt-3 text-slate-700">
              Tu solicitud{solicitud?.id_solicitud ? ` #${solicitud.id_solicitud}` : ''} está en revisión. Cuando la plataforma
              apruebe tu comercio, aquí mismo vas a poder administrarlo: micrositio, catálogo, inventario y pedidos.
            </p>
          </>
        )}
        <Boton variante="neutro" className="mt-4" onClick={onActualizar}>Revisar de nuevo</Boton>
      </Tarjeta>
      {rechazada && <CorregirSolicitud idInicial={solicitud?.id_solicitud ?? ''} />}
    </div>
  );
}
