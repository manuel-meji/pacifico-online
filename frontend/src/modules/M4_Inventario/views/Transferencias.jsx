// Transferencias entre ubicaciones (M4): CU-M4-05 «Transferir existencias», RF-M4-005 y RN-M4-012, con
// POST /api/m4/v1/transferencias (registrarTransferencia). La vista solo valida la forma de los campos; el servidor
// verifica el comercio activo, las dos sedes, la autorización previa del Administrador y que no se trasladen
// unidades reservadas, y registra la salida y la entrada con la misma referencia en una sola transacción.
import { useEffect, useMemo, useRef, useState } from 'react';
import { IconoCargando, IconoTransferir } from '../components/iconos';
import { Aviso, BotonPrimario, Campo, Tarjeta, claseEntrada } from '../components/componentes';
import { disponible, nombreSede, nombreVariacion } from '../services/inventarioApi';
import { erroresPorCampo } from '../services/errores';

const SOLO_DIGITOS = /^\d+$/;
const VACIO = { id_variacion: '', id_ubicacion_origen: '', id_ubicacion_destino: '', cantidad: '', justificacion: '', id_autorizacion: '' };

export default function Transferencias({ api, existencias, sedes, alCambiar }) {
  const variaciones = useMemo(() => [...new Map(existencias.map((e) => [e.id_variacion, e])).values()], [existencias]);
  const [form, setForm] = useState(VACIO);
  const [errores, setErrores] = useState({});
  const [resultado, setResultado] = useState(null);
  const [errorApi, setErrorApi] = useState(null);
  const [enviando, setEnviando] = useState(false);

  const idVariacion = Number(form.id_variacion);
  const origenes = existencias.filter((e) => e.id_variacion === idVariacion);
  const idOrigen = Number(form.id_ubicacion_origen);
  const destinos = useMemo(() => {
    const ids = new Set([...sedes.filter((s) => s.habilitada).map((s) => s.id_sede), ...existencias.map((e) => e.id_ubicacion)]);
    return [...ids].filter((id) => id !== idOrigen).sort((a, b) => a - b);
  }, [sedes, existencias, idOrigen]);
  const origen = origenes.find((e) => e.id_ubicacion === idOrigen);
  const destino = existencias.find((e) => e.id_variacion === idVariacion && e.id_ubicacion === Number(form.id_ubicacion_destino));

  // Valores iniciales y coherencia entre los selectores cuando cambian los datos.
  useEffect(() => {
    setForm((f) => {
      const variacion = f.id_variacion || (variaciones[0] ? String(variaciones[0].id_variacion) : '');
      const conStock = existencias.filter((e) => e.id_variacion === Number(variacion));
      const origenValido = conStock.some((e) => String(e.id_ubicacion) === f.id_ubicacion_origen);
      return { ...f, id_variacion: variacion, id_ubicacion_origen: origenValido ? f.id_ubicacion_origen : (conStock[0] ? String(conStock[0].id_ubicacion) : '') };
    });
  }, [variaciones, existencias, form.id_variacion]);
  useEffect(() => {
    setForm((f) => (destinos.some((id) => String(id) === f.id_ubicacion_destino) ? f : { ...f, id_ubicacion_destino: destinos[0] ? String(destinos[0]) : '' }));
  }, [destinos]);

  const avisoRef = useRef(null);
  useEffect(() => { if (resultado || errorApi) avisoRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' }); }, [resultado, errorApi]);

  const cambiar = (campo) => (ev) => {
    setForm({ ...form, [campo]: ev.target.value });
    setErrores({ ...errores, [campo]: null });
  };

  function validar() {
    const e = {};
    if (!(idVariacion >= 1)) e.id_variacion = 'Elija la variación.';
    if (!(idOrigen >= 1)) e.id_ubicacion_origen = 'Elija la ubicación de origen.';
    if (!(Number(form.id_ubicacion_destino) >= 1)) e.id_ubicacion_destino = 'Elija la ubicación de destino.';
    const cantidad = form.cantidad.trim();
    if (!SOLO_DIGITOS.test(cantidad) || Number(cantidad) < 1) e.cantidad = 'Escriba una cantidad entera mayor que cero, por ejemplo 5.';
    if (!form.justificacion.trim()) e.justificacion = 'Indique el motivo de la transferencia.';
    if (!form.id_autorizacion.trim()) e.id_autorizacion = 'Indique el código de la autorización del Administrador.';
    setErrores(e);
    return Object.keys(e).length === 0;
  }

  async function enviar(ev) {
    ev.preventDefault();
    setResultado(null);
    setErrorApi(null);
    if (!validar()) return;
    setEnviando(true);
    try {
      const respuesta = await api.registrarTransferencia({
        id_variacion: idVariacion,
        id_ubicacion_origen: idOrigen,
        id_ubicacion_destino: Number(form.id_ubicacion_destino),
        cantidad: Number(form.cantidad.trim()),
        justificacion: form.justificacion.trim(),
        id_autorizacion: form.id_autorizacion.trim(),
      });
      setResultado(respuesta);
      setForm({ ...form, cantidad: '', justificacion: '', id_autorizacion: '' });
      alCambiar();
    } catch (falla) {
      const detalle = falla.error ?? { codigo: 'ERR-006', mensaje: 'No se pudo completar la transferencia.' };
      setErrorApi(detalle);
      setErrores(erroresPorCampo(detalle));
    } finally {
      setEnviando(false);
    }
  }

  if (variaciones.length === 0) return <Tarjeta><p className="text-sm text-gray-600">Todavía no hay existencias para transferir. Registre una entrada en Operaciones.</p></Tarjeta>;

  const [salida, entrada] = resultado?.movimientos ?? [];

  return (
    <div className="space-y-4">
      <div ref={avisoRef} className="scroll-mt-20 space-y-2">
        {resultado && (
          <Aviso tipo="exito" titulo="Transferencia registrada">
            Salieron {salida.cantidad} de {nombreSede(sedes, salida.id_ubicacion)} (disponible {salida.saldo_disponible}) y entraron a {nombreSede(sedes, entrada.id_ubicacion)} (disponible {entrada.saldo_disponible}). Las dos líneas del Kardex comparten la referencia {resultado.id_transferencia.slice(0, 8)}.
          </Aviso>
        )}
        {errorApi && <Aviso tipo="error" titulo="No se registró la transferencia" codigo={errorApi.codigo}>{errorApi.mensaje}</Aviso>}
      </div>

      <div className="grid gap-4 lg:grid-cols-5">
        <Tarjeta className="lg:col-span-3">
          <form onSubmit={enviar} noValidate className="space-y-4">
            <Campo id="tr-variacion" etiqueta="Variación" error={errores.id_variacion}>
              <select id="tr-variacion" className={claseEntrada(errores.id_variacion)} value={form.id_variacion} onChange={cambiar('id_variacion')}>
                {variaciones.map((v) => <option key={v.id_variacion} value={v.id_variacion}>{nombreVariacion(v)}</option>)}
              </select>
            </Campo>

            <div className="grid gap-4 sm:grid-cols-[1fr_auto_1fr] sm:items-start">
              <Campo id="tr-origen" etiqueta="Desde" error={errores.id_ubicacion_origen}>
                <select id="tr-origen" className={claseEntrada(errores.id_ubicacion_origen)} value={form.id_ubicacion_origen} onChange={cambiar('id_ubicacion_origen')}>
                  {origenes.map((e) => <option key={e.id_ubicacion} value={e.id_ubicacion}>{nombreSede(sedes, e.id_ubicacion)}</option>)}
                </select>
              </Campo>
              <IconoTransferir className="mx-auto hidden h-5 w-5 text-gray-400 sm:mt-9 sm:block" />
              <Campo id="tr-destino" etiqueta="Hacia" error={errores.id_ubicacion_destino}>
                <select id="tr-destino" className={claseEntrada(errores.id_ubicacion_destino)} value={form.id_ubicacion_destino} onChange={cambiar('id_ubicacion_destino')}>
                  {destinos.length === 0 && <option value="">No hay otra sede habilitada</option>}
                  {destinos.map((id) => <option key={id} value={id}>{nombreSede(sedes, id)}</option>)}
                </select>
              </Campo>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
              <Campo id="tr-cantidad" etiqueta="Cantidad" error={errores.cantidad}>
                <input id="tr-cantidad" inputMode="numeric" className={claseEntrada(errores.cantidad)} value={form.cantidad} onChange={cambiar('cantidad')} placeholder="Ej. 5" aria-invalid={!!errores.cantidad} />
              </Campo>
              <Campo id="tr-autorizacion" etiqueta="Autorización del Administrador" error={errores.id_autorizacion} ayuda="Código que registró el Administrador del comercio.">
                <input id="tr-autorizacion" className={claseEntrada(errores.id_autorizacion)} value={form.id_autorizacion} onChange={cambiar('id_autorizacion')} placeholder="autorizacion-transferencia-…" autoComplete="off" aria-invalid={!!errores.id_autorizacion} />
              </Campo>
            </div>

            <Campo id="tr-justificacion" etiqueta="Motivo" error={errores.justificacion} ayuda="Queda en el Kardex con su usuario, el autorizador, la fecha y la hora.">
              <textarea id="tr-justificacion" rows={2} maxLength={255} className={claseEntrada(errores.justificacion)} value={form.justificacion} onChange={cambiar('justificacion')} placeholder="Reposición de la tienda…" aria-invalid={!!errores.justificacion} />
            </Campo>

            <BotonPrimario type="submit" cargando={enviando} disabled={destinos.length === 0}>
              {enviando ? <IconoCargando className="h-4 w-4 animate-spin" /> : <IconoTransferir className="h-4 w-4" />}
              {enviando ? 'Transfiriendo…' : 'Registrar transferencia'}
            </BotonPrimario>
          </form>
        </Tarjeta>

        <div className="space-y-3 lg:col-span-2">
          {[['Origen', origen, form.id_ubicacion_origen], ['Destino', destino, form.id_ubicacion_destino]].map(([titulo, e, id]) => (
            <Tarjeta key={titulo}>
              <p className="text-xs font-semibold uppercase tracking-wide text-gray-500">{titulo}</p>
              <p className="mt-0.5 font-semibold text-gray-900">{id ? nombreSede(sedes, Number(id)) : '—'}</p>
              <dl className="mt-2 grid grid-cols-3 gap-1 rounded-lg bg-brandLightBg p-2 text-center text-xs">
                {[['Física', e?.cantidad_fisica ?? 0], ['Reservada', e?.cantidad_reservada ?? 0], ['Disponible', e ? disponible(e) : 0]].map(([n, v], i) => (
                  <div key={n} className="min-w-0">
                    <dt className="truncate text-gray-500">{n}</dt>
                    <dd className={`text-base font-bold ${i === 2 ? 'text-brandDarkBlue' : 'text-gray-800'}`}>{v}</dd>
                  </div>
                ))}
              </dl>
              {titulo === 'Destino' && !e && id && <p className="mt-2 text-xs text-gray-500">Todavía no tiene esta variación: se crea con la transferencia.</p>}
            </Tarjeta>
          ))}
          <p className="text-xs text-gray-500">Solo se trasladan unidades disponibles: las reservadas por pedidos se quedan en el origen.</p>
        </div>
      </div>
    </div>
  );
}
