import { useState } from 'react';
import FormularioPago from './components/FormularioPago';
import ResultadoPago from './components/ResultadoPago';
import FacturasPedidoView from './views/FacturasPedidoView';
import HistorialComercioView from './views/HistorialComercioView';

const PEDIDO_PRUEBA = {
  id_pedido: 8942,
  estado: 'confirmado',
  monto_total: 59840.0,
  subpedidos: [],
};

const PESTANAS = [
  ['pago', 'Pagar pedido'],
  ['factura', 'Facturas'],
  ['historial', 'Historial'],
];

export default function M6App() {
  const [pantalla, setPantalla] = useState('pago');
  const [pago, setPago] = useState(null);
 
  const [intento, setIntento] = useState(0);

  const verResultado = (p) => { setPago(p); setPantalla('resultado'); };
  const otroMedio = () => { setIntento((n) => n + 1); setPantalla('pago'); };

  return (
    <div className="min-h-screen bg-brandLightBg px-4 py-6 text-gray-800">
      <nav className="mx-auto mb-5 flex max-w-xl gap-2" aria-label="Secciones de M6">
        {PESTANAS.map(([clave, texto]) => {
          const activa = clave === 'pago' ? pantalla === 'pago' || pantalla === 'resultado' : pantalla === clave;
          return (
            <button key={clave} type="button" onClick={() => setPantalla(clave)} aria-current={activa ? 'page' : undefined}
              className={`flex-1 rounded-lg border px-3 py-2 text-sm font-semibold transition ${activa ? 'border-brandDarkBlue bg-brandDarkBlue text-white' : 'border-gray-300 bg-white hover:border-brandBlue hover:text-brandBlue'}`}>
              {texto}
            </button>
          );
        })}
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
        <FacturasPedidoView idPedido={pago?.id_pedido ?? PEDIDO_PRUEBA.id_pedido} />
      )}

      {pantalla === 'historial' && (
       
          // CU-008: el filtro se elige en la pantalla; con sesión, el backend lo fija al propio (RN-12).
         <HistorialComercioView />
      )}
    </div>
  );
}
