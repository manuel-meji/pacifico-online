import { useEffect, useRef, useState } from 'react';
import { CreditCard, LoaderCircle, Lock } from 'lucide-react';
import { Alerta } from './componentes';
import { colones } from '../services/calculos';
import { confirmarPago } from '../services/m5Api';

// Medios de pago que muestra la pasarela (los mismos del checkout).
const METODOS_PAGO = [
  { valor: 'tarjeta', etiqueta: 'Tarjeta' },
  { valor: 'sinpe', etiqueta: 'SINPE' },
  { valor: 'paypal', etiqueta: 'PayPal' },
];

// URL que devuelve el doble de M6 (PagosStub / respaldo de ControladorPedido).
const DOMINIO_PASARELA_SIMULADA = 'pago.pacificos.com';

// true si no hay URL de pago o si es la del doble: en ese caso se usa este modal en vez del enlace.
export const esPasarelaSimulada = (url) => !url || String(url).includes(DOMINIO_PASARELA_SIMULADA);

function metodoInicial(pedido) {
  const valor = String(pedido?.metodo_pago ?? pedido?.medio_pago ?? pedido?.metodoPago ?? '').toLowerCase();
  return METODOS_PAGO.some((m) => m.valor === valor) ? valor : 'tarjeta';
}

// Pasarela de pago simulada de M6: confirma el pago con POST /api/m5/v1/pedidos/{id}/pago-confirmado.
//   onPagado(respuesta) → el pago se registró (respuesta trae { estado: 'PAGADO', pedido }); el padre actualiza el pedido.
//   onCerrar()          → el usuario canceló sin pagar.
export default function PasarelaPagoSimulada({ abierto, pedido, onCerrar, onPagado }) {
  const [metodo, setMetodo] = useState('tarjeta');
  const [procesando, setProcesando] = useState(false);
  const [error, setError] = useState(null);
  const procesandoRef = useRef(false); // Evita un segundo POST antes de que React deshabilite el botón.
  const primerCampoRef = useRef(null);

  useEffect(() => {
    if (!abierto) return;
    setMetodo(metodoInicial(pedido));
    setError(null);
    primerCampoRef.current?.focus();
  }, [abierto, pedido]);

  useEffect(() => {
    if (!abierto) return undefined;
    const alTeclear = (e) => { if (e.key === 'Escape' && !procesandoRef.current) onCerrar(); };
    document.addEventListener('keydown', alTeclear);
    return () => document.removeEventListener('keydown', alTeclear);
  }, [abierto, onCerrar]);

  if (!abierto || !pedido) return null;

  async function pagar(e) {
    e.preventDefault();
    if (procesandoRef.current) return;
    procesandoRef.current = true;
    setProcesando(true);
    setError(null);
    try {
      const respuesta = await confirmarPago(pedido.id_pedido);
      onPagado(respuesta);
    } catch (err) {
      setError(`${err.mensaje || err.message}${err.codigo ? ` (${err.codigo})` : ''}`);
    } finally {
      procesandoRef.current = false;
      setProcesando(false);
    }
  }

  return (
    <div className="fixed inset-0 z-40 flex items-center justify-center p-4">
      <div className="absolute inset-0 bg-gray-900/50" aria-hidden="true" onClick={() => !procesando && onCerrar()} />
      <form onSubmit={pagar} noValidate role="dialog" aria-modal="true" aria-labelledby="pasarela-titulo"
        className="relative bg-white rounded-2xl shadow-lg border border-gray-200 w-full max-w-md p-6 space-y-5">
        <div className="flex items-start gap-3">
          <div className="h-10 w-10 shrink-0 rounded-full bg-blue-50 flex items-center justify-center">
            <CreditCard className="w-5 h-5 text-brandBlue" aria-hidden="true" />
          </div>
          <div className="min-w-0">
            <h2 id="pasarela-titulo" className="text-base font-bold text-gray-900">Pasarela de pago (simulada de M6)</h2>
            <p className="mt-1 text-sm text-gray-600">No se realiza ningún cobro real.</p>
          </div>
        </div>

        <dl className="rounded-lg border border-gray-100 bg-gray-50 p-4 space-y-2 text-sm">
          <div className="flex justify-between text-gray-600">
            <dt>Número de pedido</dt>
            <dd className="font-semibold text-gray-900">#{pedido.id_pedido}</dd>
          </div>
          <div className="flex justify-between items-center pt-2 border-t border-gray-200">
            <dt className="font-bold text-gray-800">Monto total</dt>
            <dd className="text-xl font-extrabold text-brandDarkBlue">{colones(Number(pedido.monto_total ?? 0))}</dd>
          </div>
        </dl>

        <fieldset disabled={procesando}>
          <legend className="text-xs font-medium text-gray-600 mb-2">Método de pago</legend>
          <div className="grid grid-cols-3 gap-2">
            {METODOS_PAGO.map((m, i) => (
              <label key={m.valor}
                className={`min-h-11 flex items-center justify-center gap-2 rounded-lg border px-2 text-sm font-semibold cursor-pointer transition ${
                  metodo === m.valor ? 'border-brandBlue bg-blue-50 text-brandDarkBlue' : 'border-gray-200 text-gray-700 hover:bg-gray-50'
                }`}>
                <input
                  ref={i === 0 ? primerCampoRef : undefined}
                  type="radio"
                  name="metodo-pago-pasarela"
                  value={m.valor}
                  checked={metodo === m.valor}
                  onChange={() => setMetodo(m.valor)}
                  className="accent-brandBlue"
                />
                {m.etiqueta}
              </label>
            ))}
          </div>
        </fieldset>

        {error && <Alerta tipo="error" titulo="No se pudo confirmar el pago">{error}</Alerta>}

        <div className="flex flex-col-reverse sm:flex-row sm:justify-end gap-2">
          <button type="button" disabled={procesando} onClick={onCerrar}
            className="min-h-11 px-5 rounded-lg border border-gray-200 text-sm font-semibold text-gray-700 hover:bg-gray-50 transition disabled:opacity-50">
            Cancelar
          </button>
          <button type="submit" disabled={procesando}
            className="min-h-11 px-5 rounded-lg bg-brandDarkBlue hover:bg-brandBlue text-white text-sm font-bold transition inline-flex items-center justify-center gap-2 disabled:opacity-70">
            {procesando ? <LoaderCircle className="w-4 h-4 animate-spin" aria-hidden="true" /> : <Lock className="w-4 h-4" aria-hidden="true" />}
            {procesando ? 'Procesando…' : 'Pagar'}
          </button>
        </div>
      </form>
    </div>
  );
}
