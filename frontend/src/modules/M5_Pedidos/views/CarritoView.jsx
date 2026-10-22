import { CreditCard, Lock, ShoppingCart } from 'lucide-react';
import { Alerta, Boton, Encabezado, TarjetaComercio, Total } from './componentes';
import { agruparPorComercio, totalGeneral } from './calculos';

// CU-001 Gestionar carrito de compras (InterfazCarrito): productos agrupados por comercio,
// cambio de cantidad, eliminación de ítems y subtotales recalculados.
export default function CarritoPage({ items, comerciosSuspendidos, aviso, onCantidad, onQuitar, onContinuar }) {
  const grupos = agruparPorComercio(items);
  const unidades = items.reduce((s, i) => s + i.cantidad, 0);
  const hayBloqueo = grupos.some((g) => comerciosSuspendidos.includes(g.comercio.id_comercio));

  if (items.length === 0) {
    return (
      <>
        <Encabezado titulo="Mi carrito" />
        <main className="p-6 text-center">
          <ShoppingCart className="w-12 h-12 stroke-1 text-gray-300 mx-auto" aria-hidden="true" />
          <p className="mt-3 text-lg font-bold text-gray-800">Su carrito está vacío</p>
        </main>
      </>
    );
  }
  return (
    <>
      <Encabezado titulo="Mi carrito" paso={`${unidades} ${unidades === 1 ? 'producto' : 'productos'}`} />
      <main className="p-4 space-y-4 pb-44">
        {aviso && <Alerta tipo={aviso.tipo} titulo={aviso.titulo}>{aviso.texto}</Alerta>}
        {grupos.map((g, n) => (
          <TarjetaComercio key={g.comercio.id_comercio} grupo={g} numero={n + 1} editable
            suspendido={comerciosSuspendidos.includes(g.comercio.id_comercio)} onCantidad={onCantidad} onQuitar={onQuitar} />
        ))}
      </main>
      <footer className="fixed bottom-0 inset-x-0 mx-auto max-w-md bg-white border-t border-gray-200 p-4 space-y-3">
        <Total etiqueta="Total" valor={totalGeneral(grupos)} />
        <Boton icono={hayBloqueo ? Lock : CreditCard} disabled={hayBloqueo} onClick={onContinuar} className="w-full">Proceder al checkout</Boton>
      </footer>
    </>
  );
}
