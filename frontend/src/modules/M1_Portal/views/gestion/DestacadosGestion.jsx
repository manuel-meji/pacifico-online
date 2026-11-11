import { useCallback, useEffect, useMemo, useState } from 'react';
import { Aviso, BotonPrincipal, CampoConIcono, CampoSeleccion } from '../../components/componentes';
import Icono from '../../components/IconoComercio';
import { colones } from '../../services/formato';
import { buscarProductos, obtenerFichaProducto, obtenerMicrositio } from '../../services/m1Api';
import {
  aIsoCostaRica, aprobarSolicitud, definirTarifa, destacarProductoPlataforma, listarSolicitudesAdministracion,
  listarSolicitudesComercio, listarTarifas, rechazarSolicitud, solicitarDestacado,
} from '../../services/destacadosApi';

// Productos destacados en el panel de gestión (contrato M1 1.4.0, SC-08).
// - Comercio: ve las tarifas, solicita destacar uno de sus productos y sigue sus solicitudes.
// - Plataforma: define las tarifas, aprueba o rechaza solicitudes y destaca productos sin costo.

const PERIODOS = [7, 15, 30];
const ESTADOS = { pendiente: 'Pendiente', aprobada: 'Aprobada', rechazada: 'Rechazada' };

// Mensaje legible de un error del núcleo; los propios de destacados con un texto claro.
function textoError(respuesta) {
  const { codigo, mensaje } = respuesta.error || {};
  if (codigo === 'ERR-M1-002') return 'El comercio ya tiene el máximo de 3 productos destacados activos en ese periodo.';
  if (codigo === 'ERR-M1-003') return mensaje || 'El producto ya está destacado o tiene una solicitud en curso.';
  if (respuesta.estado === 401) return 'Su sesión expiró. Vuelva a ingresar.';
  if (respuesta.estado === 403) return 'Su cuenta no tiene permiso para esta acción.';
  return `${mensaje || 'No se pudo completar la acción.'}${codigo ? ` (${codigo})` : ''}`;
}

const detalles = (respuesta) => respuesta.error?.detalles || {};
const fechaHora = (iso) => (iso ? new Date(iso).toLocaleString('es-CR', { dateStyle: 'medium', timeStyle: 'short' }) : '');

// Nombre de cada producto a partir de su ficha (las solicitudes solo traen el id). Se pide una vez por id.
function useNombresProductos(ids) {
  const [nombres, setNombres] = useState({});
  const clave = [...new Set(ids)].sort().join(',');

  useEffect(() => {
    let activo = true;
    const faltan = clave.split(',').filter((id) => id && !(id in nombres));
    faltan.forEach((id) => {
      obtenerFichaProducto(Number(id)).then((r) => {
        if (activo) setNombres((n) => ({ ...n, [id]: r.ok ? r.datos.nombre : null }));
      });
    });
    return () => { activo = false; };
  }, [clave]); // eslint-disable-line react-hooks/exhaustive-deps

  return (id) => nombres[id] ?? (id in nombres ? `Producto ${id}` : 'Cargando…');
}

function Seccion({ titulo, descripcion, children }) {
  return (
    <section className="rounded-xl border border-gray-100 bg-white p-4 shadow-sm sm:p-6">
      <h2 className="text-lg font-bold text-brandDarkBlue">{titulo}</h2>
      {descripcion && <p className="mt-1 text-sm text-gray-600">{descripcion}</p>}
      <div className="mt-4">{children}</div>
    </section>
  );
}

function BadgeEstado({ estado }) {
  const estilos = {
    pendiente: 'border-amber-200 bg-amber-50 text-amber-800',
    aprobada: 'border-green-200 bg-green-50 text-green-700',
    rechazada: 'border-red-200 bg-red-50 text-red-700',
  };
  return <span className={`inline-flex rounded-full border px-2.5 py-0.5 text-xs font-semibold ${estilos[estado] || ''}`}>{ESTADOS[estado] || estado}</span>;
}

