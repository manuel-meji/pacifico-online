
import { useCallback, useEffect, useState } from 'react';
import { Check, CheckCircle2, Circle, Clock, ExternalLink, Store, X, XCircle } from 'lucide-react';
import { aprobarSolicitud, consultarSolicitud, evaluarDocumento, listarSolicitudes, rechazarSolicitud } from '../services/m2Api';
import {
  Aviso, AvisoError, AvisoInfo, Boton, C, Cargando, Etiqueta, Tarjeta, erroresDeCampo, fechaCorta, nombreDocumento, useSesion,
} from './comunes';
import { ConfirmarAccion, FiltroEstado, POR_PAGINA, Paginacion, Volver, explicar } from './AccionesPlataforma';

const ESTADOS_SOLICITUD = ['Pendiente', 'Aprobada', 'Rechazada'];

const fecha = fechaCorta;

// Pestaña del panel de la plataforma a la que se pasa desde el detalle (PanelPlataforma la escucha).
export const irAPestana = (clave) => window.dispatchEvent(new CustomEvent('m2:pestana', { detail: clave }));

// RN-011 y hallazgo 24: si otra persona ya revisó la solicitud, el servidor responde con detalles.estado.
const yaRevisada = (r) => Boolean(erroresDeCampo(r).estado);

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

function Documento({ idSolicitud, documento, editable, onEvaluado, onYaRevisada }) {
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
    else if (yaRevisada(res)) onYaRevisada?.(res);
    else setError(explicar(res, mensajeDeError));
  };
  const nombre = nombreDocumento(documento.tipo);

  return (
    <li className={`rounded-lg p-3 text-sm ${r.estilo}`}>
      <div className="flex flex-wrap items-center justify-between gap-2">
        <span className="flex items-center gap-2 font-semibold"><r.Icono size={18} /> {nombre}</span>
        <span className="text-xs">Vence: {fecha(documento.fecha_vencimiento)} · {r.texto}</span>
        {/* CU-0022: abre el archivo original descifrado (solo el Encargado de la plataforma). */}
        {/* Sin noreferrer: Sanctum usa el Referer para aplicar la sesión en esta pestaña nueva. */}
        <a href={`/api/m2/v1/solicitudes/${idSolicitud}/documentos/${documento.id_documento_comercio}/archivo`}
          target="_blank" rel="noopener"
          className="inline-flex items-center gap-1 text-xs font-bold text-[#1E3A8A] underline hover:no-underline">
          <ExternalLink size={14} /> Ver archivo
        </a>
      </div>
      {documento.observacion && !editable && <p className="mt-1 text-xs">Observación: {documento.observacion}</p>}
      {editable && (
        <div className="mt-2 flex flex-col gap-2 sm:flex-row sm:items-center">
          <input value={observacion} onChange={(e) => setObservacion(e.target.value)} placeholder="Observación (opcional)"
            aria-label={`Observación sobre ${nombre}`}
            className="w-full rounded-lg border border-slate-300 bg-white p-2 text-sm text-slate-800 focus:border-[#1E3A8A] focus:outline-none" />
          <div className="flex shrink-0 gap-2">
            <Boton className="py-2" disabled={enviando} onClick={() => evaluar('valido')}><Check size={16} /> Válido</Boton>
            <Boton variante="peligroBorde" className="py-2" disabled={enviando} onClick={() => evaluar('invalido')}><X size={16} /> Inválido</Boton>
          </div>
        </div>
      )}
      <AvisoError mensaje={error} />
    </li>
  );
}

