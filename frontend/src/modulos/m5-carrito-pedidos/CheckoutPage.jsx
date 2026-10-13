import { useState } from 'react';
import { Campo, clasesInput, Encabezado, Linea, TarjetaComercio } from './componentes';
import { agruparPorComercio, totalGeneral, validarCheckout } from './calculos';
import { metodosPago } from './datosMock';

// CU-004 Confirmar compra y generar pedido(s) (InterfazPedido), pasos 1, 2 y 9.
// onConfirmar(payload) llama a POST /api/m5/v1/pedidos (crearPedidoCheckout) y devuelve { ok } o { ok:false, ... }.
export default function CheckoutPage({ items, onVolver, onConfirmar }) {
  const [f, setF] = useState({ direccion: '', metodoPago: '', cupon: '', tarjetaRegalo: '', nota: '' });
  const [errores, setErrores] = useState({});
  const [enviando, setEnviando] = useState(false);
  const grupos = agruparPorComercio(items);
  const set = (k) => (e) => setF({ ...f, [k]: e.target.value });

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
        <fieldset className="space-y-4">
          <legend className="text-base font-semibold text-slate-900 mb-2">Envío y pago</legend>
          <Campo id="direccion" etiqueta="Dirección de envío" error={errores.direccion}>
            <textarea id="direccion" rows={3} value={f.direccion} onChange={set('direccion')} aria-invalid={!!errores.direccion} className={clasesInput(errores.direccion)} />
          </Campo>
          <Campo id="metodoPago" etiqueta="Método de pago" error={errores.metodoPago}>
            <select id="metodoPago" value={f.metodoPago} onChange={set('metodoPago')} className={clasesInput(errores.metodoPago)}>
              <option value="">Seleccione una opción</option>
              {metodosPago.map((m) => <option key={m} value={m}>{m}</option>)}
            </select>
          </Campo>
        </fieldset>

        <fieldset className="space-y-4">
          <legend className="text-base font-semibold text-slate-900 mb-2">Opcional</legend>
          <Campo id="cupon" etiqueta="Cupón de descuento" error={errores.cupon}>
            <input id="cupon" value={f.cupon} onChange={set('cupon')} className={clasesInput(errores.cupon)} />
          </Campo>
          <Campo id="tarjetaRegalo" etiqueta="Tarjeta de regalo" error={errores.tarjetaRegalo}>
            <input id="tarjetaRegalo" value={f.tarjetaRegalo} onChange={set('tarjetaRegalo')} className={clasesInput(errores.tarjetaRegalo)} />
          </Campo>
          <Campo id="nota" etiqueta="Nota para el pedido">
            <textarea id="nota" rows={2} value={f.nota} onChange={set('nota')} className={clasesInput(false)} />
          </Campo>
        </fieldset>

        <div className="space-y-3">
          <h2 className="text-base font-semibold text-slate-900">Resumen por comercio</h2>
          {grupos.map((g) => <TarjetaComercio key={g.comercio.id_comercio} grupo={g} editable={false} />)}
        </div>

        <footer className="fixed bottom-0 inset-x-0 mx-auto max-w-md bg-white border-t border-slate-200 p-4 space-y-3">
          <dl><Linea etiqueta="Total a cobrar" valor={totalGeneral(grupos)} fuerte /></dl>
          <button type="submit" disabled={enviando} className="min-h-[48px] w-full rounded-lg bg-sky-700 font-semibold text-white disabled:bg-slate-400">
            {enviando ? 'Procesando…' : 'Continuar a la pasarela de pago'}
          </button>
        </footer>
      </form>
    </>
  );
}
