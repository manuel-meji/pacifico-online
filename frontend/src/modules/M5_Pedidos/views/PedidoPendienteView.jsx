import { Clock, Store } from 'lucide-react';
import { Alerta, Encabezado, Total } from './componentes';

// CU-004 postcondición: Pedido Maestro en estado "Pendiente de Pago" y redirección a la pasarela (paso 9).
export default function PedidoPendientePage({ pedido }) {
  return (
    <>
      <Encabezado titulo="Pedido generado" />
      <main className="p-4 space-y-4">
        <section className="bg-white rounded-xl shadow-xs border border-gray-200 p-5 space-y-3">
          <p className="text-xs font-medium text-gray-600">Estado del pedido</p>
          <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-blue-50 text-brandDarkBlue border border-blue-200">
            <Clock className="w-3.5 h-3.5 text-brandBlue" aria-hidden="true" /> Pendiente de Pago
          </span>
          <ul className="space-y-3 pt-1">
            {pedido.subpedidos.map((s, n) => (
              <li key={s.comercio} className="flex flex-col gap-1 text-xs">
                <span className="flex items-center gap-2 min-w-0"><Store className="w-4 h-4 shrink-0 text-brandDarkBlue" aria-hidden="true" /><span className="font-bold text-gray-900 break-words">{s.comercio}</span></span>
                <span className="self-start font-semibold text-brandGreen bg-green-50 px-2 py-0.5 rounded border border-green-200">Subpedido {n + 1}: Pendiente</span>
              </li>
            ))}
          </ul>
          <div className="pt-3 border-t border-gray-100"><Total etiqueta="Monto total" valor={pedido.monto_total} /></div>
        </section>
        <div role="status"><Alerta tipo="info">Redirigiendo a la pasarela de pago…</Alerta></div>
      </main>
    </>
  );
}
