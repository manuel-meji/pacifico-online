import { useState } from 'react';
import { ArrowRight, Check, LoaderCircle, MapPin, Tag, Truck } from 'lucide-react';
import { Alerta, Boton, Campo, clasesInput, Encabezado, TarjetaComercio, Total } from '../components/componentes';
import { agruparPorComercio, colones, totalGeneral, validarCheckout } from '../services/calculos';
import { metodosPago } from '../services/datosMock';

const Titulo = ({ icono: Icono, children }) => (
  <h2 className="text-sm font-bold text-gray-900 uppercase tracking-wider flex items-center gap-2 mb-3">
    <Icono className="w-4 h-4 text-brandBlue" aria-hidden="true" />{children}
  </h2>
);

// CU-004 Confirmar compra y generar pedido(s) (InterfazPedido), pasos 1, 2 y 9.
// onConfirmar(payload) llama a POST /api/m5/v1/pedidos (crearPedidoCheckout) y devuelve { ok } o { ok:false, ... }.
export default function CheckoutPage({ items, onVolver, onConfirmar }) {
  const [f, setF] = useState({ direccion: '', metodoPago: '', cupon: '', tarjetaRegalo: '', nota: '' });
  const [errores, setErrores] = useState({});
  const [enviando, setEnviando] = useState(false);
  const [cuponIngresado, setCuponIngresado] = useState('');
  const grupos = agruparPorComercio(items);
  const set = (k) => (e) => setF({ ...f, [k]: e.target.value });

  // El cupón se envía con el pedido (cupon_descuento); la validación y el descuento los hace el servidor.
  function aplicarCupon() {
    const codigo = f.cupon.trim();
    if (codigo.length > 50) { setErrores((e) => ({ ...e, cupon: 'El cupón no puede superar los 50 caracteres.' })); return; }
    setErrores((e) => ({ ...e, cupon: undefined }));
    setCuponIngresado(codigo);
  }

  async function enviar(e) {
    e.preventDefault();
    const errs = validarCheckout(f);
    setErrores(errs);
    if (Object.keys(errs).length) { document.getElementById(Object.keys(errs)[0])?.focus(); return; }
    setEnviando(true);
    await onConfirmar({ direccion: f.direccion, metodoPago: f.metodoPago, cupon_descuento: f.cupon, tarjeta_regalo: f.tarjetaRegalo, nota: f.nota });
    setEnviando(false);
  }

  const subtotalItems = grupos.reduce((s, g) => s + g.subtotal, 0);
  const impuestosTotal = grupos.reduce((s, g) => s + g.impuesto, 0);
  const enviosTotal = grupos.reduce((s, g) => s + g.costo_envio, 0);
  const total = totalGeneral(grupos);

  return (
    <>
      <Encabezado titulo="Checkout" onVolver={onVolver} />
      <main className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6 pb-36 lg:pb-12">
        <form onSubmit={enviar} noValidate className="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
          {/* Columna izquierda: Formularios de Envío/Pago, Opcional y Resumen por comercio */}
          <div className="lg:col-span-7 xl:col-span-8 space-y-6">
            <section className="bg-white rounded-xl shadow-xs border border-gray-200 p-5 space-y-4">
              <Titulo icono={Truck}>Envío y pago</Titulo>
              <Campo id="direccion" etiqueta="Dirección de envío" error={errores.direccion}>
                <div className="relative">
                  <MapPin className="w-4 h-4 text-gray-400 absolute left-3 top-3" aria-hidden="true" />
                  <textarea
                    id="direccion"
                    rows={3}
                    value={f.direccion}
                    onChange={set('direccion')}
                    aria-invalid={!!errores.direccion}
                    className={clasesInput(errores.direccion, true)}
                    placeholder="Provincia, cantón, distrito y señas exactas"
                  />
                </div>
              </Campo>
              <Campo id="metodoPago" etiqueta="Método de pago" error={errores.metodoPago}>
                <select id="metodoPago" value={f.metodoPago} onChange={set('metodoPago')} className={clasesInput(errores.metodoPago)}>
                  <option value="">Seleccione una opción</option>
                  {metodosPago.map((m) => <option key={m} value={m}>{m}</option>)}
                </select>
              </Campo>
            </section>

            <section className="bg-white rounded-xl shadow-xs border border-gray-200 p-5 space-y-4">
              <Titulo icono={Tag}>Opcional</Titulo>
              <Campo id="cupon" etiqueta="Cupón de descuento" error={errores.cupon}>
                <div className="flex gap-2">
                  <input
                    id="cupon"
                    value={f.cupon}
                    onChange={set('cupon')}
                    placeholder="Ej: PACIFICO10"
                    className={`${clasesInput(errores.cupon)} flex-1 uppercase`}
                  />
                  <Boton variante="oscuro" icono={Check} type="button" onClick={aplicarCupon}>Aplicar</Boton>
                </div>
              </Campo>
              {cuponIngresado && <Alerta tipo="info" titulo="Cupón ingresado">El código {cuponIngresado.toUpperCase()} se enviará al confirmar la compra.</Alerta>}
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <Campo id="tarjetaRegalo" etiqueta="Tarjeta de regalo" error={errores.tarjetaRegalo}>
                  <input
                    id="tarjetaRegalo"
                    value={f.tarjetaRegalo}
                    onChange={set('tarjetaRegalo')}
                    placeholder="Código de tarjeta"
                    className={clasesInput(errores.tarjetaRegalo)}
                  />
                </Campo>
                <Campo id="nota" etiqueta="Nota para el pedido">
                  <textarea
                    id="nota"
                    rows={2}
                    value={f.nota}
                    onChange={set('nota')}
                    placeholder="Instrucciones especiales de entrega"
                    className={clasesInput(false)}
                  />
                </Campo>
              </div>
            </section>

            <div className="space-y-4">
              <h2 className="text-sm font-bold text-gray-900 uppercase tracking-wider">Detalle por comercio</h2>
              {grupos.map((g, n) => <TarjetaComercio key={g.comercio.id_comercio} grupo={g} numero={n + 1} editable={false} />)}
            </div>
          </div>

          {/* Columna derecha: Resumen y botón en versión escritorio (sticky) */}
          <div className="hidden lg:block lg:col-span-5 xl:col-span-4 lg:sticky lg:top-20 space-y-4">
            <div className="bg-white rounded-xl shadow-xs border border-gray-200 p-6 space-y-5">
              <h2 className="text-base font-bold text-gray-900 pb-3 border-b border-gray-100 flex items-center justify-between">
                <span>Resumen a cobrar</span>
                <span className="text-xs font-semibold text-brandDarkBlue bg-blue-50 px-2.5 py-0.5 rounded border border-blue-200">
                  {grupos.length} {grupos.length === 1 ? 'comercio' : 'comercios'}
                </span>
              </h2>

              <dl className="space-y-3 text-sm">
                <div className="flex justify-between text-gray-600">
                  <dt>Subtotal productos</dt>
                  <dd className="font-semibold text-gray-900">{colones(subtotalItems)}</dd>
                </div>
                <div className="flex justify-between text-gray-600">
                  <dt>Impuestos</dt>
                  <dd className="font-semibold text-gray-900">{colones(impuestosTotal)}</dd>
                </div>
                <div className="flex justify-between text-gray-600">
                  <dt>Costos de envío</dt>
                  <dd className="font-semibold text-gray-900">{colones(enviosTotal)}</dd>
                </div>
                <div className="pt-4 border-t border-gray-100">
                  <Total etiqueta="Total a cobrar" valor={total} />
                </div>
              </dl>

              <Boton
                type="submit"
                disabled={enviando}
                icono={enviando ? LoaderCircle : ArrowRight}
                className="w-full text-base py-3"
              >
                {enviando ? 'Procesando…' : 'Continuar a la pasarela de pago'}
              </Boton>
            </div>
          </div>

          {/* Barra fija inferior para dispositivos móviles (< lg) */}
          <footer className="fixed bottom-0 inset-x-0 bg-white border-t border-gray-200 p-4 space-y-3 shadow-lg lg:hidden z-10">
            <div className="max-w-md mx-auto space-y-3">
              <Total etiqueta="Total a cobrar" valor={total} />
              <Boton
                type="submit"
                disabled={enviando}
                icono={enviando ? LoaderCircle : ArrowRight}
                className="w-full text-sm"
              >
                {enviando ? 'Procesando…' : 'Continuar a la pasarela de pago'}
              </Boton>
            </div>
          </footer>
        </form>
      </main>
    </>
  );
}