function BotonSecundario({ children, className = '', ...props }) {
  return (
    <button type="button" {...props}
      className={`inline-flex min-h-[44px] items-center justify-center gap-1.5 rounded-lg border border-brandDarkBlue px-4 text-sm font-semibold text-brandDarkBlue transition hover:bg-brandLightBg disabled:cursor-not-allowed disabled:opacity-50 ${className}`}>
      {children}
    </button>
  );
}

// Tarjeta de una solicitud, para el comercio y para la plataforma.
function TarjetaSolicitud({ solicitud, nombreProducto, mostrarComercio = false, children }) {
  return (
    <li className="rounded-lg border border-gray-200 p-3 sm:p-4">
      <div className="flex flex-wrap items-start justify-between gap-2">
        <div className="min-w-0">
          <p className="break-words font-semibold text-gray-900">{nombreProducto(solicitud.id_producto)}</p>
          <p className="text-sm text-gray-600">
            {mostrarComercio && `Comercio ${solicitud.id_comercio} · `}{solicitud.periodo_dias} días · {colones(solicitud.monto)}
          </p>
        </div>
        <BadgeEstado estado={solicitud.estado} />
      </div>
      <p className="mt-2 text-xs text-gray-500">Solicitada el {fechaHora(solicitud.fecha_solicitud)}</p>
      {solicitud.estado === 'aprobada' && (
        <p className="mt-1 text-sm text-green-700">Destacado del {fechaHora(solicitud.fecha_inicio)} al {fechaHora(solicitud.fecha_fin)}</p>
      )}
      {solicitud.estado === 'rechazada' && solicitud.motivo_rechazo && (
        <p className="mt-1 text-sm text-red-700">Motivo: {solicitud.motivo_rechazo}</p>
      )}
      {children}
    </li>
  );
}

function useTarifas() {
  const [tarifas, setTarifas] = useState(null);
  const cargar = useCallback(() => listarTarifas().then((r) => setTarifas(r.ok ? r.datos : [])), []);
  useEffect(() => { cargar(); }, [cargar]);
  return [tarifas, cargar];
}

// ---------------------------------------------------------------------------------------------
// Comercio
// ---------------------------------------------------------------------------------------------

