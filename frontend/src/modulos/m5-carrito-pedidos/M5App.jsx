import { useState } from 'react';
import { carritoInicial, comercios } from './datosMock';
import { agruparPorComercio, totalGeneral } from './calculos';
import CarritoPage from './CarritoPage';
import CheckoutPage from './CheckoutPage';
import PedidoPendientePage from './PedidoPendientePage';

// Punto de entrada del módulo M5. `simular` fuerza las alternativas del CU-004 durante la validación:
//   'stock'    → M4 no puede reservar (alternativa al paso 4, 409 STOCK_INSUFICIENTE)
//   'comercio' → un comercio está suspendido en M2 (alternativa al paso 5)
export default function M5App({ simular = null }) {
  const [items, setItems] = useState(carritoInicial.items);
  const [pantalla, setPantalla] = useState('carrito');
  const [pedido, setPedido] = useState(null);
  const [suspendidos, setSuspendidos] = useState([]);
  const [aviso, setAviso] = useState(null);

  // Alternativa del paso 2 del CU-001: no se supera el stock; se informa la cantidad máxima.
  const cambiarCantidad = (id, cantidad) => {
    setAviso(null);
    setItems((xs) => xs.map((i) => {
      if (i.id_item_carrito !== id) return i;
      const nueva = Math.min(Math.max(1, cantidad), i.stock);
      return { ...i, cantidad: nueva, aviso: null };
    }));
  };
  const quitar = (id) => { setAviso(null); setItems((xs) => xs.filter((i) => i.id_item_carrito !== id)); };

  // Doble de POST /api/m5/v1/pedidos. Se sustituye por fetch cuando el módulo esté desplegado.
  async function confirmar() {
    await new Promise((r) => setTimeout(r, 600));
    if (simular === 'stock') {
      const agotado = items[0];
      setItems((xs) => xs.map((i) => (i === agotado ? { ...i, aviso: 'Este producto se agotó. Ajuste la cantidad o quítelo.' } : i)));
      setAviso({ tipo: 'error', texto: `${agotado.nombre} se agotó mientras compraba. Ajuste las cantidades para continuar.` });
      setPantalla('carrito');
      return;
    }
    if (simular === 'comercio' && !suspendidos.length) {
      setSuspendidos([2]);
      setAviso({ tipo: 'aviso', texto: `${comercios[2].nombre} tiene su membresía suspendida. Quite sus productos para continuar con el resto de su compra.` });
      setPantalla('carrito');
      return;
    }
    const grupos = agruparPorComercio(items);
    setPedido({ monto_total: totalGeneral(grupos), subpedidos: grupos.map((g) => ({ comercio: g.comercio.nombre })) });
    setPantalla('pedido');
  }

  return (
    <div className="mx-auto min-h-screen max-w-md bg-slate-50 text-slate-900 overflow-x-hidden">
      {pantalla === 'carrito' && (
        <CarritoPage items={items} comerciosSuspendidos={suspendidos} aviso={aviso}
          onCantidad={cambiarCantidad} onQuitar={quitar} onContinuar={() => { setAviso(null); setPantalla('checkout'); }} />
      )}
      {pantalla === 'checkout' && <CheckoutPage items={items} onVolver={() => setPantalla('carrito')} onConfirmar={confirmar} />}
      {pantalla === 'pedido' && <PedidoPendientePage pedido={pedido} />}
    </div>
  );
}