// Hallazgo 13: lo que pide la aprobación (RN-001 y RN-002), a la vista antes de tocar «Aprobar».
function requisitosDe(solicitud) {
  const { comercio, documentos } = solicitud;
  const hoy = new Date().toISOString().slice(0, 10);
  const revisados = documentos.filter((d) => d.resultado !== 'pendiente').length;
  const malos = documentos.filter((d) => d.resultado === 'invalido' || (d.fecha_vencimiento && String(d.fecha_vencimiento).slice(0, 10) < hoy)).length;
  const lista = [
    { ok: Boolean(comercio.inscrito_como_contribuyente), texto: 'Inscrito como contribuyente ante Hacienda', falta: 'que esté inscrito como contribuyente' },
    {
      ok: ['cedula_juridica', 'razon_social', 'direccion_fiscal'].every((c) => String(comercio[c] ?? '').trim() !== ''),
      texto: 'Datos del comercio completos', falta: 'completar los datos del comercio',
    },
  ];
  if (documentos.length > 0) {
    lista.push({ ok: revisados === documentos.length, texto: `Documentos revisados: ${revisados} de ${documentos.length}`, falta: 'revisar todos los documentos' });
    lista.push({ ok: malos === 0, texto: 'Sin documentos inválidos ni vencidos', falta: 'que no haya documentos inválidos ni vencidos' });
  } else if (solicitud.requiere_documentos) {
    lista.push({ ok: false, texto: 'Documentos adjuntos', falta: 'que adjunte los documentos' });
  }
  return lista;
}

// Hallazgos 15 y 20: quién registró, rechazó o aprobó la solicitud y cuándo, a la vista arriba.
function Historial({ solicitud }) {
  const filas = [];
  if (solicitud.nombre_registrador) {
    filas.push({ tono: 'neutro', texto: `Registrada por la plataforma (${solicitud.nombre_registrador}) el ${fecha(solicitud.created_at)}.` });
  }
  const por = solicitud.nombre_revisor ? ` por ${solicitud.nombre_revisor}` : '';
  if (solicitud.estado === 'Rechazada') {
    filas.push({ tono: 'rojo', texto: `Rechazada el ${fecha(solicitud.fecha_revision ?? solicitud.updated_at)}${por}.`, motivo: solicitud.motivo_rechazo });
  } else if (solicitud.ultimo_motivo_rechazo) {
    filas.push({ tono: 'rojo', texto: 'Se rechazó antes.', motivo: solicitud.ultimo_motivo_rechazo });
  }
  if (solicitud.fecha_reenvio && solicitud.estado !== 'Rechazada') {
    filas.push({ tono: 'neutro', texto: `Corregida y reenviada el ${fecha(solicitud.fecha_reenvio)}.` });
  }
  if (solicitud.estado === 'Aprobada') {
    filas.push({ tono: 'verde', texto: `Aprobada el ${fecha(solicitud.fecha_revision ?? solicitud.updated_at)}${por}.` });
  }
  if (filas.length === 0) return null;
  const estilos = { rojo: 'border-red-200 bg-red-50 text-red-900', verde: 'border-green-200 bg-green-50 text-green-900', neutro: 'border-slate-200 bg-slate-50 text-slate-700' };
  return (
    <ul className="mt-4 space-y-2" aria-label="Historial de la solicitud">
      {filas.map((f) => (
        <li key={f.texto} className={`rounded-lg border p-3 text-sm ${estilos[f.tono]}`}>
          {f.texto}
          {f.motivo && <span className="mt-1 block"><span className="font-semibold">Motivo:</span> {f.motivo}</span>}
        </li>
      ))}
    </ul>
  );
}

