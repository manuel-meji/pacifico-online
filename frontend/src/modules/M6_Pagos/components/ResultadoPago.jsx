import { CheckCircle2, XCircle, Clock } from 'lucide-react';

const VISTAS = {
  confirmado: { Icono: CheckCircle2, color: 'text-green-700 bg-green-50', titulo: 'Pago confirmado', texto: 'Tu pedido pasó a estado Pagado.' },
  aprobado: { Icono: CheckCircle2, color: 'text-green-700 bg-green-50', titulo: 'Pago aprobado', texto: 'Esperando la confirmación final del proveedor.' },
  pendiente: { Icono: Clock, color: 'text-amber-700 bg-amber-50', titulo: 'Pago en proceso', texto: 'El proveedor aún no responde. Reintentaremos automáticamente.' },
  rechazado: { Icono: XCircle, color: 'text-red-700 bg-red-50', titulo: 'Pago rechazado', texto: 'Prueba con otro medio de pago.' },
};

/** CU-002 (vista del comprador). props.pago = schema Pago del contrato. */
export default function ResultadoPago({ pago, onOtroMedio, onVerFactura }) {
  const v = VISTAS[pago.estado] ?? VISTAS.pendiente;
  return (
    <section className="mx-auto max-w-md space-y-4 rounded-xl border border-gray-200 bg-white p-6 text-center">
      <div className={`mx-auto flex h-14 w-14 items-center justify-center rounded-full ${v.color}`}>
        <v.Icono className="h-8 w-8" aria-hidden />
      </div>
      <h2 className="text-xl font-bold text-brandDarkBlue">{v.titulo}</h2>
      <p className="text-sm text-gray-600">{v.texto}</p>
      <dl className="rounded-lg bg-brandLightBg p-3 text-left text-sm">
        <div className="flex justify-between"><dt>Pago</dt><dd className="font-mono">{pago.id}</dd></div>
        <div className="flex justify-between"><dt>Estado</dt><dd className="font-semibold">{pago.estado}</dd></div>
        <div className="flex justify-between"><dt>Monto</dt><dd>₡{Number(pago.monto_total).toLocaleString('es-CR')}</dd></div>
      </dl>
      {pago.estado === 'rechazado' && (
        <button onClick={onOtroMedio} className="w-full rounded-lg border border-gray-300 py-2 text-sm font-semibold">Elegir otro medio de pago</button>
      )}
      {pago.estado === 'confirmado' && (
        <button onClick={onVerFactura} className="w-full rounded-lg bg-brandDarkBlue py-2 text-sm font-bold text-white">Ver factura electrónica</button>
      )}
    </section>
  );
}
