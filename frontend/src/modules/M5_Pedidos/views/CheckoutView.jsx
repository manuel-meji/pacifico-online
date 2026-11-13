import { useEffect, useRef, useState } from 'react';
import { ArrowRight, Check, CreditCard, LoaderCircle, MapPin, Plus, RefreshCw, Tag, Truck } from 'lucide-react';
import { Alerta, Boton, Campo, clasesInput, Encabezado, TarjetaComercio, Total } from '../components/componentes';
import { colones, validarCheckout } from '../services/calculos';
import { crearPedido, obtenerCarrito, obtenerDirecciones, validarCupon } from '../services/m5Api';

// Errores de ControladorPedido::procesarCheckout que obligan a volver al carrito.
const ERRORES_QUE_VUELVEN_AL_CARRITO = {
  'ERR-M5-001': { tipo: 'aviso', titulo: 'Carrito vacío', texto: 'Su carrito no tiene productos. Agregue productos antes de confirmar el pedido.' },
  'ERR-M5-002': { tipo: 'error', titulo: 'Stock agotado', texto: 'Uno o más productos ya no tienen existencias suficientes. Ajuste las cantidades para continuar.' },
  'ERR-003': { tipo: 'aviso', titulo: 'Comercio suspendido', texto: 'Uno de los comercios de su carrito no está disponible. Quite sus productos para continuar con el resto de su compra.' },
};

// Direcciones del cliente (las administra M1): vienen en carrito.direcciones_cliente;
// si el carrito no las trae, se piden a GET /api/m5/v1/carrito/direcciones.
async function cargarDirecciones(carrito) {
  if (Array.isArray(carrito?.direcciones_cliente) && carrito.direcciones_cliente.length > 0) {
    return carrito.direcciones_cliente;
  }
  try {
    const r = await obtenerDirecciones();
    return Array.isArray(r?.direcciones) ? r.direcciones : [];
  } catch {
    return []; // Sin direcciones se bloquea "Confirmar pedido" con un mensaje.
  }
}

const Titulo = ({ icono: Icono, children }) => (
  <h2 className="text-sm font-bold text-gray-900 uppercase tracking-wider flex items-center gap-2 mb-3">
    <Icono className="w-4 h-4 text-brandBlue" aria-hidden="true" />{children}
  </h2>
);