function DetalleSolicitud({ idSolicitud, onVolver }) {
  const { mensajeDeError } = useSesion();
  const [solicitud, setSolicitud] = useState(null);
  const [error, setError] = useState('');
  const [ok, setOk] = useState('');
  const [info, setInfo] = useState('');
  const [accion, setAccion] = useState(null);

  const cargar = useCallback(async () => {
    const r = await consultarSolicitud(idSolicitud);
    if (r.ok) setSolicitud(r.datos);
    else setError(explicar(r, mensajeDeError));
  }, [idSolicitud, mensajeDeError]);

  useEffect(() => { cargar(); }, [cargar]);

  // Si otra sesión ya la revisó, se recarga para mostrar el estado real (hallazgo 24).
  const yaRevisadaEnOtraSesion = async (r) => {
    setAccion(null);
    setOk('');
    setInfo(erroresDeCampo(r).estado?.[0] || 'Esta solicitud ya fue revisada en otra sesión.');
    await cargar();
  };

  const terminar = async (r, texto, tipo = 'ok') => {
    if (!r.ok) {
      if (yaRevisada(r)) { await yaRevisadaEnOtraSesion(r); return { ok: true }; }
      return r;
    }
    setAccion(null);
    if (tipo === 'info') { setInfo(texto); setOk(''); } else { setOk(texto); setInfo(''); }
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
  const requisitos = requisitosDe(solicitud);
  const faltan = requisitos.filter((x) => !x.ok).map((x) => x.falta);
  const listo = faltan.length === 0;

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
          <Historial solicitud={solicitud} />

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
                  editable={pendiente} onEvaluado={cargar} onYaRevisada={yaRevisadaEnOtraSesion} />
              ))}
            </ul>
          )}
        </Tarjeta>

        <Tarjeta>
          <h2 className="text-sm font-bold text-slate-700">Acciones</h2>
          <Aviso mensaje={ok} />
          <AvisoInfo mensaje={info} />
          {pendiente && (
            <ul className="mt-3 space-y-1.5 text-sm" aria-label="Requisitos para aprobar">
              {requisitos.map((x) => (
                <li key={x.texto} className={`flex items-start gap-2 ${x.ok ? 'text-green-800' : 'text-slate-600'}`}>
                  {x.ok ? <CheckCircle2 size={16} className="mt-0.5 shrink-0" /> : <Circle size={16} className="mt-0.5 shrink-0 text-slate-400" />}
                  {x.texto}
                </li>
              ))}
            </ul>
          )}
          {pendiente && !accion && (
            <div className="mt-4 grid gap-2">
              <Boton className="py-3" disabled={!listo} onClick={() => { setAccion('aprobar'); setOk(''); setInfo(''); }}><Check size={18} /> Aprobar</Boton>
              {!listo && <p className="text-xs text-slate-600">Para aprobar falta: {faltan.join('; ')}.</p>}
              <Boton variante="peligroBorde" className="py-3" onClick={() => { setAccion('rechazar'); setOk(''); setInfo(''); }}><X size={18} /> Rechazar</Boton>
            </div>
          )}
          {accion === 'aprobar' && (
            <ConfirmarAccion
              pregunta={`¿Aprobar a ${comercio.nombre_comercial}?`}
              aviso="El comercio quedará Activo y aparecerá en el portal."
              textoConfirmar="Sí, aprobar"
              onConfirmar={async () => terminar(await aprobarSolicitud(solicitud.id_solicitud), (solicitud.cuenta_creada
                ? `${comercio.nombre_comercial} fue aprobado y quedó Activo. La persona encargada (${solicitud.correo_encargado}) ya puede administrarlo al ingresar.`
                : `${comercio.nombre_comercial} fue aprobado y quedó Activo. El núcleo le envía a ${solicitud.correo_encargado} una invitación para definir su contraseña e ingresar.`))}
              onCancelar={() => setAccion(null)} />
          )}
          {accion === 'rechazar' && (
            <ConfirmarAccion
              pregunta={`¿Rechazar la solicitud de ${comercio.nombre_comercial}?`}
              aviso="La persona encargada ve el motivo al ingresar y puede corregir la solicitud."
              pideMotivo variante="peligro" textoConfirmar="Sí, rechazar"
              onConfirmar={async (motivo) => terminar(await rechazarSolicitud(solicitud.id_solicitud, motivo),
                `Solicitud rechazada. ${solicitud.nombre_encargado} puede corregirla desde su cuenta y volver a enviarla.`, 'info')}
              onCancelar={() => setAccion(null)} />
          )}
          {/* Hallazgo 20: después de aprobar, los siguientes pasos como botones. */}
          {solicitud.estado === 'Aprobada' && (
            <div className="mt-3 grid gap-2">
              <Boton variante="neutro" onClick={() => irAPestana('comercios')}><Store size={16} /> Ver en Comercios</Boton>
              <a href={`/app.html?pantalla=comercio&id=${comercio.id_comercio}`} target="_blank" rel="noopener"
                className="inline-flex items-center justify-center gap-2 rounded-lg bg-white px-4 py-2.5 text-sm font-bold text-slate-700 ring-1 ring-slate-300 hover:bg-slate-50">
                <ExternalLink size={16} /> Ver micrositio
              </a>
            </div>
          )}
          {solicitud.estado === 'Rechazada' && !info && (
            <p className="mt-3 text-sm text-slate-500">La persona encargada puede corregirla desde su cuenta y volver a enviarla.</p>
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