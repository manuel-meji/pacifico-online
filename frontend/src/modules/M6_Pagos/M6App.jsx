import { useEffect, useState } from 'react';
import FormularioPago from './components/FormularioPago';
import ResultadoPago from './components/ResultadoPago';
import FacturasPedidoView from './views/FacturasPedidoView';
import HistorialComercioView from './views/HistorialComercioView';
import HistorialPersonalView from './views/HistorialPersonalView';
import { listarFacturasPedido } from './api/pagosApi';

// Pedido de prueba por defecto (dobles de M5).
const PEDIDO_PRUEBA = {
  id_pedido: 8942,
  estado: 'confirmado',
  monto_total: 59840.0,
  subpedidos: [],
};

// Lee de la dirección ?pedido=ID, opcionalmente &monto=MONTO, y ?pantalla=historial.
function parametrosDeUrl() {
  const params = new URLSearchParams(window.location.search);
  const id = Number(params.get('pedido'));
  const monto = Number(params.get('monto'));

  return {
    id: Number.isInteger(id) && id > 0 ? id : null,
    monto: Number.isFinite(monto) && monto > 0 ? monto : null,
    pantalla: params.get('pantalla'),
  };
}

const PARAMS = parametrosDeUrl();

// El historial ya no es una pestaña: se abre desde el botón "Historial de pagos" del portal (M1).
// historial-comercio conserva la consulta por comercio o cliente (CU-008) para encargados.
const PAGINA_HISTORIAL = ['historial', 'historial-comercio'].includes(PARAMS.pantalla) ? PARAMS.pantalla : null;

// ?pedido=ID sin monto: solo se consultan las facturas de un pedido ya pagado (por ejemplo, desde el historial).
// No se muestra el formulario de pago.
const SOLO_FACTURAS = Boolean(PARAMS.id) && !PARAMS.monto && !PAGINA_HISTORIAL;

const HAY_COBRO = Boolean(PARAMS.id && PARAMS.monto) && !PAGINA_HISTORIAL;

// ?pedido=ID&monto=MONTO -> formulario de cobro (o facturas si el pedido ya fue cobrado).
// Sin parámetros         -> pedido de prueba.
const PEDIDO_ACTUAL = HAY_COBRO
  ? { id_pedido: PARAMS.id, estado: 'confirmado', monto_total: PARAMS.monto, subpedidos: [] }
  : PEDIDO_PRUEBA;

const ID_PEDIDO_FACTURAS = PARAMS.id ?? PEDIDO_PRUEBA.id_pedido;
const PANTALLA_INICIAL = 'pago';

const PESTANAS = [
  ['pago', 'Pagar pedido'],
  ['factura', 'Facturas'],
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

  if (PAGINA_HISTORIAL) {
    return (
      <div className="min-h-screen bg-brandLightBg px-4 py-6 text-gray-800">
        <div className="mx-auto mb-5 max-w-xl">
          <a href="/m1.html" className="text-sm font-semibold text-brandBlue hover:underline">← Volver al portal</a>
        </div>
        {/* Con sesión, el backend fija el filtro al propio (RN-12). */}
        {PAGINA_HISTORIAL === 'historial' ? <HistorialPersonalView /> : <HistorialComercioView />}
      </div>
    );
  }

  if (SOLO_FACTURAS) {
    return (
      <div className="min-h-screen bg-brandLightBg px-4 py-6 text-gray-800">
        <div className="mx-auto mb-5 max-w-xl">
          <a href="/m6.html?pantalla=historial" className="text-sm font-semibold text-brandBlue hover:underline">← Volver al historial</a>
        </div>
        <FacturasPedidoView idPedido={PARAMS.id} />
      </div>
    );
  }

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

      
      {!verificando && (pantalla === 'resultado' || pantalla === 'factura') && (
        <div className="mx-auto mt-4 max-w-xl">
          <a
            href="/app.html"
            className="block w-full rounded-lg border border-gray-300 bg-white py-2 text-center text-sm font-semibold text-gray-700 transition hover:bg-gray-50"
          >
            Volver al menú principal
          </a>
        </div>
      )}
      
    </div>
  );
}