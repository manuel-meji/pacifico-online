import { useEffect, useMemo, useRef, useState } from 'react';
import { LoaderCircle, Paperclip, RotateCcw, X } from 'lucide-react';
import { Alerta } from './componentes';
import { colones } from '../services/calculos';
import { nombreVariacion } from '../services/nombres';

// Debe coincidir con config/devoluciones.php (el backend vuelve a validar).
const MAX_ARCHIVOS = 5;
const MAX_BYTES = 5 * 1024 * 1024;
const TIPOS = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];

const ESTADOS_ACTIVOS = ['solicitada', 'aprobada'];

/** Unidades de cada línea que aún se pueden devolver (descuenta devoluciones en curso o aprobadas). */
export function unidadesDisponibles(subpedido, devoluciones = []) {
  const comprometidas = {};
  devoluciones
    .filter((d) => ESTADOS_ACTIVOS.includes(d.estado))
    .forEach((d) => (d.lineas ?? []).forEach((l) => {
      comprometidas[l.id_linea] = (comprometidas[l.id_linea] ?? 0) + Number(l.cantidad);
    }));

  return (subpedido?.lineas ?? []).map((l) => ({
    linea: l,
    disponible: Math.max(0, Number(l.cantidad) - (comprometidas[l.id_linea] ?? 0)),
  }));
}

/**
 * RF-019: el cliente elige qué líneas y cuántas unidades devolver (total o parcial), indica el motivo
 * y adjunta evidencia. `onConfirmar({ motivo, lineas, archivos })`.
 */