function DestacadosComercio({ idComercio }) {
  const [tarifas] = useTarifas();
  const [productos, setProductos] = useState(null);
  const [filtro, setFiltro] = useState('');
  const [idProducto, setIdProducto] = useState('');
  const [periodo, setPeriodo] = useState('');
  const [solicitudes, setSolicitudes] = useState(null);
  const [enviando, setEnviando] = useState(false);
  const [resultado, setResultado] = useState(null);
  const [errores, setErrores] = useState({});

  const cargarSolicitudes = useCallback(() => {
    listarSolicitudesComercio().then((r) => setSolicitudes(r.ok ? r.datos : []));
  }, []);

  useEffect(() => { cargarSolicitudes(); }, [cargarSolicitudes]);

  // Productos publicados del comercio, desde su micrositio (M1 los pide a M3).
  useEffect(() => {
    if (!idComercio) { setProductos([]); return; }
    obtenerMicrositio(idComercio, 1, 50).then((r) => setProductos(r.ok ? r.datos.productos.productos : []));
  }, [idComercio]);

  const visibles = useMemo(() => {
    const texto = filtro.trim().toLowerCase();
    return (productos || []).filter((p) => !texto || p.nombre.toLowerCase().includes(texto));
  }, [productos, filtro]);

  const nombreProducto = useNombresProductos((solicitudes || []).map((s) => s.id_producto));
  const tarifa = (tarifas || []).find((t) => t.periodo_dias === Number(periodo));
  const periodosConTarifa = (tarifas || []).map((t) => t.periodo_dias);

  async function solicitar(e) {
    e.preventDefault();
    const errs = {};
    if (!idProducto) errs.id_producto = 'Elija un producto.';
    if (!periodo) errs.periodo_dias = 'Elija el periodo.';
    setErrores(errs);
    setResultado(null);
    if (Object.keys(errs).length) return;

    setEnviando(true);
    const r = await solicitarDestacado(Number(idProducto), Number(periodo));
    setEnviando(false);
    if (r.ok) {
      setResultado({ tipo: 'exito', texto: 'Solicitud enviada. La plataforma la revisará pronto.' });
      setIdProducto(''); setPeriodo('');
      cargarSolicitudes();
    } else {
      setErrores(detalles(r));
      setResultado({ tipo: 'error', texto: textoError(r) });
    }
  }

  return (
    <div className="grid gap-6 lg:grid-cols-[1fr_1fr]">
      <Seccion titulo="Solicitar un producto destacado"
        descripcion="Su producto aparece en la página principal durante el periodo elegido. El monto se descuenta de su liquidación al aprobarse.">
        {tarifas && tarifas.length === 0 && <Aviso>La plataforma todavía no definió las tarifas. Vuelva a intentarlo más tarde.</Aviso>}
        {tarifas && tarifas.length > 0 && (
          <ul className="mb-4 grid grid-cols-3 gap-2">
            {tarifas.map((t) => (
              <li key={t.periodo_dias} className="rounded-lg border border-gray-200 p-2 text-center">
                <p className="text-xs text-gray-500">{t.periodo_dias} días</p>
                <p className="font-bold text-brandDarkBlue">{colones(t.monto)}</p>
              </li>
            ))}
          </ul>
        )}

        <form onSubmit={solicitar} noValidate className="space-y-4">
          {resultado && <Aviso tipo={resultado.tipo}>{resultado.texto}</Aviso>}
          <CampoConIcono id="filtro-producto" etiqueta="Buscar entre sus productos" icono="buscar" type="search"
            value={filtro} onChange={(e) => setFiltro(e.target.value)} placeholder="Nombre del producto" />
          <div>
            <p className="mb-1 text-sm font-medium text-gray-800">Producto</p>
            {productos === null && <p className="text-sm text-gray-500">Cargando sus productos…</p>}
            {productos && productos.length === 0 && <p className="text-sm text-gray-600">No hay productos publicados de su comercio.</p>}
            {visibles.length > 0 && (
              <ul className="max-h-64 space-y-1 overflow-y-auto rounded-lg border border-gray-200 p-1" role="radiogroup" aria-label="Producto">
                {visibles.map((p) => (
                  <li key={p.id_producto}>
                    <label className={`flex min-h-[44px] cursor-pointer items-center gap-3 rounded-md px-2 ${String(p.id_producto) === idProducto ? 'bg-blue-50' : 'hover:bg-gray-50'}`}>
                      <input type="radio" name="producto-destacar" value={p.id_producto} checked={String(p.id_producto) === idProducto}
                        onChange={(e) => setIdProducto(e.target.value)} className="h-5 w-5 accent-brandDarkBlue" />
                      <span className="min-w-0 flex-1 break-words text-sm text-gray-800">{p.nombre}</span>
                      <span className="shrink-0 text-xs text-gray-500">{colones(p.precio)}</span>
                    </label>
                  </li>
                ))}
              </ul>
            )}
            {errores.id_producto && <p className="mt-1 text-xs text-red-600">{errores.id_producto}</p>}
          </div>
          <CampoSeleccion id="periodo-destacado" etiqueta="Periodo" icono="calendario"
            opciones={PERIODOS.filter((p) => periodosConTarifa.includes(p)).map(String)}
            indicacion="Elija el periodo" sinOpciones="Sin tarifas definidas"
            value={periodo} onChange={(e) => setPeriodo(e.target.value)} error={errores.periodo_dias} />
          {tarifa && <p className="text-sm text-gray-700">Monto: <strong>{colones(tarifa.monto)}</strong> por {tarifa.periodo_dias} días.</p>}
          <BotonPrincipal type="submit" disabled={enviando || !tarifas?.length}>{enviando ? 'Enviando…' : 'Solicitar destacado'}</BotonPrincipal>
        </form>
      </Seccion>

      <Seccion titulo="Mis solicitudes">
        {solicitudes === null && <p className="text-sm text-gray-500">Cargando…</p>}
        {solicitudes && solicitudes.length === 0 && <p className="text-sm text-gray-600">Todavía no ha solicitado productos destacados.</p>}
        {solicitudes && solicitudes.length > 0 && (
          <ul className="space-y-3">
            {solicitudes.map((s) => <TarjetaSolicitud key={s.id_solicitud_destacado} solicitud={s} nombreProducto={nombreProducto} />)}
          </ul>
        )}
      </Seccion>
    </div>
  );
}

