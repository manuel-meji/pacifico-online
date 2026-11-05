import { useCallback, useEffect, useState } from 'react';
// datosMock solo alimenta el checkout y la confirmación simulada; el carrito ya viene de la API.
import { carritoInicial, comercios } from './services/datosMock';
import { obtenerCarrito } from './services/m5Api';
import { agruparPorComercio, totalGeneral } from './services/calculos';
import CarritoPage from './views/CarritoView';
import CheckoutPage from './views/CheckoutView';
import PedidoPendientePage from './views/PedidoPendienteView';

// Punto de entrada del modulo M5. `simular` fuerza las alternativas del CU-004 durante la validacion:
//   'stock'    → M4 no puede reservar (alternativa al paso 4, 409 STOCK_INSUFICIENTE)
//   'comercio' → un comercio esta suspendido en M2 (alternativa al paso 5)
export default function M5App({ simular = null }) {
  // Carrito real: GET /api/m5/v1/carrito (id_cliente=1 por defecto).
  const [carrito, setCarrito] = useState(null);
  const [cargando, setCargando] = useState(true);
  const [error, setError] = useState(null);

  const cargarCarrito = useCallback(async () => {
    setCargando(true);
    setError(null);
    try {
      setCarrito(await obtenerCarrito());
    } catch (e) {
      setError(e);
    } finally {
      setCargando(false);
    }
  }, []);

  useEffect(() => { cargarCarrito(); }, [cargarCarrito]);

  // Items simulados del checkout
  const [items, setItems] = useState(carritoInicial.items);
  const [pantalla, setPantalla] = useState('carrito');
  const [pedido, setPedido] = useState(null);
  const [suspendidos, setSuspendidos] = useState([]);
  const [aviso, setAviso] = useState(null);

  // Doble de POST /api/m5/v1/pedidos. Se sustituye por fetch cuando el modulo esté desplegado.
  async function confirmar() {
    await new Promise((r) => setTimeout(r, 600));
    if (simular === 'stock') {
      const agotado = items[0];
      setItems((xs) => xs.map((i) => (i === agotado ? { ...i, aviso: 'Este producto se agotó. Ajuste la cantidad o quítelo.' } : i)));
      setAviso({ tipo: 'error', titulo: 'Stock insuficiente', texto: `${agotado.nombre} se agotó mientras compraba. Ajuste las cantidades para continuar.` });
      setPantalla('carrito');
      return;
    }
    if (simular === 'comercio' && !suspendidos.length) {
      setSuspendidos([2]);
      setAviso({ tipo: 'aviso', titulo: 'Comercio suspendido', texto: `${comercios[2].nombre} tiene su membresía suspendida. Quite sus productos para continuar con el resto de su compra.` });
      setPantalla('carrito');
      return;
    }
    const grupos = agruparPorComercio(items);
    setPedido({ monto_total: totalGeneral(grupos), subpedidos: grupos.map((g) => ({ comercio: g.comercio.nombre })) });
    setPantalla('pedido');
  }

  return (
    <div className="min-h-screen bg-brandLightBg text-gray-800 font-sans antialiased flex flex-col">
      <div className="flex-1 w-full">
        {pantalla === 'carrito' && (
          <CarritoPage carrito={carrito} cargando={cargando} error={error} onReintentar={cargarCarrito} aviso={aviso}
            onContinuar={() => { setAviso(null); setPantalla('checkout'); }} />
        )}
        {pantalla === 'checkout' && <CheckoutPage items={items} onVolver={() => setPantalla('carrito')} onConfirmar={confirmar} />}
        {pantalla === 'pedido' && <PedidoPendientePage pedido={pedido} />}
      </div>
    </div>
  );
}