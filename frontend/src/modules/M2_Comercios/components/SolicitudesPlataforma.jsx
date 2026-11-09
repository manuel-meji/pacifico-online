
import { useCallback, useEffect, useState } from 'react';
import { Check, CheckCircle2, Clock, X, XCircle } from 'lucide-react';
import { aprobarSolicitud, consultarSolicitud, evaluarDocumento, listarSolicitudes, rechazarSolicitud } from '../services/m2Api';
import { Aviso, AvisoError, Boton, C, Cargando, Etiqueta, Tarjeta, useSesion } from './comunes';
import { ConfirmarAccion, FiltroEstado, POR_PAGINA, Paginacion, Volver, explicar } from './AccionesPlataforma';

const ESTADOS_SOLICITUD = ['Pendiente', 'Aprobada', 'Rechazada'];

const fecha = (valor) => (valor ? new Date(valor).toLocaleDateString('es-CR') : '—');

const Dato = ({ etiqueta, valor }) => (
  <div>
    <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">{etiqueta}</dt>
    <dd className="mt-0.5 text-sm text-slate-800">{valor ?? '—'}</dd>
  </div>
);

const RESULTADOS = {
  valido: { texto: 'Válido', estilo: 'bg-green-50 text-green-800', Icono: CheckCircle2 },
  invalido: { texto: 'Inválido', estilo: 'bg-red-50 text-red-700', Icono: XCircle },
  pendiente: { texto: 'Sin revisar', estilo: 'bg-amber-50 text-amber-800', Icono: Clock },
};

function Documento({ idSolicitud, documento, editable, onEvaluado }) {
  const { mensajeDeError } = useSesion();
  const [observacion, setObservacion] = useState(documento.observacion || '');
  const [error, setError] = useState('');
  const [enviando, setEnviando] = useState(false);
  const r = RESULTADOS[documento.resultado] || RESULTADOS.pendiente;

  const evaluar = async (resultado) => {
    setEnviando(true);
    setError('');
    const res = await evaluarDocumento(idSolicitud, documento.id_documento_comercio, resultado, observacion.trim());
    setEnviando(false);
    if (res.ok) onEvaluado();
    else setError(explicar(res, mensajeDeError));
  };

  return (
    <li className={`rounded-lg p-3 text-sm ${r.estilo}`}>
      <div className="flex flex-wrap items-center justify-between gap-2">
        <span className="flex items-center gap-2 font-semibold"><r.Icono size={18} /> {documento.tipo}</span>
        <span className="text-xs">Vence: {fecha(documento.fecha_vencimiento)} · {r.texto}</span>
      </div>
      {documento.observacion && !editable && <p className="mt-1 text-xs">Observación: {documento.observacion}</p>}
      {editable && (
        <div className="mt-2 flex flex-col gap-2 sm:flex-row sm:items-center">
          <input value={observacion} onChange={(e) => setObservacion(e.target.value)} placeholder="Observación (opcional)"
            aria-label={`Observación sobre ${documento.tipo}`}
            className="w-full rounded-lg border border-slate-300 bg-white p-2 text-sm text-slate-800 focus:border-[#1E3A8A] focus:outline-none" />
          <div className="flex shrink-0 gap-2">
            <Boton className="py-2" disabled={enviando} onClick={() => evaluar('valido')}><Check size={16} /> Válido</Boton>
            <Boton variante="peligro" className="py-2" disabled={enviando} onClick={() => evaluar('invalido')}><X size={16} /> Inválido</Boton>
          </div>
        </div>
      )}
      <AvisoError mensaje={error} />
    </li>
  );
}

