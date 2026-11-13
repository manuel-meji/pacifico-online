import { useState } from 'react';
import FormularioPago from './components/FormularioPago';
import ResultadoPago from './components/ResultadoPago';
import FacturasPedidoView from './views/FacturasPedidoView';
import HistorialComercioView from './views/HistorialComercioView';

// Pedido de prueba por defecto (dobles). Con ?pedido=ID&monto=MONTO se usa un pedido real de M5.
const PEDIDO_PRUEBA = {
  id_pedido: 8942,
  estado: 'confirmado',
  monto_total: 59840.0,
  subpedidos: [],
};

function pedidoDesdeUrl() {
  const params = new URLSearchParams(window.location.search);
  const id = Number(params.get('pedido'));
  const monto = Number(params.get('monto'));

  if (!Number.isInteger(id) || id <= 0 || !Number.isFinite(monto) || monto <= 0) {
    return PEDIDO_PRUEBA;
  }

  // El estado "confirmado" solo habilita el botón; el backend valida el estado real en M5.
  return { id_pedido: id, estado: 'confirmado', monto_total: monto, subpedidos: [] };
}

const PEDIDO_ACTUAL = pedidoDesdeUrl();

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
        <FormularioPago key={intento} pedido={PEDIDO_ACTUAL} onResultado={verResultado} />
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
        <FacturasPedidoView idPedido={pago?.id_pedido ?? PEDIDO_ACTUAL.id_pedido} />
      )}

      {pantalla === 'historial' && (
       
          // CU-008: el filtro se elige en la pantalla; con sesión, el backend lo fija al propio (RN-12).
         <HistorialComercioView />
      )}
    </div>
  );
}
