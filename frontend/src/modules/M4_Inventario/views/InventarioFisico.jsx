// Inventario físico (M4): CU-M4-02 «Registrar conteo físico y ajuste» y RN-M4-011, con POST /api/m4/v1/ajustes
// (registrarAjusteInventario). El encargado cuenta una variación en una ubicación: sin diferencia queda el conteo sin
// movimiento; con diferencia el servidor exige el motivo y la autorización previa del Administrador, compara la
// cantidad que vio el encargado con la física actual (ERR-M4-016) y no deja el físico bajo lo reservado (ERR-M4-017).
// La diferencia que muestra la pantalla es solo informativa: la calcula y la valida el servidor.
import { useEffect, useMemo, useRef, useState } from 'react';
import { IconoCargando, IconoPortapapelesCheck } from '../components/iconos';
import { Aviso, BotonPrimario, Campo, Tarjeta, claseEntrada } from '../components/componentes';
import { nombreSede, nombreVariacion } from '../services/inventarioApi';
import { erroresPorCampo } from '../services/errores';

const SOLO_DIGITOS = /^\d+$/;
const MOTIVOS = [
  ['merma', 'Merma'], ['robo', 'Robo'], ['danio', 'Daño'], ['sobrante', 'Sobrante'], ['error_conteo', 'Error de conteo'], ['otro', 'Otro'],
];

