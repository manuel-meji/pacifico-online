import { useCallback, useEffect, useState } from 'react';
import { Check, Sparkles, X } from 'lucide-react';
import {
  gestionarPublicidad, listarComercios, listarPublicidadPendiente, listarPublicidadVigente, solicitarPublicidad,
} from '../services/m2Api';
import { Aviso, AvisoError, Boton, C, Cargando, Tarjeta, fechaCorta, textoDeError, useSesion } from './comunes';
import { ConfirmarAccion, explicar as explicarError } from './AccionesPlataforma';

// Destacar comercios desde la plataforma: revisar las solicitudes de los encargados, ver los destacados
// vigentes (y retirarlos si hace falta) y destacar un comercio directamente (sin costo para el comercio).
//
// Hallazgo 37: la confirmación va dentro de la tarjeta (no window.confirm) y rechazar pide motivo.
// Hallazgo 38: un destacado nuevo para un comercio que ya está destacado empieza cuando termina el actual.

const fecha = fechaCorta;

export default function DestacadosPlataforma() {
  const { mensajeDeError } = useSesion();
  const explicar = (r) => explicarError(r, (x) => mensajeDeError?.(x) || textoDeError(x));

  const [pendientes, setPendientes] = useState(null);
  const [vigentes, setVigentes] = useState(null);
  const [comercios, setComercios] = useState([]);
  const [error, setError] = useState('');
  const [aviso, setAviso] = useState('');
  const [confirmando, setConfirmando] = useState(null); // { publicidad, accion }
  const [ocupado, setOcupado] = useState(false);
  const [idComercio, setIdComercio] = useState('');
  const [dias, setDias] = useState(7);

  const cargar = useCallback(async () => {
    setError('');
    const [rp, rv, rc] = await Promise.all([
      listarPublicidadPendiente(), listarPublicidadVigente(), listarComercios({ estado: 'Activo', por_pagina: 50 }),
    ]);
    if (rp.ok) setPendientes(rp.datos.publicidades); else setError(explicar(rp));
    if (rv.ok) setVigentes(rv.datos.publicidades); else setVigentes([]);
    if (rc.ok) setComercios(rc.datos.comercios);
  }, []); // eslint-disable-line react-hooks/exhaustive-deps

  useEffect(() => { cargar(); }, [cargar]);

  const resolver = async (motivo) => {
    const { publicidad: p, accion } = confirmando;
    const r = await gestionarPublicidad(p.id_publicidad_comercio, accion, motivo || null);
    if (!r.ok) return r;
    const textos = {
      aprobar: `${p.nombre_comercial} queda destacado por ${p.dias_solicitados} días.`,
      rechazar: `Solicitud de ${p.nombre_comercial} rechazada. El comercio ve el motivo en su historial.`,
      retirar: `Se retiró el destacado de ${p.nombre_comercial}. El comercio ve el motivo en su historial.`,
    };
    setConfirmando(null);
    setAviso(textos[accion]);
    cargar();
    return r;
  };

  const destacarDirecto = async () => {
    const comercio = comercios.find((c) => String(c.id_comercio) === String(idComercio));
    if (!comercio) return;
    setOcupado(true); setAviso(''); setError('');
    const r = await solicitarPublicidad(comercio.id_comercio, Number(dias));
    setOcupado(false);
    if (r.ok) {
      const nueva = r.datos.publicidades?.[0];
      setAviso(nueva?.inicio && nueva.inicio > new Date().toISOString().slice(0, 10)
        ? `${comercio.nombre_comercial} ya estaba destacado: el nuevo periodo va del ${fecha(nueva.inicio)} al ${fecha(nueva.fin)}.`
        : `${comercio.nombre_comercial} quedó destacado por ${dias} días.`);
      setIdComercio('');
      cargar();
    } else setError(explicar(r));
  };

  const abrir = (publicidad, accion) => { setAviso(''); setError(''); setConfirmando({ publicidad, accion }); };
  const preguntas = {
    aprobar: (p) => `¿Aprobar el destacado de ${p.nombre_comercial} por ${p.dias_solicitados} días?`,
    rechazar: (p) => `¿Rechazar el destacado de ${p.nombre_comercial}?`,
    retirar: (p) => `¿Retirar el destacado de ${p.nombre_comercial}?`,
  };
  // Se llama como función (no como componente) para que el motivo escrito no se pierda al volver a pintar.
  const confirmacion = (publicidad) => (confirmando?.publicidad.id_publicidad_comercio === publicidad.id_publicidad_comercio ? (
    <div className="w-full">
      <ConfirmarAccion
        pregunta={preguntas[confirmando.accion](publicidad)}
        aviso={confirmando.accion === 'retirar' ? 'Deja de aparecer en la portada desde hoy.' : null}
        pideMotivo={confirmando.accion !== 'aprobar'}
        variante={confirmando.accion === 'aprobar' ? 'primario' : 'peligro'}
        textoConfirmar={{ aprobar: 'Sí, aprobar', rechazar: 'Sí, rechazar', retirar: 'Sí, retirar' }[confirmando.accion]}
        onConfirmar={resolver} onCancelar={() => setConfirmando(null)} />
    </div>
  ) : null);

  return (
    <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_320px]">
      <div className="space-y-4">
        <Tarjeta>
          <h2 className={`text-xl font-extrabold ${C.marinoTexto}`}>Solicitudes de destacado</h2>
          <p className="mt-1 text-sm text-slate-500">
            Al aprobar, el comercio aparece en la portada del portal durante los días solicitados, desde hoy o, si ya está
            destacado, desde que termina su destacado actual.
          </p>
          <AvisoError mensaje={error} />
          <Aviso mensaje={aviso} />
          {pendientes === null ? <Cargando texto="Cargando solicitudes…" /> : pendientes.length === 0 ? (
            <p className="mt-4 text-sm text-slate-500">No hay solicitudes pendientes.</p>
          ) : (
            <ul className="mt-4 divide-y divide-slate-100">
              {pendientes.map((p) => (
                <li key={p.id_publicidad_comercio} className="flex flex-wrap items-center justify-between gap-3 py-3">
                  <span>
                    <span className="block font-bold text-slate-800">{p.nombre_comercial}</span>
                    <span className="text-sm text-slate-500">{p.dias_solicitados} días · solicitado el {fecha(p.solicitado_el ?? p.inicio)}</span>
                  </span>
                  {!confirmando && (
                    <span className="flex gap-2">
                      <Boton onClick={() => abrir(p, 'aprobar')}><Check size={16} /> Aprobar</Boton>
                      <Boton variante="peligroBorde" onClick={() => abrir(p, 'rechazar')}><X size={16} /> Rechazar</Boton>
                    </span>
                  )}
                  {confirmacion(p)}
                </li>
              ))}
            </ul>
          )}
        </Tarjeta>

        <Tarjeta>
          <h2 className={`text-lg font-extrabold ${C.marinoTexto}`}>Destacados vigentes</h2>
          <p className="mt-1 text-sm text-slate-500">Los que están en la portada ahora y los que ya están programados.</p>
          {vigentes === null ? <Cargando texto="Cargando destacados…" /> : vigentes.length === 0 ? (
            <p className="mt-4 text-sm text-slate-500">Ningún comercio está destacado ahora.</p>
          ) : (
            <ul className="mt-4 divide-y divide-slate-100">
              {vigentes.map((p) => (
                <li key={p.id_publicidad_comercio} className="flex flex-wrap items-center justify-between gap-3 py-3">
                  <span>
                    <span className="block font-bold text-slate-800">{p.nombre_comercial}</span>
                    <span className="text-sm text-slate-500">
                      Del {fecha(p.inicio)} al {fecha(p.fin)}
                      {p.vigente ? ` · le ${p.dias_restantes === 1 ? 'queda 1 día' : `quedan ${p.dias_restantes} días`}` : ' · programado'}
                    </span>
                  </span>
                  {!confirmando && (
                    <Boton variante="peligroBorde" onClick={() => abrir(p, 'retirar')}><X size={16} /> Retirar destacado</Boton>
                  )}
                  {confirmacion(p)}
                </li>
              ))}
            </ul>
          )}
        </Tarjeta>
      </div>

      <Tarjeta className="self-start">
        <h2 className={`flex items-center gap-2 text-lg font-extrabold ${C.marinoTexto}`}><Sparkles size={18} /> Destacar un comercio</h2>
        <p className="mt-1 text-sm text-slate-500">Por cuenta de la plataforma, por ejemplo para una campaña. No tiene costo para el comercio.</p>
        <label className="mt-4 block text-sm font-semibold text-slate-700" htmlFor="destacar-comercio">Comercio</label>
        <select id="destacar-comercio" value={idComercio} onChange={(e) => setIdComercio(e.target.value)}
          className="mt-1 w-full rounded-lg border border-slate-300 p-2.5">
          <option value="">Elegí un comercio activo</option>
          {comercios.map((c) => <option key={c.id_comercio} value={c.id_comercio}>{c.nombre_comercial}</option>)}
        </select>
        <label className="mt-3 block text-sm font-semibold text-slate-700" htmlFor="destacar-dias">Periodo</label>
        <select id="destacar-dias" value={dias} onChange={(e) => setDias(e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 p-2.5">
          {[7, 15, 30].map((d) => <option key={d} value={d}>{d} días</option>)}
        </select>
        <Boton className="mt-4 w-full justify-center" disabled={!idComercio || ocupado} onClick={destacarDirecto}>
          <Sparkles size={16} /> {ocupado ? 'Destacando…' : 'Destacar ahora'}
        </Boton>
      </Tarjeta>
    </div>
  );
}
