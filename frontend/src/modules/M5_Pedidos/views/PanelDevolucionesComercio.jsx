import { useCallback, useEffect, useState } from 'react';
import { Check, FileText, LoaderCircle, RefreshCw, RotateCcw, X } from 'lucide-react';
import { Alerta } from '../components/componentes';
import { colones } from '../services/calculos';
import { formatearFecha } from '../services/estados';
import {
  aprobarDevolucion,
  listarDevolucionesComercio,
  rechazarDevolucion,
  reintentarReembolso,
  urlEvidencia,
} from '../services/devolucionesApi';

const FILTROS = [
  { valor: 'solicitada', etiqueta: 'Pendientes' },
  { valor: 'aprobada', etiqueta: 'Aprobadas' },
  { valor: 'rechazada', etiqueta: 'Rechazadas' },
];

const REEMBOLSO = {
  pendiente: 'Reembolso en trámite en Pagos',
  procesado: 'Reembolso procesado',
  error: 'El reembolso en Pagos falló',
};

function ModalRechazo({ devolucion, ocupado, error, onConfirmar, onCerrar }) {
  const [motivo, setMotivo] = useState('');
  const [intentado, setIntentado] = useState(false);

  useEffect(() => {
    setMotivo('');
    setIntentado(false);
  }, [devolucion]);

  if (!devolucion) return null;
  const invalido = motivo.trim().length < 5;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-xs">
      <form noValidate role="dialog" aria-modal="true" aria-labelledby="rechazo-dev-titulo"
        onSubmit={(e) => { e.preventDefault(); setIntentado(true); if (!invalido && !ocupado) onConfirmar(motivo.trim()); }}
        className="w-full max-w-md rounded-2xl bg-white p-5 sm:p-6 shadow-xl space-y-4">
        <div>
          <h2 id="rechazo-dev-titulo" className="text-base font-bold text-gray-900">Rechazar devolución #{devolucion.id_devolucion}</h2>
          <p className="mt-1 text-sm text-gray-600">
            Indique por qué la solicitud no cumple las políticas de la empresa. El cliente verá este motivo.
          </p>
        </div>
        <div>
          <label htmlFor="motivo-rechazo-dev" className="text-xs font-medium text-gray-600 block mb-1">Motivo del rechazo (obligatorio)</label>
          <textarea id="motivo-rechazo-dev" rows={3} value={motivo} disabled={ocupado} onChange={(e) => setMotivo(e.target.value)}
            aria-invalid={intentado && invalido}
            className={`w-full rounded-lg border px-3 py-2 text-sm focus:outline-hidden ${intentado && invalido ? 'border-red-500' : 'border-gray-300 focus:border-brandBlue'}`} />
          {intentado && invalido && <p role="alert" className="mt-1 text-xs text-red-600">Indique el motivo (mínimo 5 caracteres).</p>}
        </div>
        {error && <Alerta tipo="error" titulo="No se pudo rechazar">{error}</Alerta>}
        <div className="flex justify-end gap-2">
          <button type="button" onClick={onCerrar} disabled={ocupado}
            className="min-h-11 px-4 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 text-sm font-semibold cursor-pointer disabled:opacity-50">Volver</button>
          <button type="submit" disabled={ocupado}
            className="min-h-11 px-5 rounded-lg bg-red-600 hover:bg-red-700 text-white text-sm font-bold inline-flex items-center gap-2 cursor-pointer disabled:opacity-70">
            {ocupado && <LoaderCircle className="w-4 h-4 animate-spin" aria-hidden="true" />}
            Rechazar devolución
          </button>
        </div>
      </form>
    </div>
  );
}

/**
 * RF-020: panel del comercio para revisar las devoluciones de sus subpedidos. Aprobar dispara RF-021
 * (reembolso en M6, reingreso a kardex en M4 y reversión de comisión en M2).
 *
 * Props: `idComercio` (el del usuario en sesión) y `nombreLinea(l)` opcional para mostrar los artículos.
 */
