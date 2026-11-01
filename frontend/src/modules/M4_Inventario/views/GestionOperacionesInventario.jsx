// Pantalla de operaciones de bodega (M4). Realiza CU-M4-01 «Registrar movimiento de inventario» (entrada con
// costo unitario y salida manual) y CU-M4-05 «Transferir stock entre ubicaciones» (con autorización previa).
// Usa las operaciones registrarMovimiento y registrarTransferencia del contrato v1.2.0 (en el PC4, vía Inertia).
// Incluye los hallazgos H1 a H12 de la validación (8.2): H1 a H6 de la revisión heurística y H7 a H12 de la evaluación por tareas.
import { useEffect, useMemo, useRef, useState } from 'react';
import { IconoCargando, IconoEntrada, IconoEscudo, IconoSalida, IconoTransferir } from '../components/iconos';
import { Aviso, BotonPrimario, Campo, Tarjeta, claseEntrada, formatoColones } from '../components/componentes';
import { ubicaciones, variaciones } from '../services/datosMock';
import { disponible } from '../services/inventarioApi';

const OPERACIONES = [
  { id: 'entrada', nombre: 'Entrada', Icono: IconoEntrada },
  { id: 'salida', nombre: 'Salida', Icono: IconoSalida },
  { id: 'transferencia', nombre: 'Transferir', Icono: IconoTransferir },
];

// H7 (8.2): las cantidades son enteras (integer, minimum 1 en el contrato): solo dígitos.
const SOLO_DIGITOS = /^\d+$/;
// El costo unitario es number (minimum 0) y se guarda como numeric(14,4): se aceptan decimales con punto, hasta dos.
// «9200», «9200.5» y «9200.50» son válidos. «9 200», «9,200», «₡9200» y «9.200» se rechazan con un mensaje que
// explica el formato, para no repetir H7: «9.200» con tres cifras tras el punto es un separador de miles, no ₡9,20.
const MONTO = /^\d+(\.\d{1,2})?$/;

function errorMonto(texto) {
  if (texto === '') return 'El costo unitario es obligatorio en las entradas.';
  if (MONTO.test(texto)) return null;
  if (/^\d{1,3}(\.\d{3})+$/.test(texto)) return `Parece un separador de miles. Escriba ${texto.replaceAll('.', '')}, sin puntos; use el punto solo para decimales (ej. 9200.50).`;
  if (/^\d+\.\d{3,}$/.test(texto)) return 'Use como máximo dos decimales, por ejemplo 9200.50.';
  return 'Escriba el monto solo con números, sin espacios, comas ni «₡». Para decimales use punto: 9200.50.';
}

const vacio = { id_variacion: 501, id_ubicacion: 12, id_ubicacion_destino: 13, cantidad: '', costo_unitario: '', justificacion: '', id_autorizacion: '' };

