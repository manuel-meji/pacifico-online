import { useState } from 'react';
import FormularioPago from './components/FormularioPago';
import ResultadoPago from './components/ResultadoPago';
import HistorialComercioView from './views/HistorialComercioView';

const PEDIDO_PRUEBA = {
  id_pedido: 8942,
  estado: 'confirmado',
  monto_total: 59840.0,
  subpedidos: [],
};


export default function M6App() {
  const [pantalla, setPantalla] = useState('pago');
  const [pago, setPago] = useState(null);
 
  const [intento, setIntento] = useState(0);

  const verResultado = (p) => { setPago(p); setPantalla('resultado'); };
  const otroMedio = () => { setIntento((n) => n + 1); setPantalla('pago'); };

  return (
    <div className="min-h-screen bg-brandLightBg px-4 py-6 text-gray-800">
      <nav className="mx-auto mb-5 flex max-w-xl gap-2">
        <button type="button" onClick={() => setPantalla('pago')}
          className={`flex-1 rounded-lg border px-3 py-2 text-sm font-semibold ${pantalla !== 'historial' ? 'border-brandDarkBlue bg-brandDarkBlue text-white' : 'border-gray-300 bg-white'}`}>
          Pagar pedido
        </button>
        <button type="button" onClick={() => setPantalla('historial')}
          className={`flex-1 rounded-lg border px-3 py-2 text-sm font-semibold ${pantalla === 'historial' ? 'border-brandDarkBlue bg-brandDarkBlue text-white' : 'border-gray-300 bg-white'}`}>
          Historial
        </button>
      </nav>

      {pantalla === 'pago' && (
        <FormularioPago key={intento} pedido={PEDIDO_PRUEBA} onResultado={verResultado} />
      )}

      {pantalla === 'resultado' && pago && (
        <div className="space-y-3">
          <ResultadoPago
            pago={pago}
            onOtroMedio={otroMedio}
            onVerFactura={() => setPantalla('factura')}
          />
        </div>
      )}

      {pantalla === 'factura' && (
       
        <p role="status" className="mx-auto max-w-xl rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
          La consulta de facturas todavía no está disponible en el backend de M6.
        </p>
      )}

      {pantalla === 'historial' && (
       
        <div className="mx-auto max-w-3xl">
          <HistorialComercioView idComercio={null} />
        </div>
      )}
    </div>
  );
}
