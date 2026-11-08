// Operaciones de bodega (M4): CU-M4-01 «Registrar movimiento de inventario», entrada con costo unitario y salida
// manual, con POST /api/m4/v1/movimientos. La vista solo valida la forma de los campos; las reglas (comercio activo,
// variación del comercio, sede habilitada, salida sin tocar lo reservado, costo promedio) las aplica el servidor.
// Las transferencias entre ubicaciones son alcance deseable y no están en esta versión (v0.9.0).
// Mantiene los hallazgos H1, H7, H8, H9 y H10 de la validación del PC3 (sección 8.2).
import { useEffect, useMemo, useRef, useState } from 'react';
import { IconoCargando, IconoEntrada, IconoSalida } from '../components/iconos';
import { Aviso, BotonPrimario, Campo, Tarjeta, claseEntrada, formatoColones } from '../components/componentes';
import { disponible, nombreSede, nombreVariacion } from '../services/inventarioApi';
import { erroresPorCampo } from '../services/errores';

const OPERACIONES = [
  { id: 'entrada', nombre: 'Entrada', Icono: IconoEntrada },
  { id: 'salida', nombre: 'Salida', Icono: IconoSalida },
];

const OTRA = 'otra';
// H7: las cantidades son enteras (integer, minimum 1 en el contrato): solo dígitos.
const SOLO_DIGITOS = /^\d+$/;
// El costo unitario es number (minimum 0): decimales con punto, hasta dos. «9.200» se rechaza como separador de miles.
const MONTO = /^\d+(\.\d{1,2})?$/;

function errorMonto(texto) {
  if (texto === '') return 'El costo unitario es obligatorio en las entradas.';
  if (MONTO.test(texto)) return null;
  if (/^\d{1,3}(\.\d{3})+$/.test(texto)) return `Parece un separador de miles. Escriba ${texto.replaceAll('.', '')}, sin puntos; use el punto solo para decimales (ej. 9200.50).`;
  if (/^\d+\.\d{3,}$/.test(texto)) return 'Use como máximo dos decimales, por ejemplo 9200.50.';
  return 'Escriba el monto solo con números, sin espacios, comas ni «₡». Para decimales use punto: 9200.50.';
}

