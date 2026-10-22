import { useState } from 'react';
import { ArrowRight, Check, LoaderCircle, MapPin, Tag, Truck } from 'lucide-react';
import { Alerta, Boton, Campo, clasesInput, Encabezado, TarjetaComercio, Total } from './componentes';
import { agruparPorComercio, totalGeneral, validarCheckout } from './calculos';
import { metodosPago } from './datosMock';

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

  return (
    <>
      <Encabezado titulo="Checkout" onVolver={onVolver} />
      <form onSubmit={enviar} noValidate className="p-4 space-y-5 pb-44">
        <section className="bg-white rounded-xl shadow-xs border border-gray-200 p-5 space-y-4">
          <Titulo icono={Truck}>Envío y pago</Titulo>
          <Campo id="direccion" etiqueta="Dirección de envío" error={errores.direccion}>
            <div className="relative">
              <MapPin className="w-4 h-4 text-gray-400 absolute left-3 top-3" aria-hidden="true" />
              <textarea id="direccion" rows={3} value={f.direccion} onChange={set('direccion')} aria-invalid={!!errores.direccion} className={clasesInput(errores.direccion, true)} />
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
              <input id="cupon" value={f.cupon} onChange={set('cupon')} placeholder="Ej: PACIFICO10" className={`${clasesInput(errores.cupon)} flex-1 uppercase`} />
              <Boton variante="oscuro" icono={Check} type="button" onClick={aplicarCupon}>Aplicar</Boton>
            </div>
          </Campo>
          {cuponIngresado && <Alerta tipo="info" titulo="Cupón ingresado">El código {cuponIngresado.toUpperCase()} se enviará al confirmar la compra.</Alerta>}
          <Campo id="tarjetaRegalo" etiqueta="Tarjeta de regalo" error={errores.tarjetaRegalo}>
            <input id="tarjetaRegalo" value={f.tarjetaRegalo} onChange={set('tarjetaRegalo')} className={clasesInput(errores.tarjetaRegalo)} />
          </Campo>
          <Campo id="nota" etiqueta="Nota para el pedido">
            <textarea id="nota" rows={2} value={f.nota} onChange={set('nota')} className={clasesInput(false)} />
          </Campo>
        </section>

        <div className="space-y-3">
          <h2 className="text-sm font-bold text-gray-900 uppercase tracking-wider">Resumen por comercio</h2>
          {grupos.map((g, n) => <TarjetaComercio key={g.comercio.id_comercio} grupo={g} numero={n + 1} editable={false} />)}
        </div>

        <footer className="fixed bottom-0 inset-x-0 mx-auto max-w-md bg-white border-t border-gray-200 p-4 space-y-3">
          <Total etiqueta="Total a cobrar" valor={totalGeneral(grupos)} />
          <Boton type="submit" disabled={enviando} icono={enviando ? LoaderCircle : ArrowRight} className="w-full text-sm">
            {enviando ? 'Procesando…' : 'Continuar a la pasarela de pago'}
          </Boton>
        </footer>
      </form>
    </>
  );
}
