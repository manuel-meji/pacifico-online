import { useCallback, useEffect, useState } from 'react';
import { Check, Sparkles, X } from 'lucide-react';
import { gestionarPublicidad, listarComercios, listarPublicidadPendiente, solicitarPublicidad } from '../services/m2Api';
import { Aviso, AvisoError, Boton, C, Cargando, Tarjeta, textoDeError, useSesion } from './comunes';

// Destacar comercios desde la plataforma: revisar las solicitudes de los encargados
// y destacar un comercio directamente (sin costo para el comercio).

const fecha = (iso) => (iso ? new Date(`${iso}T00:00:00`).toLocaleDateString('es-CR') : '');

export default function DestacadosPlataforma() {
  const { mensajeDeError } = useSesion();
  const explicar = (r) => mensajeDeError?.(r) || textoDeError(r);

  const [pendientes, setPendientes] = useState(null);
  const [comercios, setComercios] = useState([]);
  const [error, setError] = useState('');
  const [aviso, setAviso] = useState('');
  const [ocupado, setOcupado] = useState(null);
  const [idComercio, setIdComercio] = useState('');
  const [dias, setDias] = useState(7);

  const cargar = useCallback(async () => {
    setError('');
    const [rp, rc] = await Promise.all([listarPublicidadPendiente(), listarComercios({ estado: 'Activo', por_pagina: 50 })]);
    if (rp.ok) setPendientes(rp.datos.publicidades); else setError(explicar(rp));
    if (rc.ok) setComercios(rc.datos.comercios);
  }, []); // eslint-disable-line react-hooks/exhaustive-deps

  useEffect(() => { cargar(); }, [cargar]);

  const resolver = async (p, accion) => {
    if (!window.confirm(`¿${accion === 'aprobar' ? 'Aprobar' : 'Rechazar'} el destacado de ${p.nombre_comercial} por ${p.dias_solicitados} días?`)) return;
    setOcupado(p.id_publicidad_comercio); setAviso(''); setError('');
    const r = await gestionarPublicidad(p.id_publicidad_comercio, accion);
    setOcupado(null);
    if (r.ok) { setAviso(accion === 'aprobar' ? `${p.nombre_comercial} ya aparece como destacado.` : 'Solicitud rechazada.'); cargar(); }
    else setError(explicar(r));
  };

  const destacarDirecto = async () => {
    const comercio = comercios.find((c) => String(c.id_comercio) === String(idComercio));
    if (!comercio) return;
    setOcupado('directo'); setAviso(''); setError('');
    const r = await solicitarPublicidad(comercio.id_comercio, Number(dias));
    setOcupado(null);
    if (r.ok) { setAviso(`${comercio.nombre_comercial} quedó destacado por ${dias} días.`); setIdComercio(''); }
    else setError(explicar(r));
  };

  return (
    <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_320px]">
      <Tarjeta>
        <h2 className={`text-xl font-extrabold ${C.marinoTexto}`}>Solicitudes de destacado</h2>
        <p className="mt-1 text-sm text-slate-500">Los comercios aprobados aparecen como destacados en la portada del portal durante los días solicitados, contados desde hoy.</p>
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
                  <span className="text-sm text-slate-500">{p.dias_solicitados} días · solicitado el {fecha(p.inicio)}</span>
                </span>
                <span className="flex gap-2">
                  <Boton disabled={ocupado !== null} onClick={() => resolver(p, 'aprobar')}><Check size={16} /> Aprobar</Boton>
                  <Boton variante="neutro" disabled={ocupado !== null} onClick={() => resolver(p, 'rechazar')}><X size={16} /> Rechazar</Boton>
                </span>
              </li>
            ))}
          </ul>
        )}
      </Tarjeta>

      <Tarjeta>
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
        <Boton className="mt-4 w-full justify-center" disabled={!idComercio || ocupado !== null} onClick={destacarDirecto}>
          <Sparkles size={16} /> {ocupado === 'directo' ? 'Destacando…' : 'Destacar ahora'}
        </Boton>
      </Tarjeta>
    </div>
  );
}