// ---------------------------------------------------------------------------------------------
// Plataforma
// ---------------------------------------------------------------------------------------------

function TarifasPlataforma() {
  const [tarifas, cargar] = useTarifas();
  const [valores, setValores] = useState({});
  const [estado, setEstado] = useState({});

  useEffect(() => {
    if (tarifas) setValores(Object.fromEntries(PERIODOS.map((p) => [p, String(tarifas.find((t) => t.periodo_dias === p)?.monto ?? '')])));
  }, [tarifas]);

  async function guardar(periodo) {
    const monto = Number(valores[periodo]);
    if (valores[periodo] === '' || !Number.isFinite(monto) || monto < 0) {
      setEstado((e) => ({ ...e, [periodo]: { tipo: 'error', texto: 'Ingrese un monto mayor o igual a 0.' } }));
      return;
    }
    setEstado((e) => ({ ...e, [periodo]: { tipo: 'cargando' } }));
    const r = await definirTarifa(periodo, Math.round(monto * 100) / 100);
    setEstado((e) => ({ ...e, [periodo]: r.ok ? { tipo: 'exito', texto: 'Guardada.' } : { tipo: 'error', texto: detalles(r).monto || textoError(r) } }));
    if (r.ok) cargar();
  }

  return (
    <Seccion titulo="Tarifas" descripcion="Monto fijo por periodo. Las solicitudes ya hechas conservan la tarifa con la que se pidieron.">
      <ul className="grid gap-4 sm:grid-cols-3">
        {PERIODOS.map((p) => (
          <li key={p}>
            <CampoConIcono id={`tarifa-${p}`} etiqueta={`${p} días (₡)`} icono="dinero" type="number" min="0" step="0.01" inputMode="decimal"
              value={valores[p] ?? ''} onChange={(e) => setValores((v) => ({ ...v, [p]: e.target.value }))} />
            <BotonSecundario className="mt-2 w-full" onClick={() => guardar(p)} disabled={estado[p]?.tipo === 'cargando'}>
              {estado[p]?.tipo === 'cargando' ? 'Guardando…' : 'Guardar'}
            </BotonSecundario>
            {estado[p]?.texto && <p className={`mt-1 text-xs ${estado[p].tipo === 'error' ? 'text-red-600' : 'text-green-700'}`}>{estado[p].texto}</p>}
          </li>
        ))}
      </ul>
    </Seccion>
  );
}