function DetalleSolicitud({ idSolicitud, onVolver }) {
  const { mensajeDeError } = useSesion();
  const [solicitud, setSolicitud] = useState(null);
  const [error, setError] = useState('');
  const [ok, setOk] = useState('');
  const [accion, setAccion] = useState(null); 

  const cargar = useCallback(async () => {
    const r = await consultarSolicitud(idSolicitud);
    if (r.ok) setSolicitud(r.datos);
    else setError(explicar(r, mensajeDeError));
  }, [idSolicitud, mensajeDeError]);

  useEffect(() => { cargar(); }, [cargar]);

  const terminar = async (r, texto) => {
    if (!r.ok) return r;
    setAccion(null);
    setOk(texto);
    await cargar();
    return r;
  };

  if (!solicitud) {
    return (
      <div>
        <Volver onClick={onVolver} />
        {error ? <AvisoError mensaje={error} /> : <Cargando />}
      </div>
    );
  }

  const { comercio, documentos } = solicitud;
  const pendiente = solicitud.estado === 'Pendiente';

  return (
    <div>
      <Volver onClick={onVolver} />
      <div className="grid gap-5 lg:grid-cols-3">
        <Tarjeta className="lg:col-span-2">
          <div className="flex flex-wrap items-center justify-between gap-2">
            <h1 className={`text-2xl font-extrabold ${C.marinoTexto}`}>{comercio.nombre_comercial}</h1>
            <Etiqueta estado={solicitud.estado} />
          </div>
          <p className="text-sm text-slate-500">Solicitud #{solicitud.id_solicitud} · recibida el {fecha(solicitud.created_at)}</p>

          <h2 className="mt-5 text-sm font-bold text-slate-700">Datos del comercio</h2>
          <dl className="mt-2 grid gap-3 sm:grid-cols-2">
            <Dato etiqueta="Cédula jurídica" valor={comercio.cedula_juridica} />
            <Dato etiqueta="Razón social" valor={comercio.razon_social} />
            <Dato etiqueta="Dirección fiscal" valor={comercio.direccion_fiscal} />
            <Dato etiqueta="Inscrito como contribuyente" valor={comercio.inscrito_como_contribuyente ? 'Sí' : 'No'} />
          </dl>

          <h2 className="mt-5 text-sm font-bold text-slate-700">Encargado</h2>
          <dl className="mt-2 grid gap-3 sm:grid-cols-3">
            <Dato etiqueta="Nombre" valor={solicitud.nombre_encargado} />
            <Dato etiqueta="Correo" valor={solicitud.correo_encargado} />
            <Dato etiqueta="Teléfono" valor={solicitud.telefono_encargado} />
          </dl>

          <h2 className="mt-5 text-sm font-bold text-slate-700">Documentos</h2>
          {documentos.length === 0 ? (
            <p className="mt-2 text-sm text-slate-500">
              {solicitud.requiere_documentos ? 'La solicitud requiere documentos y no tiene ninguno.' : 'Esta solicitud no adjuntó documentos.'}
            </p>
          ) : (
            <ul className="mt-2 space-y-2">
              {documentos.map((d) => (
                <Documento key={d.id_documento_comercio} idSolicitud={solicitud.id_solicitud} documento={d}
                  editable={pendiente} onEvaluado={cargar} />
              ))}
            </ul>
          )}

          {solicitud.motivo_rechazo && (
            <p className="mt-4 text-sm text-slate-700"><span className="font-semibold">Motivo del rechazo:</span> {solicitud.motivo_rechazo}</p>
          )}
          {solicitud.fecha_reenvio && (
            <p className="mt-1 text-xs text-slate-500">Corregida y reenviada el {fecha(solicitud.fecha_reenvio)}.</p>
          )}
        </Tarjeta>

        <Tarjeta>
          <h2 className="text-sm font-bold text-slate-700">Acciones</h2>
          <Aviso mensaje={ok} />
          {pendiente && !accion && (
            <div className="mt-3 grid gap-2">
              <Boton className="py-3" onClick={() => { setAccion('aprobar'); setOk(''); }}><Check size={18} /> Aprobar</Boton>
              <Boton variante="peligro" className="py-3" onClick={() => { setAccion('rechazar'); setOk(''); }}><X size={18} /> Rechazar</Boton>
            </div>
          )}
          {accion === 'aprobar' && (
            <ConfirmarAccion
              pregunta={`¿Aprobar a ${comercio.nombre_comercial}?`}
              aviso="El comercio quedará Activo y se creará la cuenta de su encargado."
              textoConfirmar="Sí, aprobar"
              onConfirmar={async () => terminar(await aprobarSolicitud(solicitud.id_solicitud), `${comercio.nombre_comercial} fue aprobado y quedó Activo.`)}
              onCancelar={() => setAccion(null)} />
          )}
          {accion === 'rechazar' && (
            <ConfirmarAccion
              pregunta={`¿Rechazar la solicitud de ${comercio.nombre_comercial}?`}
              pideMotivo variante="peligro" textoConfirmar="Sí, rechazar"
              onConfirmar={async (motivo) => terminar(await rechazarSolicitud(solicitud.id_solicitud, motivo), 'La solicitud fue rechazada.')}
              onCancelar={() => setAccion(null)} />
          )}
          {!pendiente && (
            <p className="mt-3 text-sm text-slate-500">
              {solicitud.estado === 'Aprobada'
                ? 'La solicitud ya fue aprobada. El comercio se administra en la pestaña Comercios.'
                : 'La solicitud fue rechazada. El interesado puede corregirla y volver a enviarla.'}
            </p>
          )}
          <AvisoError mensaje={error} />
        </Tarjeta>
      </div>
    </div>
  );
}

