import { useEffect, useState } from 'react';
import FormularioPago from './components/FormularioPago';
import ResultadoPago from './components/ResultadoPago';
import FacturasPedidoView from './views/FacturasPedidoView';
import HistorialComercioView from './views/HistorialComercioView';
import { listarFacturasPedido } from './api/pagosApi';

// Pedido de prueba por defecto (dobles de M5).
const PEDIDO_PRUEBA = {
  id_pedido: 8942,
  estado: 'confirmado',
  monto_total: 59840.0,
  subpedidos: [],
};

// Lee de la dirección ?pedido=ID y, opcionalmente, &monto=MONTO.
function parametrosDeUrl() {
  const params = new URLSearchParams(window.location.search);
  const id = Number(params.get('pedido'));
  const monto = Number(params.get('monto'));

  return {
    id: Number.isInteger(id) && id > 0 ? id : null,
    monto: Number.isFinite(monto) && monto > 0 ? monto : null,
  };
}

const PARAMS = parametrosDeUrl();
const HAY_COBRO = Boolean(PARAMS.id && PARAMS.monto);

// ?pedido=ID&monto=MONTO -> formulario de cobro (o facturas si el pedido ya fue cobrado).
// Solo ?pedido=ID        -> facturas del pedido.
// Sin parámetros         -> pedido de prueba.
const PEDIDO_ACTUAL = HAY_COBRO
  ? { id_pedido: PARAMS.id, estado: 'confirmado', monto_total: PARAMS.monto, subpedidos: [] }
  : PEDIDO_PRUEBA;

const ID_PEDIDO_FACTURAS = PARAMS.id ?? PEDIDO_PRUEBA.id_pedido;
const PANTALLA_INICIAL = PARAMS.id && !PARAMS.monto ? 'factura' : 'pago';

const PESTANAS = [
  ['pago', 'Pagar pedido'],
  ['factura', 'Facturas'],
  ['historial', 'Historial'],
];

export default function M6App() {
  const [pantalla, setPantalla] = useState(PANTALLA_INICIAL);
  const [pago, setPago] = useState(null);
  const [intento, setIntento] = useState(0);
  const [verificando, setVerificando] = useState(HAY_COBRO);

  // Si el pedido ya tiene facturas, ya fue cobrado: se muestran en vez del formulario.
  useEffect(() => {
    if (!HAY_COBRO) return;

    listarFacturasPedido(PARAMS.id)
      .then((facturas) => {
        if (Array.isArray(facturas) && facturas.length > 0) setPantalla('factura');
      })
      .catch(() => {
        // Sin facturas o sin respuesta: se queda en el formulario de cobro.
      })
      .finally(() => setVerificando(false));
  }, []);

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

      {verificando && (
        <p className="mx-auto max-w-xl text-sm text-gray-500">Verificando el pedido…</p>
      )}

      {!verificando && pantalla === 'pago' && (
        <FormularioPago key={intento} pedido={PEDIDO_ACTUAL} onResultado={verResultado} />
      )}

      {!verificando && pantalla === 'resultado' && pago && (
        <div className="space-y-3">
          <ResultadoPago
            pago={pago}
            onOtroMedio={otroMedio}
            onVerFactura={() => setPantalla('factura')}
          />
        </div>
      )}

      {!verificando && pantalla === 'factura' && (
        <FacturasPedidoView idPedido={pago?.id_pedido ?? ID_PEDIDO_FACTURAS} />
      )}

      {!verificando && pantalla === 'historial' && (
        // CU-008: el filtro se elige en la pantalla; con sesión, el backend lo fija al propio (RN-12).
        <HistorialComercioView />
      )}
    </div>
  );
}