function SolicitudesPlataforma() {
  const [filtro, setFiltro] = useState('pendiente');
  const [solicitudes, setSolicitudes] = useState(null);
  const [rechazando, setRechazando] = useState(null);
  const [motivo, setMotivo] = useState('');
  const [ocupada, setOcupada] = useState(null);
  const [aviso, setAviso] = useState(null);

  const cargar = useCallback(() => {
    setSolicitudes(null);
    listarSolicitudesAdministracion({ estado: filtro || undefined }).then((r) => setSolicitudes(r.ok ? r.datos : []));
  }, [filtro]);

  useEffect(() => { cargar(); }, [cargar]);

  const nombreProducto = useNombresProductos((solicitudes || []).map((s) => s.id_producto));

  async function aprobar(s) {
    setOcupada(s.id_solicitud_destacado); setAviso(null);
    const r = await aprobarSolicitud(s.id_solicitud_destacado);
    setOcupada(null);
    setAviso(r.ok ? { tipo: 'exito', texto: 'Solicitud aprobada: el producto ya está destacado.' } : { tipo: 'error', texto: textoError(r) });
    if (r.ok) cargar();
  }

  async function rechazar(s) {
    if (!motivo.trim()) { setAviso({ tipo: 'error', texto: 'Escriba el motivo del rechazo.' }); return; }
    setOcupada(s.id_solicitud_destacado); setAviso(null);
    const r = await rechazarSolicitud(s.id_solicitud_destacado, motivo.trim());
    setOcupada(null);
    setAviso(r.ok ? { tipo: 'exito', texto: 'Solicitud rechazada.' } : { tipo: 'error', texto: detalles(r).motivo || textoError(r) });
    if (r.ok) { setRechazando(null); setMotivo(''); cargar(); }
  }

  return (
    <Seccion titulo="Solicitudes de los comercios">
      <div className="mb-4 flex flex-wrap gap-2" role="group" aria-label="Filtrar por estado">
        {[['pendiente', 'Pendientes'], ['aprobada', 'Aprobadas'], ['rechazada', 'Rechazadas'], ['', 'Todas']].map(([valor, texto]) => (
          <button key={texto} type="button" aria-pressed={filtro === valor} onClick={() => setFiltro(valor)}
            className={`min-h-[40px] rounded-full border px-3 text-sm font-medium ${filtro === valor ? 'border-brandDarkBlue bg-brandDarkBlue text-white' : 'border-gray-200 text-gray-700 hover:border-brandBlue'}`}>
            {texto}
          </button>
        ))}
      </div>
      {aviso && <div className="mb-3"><Aviso tipo={aviso.tipo}>{aviso.texto}</Aviso></div>}
      {solicitudes === null && <p className="text-sm text-gray-500">Cargando…</p>}
      {solicitudes && solicitudes.length === 0 && <p className="text-sm text-gray-600">No hay solicitudes en este estado.</p>}
      {solicitudes && solicitudes.length > 0 && (
        <ul className="space-y-3">
          {solicitudes.map((s) => (
            <TarjetaSolicitud key={s.id_solicitud_destacado} solicitud={s} nombreProducto={nombreProducto} mostrarComercio>
              {s.estado === 'pendiente' && (
                rechazando === s.id_solicitud_destacado ? (
                  <div className="mt-3 space-y-2">
                    <CampoConIcono id={`motivo-${s.id_solicitud_destacado}`} etiqueta="Motivo del rechazo (lo ve el comercio)" icono="texto"
                      multilinea maxLength={500} value={motivo} onChange={(e) => setMotivo(e.target.value)} />
                    <div className="flex flex-col gap-2 sm:flex-row">
                      <BotonSecundario onClick={() => rechazar(s)} disabled={ocupada === s.id_solicitud_destacado}>Confirmar rechazo</BotonSecundario>
                      <BotonSecundario onClick={() => { setRechazando(null); setMotivo(''); }}>Cancelar</BotonSecundario>
                    </div>
                  </div>
                ) : (
                  <div className="mt-3 flex flex-col gap-2 sm:flex-row">
                    <button type="button" onClick={() => aprobar(s)} disabled={ocupada === s.id_solicitud_destacado}
                      className="inline-flex min-h-[44px] items-center justify-center gap-1.5 rounded-lg bg-brandDarkBlue px-4 text-sm font-bold text-white hover:bg-brandBlue disabled:opacity-50">
                      <Icono nombre="check" className="h-4 w-4" /> Aprobar
                    </button>
                    <BotonSecundario onClick={() => { setRechazando(s.id_solicitud_destacado); setMotivo(''); }}>
                      <Icono nombre="cerrar" className="h-4 w-4" /> Rechazar
                    </BotonSecundario>
                  </div>
                )
              )}
            </TarjetaSolicitud>
          ))}
        </ul>
      )}
    </Seccion>
  );
}

