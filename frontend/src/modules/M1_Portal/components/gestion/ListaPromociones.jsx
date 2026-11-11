import { useState } from 'react';
import { Aviso } from '../componentes';
import { CargandoTarjetas, ErrorVista, Vacio } from '../EstadosVista';
import useConsulta from '../../services/useConsulta';
import { listarPromocionesAdministracion, listarPromocionesComercio, mostrarPromocion } from '../../services/promocionesApi';
import TarjetaPromocion from './TarjetaPromocion';
import FormularioPromocion from './FormularioPromocion';
import { DialogoAdelantarFin, DialogoDesactivar, DialogoOcultar, DialogoOrden } from './DialogosPromocion';
import { Boton, ESTADOS_PROMOCION, mensajeErrorPromocion, useComercios, useNombresProductos } from './promocionesUi';

// Filtro de estado como botones (en celular se desplazan de lado).
function FiltroEstado({ valor, onCambio }) {
  const opciones = [['', 'Todas'], ...Object.entries(ESTADOS_PROMOCION).map(([clave, info]) => [clave, info.filtro])];
  return (
    <div role="group" aria-label="Filtrar por estado" className="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:flex-wrap sm:px-0">
      {opciones.map(([clave, texto]) => (
        <button key={clave || 'todas'} type="button" aria-pressed={valor === clave} onClick={() => onCambio(clave)}
          className={`min-h-[44px] shrink-0 rounded-full border px-4 text-sm font-semibold transition ${valor === clave ? 'border-brandDarkBlue bg-brandDarkBlue text-white' : 'border-gray-200 bg-white text-gray-700 hover:border-brandBlue'}`}>
          {texto}
        </button>
      ))}
    </div>
  );
}

// Lista con sus estados de carga, error y vacío. `acciones(promocion)` devuelve los botones de cada tarjeta.
function Lista({ consulta, nombreProducto, nombreComercio, vacio, acciones }) {
  if (consulta.estado === 'cargando') return <CargandoTarjetas cantidad={3} etiqueta="Cargando promociones" />;
  if (consulta.estado === 'error') return <ErrorVista resultado={consulta.resultado} onReintentar={consulta.reintentar} />;
  const promociones = consulta.datos ?? [];
  if (promociones.length === 0) return vacio;
  return (
    <ul className={`grid gap-3 lg:grid-cols-2 ${consulta.estado === 'actualizando' ? 'opacity-60' : ''}`} aria-busy={consulta.estado === 'actualizando'}>
      {promociones.map((p) => (
        <li key={p.id_promocion} className="min-w-0">
          <TarjetaPromocion promocion={p} nombreProducto={nombreProducto(p.id_producto)} nombreComercio={nombreComercio?.(p.id_comercio)}>
            {acciones(p)}
          </TarjetaPromocion>
        </li>
      ))}
    </ul>
  );
}

// ---------------------------------------------------------------------------
// Comercio: sus promociones, crear, editar programadas, adelantar fin y desactivar.
// ---------------------------------------------------------------------------
export function PromocionesComercio({ idComercio }) {
  const [estado, setEstado] = useState('');
  const [dialogo, setDialogo] = useState(null); // { tipo: 'crear' | 'editar' | 'adelantar' | 'desactivar', promocion }
  const [aviso, setAviso] = useState('');
  const consulta = useConsulta(() => listarPromocionesComercio(estado || null), [estado]);
  const nombreProducto = useNombresProductos((consulta.datos ?? []).map((p) => p.id_producto));

  const cerrar = () => setDialogo(null);
  const listo = (texto) => () => { setDialogo(null); setAviso(texto); consulta.reintentar(); };

  const acciones = (p) => {
    const botones = [];
    if (p.estado === 'programada') {
      botones.push(<Boton key="editar" icono="lapiz" onClick={() => setDialogo({ tipo: 'editar', promocion: p })}>Editar</Boton>);
    }
    if (p.estado === 'vigente' || p.estado === 'oculta') {
      botones.push(<Boton key="adelantar" icono="reloj" onClick={() => setDialogo({ tipo: 'adelantar', promocion: p })}>Adelantar fin</Boton>);
    }
    if (['programada', 'vigente', 'oculta'].includes(p.estado)) {
      botones.push(<Boton key="desactivar" variante="peligro" icono="prohibido" onClick={() => setDialogo({ tipo: 'desactivar', promocion: p })}>Desactivar</Boton>);
    }
    return botones.length ? botones : null;
  };

  return (
    <section aria-labelledby="titulo-promociones" className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 id="titulo-promociones" className="text-xl font-bold text-brandDarkBlue">Promociones</h2>
          <p className="text-sm text-gray-600">Descuentos de sus productos. Se activan y terminan solos en las fechas que elija.</p>
        </div>
        <Boton variante="principal" icono="mas" onClick={() => { setAviso(''); setDialogo({ tipo: 'crear' }); }} className="w-full sm:w-auto">Nueva promoción</Boton>
      </div>

      <FiltroEstado valor={estado} onCambio={setEstado} />
      {aviso && <Aviso tipo="exito">{aviso}</Aviso>}

      <Lista consulta={consulta} nombreProducto={nombreProducto} acciones={acciones}
        vacio={(
          <Vacio icono="etiqueta" titulo={estado ? 'No hay promociones con ese estado' : 'Todavía no tiene promociones'}>
            {estado ? 'Pruebe con otro filtro.' : 'Cree una para que sus productos salgan con descuento en el portal.'}
          </Vacio>
        )} />

      {dialogo?.tipo === 'crear' && <FormularioPromocion idComercio={idComercio} onCerrar={cerrar} onGuardada={listo('Promoción creada.')} />}
      {dialogo?.tipo === 'editar' && <FormularioPromocion idComercio={idComercio} promocion={dialogo.promocion} onCerrar={cerrar} onGuardada={listo('Cambios guardados.')} />}
      {dialogo?.tipo === 'adelantar' && <DialogoAdelantarFin promocion={dialogo.promocion} onCerrar={cerrar} onListo={listo('Se adelantó el fin de la promoción.')} />}
      {dialogo?.tipo === 'desactivar' && <DialogoDesactivar promocion={dialogo.promocion} onCerrar={cerrar} onListo={listo('Promoción desactivada.')} />}
    </section>
  );
}

