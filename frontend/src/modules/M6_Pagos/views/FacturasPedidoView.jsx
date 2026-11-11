import { useCallback, useEffect, useState } from 'react';
import { Loader2, ReceiptText, RefreshCw } from 'lucide-react';
import DetalleFactura from '../components/DetalleFactura';
import { emitirNota, listarFacturasPedido } from '../api/pagosApi';
import { colones } from '../lib/documentos';

/**
 * CU-004. Facturas electrónicas de un pedido: una por subpedido/comercio (decisión 3.1 del Comité).
 * Desde cada factura se emiten notas de crédito (CU-005) y de débito (CU-006).
 */
export default function FacturasPedidoView({ idPedido, api = { listarFacturasPedido, emitirNota } }) {
  const [facturas, setFacturas] = useState(null);
  const [error, setError] = useState(null);

  const cargar = useCallback(() => {
    setError(null);
    setFacturas(null);
    api.listarFacturasPedido(idPedido).then(setFacturas).catch((e) => setError(e.mensaje || e.message));
  }, [idPedido]);

  useEffect(cargar, [cargar]);

  const total = facturas?.reduce((suma, f) => suma + Number(f.monto_total), 0) ?? 0;

  return (
    <section className="mx-auto w-full max-w-xl space-y-4">
      <header className="flex flex-wrap items-center justify-between gap-2">
        <div>
          <h2 className="flex items-center gap-2 text-lg font-bold text-brandDarkBlue">
            <ReceiptText className="h-5 w-5" aria-hidden /> Facturas del pedido #{idPedido}
          </h2>
          {facturas?.length > 0 && (
            <p className="text-sm text-gray-600">{facturas.length} factura(s) · total {colones(total)}</p>
          )}
        </div>
        <button type="button" onClick={cargar}
          className="flex items-center gap-2 rounded-lg border border-brandBlue px-3 py-2 text-sm font-semibold text-brandBlue transition hover:bg-blue-50">
          <RefreshCw className="h-4 w-4" aria-hidden /> Actualizar
        </button>
      </header>

      {error && <p role="alert" className="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">{error}</p>}

      {!error && facturas === null && (
        <p className="flex items-center gap-2 text-sm text-gray-500"><Loader2 className="h-4 w-4 animate-spin" aria-hidden /> Cargando facturas…</p>
      )}

      {facturas?.length === 0 && (
        <p role="status" className="rounded-lg border border-gray-200 bg-white p-4 text-sm text-gray-700">
          Este pedido todavía no tiene facturas. Se emiten automáticamente cuando el pago queda confirmado.
        </p>
      )}

      {facturas?.map((f) => <DetalleFactura key={f.id} factura={f} api={{ emitirNota: api.emitirNota }} />)}
    </section>
  );
}