export default function GestionOperacionesInventario({ api, existencias, alCambiar }) {
  const [operacion, setOperacion] = useState('entrada');
  const [form, setForm] = useState(vacio);
  const [errores, setErrores] = useState({});
  const [resultado, setResultado] = useState(null);
  const [errorApi, setErrorApi] = useState(null);
  const [enviando, setEnviando] = useState(false);
  const [erroresApi, setErroresApi] = useState({}); // detalles por campo que devuelve la API (ERR-005, ERR-002): H8
  const [comercio, setComercio] = useState({ cargando: true }); // estado del comercio al abrir la vista: H9

  // H9 (8.2): se consulta el estado del comercio (ObtenerComercioActivo, M2) al abrir la pantalla, no al enviar.
  useEffect(() => {
    let vigente = true;
    api.consultarEstadoComercio()
      .then((c) => vigente && setComercio({ estado: c.estado, activo: c.activo, motivo: c.motivo_estado }))
      .catch((err) => vigente && setComercio({ error: err.error }));
    return () => { vigente = false; };
  }, [api]);
  const bloqueado = !comercio.cargando && !comercio.error && comercio.activo === false;

  const avisoRef = useRef(null);
  // Hallazgo H1 (8.2): a 360 px el resultado quedaba fuera de la vista al enviar; se lleva el aviso a la vista.
  useEffect(() => { if (resultado || errorApi) avisoRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' }); }, [resultado, errorApi]);

  const existencia = useMemo(
    () => existencias.find((e) => e.id_variacion === Number(form.id_variacion) && e.id_ubicacion === Number(form.id_ubicacion)),
    [existencias, form.id_variacion, form.id_ubicacion],
  );
  const destino = existencias.find((e) => e.id_variacion === Number(form.id_variacion) && e.id_ubicacion === Number(form.id_ubicacion_destino));
  const cambiar = (campo) => (ev) => {
    setForm({ ...form, [campo]: ev.target.value });
    setErrores({ ...errores, [campo]: null });
    setErroresApi({ ...erroresApi, [campo]: null });
  };
  // Errores visibles por campo: los del cliente primero y, si no hay, el motivo que trae detalles de la API (H8).
  const err = { ...erroresApi, ...Object.fromEntries(Object.entries(errores).filter(([, v]) => v)) };
  const costoTexto = form.costo_unitario.trim();
  const costoInterpretado = operacion === 'entrada' && MONTO.test(costoTexto) ? Number(costoTexto) : null;

  // Validación del lado del cliente con las mismas reglas del esquema; el servidor responde ERR-005 si algo se escapa.
  function validar() {
    const e = {};
    const cantidadTexto = form.cantidad.trim();
    if (cantidadTexto === '') e.cantidad = 'Ingrese una cantidad entera mayor que cero.';
    else if (!SOLO_DIGITOS.test(cantidadTexto)) e.cantidad = 'Escriba la cantidad solo con números enteros, por ejemplo 10.';
    else if (Number(cantidadTexto) < 1) e.cantidad = 'Ingrese una cantidad entera mayor que cero.';
    if (operacion === 'entrada') {
      const falla = errorMonto(costoTexto);
      if (falla) e.costo_unitario = falla;
    }
    if (!form.justificacion.trim()) e.justificacion = 'Indique el motivo del movimiento.';
    if (operacion === 'transferencia') {
      if (Number(form.id_ubicacion) === Number(form.id_ubicacion_destino)) e.id_ubicacion_destino = 'El destino debe ser distinto del origen.';
      if (!form.id_autorizacion.trim()) e.id_autorizacion = 'La transferencia requiere la autorización del Administrador del comercio.';
    }
    setErrores(e);
    return Object.keys(e).length === 0;
  }

  async function enviar(ev) {
    ev.preventDefault();
    setResultado(null);
    setErrorApi(null);
    setErroresApi({});
    if (bloqueado || !validar()) return;
    setEnviando(true);
    try {
      const base = { id_comercio: 104, id_variacion: Number(form.id_variacion), cantidad: Number(form.cantidad.trim()), justificacion: form.justificacion.trim() };
      const respuesta = operacion === 'transferencia'
        ? await api.registrarTransferencia(existencias, { ...base, id_ubicacion_origen: Number(form.id_ubicacion), id_ubicacion_destino: Number(form.id_ubicacion_destino), id_autorizacion: form.id_autorizacion.trim() })
        : await api.registrarMovimientoManual(existencias, { ...base, id_ubicacion: Number(form.id_ubicacion), tipo_movimiento: operacion, ...(operacion === 'entrada' && { costo_unitario: Number(costoTexto) }) });
      setResultado({ operacion, respuesta });
      setForm({ ...vacio, id_variacion: form.id_variacion, id_ubicacion: form.id_ubicacion });
      alCambiar();
    } catch (falla) {
      const detalle = falla.error ?? { codigo: 'ERR-006', mensaje: 'No se pudo completar la operación.' };
      setErrorApi(detalle);
      // ERR-005 trae en detalles el mensaje por campo; ERR-002 trae el código en detalles.id_autorizacion y el motivo en mensaje.
      setErroresApi(detalle.codigo === 'ERR-002' && detalle.detalles?.id_autorizacion
        ? { id_autorizacion: detalle.mensaje }
        : detalle.codigo === 'ERR-005'
          ? Object.fromEntries(Object.entries(detalle.detalles ?? {}).filter(([, v]) => typeof v === 'string'))
          : {});
    } finally {
      setEnviando(false);
    }
  }

  const etiquetaUbicacion = operacion === 'transferencia' ? 'Ubicación de origen' : 'Ubicación';

  return (
    <div className="space-y-4">
      <div role="tablist" aria-label="Tipo de operación" className="grid grid-cols-3 gap-1 rounded-xl bg-gray-100 p-1">
        {OPERACIONES.map(({ id, nombre, Icono }) => (
          <button key={id} role="tab" id={`tab-${id}`} aria-selected={operacion === id} aria-controls="panel-operacion" type="button"
            onClick={() => { setOperacion(id); setErrores({}); setErroresApi({}); setErrorApi(null); setResultado(null); }}
            className={`flex min-w-0 flex-col items-center gap-1 rounded-lg px-1 py-2 text-xs font-semibold transition sm:flex-row sm:justify-center sm:text-sm ${operacion === id ? 'bg-white text-brandDarkBlue shadow-sm' : 'text-gray-600 hover:text-brandBlue'}`}>
            <Icono className="h-5 w-5" />
            <span>{nombre}</span>
          </button>
        ))}
      </div>

      {bloqueado && (
        <Aviso tipo="error" titulo="El comercio no puede registrar movimientos" codigo="ERR-M4-015">
          El comercio está {String(comercio.estado).toLowerCase()}{comercio.motivo ? ` (${comercio.motivo})` : ''}. No se permiten entradas, salidas ni transferencias hasta que vuelva a estar activo.
        </Aviso>
      )}
      {comercio.error && (
        <Aviso tipo="aviso" titulo="No se pudo verificar el estado del comercio" codigo={comercio.error.codigo}>
          {comercio.error.mensaje} Puede intentar registrar la operación; si el problema continúa, se le avisará al enviar.
        </Aviso>
      )}

      <div ref={avisoRef} className="scroll-mt-4 space-y-2">
      {resultado && (
        <Aviso tipo="exito" titulo={resultado.operacion === 'transferencia' ? 'Transferencia registrada' : 'Movimiento registrado'}>
          {resultado.operacion === 'transferencia'
            ? `Se movieron ${resultado.respuesta.movimientos[0].cantidad} unidades. Quedan ${resultado.respuesta.movimientos[0].saldo_disponible} disponibles en el origen.`
            : `Disponible: ${resultado.respuesta.saldo_disponible} · Reservado: ${resultado.respuesta.saldo_reservado}. Quedó en el Kardex.`}
        </Aviso>
      )}
      {errorApi && <Aviso tipo="error" titulo="No se registró la operación" codigo={errorApi.codigo}>{errorApi.mensaje}</Aviso>}
      </div>

      <div role="tabpanel" id="panel-operacion" aria-labelledby={`tab-${operacion}`}>
      <Tarjeta>
        <form onSubmit={enviar} noValidate className="space-y-4">
          <Campo id="variacion" etiqueta="Variación">
            <select id="variacion" className={claseEntrada(false)} value={form.id_variacion} onChange={cambiar('id_variacion')}>
              {variaciones.map((v) => <option key={v.id_variacion} value={v.id_variacion}>{v.sku} · {v.atributos}</option>)}
            </select>
          </Campo>

          <Campo id="ubicacion" etiqueta={etiquetaUbicacion}>
            <select id="ubicacion" className={claseEntrada(false)} value={form.id_ubicacion} onChange={cambiar('id_ubicacion')}>
              {ubicaciones.map((u) => <option key={u.id_ubicacion} value={u.id_ubicacion}>{u.nombre}</option>)}
            </select>
          </Campo>

          {existencia && (
            <dl className="grid grid-cols-4 gap-1 rounded-lg bg-brandLightBg p-2 text-center text-xs" aria-label="Existencia actual">
              {[['Física', existencia.cantidad_fisica], ['Reservada', existencia.cantidad_reservada], ['Defectuosa', existencia.cantidad_defectuosa], ['Disponible', disponible(existencia)]].map(([n, v], i) => (
                <div key={n} className="min-w-0">
                  <dt className="truncate text-gray-500">{n}</dt>
                  <dd className={`text-base font-bold ${i === 3 ? 'text-brandDarkBlue' : 'text-gray-800'}`}>{v}</dd>
                </div>
              ))}
            </dl>
          )}

          {operacion !== 'entrada' && (
            <p className="text-xs text-gray-500">Solo se pueden usar las unidades disponibles: las reservadas por pedidos y las defectuosas no salen ni se trasladan.</p>
          )}

          {operacion === 'transferencia' && (
            <Campo id="destino" etiqueta="Ubicación de destino" error={err.id_ubicacion_destino}>
              <select id="destino" aria-invalid={!!err.id_ubicacion_destino} aria-describedby={err.id_ubicacion_destino ? 'destino-error' : undefined} className={claseEntrada(err.id_ubicacion_destino)} value={form.id_ubicacion_destino} onChange={cambiar('id_ubicacion_destino')}>
                {ubicaciones.map((u) => <option key={u.id_ubicacion} value={u.id_ubicacion}>{u.nombre}</option>)}
              </select>
              {destino && <p className="text-xs text-gray-500">En destino hay <span className="font-semibold text-gray-700">{disponible(destino)}</span> disponibles.</p>}
            </Campo>
          )}

          <div className={`grid gap-4 ${operacion === 'entrada' ? 'grid-cols-2' : 'grid-cols-1'}`}>
            <Campo id="cantidad" etiqueta="Cantidad" error={err.cantidad}>
              <input id="cantidad" inputMode="numeric" className={claseEntrada(err.cantidad)} value={form.cantidad} onChange={cambiar('cantidad')} placeholder="Ej. 10" aria-invalid={!!err.cantidad} aria-describedby={err.cantidad ? 'cantidad-error' : undefined} />
            </Campo>
            {operacion === 'entrada' && (
              <Campo id="costo" etiqueta="Costo unitario (₡)" error={err.costo_unitario}>
                <input id="costo" inputMode="decimal" className={claseEntrada(err.costo_unitario)} value={form.costo_unitario} onChange={cambiar('costo_unitario')} placeholder="Ej. 8500.50" aria-invalid={!!err.costo_unitario} aria-describedby={err.costo_unitario ? 'costo-error' : undefined} />
              </Campo>
            )}
          </div>
          {costoInterpretado !== null && (
            <p className="text-xs text-gray-500" aria-live="polite">Se registrará un costo unitario de <span className="font-semibold text-gray-700">{formatoColones(costoInterpretado)}</span>.</p>
          )}
          {operacion === 'entrada' && existencia && (
            <p className="text-xs text-gray-500">Costo promedio actual: <span className="font-semibold text-gray-700">{formatoColones(existencia.costo_promedio)}</span></p>
          )}

          <Campo id="justificacion" etiqueta="Motivo" error={err.justificacion} ayuda="Queda en el Kardex junto con su usuario, la fecha y la hora.">
            <textarea id="justificacion" rows={2} maxLength={255} className={claseEntrada(err.justificacion)} value={form.justificacion} onChange={cambiar('justificacion')} aria-invalid={!!err.justificacion} aria-describedby={err.justificacion ? 'justificacion-error' : undefined}
              placeholder={operacion === 'entrada' ? 'Compra a proveedor, factura…' : 'Motivo del movimiento'} />
          </Campo>

          {operacion === 'transferencia' && (
            <Campo id="autorizacion" etiqueta="Código de autorización" error={err.id_autorizacion} ayuda="Lo emite el Administrador del comercio para esta variación, ubicaciones y cantidad.">
              <div className="relative">
                <IconoEscudo className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                <input id="autorizacion" className={`${claseEntrada(err.id_autorizacion)} pl-9`} value={form.id_autorizacion} onChange={cambiar('id_autorizacion')} placeholder="Código que le dio el administrador" autoCapitalize="none" aria-invalid={!!err.id_autorizacion} aria-describedby={err.id_autorizacion ? 'autorizacion-error' : undefined} />
              </div>
            </Campo>
          )}

          <BotonPrimario type="submit" cargando={enviando} disabled={bloqueado}>
            {enviando && <IconoCargando className="h-4 w-4 animate-spin" />}
            {enviando ? 'Registrando…' : operacion === 'transferencia' ? 'Registrar transferencia' : `Registrar ${operacion}`}
          </BotonPrimario>
        </form>
      </Tarjeta>
      </div>
    </div>
  );
}