export default function GestionOperacionesInventario({ api, existencias, sedes, alCambiar }) {
  const variaciones = useMemo(() => [...new Map(existencias.map((e) => [e.id_variacion, e])).values()], [existencias]);
  const ubicaciones = useMemo(() => {
    const ids = new Set([...sedes.filter((s) => s.habilitada).map((s) => s.id_sede), ...existencias.map((e) => e.id_ubicacion)]);
    return [...ids].sort((a, b) => a - b);
  }, [sedes, existencias]);

  const [operacion, setOperacion] = useState('entrada');
  const [form, setForm] = useState({ id_variacion: '', otra_variacion: '', id_ubicacion: '', otra_ubicacion: '', cantidad: '', costo_unitario: '', justificacion: '' });
  const [errores, setErrores] = useState({});
  const [erroresApi, setErroresApi] = useState({});
  const [resultado, setResultado] = useState(null);
  const [errorApi, setErrorApi] = useState(null);
  const [enviando, setEnviando] = useState(false);
  const [comercio, setComercio] = useState({ cargando: true });

  // Valores iniciales cuando llegan las existencias y las sedes.
  useEffect(() => {
    setForm((f) => ({
      ...f,
      id_variacion: f.id_variacion || (variaciones[0] ? String(variaciones[0].id_variacion) : OTRA),
      id_ubicacion: f.id_ubicacion || (ubicaciones[0] ? String(ubicaciones[0]) : OTRA),
    }));
  }, [variaciones, ubicaciones]);

  // H9: el estado del comercio (ObtenerComercioActivo de M2) se consulta al abrir la pantalla.
  useEffect(() => {
    let vigente = true;
    api.consultarEstadoComercio()
      .then((c) => vigente && setComercio({ estado: c.estado, activo: c.activo, motivo: c.motivo_estado }))
      .catch((falla) => vigente && setComercio({ error: falla.error }));
    return () => { vigente = false; };
  }, [api]);
  const bloqueado = !comercio.cargando && !comercio.error && comercio.activo === false;

  const avisoRef = useRef(null);
  // H1: a 360 px el resultado quedaba fuera de la vista al enviar; se lleva el aviso a la vista.
  useEffect(() => { if (resultado || errorApi) avisoRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' }); }, [resultado, errorApi]);

  const idVariacion = Number(form.id_variacion === OTRA ? form.otra_variacion : form.id_variacion);
  const idUbicacion = Number(form.id_ubicacion === OTRA ? form.otra_ubicacion : form.id_ubicacion);
  const existencia = existencias.find((e) => e.id_variacion === idVariacion && e.id_ubicacion === idUbicacion);

  const cambiar = (campo) => (ev) => {
    setForm({ ...form, [campo]: ev.target.value });
    setErrores({ ...errores, [campo]: null });
    setErroresApi({ ...erroresApi, [campo]: null });
  };
  const err = { ...erroresApi, ...Object.fromEntries(Object.entries(errores).filter(([, v]) => v)) };
  const costoTexto = form.costo_unitario.trim();
  const costoInterpretado = operacion === 'entrada' && MONTO.test(costoTexto) ? Number(costoTexto) : null;

  function validar() {
    const e = {};
    if (!(idVariacion >= 1)) e.otra_variacion = 'Indique el número de la variación (lo da el catálogo).';
    if (!(idUbicacion >= 1)) e.otra_ubicacion = 'Indique el número de la ubicación.';
    const cantidad = form.cantidad.trim();
    if (cantidad === '' || !SOLO_DIGITOS.test(cantidad) || Number(cantidad) < 1) e.cantidad = 'Escriba una cantidad entera mayor que cero, por ejemplo 10.';
    if (operacion === 'entrada') {
      const falla = errorMonto(costoTexto);
      if (falla) e.costo_unitario = falla;
    }
    if (!form.justificacion.trim()) e.justificacion = 'Indique el motivo del movimiento.';
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
      const respuesta = await api.registrarMovimientoManual({
        id_variacion: idVariacion,
        id_ubicacion: idUbicacion,
        tipo_movimiento: operacion,
        cantidad: Number(form.cantidad.trim()),
        justificacion: form.justificacion.trim(),
        ...(operacion === 'entrada' && { costo_unitario: Number(costoTexto) }),
      });
      setResultado({ operacion, respuesta });
      setForm({ ...form, cantidad: '', costo_unitario: '', justificacion: '' });
      alCambiar();
    } catch (falla) {
      const detalle = falla.error ?? { codigo: 'ERR-006', mensaje: 'No se pudo completar la operación.' };
      setErrorApi(detalle);
      setErroresApi(erroresPorCampo(detalle)); // H8: el motivo de cada campo que devuelve el servidor
    } finally {
      setEnviando(false);
    }
  }

  return (
    <div className="space-y-4">
      <div role="tablist" aria-label="Tipo de operación" className="grid grid-cols-2 gap-1 rounded-xl bg-gray-100 p-1">
        {OPERACIONES.map(({ id, nombre, Icono }) => (
          <button key={id} role="tab" id={`tab-${id}`} aria-selected={operacion === id} aria-controls="panel-operacion" type="button"
            onClick={() => { setOperacion(id); setErrores({}); setErroresApi({}); setErrorApi(null); setResultado(null); }}
            className={`flex min-w-0 items-center justify-center gap-2 rounded-lg px-1 py-2 text-sm font-semibold transition ${operacion === id ? 'bg-white text-brandDarkBlue shadow-sm' : 'text-gray-600 hover:text-brandBlue'}`}>
            <Icono className="h-5 w-5" />
            <span>{nombre}</span>
          </button>
        ))}
      </div>

      {bloqueado && (
        <Aviso tipo="error" titulo="El comercio no puede registrar movimientos" codigo="ERR-M4-015">
          El comercio está {String(comercio.estado).toLowerCase()}{comercio.motivo ? ` (${comercio.motivo})` : ''}. No se permiten entradas ni salidas hasta que vuelva a estar activo.
        </Aviso>
      )}
      {comercio.error && (
        <Aviso tipo="aviso" titulo="No se pudo verificar el estado del comercio" codigo={comercio.error.codigo}>
          {comercio.error.mensaje} Puede intentar registrar la operación; el servidor lo verifica de nuevo.
        </Aviso>
      )}

      <div ref={avisoRef} className="scroll-mt-4 space-y-2">
        {resultado && (
          <Aviso tipo="exito" titulo="Movimiento registrado">
            Disponible: {resultado.respuesta.saldo_disponible} · Reservado: {resultado.respuesta.saldo_reservado}. Quedó en el Kardex.
          </Aviso>
        )}
        {errorApi && <Aviso tipo="error" titulo="No se registró la operación" codigo={errorApi.codigo}>{errorApi.mensaje}</Aviso>}
      </div>

      <div role="tabpanel" id="panel-operacion" aria-labelledby={`tab-${operacion}`}>
        <Tarjeta>
          <form onSubmit={enviar} noValidate className="space-y-4">
            <Campo id="variacion" etiqueta="Variación" error={err.otra_variacion ?? err.id_variacion}>
              <select id="variacion" className={claseEntrada(false)} value={form.id_variacion} onChange={cambiar('id_variacion')}>
                {variaciones.map((v) => <option key={v.id_variacion} value={v.id_variacion}>{nombreVariacion(v)}</option>)}
                <option value={OTRA}>Otra variación (primera entrada)</option>
              </select>
              {form.id_variacion === OTRA && (
                <input aria-label="Número de variación" inputMode="numeric" className={claseEntrada(err.otra_variacion)} value={form.otra_variacion} onChange={cambiar('otra_variacion')} placeholder="Número de la variación en el catálogo" />
              )}
            </Campo>

            <Campo id="ubicacion" etiqueta="Ubicación" error={err.otra_ubicacion ?? err.id_ubicacion}>
              <select id="ubicacion" className={claseEntrada(false)} value={form.id_ubicacion} onChange={cambiar('id_ubicacion')}>
                {ubicaciones.map((id) => <option key={id} value={id}>{nombreSede(sedes, id)}</option>)}
                <option value={OTRA}>Otra ubicación</option>
              </select>
              {form.id_ubicacion === OTRA && (
                <input aria-label="Número de ubicación" inputMode="numeric" className={claseEntrada(err.otra_ubicacion)} value={form.otra_ubicacion} onChange={cambiar('otra_ubicacion')} placeholder="Número de la sede" />
              )}
            </Campo>

            {existencia && (
              <dl className="grid grid-cols-3 gap-1 rounded-lg bg-brandLightBg p-2 text-center text-xs" aria-label="Existencia actual">
                {[['Física', existencia.cantidad_fisica], ['Reservada', existencia.cantidad_reservada], ['Disponible', disponible(existencia)]].map(([n, v], i) => (
                  <div key={n} className="min-w-0">
                    <dt className="truncate text-gray-500">{n}</dt>
                    <dd className={`text-base font-bold ${i === 2 ? 'text-brandDarkBlue' : 'text-gray-800'}`}>{v}</dd>
                  </div>
                ))}
              </dl>
            )}

            {operacion === 'salida' && (
              <p className="text-xs text-gray-500">Solo se pueden usar las unidades disponibles: las reservadas por pedidos no salen.</p>
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

            <Campo id="justificacion" etiqueta="Motivo" error={err.justificacion} ayuda="Queda en el Kardex junto con su usuario, la fecha y la hora.">
              <textarea id="justificacion" rows={2} maxLength={255} className={claseEntrada(err.justificacion)} value={form.justificacion} onChange={cambiar('justificacion')} aria-invalid={!!err.justificacion} aria-describedby={err.justificacion ? 'justificacion-error' : undefined}
                placeholder={operacion === 'entrada' ? 'Compra a proveedor, factura…' : 'Motivo de la salida'} />
            </Campo>

            <BotonPrimario type="submit" cargando={enviando} disabled={bloqueado}>
              {enviando && <IconoCargando className="h-4 w-4 animate-spin" />}
              {enviando ? 'Registrando…' : `Registrar ${operacion}`}
            </BotonPrimario>
          </form>
        </Tarjeta>
      </div>
    </div>
  );
}
