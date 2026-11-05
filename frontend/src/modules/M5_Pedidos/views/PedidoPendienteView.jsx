import { Clock, ExternalLink, ShoppingCart, Store } from 'lucide-react';
import { Alerta, Boton, Encabezado, Total } from '../components/componentes';
import { colones } from '../services/calculos';

// Etiquetas visibles para estado_pedido / estado_subpedido.
const ESTADOS = {
  pendiente: 'Pendiente de pago',
  confirmado: 'Confirmado',
  pagado: 'Pagado',
  en_preparacion: 'En preparación',
  despachado: 'Despachado',
  entregado: 'Entregado',
  cancelado: 'Cancelado',
};
const etiquetaEstado = (estado) => ESTADOS[String(estado ?? '').toLowerCase()] ?? estado ?? 'Pendiente de pago';

// Los montos llegan como texto decimal ("45000.00") por los casts del modelo.
const monto = (valor) => colones(Number(valor ?? 0));

// CU-004 postcondición: Pedido Maestro en estado "Pendiente de pago" y enlace a la pasarela (paso 9).
// `pedido` es la respuesta 201 de POST /api/m5/v1/pedidos; `grupos` son los grupos_comercio del carrito,
// usados solo para mostrar el nombre de cada comercio (el subpedido trae id_comercio).
export default function PedidoPendientePage({ pedido, grupos = [], onSeguirComprando }) {
  const nombreComercio = (idComercio) =>
    grupos.find((g) => Number(g.id_comercio) === Number(idComercio))?.nombre_comercio ?? `Comercio ${idComercio}`;
  const subpedidos = pedido.subpedidos ?? [];

  return (
    <>
      <Encabezado titulo="Pedido generado" />
      <main className="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-5">
        <section className="bg-white rounded-xl shadow-xs border border-gray-200 p-6 sm:p-8 space-y-4">
          <div className="flex items-center justify-between gap-3 flex-wrap">
            <div>
              <p className="text-xs font-medium text-gray-500">Número de pedido</p>
              <h2 className="text-lg font-bold text-gray-900 mt-0.5">Pedido #{pedido.id_pedido}</h2>
            </div>
            <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-blue-50 text-brandDarkBlue border border-blue-200 shadow-2xs">
              <Clock className="w-3.5 h-3.5 text-brandBlue" aria-hidden="true" /> {etiquetaEstado(pedido.estado_pedido)}
            </span>
          </div>

          <div className="pt-2 border-t border-gray-100">
            <p className="text-xs font-semibold text-gray-700 uppercase tracking-wider mb-2">Subpedidos por comercio</p>
            <ul className="space-y-3">
              {subpedidos.map((s, n) => (
                <li key={s.id_subpedido ?? n} className="p-3 sm:p-4 bg-gray-50 rounded-lg border border-gray-100 text-xs sm:text-sm space-y-2">
                  <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                    <span className="flex items-center gap-2 min-w-0">
                      <Store className="w-4 h-4 shrink-0 text-brandDarkBlue" aria-hidden="true" />
                      <span className="font-bold text-gray-900 break-words">{nombreComercio(s.id_comercio)}</span>
                    </span>
                    <span className="self-start sm:self-auto font-semibold text-brandGreen bg-green-50 px-2.5 py-0.5 rounded border border-green-200 text-xs">
                      Subpedido {n + 1}: {etiquetaEstado(s.estado_subpedido)}
                    </span>
                  </div>
                  <dl className="space-y-1 text-gray-600">
                    <div className="flex justify-between"><dt>Subtotal</dt><dd className="font-medium">{monto(s.subtotal)}</dd></div>
                    <div className="flex justify-between"><dt>IVA</dt><dd className="font-medium">{monto(s.impuesto)}</dd></div>
                    <div className="flex justify-between"><dt>Envío</dt><dd className="font-medium">{monto(s.costo_envio)}</dd></div>
                    {Number(s.descuento) > 0 && (
                      <div className="flex justify-between"><dt>Descuento</dt><dd className="font-medium">−{monto(s.descuento)}</dd></div>
                    )}
                    <div className="flex justify-between pt-1 border-t border-gray-200 text-gray-900">
                      <dt className="font-bold">Total del subpedido</dt><dd className="font-extrabold">{monto(s.total)}</dd>
                    </div>
                  </dl>
                </li>
              ))}
            </ul>
          </div>

          <div className="pt-4 border-t border-gray-100">
            <Total etiqueta="Total general" valor={Number(pedido.monto_total ?? 0)} />
          </div>

          {pedido.urlPasarelaPago && (
            <a
              href={pedido.urlPasarelaPago}
              target="_blank"
              rel="noopener noreferrer"
              className="w-full inline-flex items-center justify-center gap-2 rounded-lg min-h-11 py-3 px-5 bg-brandDarkBlue hover:bg-brandBlue text-white font-bold shadow-xs transition"
            >
              <ExternalLink className="w-4 h-4" aria-hidden="true" />
              Ir a pagar
              <span className="sr-only">(se abre en otra pestaña)</span>
            </a>
          )}
        </section>

        <div role="status">
          <Alerta tipo="info" titulo="Pedido registrado">
            Su pedido quedó pendiente de pago. Complete el pago en la pasarela para confirmarlo.
          </Alerta>
        </div>

        {onSeguirComprando && (
          <Boton variante="oscuro" icono={ShoppingCart} onClick={onSeguirComprando}>Volver al carrito</Boton>
        )}
      </main>
    </>
  );
}
