import { Download } from 'lucide-react';

/** CU-004. props.factura = schema Factura del contrato. */
export default function DetalleFactura({ factura }) {
  const descargar = () => {
    const url = URL.createObjectURL(new Blob([factura.xml], { type: 'application/xml' }));
    const a = Object.assign(document.createElement('a'), { href: url, download: `${factura.consecutivo}.xml` });
    a.click();
    URL.revokeObjectURL(url);
  };
  return (
    <section className="mx-auto max-w-xl space-y-3 rounded-xl border border-gray-200 bg-white p-5">
      <h2 className="text-lg font-bold text-brandDarkBlue">Factura {factura.consecutivo}</h2>
      <dl className="space-y-1 text-sm">
        <div className="flex justify-between"><dt>Subpedido</dt><dd>{factura.id_subpedido}</dd></div>
        <div className="flex justify-between"><dt>Estado ante Hacienda</dt><dd className="font-semibold">{factura.estado_hacienda}</dd></div>
        <div className="flex justify-between"><dt>Emitida</dt><dd>{new Date(factura.fecha_emision).toLocaleString('es-CR')}</dd></div>
      </dl>
      <button onClick={descargar} className="inline-flex items-center gap-2 rounded-lg bg-brandDarkBlue px-4 py-2 text-sm font-bold text-white">
        <Download className="h-4 w-4" aria-hidden /> Descargar XML
      </button>
    </section>
  );
}
