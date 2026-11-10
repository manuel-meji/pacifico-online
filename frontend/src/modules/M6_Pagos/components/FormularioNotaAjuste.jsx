import { useState } from 'react';
import { FilePlus2, FileMinus2, Loader2 } from 'lucide-react';
import { emitirNota } from '../api/pagosApi';
import { colones, validarNota } from '../lib/documentos';

const TIPOS = {
  credito: { Icono: FileMinus2, nombre: 'Nota de crédito', ayuda: 'Disminuye el monto facturado (devolución, descuento). El total acumulado no puede superar la factura.' },
  debito: { Icono: FilePlus2, nombre: 'Nota de débito', ayuda: 'Aumenta el monto facturado (cargo adicional). Solo sobre facturas aceptadas por Hacienda.' },
};

/** Traduce el error del contrato a un texto para el usuario. */
function mensajeDeError(e) {
  if (e.codigo === 'ERR-M6-017' && e.detalles?.disponible !== undefined) {
    return `${e.mensaje} Saldo disponible para notas de crédito: ${colones(e.detalles.disponible)}.`;
  }
  if (e.codigo === 'ERR-005' && e.detalles) {
    const porCampo = Object.values(e.detalles).flat().filter((m) => typeof m === 'string' && m !== 'ERR-005');
    if (porCampo.length) return porCampo.join(' ');
  }
  return e.mensaje || e.message || 'No se pudo emitir la nota.';
}

/**
 * CU-005 / CU-006. Emite una nota de crédito o de débito sobre una factura.
 * props.factura = schema Factura; props.onEmitida(nota) recibe el schema NotaCreditoDebito.
 */
export default function FormularioNotaAjuste({ factura, onEmitida, api = { emitirNota } }) {
  const debitoPermitido = factura.estado_hacienda === 'aceptada';
  const [tipo, setTipo] = useState('credito');
  const [monto, setMonto] = useState('');
  const [motivo, setMotivo] = useState('');
  const [errores, setErrores] = useState({});
  const [errorServidor, setErrorServidor] = useState(null);
  const [enviando, setEnviando] = useState(false);
  const idBase = `nota-${factura.id}`;

  async function enviar(evento) {
    evento.preventDefault();
    const r = validarNota({ monto, motivo });
    setErrores(r.errores);
    setErrorServidor(null);
    if (!r.valido) return;

    setEnviando(true);
    try {
      const nota = await api.emitirNota(factura.id, tipo, { monto: Number(monto), motivo: motivo.trim() });
      setMonto('');
      setMotivo('');
      onEmitida?.(nota);
    } catch (e) {
      setErrorServidor(mensajeDeError(e));
    } finally {
      setEnviando(false);
    }
  }

  return (
    <form onSubmit={enviar} noValidate className="space-y-3 rounded-lg border border-gray-200 bg-brandLightBg p-4">
      <fieldset>
        <legend className="mb-2 text-sm font-semibold text-brandDarkBlue">Emitir nota sobre esta factura</legend>
        <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
          {Object.entries(TIPOS).map(([clave, { Icono, nombre }]) => {
            const deshabilitado = clave === 'debito' && !debitoPermitido;
            return (
              <label key={clave}
                className={`flex items-center gap-2 rounded-lg border-2 p-3 text-sm font-semibold focus-within:ring-2 focus-within:ring-brandBlue ${
                  deshabilitado ? 'cursor-not-allowed border-gray-200 bg-gray-100 text-gray-400'
                    : tipo === clave ? 'cursor-pointer border-brandBlue bg-blue-50' : 'cursor-pointer border-gray-200 bg-white'}`}>
                <input type="radio" name={`${idBase}-tipo`} value={clave} checked={tipo === clave} disabled={deshabilitado}
                  onChange={() => { setTipo(clave); setErrorServidor(null); }} className="sr-only" />
                <Icono className="h-5 w-5" aria-hidden /> {nombre}
              </label>
            );
          })}
        </div>
        <p className="mt-2 text-xs text-gray-600">{TIPOS[tipo].ayuda}</p>
        {!debitoPermitido && (
          <p className="mt-1 text-xs text-amber-700">
            La nota de débito se habilita cuando Hacienda acepte la factura (estado actual: {factura.estado_hacienda}).
          </p>
        )}
      </fieldset>

      <div>
        <label htmlFor={`${idBase}-monto`} className="mb-1 block text-sm font-medium">Monto (₡)</label>
        <input id={`${idBase}-monto`} type="number" inputMode="decimal" min="0.01" step="0.01" value={monto}
          onChange={(e) => setMonto(e.target.value)} aria-invalid={Boolean(errores.monto)}
          className="w-full rounded-lg border border-gray-300 bg-white p-2 text-sm focus:border-brandBlue focus:outline-none focus:ring-2 focus:ring-brandBlue/30" />
        {errores.monto && <p role="alert" className="mt-1 text-xs text-red-600">{errores.monto}</p>}
      </div>

      <div>
        <label htmlFor={`${idBase}-motivo`} className="mb-1 block text-sm font-medium">Motivo</label>
        <textarea id={`${idBase}-motivo`} rows={2} maxLength={200} value={motivo}
          onChange={(e) => setMotivo(e.target.value)} aria-invalid={Boolean(errores.motivo)}
          className="w-full rounded-lg border border-gray-300 bg-white p-2 text-sm focus:border-brandBlue focus:outline-none focus:ring-2 focus:ring-brandBlue/30" />
        {errores.motivo && <p role="alert" className="mt-1 text-xs text-red-600">{errores.motivo}</p>}
      </div>

      {errorServidor && <p role="alert" className="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">{errorServidor}</p>}

      <button type="submit" disabled={enviando}
        className="flex w-full items-center justify-center gap-2 rounded-lg bg-brandDarkBlue px-4 py-2.5 text-sm font-bold text-white transition duration-200 hover:bg-brandBlue disabled:cursor-not-allowed disabled:bg-gray-300 disabled:text-gray-500">
        {enviando && <Loader2 className="h-4 w-4 animate-spin" aria-hidden />}
        {enviando ? 'Emitiendo…' : `Emitir ${TIPOS[tipo].nombre.toLowerCase()}`}
      </button>
    </form>
  );
}
