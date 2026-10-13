import { Alerta, Encabezado, Linea, TarjetaComercio } from './componentes';
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
          <p className="text-lg font-medium text-slate-800">Su carrito está vacío</p>
        </main>
      </>
    );
  }
  return (
    <>
      <Encabezado titulo="Mi carrito" paso={`${unidades} ${unidades === 1 ? 'producto' : 'productos'}`} />
      <main className="p-4 space-y-4 pb-40">
        {aviso && <Alerta tipo={aviso.tipo}>{aviso.texto}</Alerta>}
        {grupos.map((g) => (
          <TarjetaComercio key={g.comercio.id_comercio} grupo={g} editable
            suspendido={comerciosSuspendidos.includes(g.comercio.id_comercio)} onCantidad={onCantidad} onQuitar={onQuitar} />
        ))}
      </main>
      <footer className="fixed bottom-0 inset-x-0 mx-auto max-w-md bg-white border-t border-slate-200 p-4 space-y-3">
        <dl><Linea etiqueta="Total" valor={totalGeneral(grupos)} fuerte /></dl>
        <button onClick={onContinuar} disabled={hayBloqueo}
          className="min-h-[48px] w-full rounded-lg bg-sky-700 font-semibold text-white disabled:bg-slate-400">Proceder al checkout</button>
      </footer>
    </>
  );
}
