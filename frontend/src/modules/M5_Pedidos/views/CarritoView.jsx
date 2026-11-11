import { useCallback, useState } from 'react';
import { CreditCard, LoaderCircle, Lock, RefreshCw, ShoppingCart, Trash2 } from 'lucide-react';
import { Alerta, Boton, Encabezado, ModalConfirmacion, TarjetaComercio, Total } from '../components/componentes';
import AgregarProductoDemo from '../components/AgregarProductoDemo';
import { colones } from '../services/calculos';

// CU-001 Gestionar carrito de compras (InterfazCarrito): productos agrupados por comercio
// GET /api/m5/v1/carrito (grupos_comercio + resumen). Las acciones (cantidad, quitar, vaciar, agregar)
// llegan como props desde M5App y los montos se muestran tal como los devuelve la API.
export default function CarritoPage({
  carrito, cargando, error, onReintentar, aviso, avisosItem = {}, avisoDemo, pendientes,
  onCantidad, onQuitar, onVaciar, onAgregar, onContinuar,
}) {
  const [confirmandoVaciar, setConfirmandoVaciar] = useState(false);
  const vaciando = pendientes?.has('vaciar') ?? false;
  const cerrarModalVaciar = useCallback(() => setConfirmandoVaciar(false), []);

  const esModoDemo = typeof window !== 'undefined' && new URLSearchParams(window.location.search).has('demo');
  const demo = esModoDemo ? (
    <AgregarProductoDemo ocupado={pendientes?.has('agregar')} aviso={avisoDemo} onAgregar={onAgregar} />
  ) : null;

  if (cargando) {
    return (
      <>
        <Encabezado titulo="Mi carrito" />
        <main className="max-w-4xl mx-auto px-4 py-16 flex flex-col items-center gap-3 text-gray-500" aria-live="polite">
          <LoaderCircle className="w-8 h-8 animate-spin text-brandBlue" aria-hidden="true" />
          <p className="text-sm font-medium">Cargando su carrito…</p>
        </main>
      </>
    );
  }

  if (error) {
    return (
      <>
        <Encabezado titulo="Mi carrito" />
        <main className="max-w-xl mx-auto px-4 py-12 space-y-4">
          <Alerta tipo="error" titulo="No se pudo cargar el carrito">
            {error.mensaje || error.message}{error.codigo ? ` (${error.codigo})` : ''}
          </Alerta>
          {onReintentar && (
            <Boton variante="oscuro" icono={RefreshCw} onClick={onReintentar}>Reintentar</Boton>
          )}
        </main>
      </>
    );
  }

  // Se agrega a cada ítem el aviso de stock (409) que M5App guarda por id_item_carrito.
  const grupos = (carrito?.grupos_comercio ?? []).map((g) => ({
    ...g,
    items: g.items.map((i) => ({ ...i, aviso: avisosItem[i.id_item_carrito] ?? null })),
  }));
  const items = grupos.flatMap((g) => g.items);

  if (items.length === 0) {
    return (
      <>
        <Encabezado titulo="Mi carrito" />
        <main className="max-w-4xl mx-auto px-4 py-8 space-y-6">
          {aviso && <Alerta tipo={aviso.tipo} titulo={aviso.titulo}>{aviso.texto}</Alerta>}
          {demo}
          <div className="bg-white rounded-2xl shadow-xs border border-gray-200 p-8 sm:p-12 max-w-md mx-auto text-center space-y-4">
            <ShoppingCart className="w-16 h-16 stroke-1 text-gray-300 mx-auto" aria-hidden="true" />
            <div>
              <p className="text-xl font-bold text-gray-800">Su carrito está vacío</p>
              <p className="mt-1 text-sm text-gray-500">No ha agregado productos a su carrito de compras.</p>
            </div>
            <div>
              <a
                href="/app.html"
                className="inline-flex items-center justify-center gap-2 rounded-xl bg-brandDarkBlue hover:bg-brandBlue text-white font-semibold px-5 py-2.5 transition text-sm shadow-xs cursor-pointer"
              >
                Explorar productos en la tienda
              </a>
            </div>
          </div>
        </main>
      </>
    );
  }

  const resumen = carrito.resumen ?? {};
  const unidades = items.reduce((s, i) => s + i.cantidad, 0);
  const suspendidos = grupos.filter((g) => g.comercio_suspendido);
  const hayBloqueo = carrito.hay_comercio_suspendido ?? suspendidos.length > 0;
  const total = resumen.total ?? 0;

  async function confirmarVaciar() {
    await onVaciar();
    cerrarModalVaciar();
  }

  return (
    <>
      <Encabezado titulo="Mi carrito" paso={`${unidades} ${unidades === 1 ? 'producto' : 'productos'}`} />
      <main className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6 pb-36 lg:pb-12 space-y-6">
        {aviso && <Alerta tipo={aviso.tipo} titulo={aviso.titulo}>{aviso.texto}</Alerta>}
        {suspendidos.length > 0 && (
          <Alerta tipo="aviso" titulo="Comercio suspendido">
            {suspendidos.map((g) => g.nombre_comercio).join(', ')} tiene la membresía suspendida.
            Sus productos están deshabilitados: quítelos para continuar con el resto de su compra.
          </Alerta>
        )}

        {demo}

        <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
          {/* Columna izquierda: Subpedidos por comercio */}
          <div className="lg:col-span-7 xl:col-span-8 space-y-5">
            <div className="flex justify-end">
              <Boton variante="peligro" icono={Trash2} disabled={vaciando} onClick={() => setConfirmandoVaciar(true)}>
                Vaciar carrito
              </Boton>
            </div>
            {grupos.map((g, n) => (
              <TarjetaComercio
                key={g.id_comercio}
                grupo={g}
                numero={n + 1}
                editable
                suspendido={g.comercio_suspendido}
                pendientes={pendientes}
                onCantidad={onCantidad}
                onQuitar={onQuitar}
              />
            ))}
          </div>

          {/* Columna derecha: Resumen de compra en version escritorio (sticky) */}
          <div className="hidden lg:block lg:col-span-5 xl:col-span-4 lg:sticky lg:top-20 space-y-4">
            <div className="bg-white rounded-xl shadow-xs border border-gray-200 p-6 space-y-5">
              <h2 className="text-base font-bold text-gray-900 pb-3 border-b border-gray-100 flex items-center justify-between">
                <span>Resumen de compra</span>
                <span className="text-xs font-semibold text-brandDarkBlue bg-blue-50 px-2.5 py-0.5 rounded border border-blue-200">
                  {grupos.length} {grupos.length === 1 ? 'comercio' : 'comercios'}
                </span>
              </h2>

              <dl className="space-y-3 text-sm">
                <div className="flex justify-between text-gray-600">
                  <dt>Subtotal ({unidades} {unidades === 1 ? 'artículo' : 'artículos'})</dt>
                  <dd className="font-semibold text-gray-900">{colones(resumen.subtotal ?? 0)}</dd>
                </div>
                <div className="flex justify-between text-gray-600">
                  <dt>IVA (13 %)</dt>
                  <dd className="font-semibold text-gray-900">{colones(resumen.impuesto ?? 0)}</dd>
                </div>
                <div className="flex justify-between text-gray-600">
                  <dt>Envío</dt>
                  <dd className="font-semibold text-gray-900">{colones(resumen.costo_envio ?? 0)}</dd>
                </div>
                <div className="pt-4 border-t border-gray-100">
                  <Total etiqueta="Total" valor={total} />
                </div>
              </dl>

              <Boton
                icono={hayBloqueo ? Lock : CreditCard}
                disabled={hayBloqueo}
                onClick={onContinuar}
                className="w-full text-base py-3"
              >
                Proceder al pago
              </Boton>

              {hayBloqueo && (
                <p className="text-xs text-red-600 text-center font-medium">
                  Debe resolver los comercios o ítems suspendidos antes de continuar.
                </p>
              )}
            </div>
          </div>
        </div>
      </main>

      {/* Barra fija inferior para dispositivos moviles (< lg) */}
      <footer className="fixed bottom-0 inset-x-0 bg-white border-t border-gray-200 p-4 space-y-3 shadow-lg lg:hidden z-10">
        <div className="max-w-md mx-auto space-y-3">
          <Total etiqueta="Total" valor={total} />
          <Boton
            icono={hayBloqueo ? Lock : CreditCard}
            disabled={hayBloqueo}
            onClick={onContinuar}
            className="w-full"
          >
            Proceder al checkout
          </Boton>
        </div>
      </footer>

      <ModalConfirmacion
        abierto={confirmandoVaciar}
        titulo="¿Vaciar el carrito?"
        textoConfirmar="Vaciar carrito"
        ocupado={vaciando}
        onConfirmar={confirmarVaciar}
        onCancelar={cerrarModalVaciar}
      >
        Se quitarán los {unidades} {unidades === 1 ? 'producto' : 'productos'} del carrito y se liberarán sus reservas. Esta acción no se puede deshacer.
      </ModalConfirmacion>
    </>
  );
}