// ---------------------------------------------------------------------------
// Plataforma: todas las promociones, filtros, orden en el portal, ocultar y volver a mostrar.
// ---------------------------------------------------------------------------
export function PromocionesPlataforma() {
  const [estado, setEstado] = useState('');
  const [idComercio, setIdComercio] = useState('');
  const [dialogo, setDialogo] = useState(null); // { tipo: 'orden' | 'ocultar', promocion }
  const [aviso, setAviso] = useState({ tipo: 'exito', texto: '' });
  const [mostrando, setMostrando] = useState(null);
  const comercios = useComercios();
  const consulta = useConsulta(() => listarPromocionesAdministracion({ estado: estado || null, idComercio: idComercio || null }), [estado, idComercio]);
  const nombreProducto = useNombresProductos((consulta.datos ?? []).map((p) => p.id_producto));
  const nombreComercio = (id) => comercios.find((c) => c.id_comercio === id)?.nombre_comercial ?? `Comercio ${id}`;

  const cerrar = () => setDialogo(null);
  const listo = (texto) => () => { setDialogo(null); setAviso({ tipo: 'exito', texto }); consulta.reintentar(); };

  const mostrar = async (p) => {
    setMostrando(p.id_promocion);
    const respuesta = await mostrarPromocion(p.id_promocion);
    setMostrando(null);
    if (respuesta.ok) listo('La promoción se volvió a mostrar.')();
    else setAviso({ tipo: 'error', texto: mensajeErrorPromocion(respuesta) });
  };

  const acciones = (p) => {
    if (p.estado === 'finalizada' || p.estado === 'desactivada') return null;
    return [
      <Boton key="orden" icono="ordenar" onClick={() => setDialogo({ tipo: 'orden', promocion: p })}>Cambiar orden</Boton>,
      p.estado === 'oculta'
        ? <Boton key="mostrar" icono="ojo" disabled={mostrando === p.id_promocion} onClick={() => mostrar(p)}>{mostrando === p.id_promocion ? 'Mostrando...' : 'Volver a mostrar'}</Boton>
        : <Boton key="ocultar" variante="peligro" icono="ojoTachado" onClick={() => setDialogo({ tipo: 'ocultar', promocion: p })}>Ocultar</Boton>,
    ];
  };

  return (
    <section aria-labelledby="titulo-promociones" className="space-y-4">
      <div>
        <h2 id="titulo-promociones" className="text-xl font-bold text-brandDarkBlue">Promociones de los comercios</h2>
        <p className="text-sm text-gray-600">Ordene la sección de promociones del portal y oculte las que incumplan las políticas.</p>
      </div>

      <div className="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <FiltroEstado valor={estado} onCambio={setEstado} />
        <div className="lg:w-72">
          <label htmlFor="filtro-comercio" className="mb-1 block text-sm font-medium text-gray-800">Comercio</label>
          <select id="filtro-comercio" value={idComercio} onChange={(e) => setIdComercio(e.target.value)}
            className="min-h-[44px] w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-base text-gray-800 focus:border-brandBlue focus:outline-none focus:ring-2 focus:ring-brandBlue/30">
            <option value="">Todos los comercios</option>
            {comercios.map((c) => <option key={c.id_comercio} value={c.id_comercio}>{c.nombre_comercial}</option>)}
          </select>
        </div>
      </div>

      {aviso.texto && <Aviso tipo={aviso.tipo}>{aviso.texto}</Aviso>}

      <Lista consulta={consulta} nombreProducto={nombreProducto} nombreComercio={nombreComercio} acciones={acciones}
        vacio={<Vacio icono="etiqueta" titulo="No hay promociones con esos filtros">Pruebe con otro estado o comercio.</Vacio>} />

      {dialogo?.tipo === 'orden' && <DialogoOrden promocion={dialogo.promocion} onCerrar={cerrar} onListo={listo('Orden guardado.')} />}
      {dialogo?.tipo === 'ocultar' && <DialogoOcultar promocion={dialogo.promocion} onCerrar={cerrar} onListo={listo('Promoción oculta. El carrito vuelve a cobrar el precio normal.')} />}
    </section>
  );
}