function DestacarSinCosto() {
  const [termino, setTermino] = useState('');
  const [resultados, setResultados] = useState(null);
  const [elegido, setElegido] = useState(null);
  const [inicio, setInicio] = useState('');
  const [fin, setFin] = useState('');
  const [errores, setErrores] = useState({});
  const [aviso, setAviso] = useState(null);
  const [enviando, setEnviando] = useState(false);

  async function buscar(e) {
    e.preventDefault();
    if (termino.trim().length < 2) { setErrores({ termino: 'Escriba al menos 2 letras.' }); return; }
    setErrores({}); setResultados(null);
    const r = await buscarProductos({ q: termino.trim(), por_pagina: 20 });
    setResultados(r.ok ? r.datos.productos : []);
  }

  async function destacar(e) {
    e.preventDefault();
    const errs = {};
    if (!elegido) errs.producto = 'Elija un producto.';
    if (!inicio) errs.fecha_inicio = 'Indique la fecha y hora de inicio.';
    if (!fin) errs.fecha_fin = 'Indique la fecha y hora de fin.';
    if (inicio && fin && fin <= inicio) errs.fecha_fin = 'Debe ser posterior al inicio.';
    setErrores(errs); setAviso(null);
    if (Object.keys(errs).length) return;

    setEnviando(true);
    const r = await destacarProductoPlataforma({ idProducto: elegido.id_producto, fechaInicio: aIsoCostaRica(inicio), fechaFin: aIsoCostaRica(fin) });
    setEnviando(false);
    if (r.ok) {
      setAviso({ tipo: 'exito', texto: `${elegido.nombre} quedó destacado sin costo.` });
      setElegido(null); setInicio(''); setFin('');
    } else {
      setErrores(detalles(r));
      setAviso({ tipo: 'error', texto: textoError(r) });
    }
  }

  return (
    <Seccion titulo="Destacar un producto sin costo" descripcion="Para campañas de la plataforma. Respeta el máximo de 3 destacados activos por comercio.">
      <form onSubmit={buscar} noValidate className="flex flex-col gap-2 sm:flex-row sm:items-end">
        <div className="flex-1">
          <CampoConIcono id="buscar-destacar" etiqueta="Buscar producto" icono="buscar" type="search" value={termino}
            onChange={(e) => setTermino(e.target.value)} error={errores.termino} placeholder="Nombre del producto" />
        </div>
        <BotonSecundario type="submit"><Icono nombre="buscar" className="h-4 w-4" /> Buscar</BotonSecundario>
      </form>

      {resultados && resultados.length === 0 && <p className="mt-3 text-sm text-gray-600">No se encontraron productos.</p>}
      {resultados && resultados.length > 0 && (
        <ul className="mt-3 max-h-56 space-y-1 overflow-y-auto rounded-lg border border-gray-200 p-1">
          {resultados.map((p) => (
            <li key={p.id_producto}>
              <button type="button" onClick={() => setElegido(p)}
                className={`flex min-h-[44px] w-full items-center justify-between gap-2 rounded-md px-2 text-left text-sm ${elegido?.id_producto === p.id_producto ? 'bg-blue-50 font-semibold' : 'hover:bg-gray-50'}`}>
                <span className="min-w-0 break-words">{p.nombre}</span>
                <span className="shrink-0 text-xs text-gray-500">{p.comercio?.nombre_comercial}</span>
              </button>
            </li>
          ))}
        </ul>
      )}
      {errores.producto && <p className="mt-1 text-xs text-red-600">{errores.producto}</p>}

      <form onSubmit={destacar} noValidate className="mt-4 space-y-4">
        {elegido && <p className="text-sm text-gray-700">Producto: <strong>{elegido.nombre}</strong></p>}
        <div className="grid gap-4 sm:grid-cols-2">
          <CampoConIcono id="destacar-inicio" etiqueta="Inicio (hora de Costa Rica)" icono="calendario" type="datetime-local"
            value={inicio} onChange={(e) => setInicio(e.target.value)} error={errores.fecha_inicio} />
          <CampoConIcono id="destacar-fin" etiqueta="Fin (hora de Costa Rica)" icono="calendario" type="datetime-local"
            value={fin} onChange={(e) => setFin(e.target.value)} error={errores.fecha_fin} />
        </div>
        {aviso && <Aviso tipo={aviso.tipo}>{aviso.texto}</Aviso>}
        <BotonPrincipal type="submit" disabled={enviando}>{enviando ? 'Guardando…' : 'Destacar sin costo'}</BotonPrincipal>
      </form>
    </Seccion>
  );
}

function DestacadosPlataforma() {
  return (
    <div className="space-y-6">
      <SolicitudesPlataforma />
      <div className="grid gap-6 lg:grid-cols-2">
        <TarifasPlataforma />
        <DestacarSinCosto />
      </div>
    </div>
  );
}

export default function DestacadosGestion({ usuario }) {
  if (usuario.rol === 'encargado_plataforma') return <DestacadosPlataforma />;
  return <DestacadosComercio idComercio={usuario.id_comercio} />;
}