export default function SolicitudesPlataforma() {
  const { mensajeDeError } = useSesion();
  const [estado, setEstado] = useState('Pendiente');
  const [pagina, setPagina] = useState(1);
  const [datos, setDatos] = useState(null);
  const [error, setError] = useState('');
  const [seleccionada, setSeleccionada] = useState(null);

  const cargar = useCallback(async () => {
    setDatos(null);
    setError('');
    const r = await listarSolicitudes({ estado, pagina, por_pagina: POR_PAGINA });
    if (r.ok) setDatos(r.datos);
    else setError(explicar(r, mensajeDeError));
  }, [estado, pagina, mensajeDeError]);

  useEffect(() => {
    if (seleccionada === null) cargar();
  }, [cargar, seleccionada]);

  if (seleccionada !== null) {
    return <DetalleSolicitud idSolicitud={seleccionada} onVolver={() => setSeleccionada(null)} />;
  }

  return (
    <Tarjeta>
      <div className="flex flex-wrap items-end justify-between gap-3">
        <h1 className={`text-2xl font-extrabold ${C.marinoTexto}`}>Solicitudes de incorporación</h1>
        <FiltroEstado etiqueta="Estado" valor={estado} opciones={ESTADOS_SOLICITUD}
          onCambiar={(v) => { setEstado(v); setPagina(1); }} />
      </div>
      <AvisoError mensaje={error} />
      {!datos && !error && <Cargando />}
      {datos && datos.solicitudes.length === 0 && (
        <p className="mt-4 text-sm text-slate-500">No hay solicitudes{estado ? ` en estado ${estado}` : ''}.</p>
      )}
      {datos && datos.solicitudes.length > 0 && (
        <ul className="mt-4 divide-y divide-slate-200 rounded-xl border border-slate-200">
          {datos.solicitudes.map((s) => (
            <li key={s.id_solicitud} className="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
              <div className="min-w-0">
                <p className="font-semibold text-slate-800">{s.comercio.nombre_comercial}</p>
                <p className="text-xs text-slate-500">
                  Cédula jurídica {s.comercio.cedula_juridica} · Solicitud #{s.id_solicitud} · {fecha(s.created_at)}
                </p>
              </div>
              <div className="flex shrink-0 items-center justify-between gap-3 sm:justify-end">
                <Etiqueta estado={s.estado} />
                <Boton variante="neutro" className="py-2" onClick={() => setSeleccionada(s.id_solicitud)}>Ver</Boton>
              </div>
            </li>
          ))}
        </ul>
      )}
      {datos && <Paginacion pagina={pagina} total={datos.total} onCambiar={setPagina} />}
    </Tarjeta>
  );
}