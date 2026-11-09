import { useCallback, useEffect, useState } from 'react';
import { Ban, RotateCcw } from 'lucide-react';
import { listarComercios, reactivarComercio, suspenderComercio } from '../services/m2Api';
import { Aviso, AvisoError, Boton, C, Cargando, Etiqueta, Tarjeta, useSesion } from './comunes';
import { ConfirmarAccion, FiltroEstado, POR_PAGINA, Paginacion, explicar } from './AccionesPlataforma';

const ESTADOS_COMERCIO = ['Pendiente', 'Activo', 'Rechazado', 'Suspendido'];

export default function ComerciosPlataforma() {
  const { mensajeDeError } = useSesion();
  const [estado, setEstado] = useState('');
  const [pagina, setPagina] = useState(1);
  const [datos, setDatos] = useState(null);
  const [error, setError] = useState('');
  const [ok, setOk] = useState('');
  const [accion, setAccion] = useState(null); 

  const cargar = useCallback(async () => {
    setDatos(null);
    setError('');
    const r = await listarComercios({ estado, pagina, por_pagina: POR_PAGINA });
    if (r.ok) setDatos(r.datos);
    else setError(explicar(r, mensajeDeError));
  }, [estado, pagina, mensajeDeError]);

  useEffect(() => { cargar(); }, [cargar]);

  const terminar = (r, texto) => {
    if (!r.ok) return r;
    const ficha = r.datos;
    setDatos((d) => ({
      ...d,
      comercios: d.comercios
        .map((c) => (c.id_comercio === ficha.id_comercio ? ficha : c))
        .filter((c) => !estado || c.estado === estado),
    }));
    setAccion(null);
    setOk(texto);
    return r;
  };

  return (
    <Tarjeta>
      <div className="flex flex-wrap items-end justify-between gap-3">
        <h1 className={`text-2xl font-extrabold ${C.marinoTexto}`}>Comercios</h1>
        <FiltroEstado etiqueta="Estado" valor={estado} opciones={ESTADOS_COMERCIO}
          onCambiar={(v) => { setEstado(v); setPagina(1); setAccion(null); setOk(''); }} />
      </div>
      <Aviso mensaje={ok} />
      <AvisoError mensaje={error} />
      {!datos && !error && <Cargando />}
      {datos && datos.comercios.length === 0 && (
        <p className="mt-4 text-sm text-slate-500">No hay comercios{estado ? ` en estado ${estado}` : ''}.</p>
      )}
      {datos && datos.comercios.length > 0 && (
        <ul className="mt-4 divide-y divide-slate-200 rounded-xl border border-slate-200">
          {datos.comercios.map((c) => (
            <li key={c.id_comercio} className="p-4">
              <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="min-w-0">
                  <p className="font-semibold text-slate-800">{c.nombre_comercial}</p>
                  <p className="text-xs text-slate-500">Cédula jurídica {c.cedula_juridica} · {c.razon_social}</p>
                  {c.motivo_estado && <p className="mt-1 text-xs text-slate-600"><span className="font-semibold">Motivo:</span> {c.motivo_estado}</p>}
                </div>
                <div className="flex shrink-0 items-center justify-between gap-3 sm:justify-end">
                  <Etiqueta estado={c.estado} />
                  {c.estado === 'Activo' && (
                    <Boton variante="peligro" className="py-2" onClick={() => { setAccion({ id: c.id_comercio, tipo: 'suspender' }); setOk(''); }}>
                      <Ban size={16} /> Suspender
                    </Boton>
                  )}
                  {c.estado === 'Suspendido' && (
                    <Boton variante="secundario" className="py-2" onClick={() => { setAccion({ id: c.id_comercio, tipo: 'reactivar' }); setOk(''); }}>
                      <RotateCcw size={16} /> Reactivar
                    </Boton>
                  )}
                </div>
              </div>
              {accion?.id === c.id_comercio && accion.tipo === 'suspender' && (
                <ConfirmarAccion
                  pregunta={`¿Suspender a ${c.nombre_comercial}?`}
                  aviso="Dejará de verse en el portal. Los pedidos ya pagados se completan, pero no entrarán pedidos nuevos."
                  pideMotivo variante="peligro" textoConfirmar="Sí, suspender"
                  onConfirmar={async (motivo) => terminar(await suspenderComercio(c.id_comercio, motivo), `${c.nombre_comercial} quedó Suspendido.`)}
                  onCancelar={() => setAccion(null)} />
              )}
              {accion?.id === c.id_comercio && accion.tipo === 'reactivar' && (
                <ConfirmarAccion
                  pregunta={`¿Reactivar a ${c.nombre_comercial}?`}
                  aviso="Volverá a verse en el portal y podrá recibir pedidos."
                  variante="secundario" textoConfirmar="Sí, reactivar"
                  onConfirmar={async () => terminar(await reactivarComercio(c.id_comercio), `${c.nombre_comercial} quedó Activo de nuevo.`)}
                  onCancelar={() => setAccion(null)} />
              )}
            </li>
          ))}
        </ul>
      )}
      {datos && <Paginacion pagina={pagina} total={datos.total} onCambiar={setPagina} />}
    </Tarjeta>
  );
}