import { useMemo, useState } from 'react';
import { CreditCard, Smartphone, Globe, ChevronDown } from 'lucide-react';
import { MEDIOS_PAGO, TOKENS_SIMULADOR, validarSolicitudPago } from '../lib/validacionPago';
import { procesarPago, crearClaveIdempotencia } from '../api/pagosApi';

const ICONOS = { tarjeta: CreditCard, sinpe: Smartphone, paypal: Globe };
const NOMBRES = { tarjeta: 'Tarjeta', sinpe: 'SINPE Móvil', paypal: 'PayPal' };
const colones = (n) => `₡${Number(n).toLocaleString('es-CR', { minimumFractionDigits: 2 })}`;

/**
 * CU-001. No captura número de tarjeta ni CVV: el simulador de pasarela entrega un token.
 * props.pedido = { id_pedido, estado, monto_total, subpedidos: [{ id_subpedido, comercio, subtotal }] }
 */
export default function FormularioPago({ pedido, onResultado, api = { procesarPago } }) {
  const [medio, setMedio] = useState('tarjeta');
  const [token, setToken] = useState(TOKENS_SIMULADOR.tarjeta[0].token);
  const [errores, setErrores] = useState({});
  const [enviando, setEnviando] = useState(false);
  const [errorServidor, setErrorServidor] = useState(null);
  // Una sola clave por pedido: reintentar el envío NO genera un cobro nuevo (RN-M6-02).
  const clave = useMemo(() => crearClaveIdempotencia(), [pedido?.id_pedido]);

  const elegirMedio = (m) => { setMedio(m); setToken(TOKENS_SIMULADOR[m][0].token); setErrores({}); };

  async function enviar() {
    const r = validarSolicitudPago({ pedido, montoIngresado: pedido.monto_total, medioPago: medio, token });
    setErrores(r.errores);
    if (!r.valido) return;
    setEnviando(true); setErrorServidor(null);
    try {
      const pago = await api.procesarPago(
        { id_pedido: pedido.id_pedido, medio_pago: medio, monto_total: pedido.monto_total, token_medio_pago: token },
        clave,
      );
      onResultado?.(pago);
    } catch (e) {
      setErrorServidor(e.mensaje || e.message);
    } finally { setEnviando(false); }
  }

  return (
    <section className="mx-auto w-full max-w-xl space-y-5 rounded-xl border border-gray-200 bg-white p-5">
      <header>
        <h2 className="text-lg font-bold text-brandDarkBlue">Pagar pedido #{pedido.id_pedido}</h2>
        <p className="text-2xl font-extrabold">{colones(pedido.monto_total)}</p>
      </header>

      <details className="rounded-lg bg-brandLightBg p-3 text-sm">
        <summary className="flex cursor-pointer items-center justify-between font-medium">
          Desglose por comercio <ChevronDown className="h-4 w-4" aria-hidden />
        </summary>
        <ul className="mt-2 space-y-1">
          {pedido.subpedidos?.map((s) => (
            <li key={s.id_subpedido} className="flex justify-between">
              <span>{s.comercio}</span><span>{colones(s.subtotal)}</span>
            </li>
          ))}
        </ul>
      </details>

      <fieldset>
        <legend className="mb-2 text-sm font-medium">Medio de pago</legend>
        <div className="grid grid-cols-1 gap-2 sm:grid-cols-3">
          {MEDIOS_PAGO.map((m) => {
            const Icono = ICONOS[m];
            return (
              <label key={m} className={`flex cursor-pointer items-center gap-2 rounded-lg border-2 p-3 text-sm font-semibold focus-within:ring-2 focus-within:ring-brandBlue ${medio === m ? 'border-brandBlue bg-blue-50' : 'border-gray-200'}`}>
                <input type="radio" name="medio" value={m} checked={medio === m} onChange={() => elegirMedio(m)} className="sr-only" />
                <Icono className="h-5 w-5 text-brandDarkBlue" aria-hidden /> {NOMBRES[m]}
              </label>
            );
          })}
        </div>
        {errores.medioPago && <p role="alert" className="mt-1 text-xs text-red-600">{errores.medioPago}</p>}
      </fieldset>

      <div>
        <label htmlFor="token" className="mb-1 block text-sm font-medium">Escenario del simulador</label>
        <select id="token" value={token} onChange={(e) => setToken(e.target.value)} className="w-full rounded-lg border border-gray-300 p-2 text-sm">
          {TOKENS_SIMULADOR[medio].map((t) => <option key={t.token} value={t.token}>{t.etiqueta}</option>)}
        </select>
        <p className="mt-1 text-xs text-gray-500">Por seguridad, la plataforma no solicita ni guarda número de tarjeta ni CVV.</p>
      </div>

      {Object.values(errores).map((e) => <p key={e} role="alert" className="text-sm text-red-600">{e}</p>)}
      {errorServidor && <p role="alert" className="text-sm text-red-600">{errorServidor}</p>}

      <button type="button" onClick={enviar} disabled={enviando}
        className="w-full rounded-lg bg-brandDarkBlue py-3 text-sm font-bold text-white hover:bg-brandBlue disabled:opacity-60">
        {enviando ? 'Procesando pago…' : `Pagar ${colones(pedido.monto_total)}`}
      </button>
    </section>
  );
}