export function ModalSolicitudDevolucion({ subpedido, devoluciones = [], ocupado, error, onConfirmar, onCerrar }) {
  const [motivo, setMotivo] = useState('');
  const [seleccion, setSeleccion] = useState({}); // { id_linea: cantidad a devolver }
  const [archivos, setArchivos] = useState([]);
  const [errorArchivos, setErrorArchivos] = useState(null);
  const [intentado, setIntentado] = useState(false);
  const campoRef = useRef(null);

  const unidades = useMemo(() => unidadesDisponibles(subpedido, devoluciones), [subpedido, devoluciones]);

  useEffect(() => {
    if (!subpedido) return;
    setMotivo('');
    setSeleccion({});
    setArchivos([]);
    setErrorArchivos(null);
    setIntentado(false);
    campoRef.current?.focus();
  }, [subpedido]);

  useEffect(() => {
    if (!subpedido) return undefined;
    const alTeclear = (e) => { if (e.key === 'Escape' && !ocupado) onCerrar(); };
    document.addEventListener('keydown', alTeclear);
    return () => document.removeEventListener('keydown', alTeclear);
  }, [subpedido, ocupado, onCerrar]);

  if (!subpedido) return null;

  const lineasElegidas = Object.entries(seleccion)
    .filter(([, cantidad]) => cantidad > 0)
    .map(([id_linea, cantidad]) => ({ id_linea: Number(id_linea), cantidad }));
  const motivoVacio = motivo.trim().length < 5;
  const sinLineas = lineasElegidas.length === 0;
  const sinEvidencia = archivos.length === 0;

  const totalEstimado = unidades.reduce((suma, { linea }) => {
    const cantidad = seleccion[linea.id_linea] ?? 0;
    const unitario = Number(linea.precio_unitario ?? 0) * (1 + Number(linea.tarifa_impuesto ?? 0) / 100);
    return suma + cantidad * unitario;
  }, 0);

  const cambiarCantidad = (idLinea, cantidad, maximo) =>
    setSeleccion((s) => ({ ...s, [idLinea]: Math.min(maximo, Math.max(0, Number(cantidad) || 0)) }));

  const devolverTodo = () => {
    const todo = {};
    unidades.forEach(({ linea, disponible }) => { if (disponible > 0) todo[linea.id_linea] = disponible; });
    setSeleccion(todo);
  };

  function agregarArchivos(evento) {
    const nuevos = Array.from(evento.target.files ?? []);
    evento.target.value = '';
    const validos = [];
    let problema = null;

    nuevos.forEach((archivo) => {
      if (!TIPOS.includes(archivo.type)) problema = `"${archivo.name}" no es una imagen (JPG, PNG, WEBP) ni un PDF.`;
      else if (archivo.size > MAX_BYTES) problema = `"${archivo.name}" supera los ${MAX_BYTES / 1024 / 1024} MB.`;
      else validos.push(archivo);
    });

    const combinados = [...archivos, ...validos];
    if (combinados.length > MAX_ARCHIVOS) problema = `Puede adjuntar hasta ${MAX_ARCHIVOS} archivos.`;

    setErrorArchivos(problema);
    setArchivos(combinados.slice(0, MAX_ARCHIVOS));
  }

  function enviar(e) {
    e.preventDefault();
    setIntentado(true);
    if (ocupado || motivoVacio || sinLineas || sinEvidencia) return;
    onConfirmar({ motivo: motivo.trim(), lineas: lineasElegidas, archivos });
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-xs">
      <form onSubmit={enviar} noValidate role="dialog" aria-modal="true" aria-labelledby="solicitud-dev-titulo"
        className="w-full max-w-lg max-h-[90vh] overflow-y-auto rounded-2xl bg-white p-5 sm:p-6 shadow-xl space-y-4">
        <div>
          <h2 id="solicitud-dev-titulo" className="text-base font-bold text-gray-900">
            Solicitar devolución · Subpedido #{subpedido.id_subpedido}
          </h2>
          <p className="mt-1 text-sm text-gray-600">
            Elija los artículos a devolver, cuéntenos qué ocurrió y adjunte evidencia. El comercio revisará la solicitud
            y, si la aprueba, se le reembolsará el monto correspondiente.
          </p>
        </div>

        <fieldset className="space-y-2">
          <div className="flex items-center justify-between">
            <legend className="text-xs font-medium text-gray-600">Artículos a devolver</legend>
            <button type="button" onClick={devolverTodo} disabled={ocupado}
              className="text-xs font-semibold text-brandDarkBlue hover:text-brandBlue cursor-pointer disabled:opacity-50">
              Devolver todo
            </button>
          </div>
          <ul className="divide-y divide-gray-100 rounded-lg border border-gray-200">
            {unidades.map(({ linea, disponible }) => (
              <li key={linea.id_linea} className="px-3 py-2.5 flex items-center gap-3 text-sm">
                <div className="min-w-0 flex-1">
                  <p className="font-semibold text-gray-900 truncate">{nombreVariacion(linea)}</p>
                  <p className="text-xs text-gray-500">
                    {colones(Number(linea.precio_unitario ?? 0))} c/u · {disponible} de {linea.cantidad} disponibles
                  </p>
                </div>
                <label className="text-xs text-gray-600 flex items-center gap-1.5">
                  <span className="sr-only">Cantidad a devolver de {nombreVariacion(linea)}</span>
                  <input type="number" min={0} max={disponible} inputMode="numeric"
                    value={seleccion[linea.id_linea] ?? 0}
                    disabled={ocupado || disponible === 0}
                    onChange={(e) => cambiarCantidad(linea.id_linea, e.target.value, disponible)}
                    className="w-16 rounded-lg border border-gray-300 px-2 py-1.5 text-sm text-center focus:outline-hidden focus:border-brandBlue disabled:bg-gray-100" />
                </label>
              </li>
            ))}
          </ul>
          {intentado && sinLineas && <p role="alert" className="text-xs text-red-600">Seleccione al menos un artículo.</p>}
          {!sinLineas && (
            <p className="text-xs text-gray-600">
              Monto estimado a reembolsar: <span className="font-semibold">{colones(totalEstimado)}</span>
              {' '}(el monto final lo confirma el sistema).
            </p>
          )}
        </fieldset>

        <div>
          <label htmlFor="motivo-solicitud-dev" className="text-xs font-medium text-gray-600 block mb-1">Motivo detallado (obligatorio)</label>
          <textarea ref={campoRef} id="motivo-solicitud-dev" rows={3} value={motivo} disabled={ocupado}
            onChange={(e) => setMotivo(e.target.value)}
            placeholder="Ejemplo: El producto llegó con daño físico o no corresponde con lo solicitado."
            aria-invalid={intentado && motivoVacio}
            className={`w-full rounded-lg border px-3 py-2 text-sm focus:outline-hidden ${
              intentado && motivoVacio ? 'border-red-500' : 'border-gray-300 focus:border-brandBlue'
            }`} />
          {intentado && motivoVacio && <p role="alert" className="mt-1 text-xs text-red-600">Indique el motivo (mínimo 5 caracteres).</p>}
        </div>

        <div>
          <span className="text-xs font-medium text-gray-600 block mb-1">Evidencia (obligatoria)</span>
          <label className="inline-flex items-center gap-2 min-h-11 px-4 rounded-lg border border-dashed border-gray-300 text-sm text-gray-700 hover:bg-gray-50 cursor-pointer">
            <Paperclip className="w-4 h-4" aria-hidden="true" />
            Adjuntar fotos o PDF
            <input type="file" multiple accept="image/jpeg,image/png,image/webp,application/pdf"
              onChange={agregarArchivos} disabled={ocupado} className="sr-only" data-testid="input-evidencia" />
          </label>
          <p className="mt-1 text-xs text-gray-500">Hasta {MAX_ARCHIVOS} archivos de {MAX_BYTES / 1024 / 1024} MB (JPG, PNG, WEBP o PDF).</p>
          {archivos.length > 0 && (
            <ul className="mt-2 space-y-1">
              {archivos.map((archivo, i) => (
                <li key={`${archivo.name}-${i}`} className="flex items-center justify-between gap-2 rounded-lg bg-gray-50 px-3 py-1.5 text-xs">
                  <span className="truncate">{archivo.name}</span>
                  <button type="button" onClick={() => setArchivos((a) => a.filter((_, j) => j !== i))} disabled={ocupado}
                    aria-label={`Quitar ${archivo.name}`} className="text-gray-500 hover:text-red-600 cursor-pointer">
                    <X className="w-4 h-4" aria-hidden="true" />
                  </button>
                </li>
              ))}
            </ul>
          )}
          {errorArchivos && <p role="alert" className="mt-1 text-xs text-red-600">{errorArchivos}</p>}
          {intentado && sinEvidencia && !errorArchivos && <p role="alert" className="mt-1 text-xs text-red-600">Adjunte al menos un archivo de evidencia.</p>}
        </div>

        {error && <Alerta tipo="error" titulo="No se pudo registrar la solicitud">{error}</Alerta>}

        <div className="flex justify-end gap-2 pt-2">
          <button type="button" onClick={onCerrar} disabled={ocupado}
            className="min-h-11 px-4 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 text-sm font-semibold transition cursor-pointer disabled:opacity-50">
            Volver
          </button>
          <button type="submit" disabled={ocupado}
            className="min-h-11 px-5 rounded-lg bg-amber-600 hover:bg-amber-700 text-white text-sm font-bold transition inline-flex items-center justify-center gap-2 cursor-pointer disabled:opacity-70">
            {ocupado ? <LoaderCircle className="w-4 h-4 animate-spin" aria-hidden="true" /> : <RotateCcw className="w-4 h-4" aria-hidden="true" />}
            {ocupado ? 'Enviando…' : 'Enviar solicitud'}
          </button>
        </div>
      </form>
    </div>
  );
}

export default ModalSolicitudDevolucion;
