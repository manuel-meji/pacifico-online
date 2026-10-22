import { Clock, Store } from 'lucide-react';
import { Alerta, Encabezado, Total } from '../components/componentes';

// CU-004 postcondición: Pedido Maestro en estado "Pendiente de Pago" y redirección a la pasarela (paso 9).
export default function PedidoPendientePage({ pedido }) {
  return (
    <>
      <Encabezado titulo="Pedido generado" />
      <main className="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-5">
        <section className="bg-white rounded-xl shadow-xs border border-gray-200 p-6 sm:p-8 space-y-4">
          <div className="flex items-center justify-between gap-3 flex-wrap">
            <div>
              <p className="text-xs font-medium text-gray-500">Estado del pedido</p>
              <h2 className="text-lg font-bold text-gray-900 mt-0.5">Pedido Maestro en trámite</h2>
            </div>
            <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-blue-50 text-brandDarkBlue border border-blue-200 shadow-2xs">
              <Clock className="w-3.5 h-3.5 text-brandBlue animate-pulse" aria-hidden="true" /> Pendiente de Pago
            </span>
          </div>

          <div className="pt-2 border-t border-gray-100">
            <p className="text-xs font-semibold text-gray-700 uppercase tracking-wider mb-2">Comercios involucrados:</p>
            <ul className="space-y-3">
              {pedido.subpedidos.map((s, n) => (
                <li key={s.comercio} className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 p-3 bg-gray-50 rounded-lg border border-gray-100 text-xs sm:text-sm">
                  <span className="flex items-center gap-2 min-w-0">
                    <Store className="w-4 h-4 shrink-0 text-brandDarkBlue" aria-hidden="true" />
                    <span className="font-bold text-gray-900 break-words">{s.comercio}</span>
                  </span>
                  <span className="self-start sm:self-auto font-semibold text-brandGreen bg-green-50 px-2.5 py-0.5 rounded border border-green-200 text-xs">
                    Subpedido {n + 1}: Pendiente
                  </span>
                </li>
              ))}
            </ul>
          </div>

          <div className="pt-4 border-t border-gray-100">
            <Total etiqueta="Monto total a transferir" valor={pedido.monto_total} />
          </div>
        </section>

        <div role="status">
          <Alerta tipo="info" titulo="Conexión en curso">
            Redirigiendo a la pasarela de pago para completar la transacción…
          </Alerta>
        </div>
      </main>
    </>
  );
}