export default function InventarioFisico({ api, existencias, sedes, alCambiar }) {
  const variaciones = useMemo(() => [...new Map(existencias.map((e) => [e.id_variacion, e])).values()], [existencias]);
  const [form, setForm] = useState({ id_variacion: '', id_ubicacion: '', cantidad_contada: '', motivo_ajuste: 'merma', justificacion: '', id_autorizacion: '' });
  const [errores, setErrores] = useState({});
  const [resultado, setResultado] = useState(null);
  const [errorApi, setErrorApi] = useState(null);
  const [enviando, setEnviando] = useState(false);

  const idVariacion = Number(form.id_variacion);
  const ubicaciones = existencias.filter((e) => e.id_variacion === idVariacion);
  const existencia = ubicaciones.find((e) => e.id_ubicacion === Number(form.id_ubicacion));

  useEffect(() => {
    setForm((f) => {
      const variacion = f.id_variacion || (variaciones[0] ? String(variaciones[0].id_variacion) : '');
      const filas = existencias.filter((e) => e.id_variacion === Number(variacion));
      const valida = filas.some((e) => String(e.id_ubicacion) === f.id_ubicacion);
      return { ...f, id_variacion: variacion, id_ubicacion: valida ? f.id_ubicacion : (filas[0] ? String(filas[0].id_ubicacion) : '') };
    });
  }, [variaciones, existencias, form.id_variacion]);

  const avisoRef = useRef(null);
  useEffect(() => { if (resultado || errorApi) avisoRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' }); }, [resultado, errorApi]);

  const contadaTexto = form.cantidad_contada.trim();
  const diferencia = existencia && SOLO_DIGITOS.test(contadaTexto) ? Number(contadaTexto) - existencia.cantidad_fisica : null;
  const conDiferencia = diferencia !== null && diferencia !== 0;

  const cambiar = (campo) => (ev) => {
    setForm({ ...form, [campo]: ev.target.value });
    setErrores({ ...errores, [campo]: null });
  };

  function validar() {
    const e = {};
    if (!existencia) e.id_ubicacion = 'Elija una ubicación con existencia registrada.';
    if (!SOLO_DIGITOS.test(contadaTexto)) e.cantidad_contada = 'Escriba la cantidad contada, un entero de 0 en adelante.';
    if (!form.justificacion.trim()) e.justificacion = 'Describa el conteo (por ejemplo, conteo mensual de bodega).';
    if (conDiferencia && !form.id_autorizacion.trim()) e.id_autorizacion = 'Con diferencia se requiere la autorización del Administrador.';
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
      const respuesta = await api.registrarAjuste({
        id_variacion: idVariacion,
        id_ubicacion: existencia.id_ubicacion,
        cantidad_sistema_vista: existencia.cantidad_fisica,
        cantidad_contada: Number(contadaTexto),
        motivo_ajuste: form.motivo_ajuste,
        justificacion: form.justificacion.trim(),
        ...(form.id_autorizacion.trim() && { id_autorizacion: form.id_autorizacion.trim() }),
      });
      setResultado(respuesta);
      setForm({ ...form, cantidad_contada: '', justificacion: '', id_autorizacion: '' });
      alCambiar();
    } catch (falla) {
      const detalle = falla.error ?? { codigo: 'ERR-006', mensaje: 'No se pudo registrar el conteo.' };
      setErrorApi(detalle);
      setErrores(erroresPorCampo(detalle));
      if (detalle.codigo === 'ERR-M4-016') alCambiar(); // el físico cambió: se recarga para volver a contar
    } finally {
      setEnviando(false);
    }
  }

  if (variaciones.length === 0) return <Tarjeta><p className="text-sm text-gray-600">Todavía no hay existencias para contar.</p></Tarjeta>;

  return (
    <div className="space-y-4">
      <div ref={avisoRef} className="scroll-mt-20 space-y-2">
        {resultado && (
          <Aviso tipo="exito" titulo={resultado.diferencia === 0 ? 'Conteo registrado sin diferencia' : 'Ajuste registrado'}>
            Sistema {resultado.cantidad_sistema} · contado {resultado.cantidad_contada} · diferencia {resultado.diferencia > 0 ? '+' : ''}{resultado.diferencia}.
            {resultado.id_movimiento ? ` Quedó en el Kardex (autorizó el usuario ${resultado.id_autorizador}).` : ' No genera movimiento.'}
          </Aviso>
        )}
        {errorApi && <Aviso tipo="error" titulo="No se registró el conteo" codigo={errorApi.codigo}>{errorApi.mensaje}</Aviso>}
      </div>

      <div className="grid gap-4 lg:grid-cols-5">
        <Tarjeta className="lg:col-span-3">
          <form onSubmit={enviar} noValidate className="space-y-4">
            <div className="grid gap-4 sm:grid-cols-2">
              <Campo id="if-variacion" etiqueta="Variación" error={errores.id_variacion}>
                <select id="if-variacion" className={claseEntrada(errores.id_variacion)} value={form.id_variacion} onChange={cambiar('id_variacion')}>
                  {variaciones.map((v) => <option key={v.id_variacion} value={v.id_variacion}>{nombreVariacion(v)}</option>)}
                </select>
              </Campo>
              <Campo id="if-ubicacion" etiqueta="Ubicación" error={errores.id_ubicacion}>
                <select id="if-ubicacion" className={claseEntrada(errores.id_ubicacion)} value={form.id_ubicacion} onChange={cambiar('id_ubicacion')}>
                  {ubicaciones.map((e) => <option key={e.id_ubicacion} value={e.id_ubicacion}>{nombreSede(sedes, e.id_ubicacion)}</option>)}
                </select>
              </Campo>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
              <Campo id="if-contada" etiqueta="Cantidad contada" error={errores.cantidad_contada}>
                <input id="if-contada" inputMode="numeric" className={claseEntrada(errores.cantidad_contada)} value={form.cantidad_contada} onChange={cambiar('cantidad_contada')} placeholder="Unidades físicas" aria-invalid={!!errores.cantidad_contada} />
              </Campo>
              <Campo id="if-motivo" etiqueta="Motivo" error={errores.motivo_ajuste}>
                <select id="if-motivo" className={claseEntrada(errores.motivo_ajuste)} value={form.motivo_ajuste} onChange={cambiar('motivo_ajuste')}>
                  {MOTIVOS.map(([valor, nombre]) => <option key={valor} value={valor}>{nombre}</option>)}
                </select>
              </Campo>
            </div>

            {diferencia !== null && (
              <p aria-live="polite" className={`rounded-lg px-3 py-2 text-sm font-semibold ${conDiferencia ? 'bg-amber-50 text-amber-900' : 'bg-green-50 text-green-800'}`}>
                {conDiferencia ? `Diferencia de ${diferencia > 0 ? '+' : ''}${diferencia} unidades: requiere la autorización del Administrador.` : 'Sin diferencia: se registra el conteo sin movimiento.'}
              </p>
            )}

            {(conDiferencia || errores.id_autorizacion) && (
              <Campo id="if-autorizacion" etiqueta="Autorización del Administrador" error={errores.id_autorizacion} ayuda="Código que registró el Administrador del comercio para esta diferencia.">
                <input id="if-autorizacion" className={claseEntrada(errores.id_autorizacion)} value={form.id_autorizacion} onChange={cambiar('id_autorizacion')} placeholder="autorizacion-ajuste-…" autoComplete="off" aria-invalid={!!errores.id_autorizacion} />
              </Campo>
            )}

            <Campo id="if-justificacion" etiqueta="Justificación" error={errores.justificacion} ayuda="Queda registrada con su usuario, la fecha y la hora.">
              <textarea id="if-justificacion" rows={2} maxLength={255} className={claseEntrada(errores.justificacion)} value={form.justificacion} onChange={cambiar('justificacion')} placeholder="Conteo mensual de bodega…" aria-invalid={!!errores.justificacion} />
            </Campo>

            <BotonPrimario type="submit" cargando={enviando}>
              {enviando ? <IconoCargando className="h-4 w-4 animate-spin" /> : <IconoPortapapelesCheck className="h-4 w-4" />}
              {enviando ? 'Registrando…' : conDiferencia ? 'Registrar ajuste' : 'Registrar conteo'}
            </BotonPrimario>
          </form>
        </Tarjeta>

        <Tarjeta className="h-fit lg:col-span-2">
          <p className="text-xs font-semibold uppercase tracking-wide text-gray-500">Según el sistema</p>
          <p className="mt-0.5 font-semibold text-gray-900">{existencia ? nombreSede(sedes, existencia.id_ubicacion) : '—'}</p>
          <dl className="mt-2 grid grid-cols-2 gap-2 text-center text-xs">
            <div className="rounded-lg bg-brandLightBg p-2"><dt className="text-gray-500">Física</dt><dd className="text-2xl font-extrabold text-brandDarkBlue">{existencia?.cantidad_fisica ?? '—'}</dd></div>
            <div className="rounded-lg bg-brandLightBg p-2"><dt className="text-gray-500">Reservada</dt><dd className="text-2xl font-extrabold text-gray-800">{existencia?.cantidad_reservada ?? '—'}</dd></div>
          </dl>
          <p className="mt-2 text-xs text-gray-500">El conteo no puede quedar por debajo de las unidades reservadas por pedidos.</p>
        </Tarjeta>
      </div>
    </div>
  );
}
