import { Encabezado, Linea } from './componentes';

// CU-004 postcondición: Pedido Maestro en estado "Pendiente de Pago" y redirección a la pasarela (paso 9).
export default function PedidoPendientePage({ pedido }) {
  return (
    <>
      <Encabezado titulo="Pedido generado" />
      <main className="p-4 space-y-4">
        <section className="rounded-xl bg-white border border-slate-200 p-4 space-y-2">
          <p className="text-sm text-slate-600">Estado del pedido</p>
          <p className="text-lg font-semibold text-slate-900">Pendiente de Pago</p>
          <ul className="text-sm text-slate-700 space-y-1">
            {pedido.subpedidos.map((s) => <li key={s.comercio}>{s.comercio}: Pendiente</li>)}
          </ul>
          <dl className="pt-2 border-t border-slate-100"><Linea etiqueta="Monto total" valor={pedido.monto_total} fuerte /></dl>
        </section>
        <p role="status" className="text-center text-sm text-slate-600">Redirigiendo a la pasarela de pago…</p>
      </main>
    </>
  );
}