// CU-004 Confirmar compra y generar pedido(s) (InterfazPedido), pasos 1, 2 y 9.
// El resumen sale de GET /api/m5/v1/carrito y "Confirmar pedido" llama a POST /api/m5/v1/pedidos.
//   onPedidoCreado(respuesta, grupos) → respuesta 201 y grupos del carrito (para el nombre de cada comercio).
//   onVolverConAviso(aviso)           → regresa al carrito mostrando el error.
export default function CheckoutPage({ onVolver, onVolverConAviso, onPedidoCreado }) {
  const [carrito, setCarrito] = useState(null);
  const [cargando, setCargando] = useState(true);
  const [errorCarga, setErrorCarga] = useState(null);
  const [intentoCarga, setIntentoCarga] = useState(0);

  const [direcciones, setDirecciones] = useState([]);
  const [f, setF] = useState({ idDireccion: '', cupon: '', tarjetaRegalo: '', nota: '' });
  const [errores, setErrores] = useState({});
  const [cuponAplicado, setCuponAplicado] = useState(null);
  const [validandoCupon, setValidandoCupon] = useState(false);
  const [enviando, setEnviando] = useState(false);
  const [errorEnvio, setErrorEnvio] = useState(null);
  const [recalculandoEnvio, setRecalculandoEnvio] = useState(false);
  const enviandoRef = useRef(false); // Evita un segundo POST antes de que React deshabilite el botón.

  useEffect(() => {
    let vigente = true;
    setCargando(true);
    setErrorCarga(null);
    (async () => {
      try {
        const c = await obtenerCarrito();
        const dirs = await cargarDirecciones(c);
        if (!vigente) return;
        setDirecciones(dirs);
        const preseleccionada = dirs.find((d) => d.es_principal === true) ?? dirs[0];
        const idInicial = preseleccionada ? String(preseleccionada.id_direccion_cliente) : '';
        setF((actual) => ({ ...actual, idDireccion: idInicial }));

        if (idInicial) {
          try {
            const cConDir = await obtenerCarrito(undefined, { id_direccion_cliente: idInicial });
            if (vigente) setCarrito(cConDir);
          } catch {
            if (vigente) setCarrito(c);
          }
        } else {
          setCarrito(c);
        }
      } catch (e) {
        if (vigente) setErrorCarga(e);
      } finally {
        if (vigente) setCargando(false);
      }
    })();
    return () => { vigente = false; };
  }, [intentoCarga]);

  const cambiarDireccion = async (e) => {
    const nuevaDirId = e.target.value;
    setF((actual) => ({ ...actual, idDireccion: nuevaDirId }));
    setErrores((prev) => ({ ...prev, idDireccion: undefined }));

    if (!nuevaDirId) return;

    setRecalculandoEnvio(true);
    try {
      const c = await obtenerCarrito(undefined, { id_direccion_cliente: nuevaDirId });
      setCarrito(c);
    } catch (err) {
      console.error('Error al recalcular tarifas de envío por dirección:', err);
    } finally {
      setRecalculandoEnvio(false);
    }
  };

  const set = (k) => (e) => {
    const valor = e.target.value;
    setF({ ...f, [k]: valor });
    if (k === 'cupon') {
      setCuponAplicado(null);
      setErrores((prev) => ({ ...prev, cupon: undefined }));
    }
  };

  async function aplicarCupon() {
    const codigo = f.cupon.trim();
    if (!codigo) {
      setCuponAplicado(null);
      setErrores((e) => ({ ...e, cupon: 'Ingrese un código de cupón.' }));
      return;
    }
    if (codigo.length > 50) {
      setErrores((e) => ({ ...e, cupon: 'El cupón no puede superar los 50 caracteres.' }));
      return;
    }

    setValidandoCupon(true);
    setErrores((e) => ({ ...e, cupon: undefined }));
    try {
      const resultado = await validarCupon({
        codigo,
        subtotal: Number(carrito?.resumen?.subtotal ?? 0),
        ids_comercios: (carrito?.grupos_comercio ?? []).map((g) => Number(g.id_comercio)),
        subtotales_por_comercio: (carrito?.grupos_comercio ?? []).map((g) => ({
          id_comercio: Number(g.id_comercio),
          subtotal: Number(g.subtotal ?? 0),
        })),
      });

      if (!resultado?.valido) {
        setCuponAplicado(null);
        setErrores((e) => ({ ...e, cupon: resultado?.mensaje || 'El cupón no aplica para este pedido.' }));
        return;
      }

      setCuponAplicado({
        codigo: codigo.toUpperCase(),
        monto_descuento: Number(resultado.monto_descuento ?? 0),
        mensaje: resultado.mensaje || 'Cupón aplicado.',
      });
    } catch (err) {
      setCuponAplicado(null);
      setErrores((e) => ({ ...e, cupon: err.mensaje || err.message }));
    } finally {
      setValidandoCupon(false);
    }
  }

  async function enviar(e) {
    e.preventDefault();
    if (enviandoRef.current || direcciones.length === 0) return;
    const errs = validarCheckout(f);
    setErrores(errs);
    if (Object.keys(errs).length) { document.getElementById(Object.keys(errs)[0])?.focus(); return; }

    enviandoRef.current = true;
    setEnviando(true);
    setErrorEnvio(null);
    try {
      const respuesta = await crearPedido({
        id_direccion_cliente: Number(f.idDireccion),
        cupon_descuento: cuponAplicado?.codigo || f.cupon.trim() || null,
        tarjeta_regalo: f.tarjetaRegalo.trim() || null,
        nota: f.nota.trim() || null,
      });
      // La respuesta 201 no trae direccion_envio_texto; se completa con la dirección elegida.
      const elegida = direcciones.find((d) => String(d.id_direccion_cliente) === f.idDireccion);
      onPedidoCreado(
        {
          ...respuesta,
          direccion_envio_texto: respuesta.direccion_envio_texto ?? elegida?.texto_completo ?? null,
        },
        carrito?.grupos_comercio ?? [],
      );
    } catch (err) {
      const aviso = ERRORES_QUE_VUELVEN_AL_CARRITO[err.codigo];
      if (aviso) {
        onVolverConAviso({ ...aviso, texto: err.mensaje ? `${aviso.texto} Detalle: ${err.mensaje}.` : aviso.texto });
        return;
      }
      if (err.codigo === 'ERR-M5-CUPON') {
        setCuponAplicado(null);
        setErrores((prev) => ({ ...prev, cupon: err.mensaje || err.message }));
      }
      setErrorEnvio(err);
    } finally {
      enviandoRef.current = false;
      setEnviando(false);
    }
  }

  if (cargando) {
    return (
      <>
        <Encabezado titulo="Checkout" onVolver={onVolver} />
        <main className="max-w-4xl mx-auto px-4 py-16 flex flex-col items-center gap-3 text-gray-500" aria-live="polite">
          <LoaderCircle className="w-8 h-8 animate-spin text-brandBlue" aria-hidden="true" />
          <p className="text-sm font-medium">Cargando el resumen de su compra…</p>
        </main>
      </>
    );
  }

  if (errorCarga) {
    return (
      <>
        <Encabezado titulo="Checkout" onVolver={onVolver} />
        <main className="max-w-xl mx-auto px-4 py-12 space-y-4">
          <Alerta tipo="error" titulo="No se pudo cargar el carrito">
            {errorCarga.mensaje || errorCarga.message}{errorCarga.codigo ? ` (${errorCarga.codigo})` : ''}
          </Alerta>
          <Boton variante="oscuro" icono={RefreshCw} onClick={() => setIntentoCarga((n) => n + 1)}>Reintentar</Boton>
        </main>
      </>
    );
  }

  const grupos = carrito?.grupos_comercio ?? [];
  const resumen = carrito?.resumen ?? {};
  const descuento = cuponAplicado?.monto_descuento ?? 0;
  const total = Math.max(0, Number(resumen.total ?? 0) - descuento);
  const hayBloqueo = carrito?.hay_comercio_suspendido ?? grupos.some((g) => g.comercio_suspendido);
  const sinDirecciones = direcciones.length === 0;

  if (grupos.length === 0) {
    return (
      <>
        <Encabezado titulo="Checkout" onVolver={onVolver} />
        <main className="max-w-xl mx-auto px-4 py-12 space-y-4">
          <Alerta tipo="aviso" titulo="Carrito vacío">Agregue productos a su carrito antes de confirmar el pedido.</Alerta>
          <Boton variante="oscuro" onClick={onVolver}>Volver al carrito</Boton>
        </main>
      </>
    );
  }

  const botonConfirmar = (clases) => (
    <Boton
      type="submit"
      disabled={enviando || hayBloqueo || sinDirecciones || recalculandoEnvio}
      icono={enviando || recalculandoEnvio ? LoaderCircle : ArrowRight}
      className={clases}
    >
      {enviando ? 'Procesando…' : recalculandoEnvio ? 'Actualizando tarifas…' : 'Confirmar pedido'}
    </Boton>
  );

  return (
    <>
      <Encabezado titulo="Checkout" onVolver={onVolver} />
      <main className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6 pb-36 lg:pb-12 space-y-6">
        {errorEnvio && (
          <Alerta tipo="error" titulo="No se pudo crear el pedido">
            {errorEnvio.mensaje || errorEnvio.message}{errorEnvio.codigo ? ` (${errorEnvio.codigo})` : ''}
          </Alerta>
        )}
        {hayBloqueo && (
          <Alerta tipo="aviso" titulo="Comercio suspendido">
            Hay productos de un comercio suspendido. Vuelva al carrito y quítelos para continuar.
          </Alerta>
        )}

        <form onSubmit={enviar} noValidate className="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
          {/* Columna izquierda: Formularios de Envío/Pago, Opcional y Resumen por comercio */}
          <div className="lg:col-span-7 xl:col-span-8 space-y-6">
            <section className="bg-white rounded-xl shadow-xs border border-gray-200 p-5 space-y-4">
              <div className="flex items-center justify-between gap-3 flex-wrap">
                <Titulo icono={Truck}>Dirección de entrega</Titulo>
                <a
                  href="/app.html?pantalla=perfil&motivo=checkout_dir"
                  className="text-xs font-semibold text-brandDarkBlue hover:text-brandBlue inline-flex items-center gap-1.5 transition cursor-pointer"
                >
                  <Plus className="w-3.5 h-3.5 text-brandGreen" aria-hidden="true" />
                  <span>Gestionar direcciones</span>
                </a>
              </div>
              <Campo id="idDireccion" etiqueta="Dirección de envío" error={errores.idDireccion}>
                <div className="relative">
                  <MapPin className="w-4 h-4 text-gray-400 absolute left-3 top-3" aria-hidden="true" />
                  <select
                    id="idDireccion"
                    value={f.idDireccion}
                    onChange={cambiarDireccion}
                    disabled={sinDirecciones || recalculandoEnvio}
                    aria-invalid={!!errores.idDireccion}
                    aria-describedby="idDireccion-ayuda"
                    className={clasesInput(errores.idDireccion, true)}
                  >
                    {sinDirecciones && <option value="">Sin direcciones registradas</option>}
                    {direcciones.map((d) => (
                      <option key={d.id_direccion_cliente} value={String(d.id_direccion_cliente)}>
                        {d.texto_completo ?? `Dirección #${d.id_direccion_cliente}`}
                      </option>
                    ))}
                  </select>
                </div>
              </Campo>

              <div className="flex items-center justify-between gap-2 pt-1 flex-wrap">
                <p id="idDireccion-ayuda" className="text-xs text-gray-500">
                  Seleccione dónde desea recibir su pedido.
                </p>
                <a
                  href="/app.html?pantalla=perfil&motivo=checkout_dir"
                  className="text-xs font-bold text-brandBlue hover:text-brandDarkBlue inline-flex items-center gap-1 transition hover:underline"
                >
                  + Agregar nueva dirección
                </a>
              </div>

              {sinDirecciones && (
                <div className="rounded-xl border border-amber-200 bg-amber-50 p-4 space-y-3">
                  <Alerta tipo="aviso" titulo="Sin dirección de envío">
                    No tiene direcciones registradas en su cuenta. Debe agregar al menos una dirección para poder confirmar el pedido.
                  </Alerta>
                  <div className="pt-1">
                    <a
                      href="/app.html?pantalla=perfil&motivo=checkout_dir"
                      className="inline-flex items-center gap-2 rounded-lg bg-brandDarkBlue hover:bg-brandBlue text-white text-xs font-bold py-2 px-4 shadow-xs transition"
                    >
                      <Plus className="w-4 h-4" /> Agregar dirección ahora
                    </a>
                  </div>
                </div>
              )}
            </section>

            <section className="bg-white rounded-xl shadow-xs border border-gray-200 p-5 space-y-4">
              <Titulo icono={Tag}>Opcional</Titulo>
              <Campo id="cupon" etiqueta="Cupón de descuento" error={errores.cupon}>
                <div className="flex gap-2">
                  <input
                    id="cupon"
                    value={f.cupon}
                    onChange={set('cupon')}
                    maxLength={50}
                    placeholder="Ej: PACIFICO10"
                    className={`${clasesInput(errores.cupon)} flex-1 uppercase`}
                  />
                  <Boton
                    variante="oscuro"
                    icono={validandoCupon ? LoaderCircle : Check}
                    type="button"
                    disabled={validandoCupon}
                    onClick={aplicarCupon}
                  >
                    {validandoCupon ? 'Validando…' : 'Aplicar'}
                  </Boton>
                </div>
              </Campo>
              {cuponAplicado && (
                <Alerta tipo="exito" titulo={`Cupón ${cuponAplicado.codigo} aplicado`}>
                  {cuponAplicado.mensaje} (-{colones(cuponAplicado.monto_descuento)})
                </Alerta>
              )}
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <Campo id="tarjetaRegalo" etiqueta="Tarjeta de regalo" error={errores.tarjetaRegalo}>
                  <input
                    id="tarjetaRegalo"
                    value={f.tarjetaRegalo}
                    onChange={set('tarjetaRegalo')}
                    maxLength={50}
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
              {grupos.map((g, n) => (
                <TarjetaComercio key={g.id_comercio} grupo={g} numero={n + 1} editable={false} suspendido={g.comercio_suspendido} />
              ))}
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
                  <dd className="font-semibold text-gray-900">{colones(resumen.subtotal ?? 0)}</dd>
                </div>
                <div className="flex justify-between text-gray-600">
                  <dt>IVA (13 %)</dt>
                  <dd className="font-semibold text-gray-900">{colones(resumen.impuesto ?? 0)}</dd>
                </div>
                <div className="flex justify-between text-gray-600">
                  <dt className="flex items-center gap-1.5">
                    <span>Envío</span>
                    {recalculandoEnvio && <LoaderCircle className="w-3 h-3 animate-spin text-brandBlue" />}
                  </dt>
                  <dd className="font-semibold text-gray-900">
                    {recalculandoEnvio ? 'Recalculando…' : colones(resumen.costo_envio ?? 0)}
                  </dd>
                </div>
                {descuento > 0 && (
                  <div className="flex justify-between text-brandGreen">
                    <dt>Descuento ({cuponAplicado.codigo})</dt>
                    <dd className="font-semibold">-{colones(descuento)}</dd>
                  </div>
                )}
                <div className="pt-4 border-t border-gray-100">
                  <Total etiqueta="Total a cobrar" valor={total} />
                </div>
              </dl>

              {botonConfirmar('w-full text-base py-3')}
            </div>
          </div>

          {/* Barra fija inferior para dispositivos móviles (< lg) */}
          <footer className="fixed bottom-0 inset-x-0 bg-white border-t border-gray-200 p-4 space-y-3 shadow-lg lg:hidden z-10">
            <div className="max-w-md mx-auto space-y-3">
              <Total etiqueta="Total a cobrar" valor={total} />
              {botonConfirmar('w-full text-sm')}
            </div>
          </footer>
        </form>
      </main>
    </>
  );
}