export default function PanelDevolucionesComercio({ idComercio, nombreLinea }) {
  const [filtro, setFiltro] = useState('solicitada');
  const [devoluciones, setDevoluciones] = useState([]);
  const [cargando, setCargando] = useState(true);
  const [error, setError] = useState(null);
  const [aviso, setAviso] = useState(null);
  const [procesando, setProcesando] = useState(null); // id_devolucion en curso
  const [paraRechazar, setParaRechazar] = useState(null);
  const [errorRechazo, setErrorRechazo] = useState(null);

  const cargar = useCallback(async () => {
    setCargando(true);
    setError(null);
    try {
      const datos = await listarDevolucionesComercio(idComercio, filtro);
      setDevoluciones(datos.devoluciones ?? []);
    } catch (e) {
      setError(`${e.mensaje || e.message}${e.codigo ? ` (${e.codigo})` : ''}`);
    } finally {
      setCargando(false);
    }
  }, [idComercio, filtro]);

  useEffect(() => { cargar(); }, [cargar]);

  async function ejecutar(idDevolucion, accion, exito) {
    setProcesando(idDevolucion);
    setError(null);
    setAviso(null);
    try {
      const resp = await accion();
      setAviso({ tipo: resp.advertencia ? 'advertencia' : 'exito', texto: resp.advertencia || exito });
      await cargar();
      return true;
    } catch (e) {
      setError(`${e.mensaje || e.message}${e.codigo ? ` (${e.codigo})` : ''}`);
      return false;
    } finally {
      setProcesando(null);
    }
  }

  const aprobar = (d) => ejecutar(d.id_devolucion, () => aprobarDevolucion(d.id_devolucion, idComercio),
    `Devolución #${d.id_devolucion} aprobada. Se notificó a Inventario, Pagos y Comercios.`);

  const reintentar = (d) => ejecutar(d.id_devolucion, () => reintentarReembolso(d.id_devolucion, idComercio),
    `Reembolso de la devolución #${d.id_devolucion} solicitado en Pagos.`);

  async function rechazar(motivo) {
    setErrorRechazo(null);
    const d = paraRechazar;
    setProcesando(d.id_devolucion);
    try {
      await rechazarDevolucion(d.id_devolucion, motivo, idComercio);
      setParaRechazar(null);
      setAviso({ tipo: 'exito', texto: `Devolución #${d.id_devolucion} rechazada.` });
      await cargar();
    } catch (e) {
      setErrorRechazo(`${e.mensaje || e.message}${e.codigo ? ` (${e.codigo})` : ''}`);
    } finally {
      setProcesando(null);
    }
  }

  return (
    <section aria-labelledby="titulo-devoluciones" className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <h2 id="titulo-devoluciones" className="text-base font-bold text-gray-900 inline-flex items-center gap-2">
          <RotateCcw className="w-4 h-4" aria-hidden="true" /> Devoluciones
        </h2>
        <div className="flex items-center gap-2">
          <div role="tablist" aria-label="Estado de la devolución" className="inline-flex rounded-lg border border-gray-200 overflow-hidden">
            {FILTROS.map((f) => (
              <button key={f.valor} role="tab" type="button" aria-selected={filtro === f.valor} onClick={() => setFiltro(f.valor)}
                className={`px-3 min-h-9 text-xs font-semibold cursor-pointer transition ${filtro === f.valor ? 'bg-brandDarkBlue text-white' : 'bg-white text-gray-700 hover:bg-gray-50'}`}>
                {f.etiqueta}
              </button>
            ))}
          </div>
          <button type="button" onClick={cargar} disabled={cargando} aria-label="Actualizar"
            className="min-h-9 min-w-9 inline-flex items-center justify-center rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50 cursor-pointer disabled:opacity-50">
            <RefreshCw className={`w-4 h-4 ${cargando ? 'animate-spin' : ''}`} aria-hidden="true" />
          </button>
        </div>
      </div>

      {aviso && <Alerta tipo={aviso.tipo === 'exito' ? 'exito' : 'aviso'} titulo={aviso.tipo === 'exito' ? 'Listo' : 'Atención'}>{aviso.texto}</Alerta>}
      {error && <Alerta tipo="error" titulo="No se pudo completar la acción">{error}</Alerta>}

      {cargando && devoluciones.length === 0 ? (
        <p className="text-sm text-gray-500 inline-flex items-center gap-2"><LoaderCircle className="w-4 h-4 animate-spin" aria-hidden="true" /> Cargando…</p>
      ) : devoluciones.length === 0 ? (
        <p className="text-sm text-gray-500">No hay devoluciones {FILTROS.find((f) => f.valor === filtro)?.etiqueta.toLowerCase()}.</p>
      ) : (
        <ul className="space-y-3">
          {devoluciones.map((d) => (
            <li key={d.id_devolucion} className="rounded-xl border border-gray-200 bg-white p-4 space-y-2.5" data-testid="tarjeta-devolucion">
              <div className="flex flex-wrap items-start justify-between gap-2">
                <div>
                  <p className="text-sm font-bold text-gray-900">
                    Devolución {d.tipo === 'total' ? 'total' : 'parcial'} #{d.id_devolucion} · Subpedido #{d.id_subpedido}
                  </p>
                  <p className="text-xs text-gray-500">Solicitada el {formatearFecha(d.fecha_solicitud)}</p>
                </div>
                <p className="text-sm font-bold text-gray-900">{colones(Number(d.monto_solicitado ?? 0))}</p>
              </div>

              <p className="text-xs text-gray-700 break-words"><span className="font-semibold">Motivo del cliente:</span> {d.motivo}</p>

              <ul className="text-xs text-gray-600 space-y-0.5">
                {(d.lineas ?? []).map((l) => (
                  <li key={l.id_devolucion_linea ?? l.id_linea}>
                    {l.cantidad} × {nombreLinea ? nombreLinea(l) : `Línea #${l.id_linea}`} · {colones(Number(l.monto ?? 0))}
                  </li>
                ))}
              </ul>

              {(d.evidencias ?? []).length > 0 && (
                <ul className="flex flex-wrap gap-x-3 gap-y-1 text-xs">
                  {d.evidencias.map((e) => (
                    <li key={e.id_evidencia}>
                      <a href={urlEvidencia(e, { idComercio })} target="_blank" rel="noreferrer"
                        className="inline-flex items-center gap-1 text-brandDarkBlue underline underline-offset-2">
                        <FileText className="w-3.5 h-3.5" aria-hidden="true" />
                        <span className="truncate max-w-48">{e.nombre_original}</span>
                      </a>
                    </li>
                  ))}
                </ul>
              )}

              {d.estado === 'rechazada' && <p className="text-xs text-red-700 break-words"><span className="font-semibold">Rechazada:</span> {d.motivo_rechazo}</p>}

              {d.estado === 'aprobada' && (
                <p className={`text-xs font-semibold ${d.estado_reembolso === 'error' ? 'text-red-700' : 'text-green-700'}`}>
                  {REEMBOLSO[d.estado_reembolso] ?? 'Aprobada'}
                  {d.estado_reembolso === 'error' && d.error_reembolso ? `: ${d.error_reembolso}` : ''}
                </p>
              )}

              <div className="flex flex-wrap gap-2 pt-1">
                {d.estado === 'solicitada' && (
                  <>
                    <button type="button" onClick={() => aprobar(d)} disabled={procesando === d.id_devolucion}
                      className="min-h-10 px-4 rounded-lg bg-brandGreen hover:opacity-90 text-white text-xs font-bold inline-flex items-center gap-1.5 cursor-pointer disabled:opacity-60">
                      {procesando === d.id_devolucion ? <LoaderCircle className="w-4 h-4 animate-spin" aria-hidden="true" /> : <Check className="w-4 h-4" aria-hidden="true" />}
                      Aprobar
                    </button>
                    <button type="button" onClick={() => { setErrorRechazo(null); setParaRechazar(d); }} disabled={procesando === d.id_devolucion}
                      className="min-h-10 px-4 rounded-lg border border-red-200 text-red-600 hover:bg-red-50 text-xs font-semibold inline-flex items-center gap-1.5 cursor-pointer disabled:opacity-60">
                      <X className="w-4 h-4" aria-hidden="true" /> Rechazar
                    </button>
                  </>
                )}
                {d.estado === 'aprobada' && d.estado_reembolso === 'error' && !d.id_reembolso_m6 && (
                  <button type="button" onClick={() => reintentar(d)} disabled={procesando === d.id_devolucion}
                    className="min-h-10 px-4 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 text-xs font-semibold inline-flex items-center gap-1.5 cursor-pointer disabled:opacity-60">
                    <RefreshCw className="w-4 h-4" aria-hidden="true" /> Reintentar reembolso
                  </button>
                )}
              </div>
            </li>
          ))}
        </ul>
      )}

      <ModalRechazo devolucion={paraRechazar} ocupado={procesando === paraRechazar?.id_devolucion} error={errorRechazo}
        onConfirmar={rechazar} onCerrar={() => setParaRechazar(null)} />
    </section>
  );
}
