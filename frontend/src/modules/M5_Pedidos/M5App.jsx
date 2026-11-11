import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { ModalLoginRequerido, NavegacionM5 } from './components/componentes';
import { actualizarCantidad, agregarItem, eliminarItem, obtenerCarrito, vaciarCarrito } from './services/m5Api';
import { obtenerUsuario } from '../Nucleo/services/sesionApi';
import CarritoPage from './views/CarritoView';
import CheckoutPage from './views/CheckoutView';
import PedidoPendientePage from './views/PedidoPendienteView';
import MisPedidosView from './views/MisPedidosView';
import DetallePedidoView from './views/DetallePedidoView';
import PanelComercioView from './views/PanelComercioView';
import ListaDeseosView from './views/ListaDeseosView';

const textoError = (e) => `${e.mensaje || e.message}${e.codigo ? ` (${e.codigo})` : ''}`;
const maximoDisponible = (e) => e.detalles?.existencia_disponible ?? 0;

// ---------- Agregar al carrito desde un enlace (p. ej. el portal de M1) ----------
// ?agregar=<id_variacion>&cantidad=<n>  (cantidad por defecto 1)
const PARAM_VARIACION = 'agregar';
const PARAM_CANTIDAD = 'cantidad';

// Devuelve null si no hay ?agregar=, { invalido: true } si los valores no son enteros >= 1,
// o { idVariacion, cantidad }.
function leerAgregarDeUrl() {
  const params = new URLSearchParams(window.location.search);
  if (!params.has(PARAM_VARIACION)) return null;
  const idVariacion = Number(params.get(PARAM_VARIACION));
  const cantidad = params.has(PARAM_CANTIDAD) ? Number(params.get(PARAM_CANTIDAD)) : 1;
  const valido = (n) => Number.isInteger(n) && n >= 1;
  if (!valido(idVariacion) || !valido(cantidad)) return { invalido: true };
  return { idVariacion, cantidad };
}

// Quita solo ?agregar y ?cantidad, conservando el resto de la URL, para que recargar no vuelva a agregar.
function limpiarAgregarDeUrl() {
  const url = new URL(window.location.href);
  url.searchParams.delete(PARAM_VARIACION);
  url.searchParams.delete(PARAM_CANTIDAD);
  window.history.replaceState(window.history.state, '', `${url.pathname}${url.search}${url.hash}`);
}

function avisoErrorAgregar(e) {
  if (e.status === 409 && e.detalles?.existencia_disponible !== undefined) {
    return {
      tipo: 'error',
      titulo: 'Stock insuficiente',
      texto: `No se pudo agregar el producto. Cantidad máxima disponible: ${maximoDisponible(e)}.`,
    };
  }
  if (e.codigo === 'ERR-003' || /suspend/i.test(e.mensaje ?? '')) {
    return { tipo: 'error', titulo: 'Comercio suspendido', texto: `El comercio de este producto no está disponible. ${textoError(e)}` };
  }
  if (e.status === 422 || e.status === 404) {
    return { tipo: 'error', titulo: 'Producto no disponible', texto: `La variación solicitada no existe o no está a la venta. ${textoError(e)}` };
  }
  return { tipo: 'error', titulo: 'No se pudo agregar el producto', texto: textoError(e) };
}

// Punto de entrada del modulo M5: carrito → checkout → pedido pendiente de pago, todo contra /api/m5/v1.
// Las alternativas del CU-004 (stock agotado, comercio suspendido) llegan ahora como errores reales de la API.
export default function M5App() {
  // Carrito real: GET /api/m5/v1/carrito (id_cliente=1 por defecto).
  const [carrito, setCarrito] = useState(null);
  const [cargando, setCargando] = useState(true);
  const [error, setError] = useState(null);

  const cargarCarrito = useCallback(async () => {
    setCargando(true);
    setError(null);
    try {
      setCarrito(await obtenerCarrito());
    } catch (e) {
      setError(e);
    } finally {
      setCargando(false);
    }
  }, []);


  // ---------- Acciones del carrito (la pantalla se actualiza con la respuesta de la API) ----------
  // Claves en curso: 'cantidad-<id>', 'quitar-<id>', 'vaciar', 'agregar'. El ref evita el doble clic
  // antes de que React vuelva a pintar; el estado deshabilita el botón correspondiente.
  const enCurso = useRef(new Set());
  const [pendientes, setPendientes] = useState(() => new Set());
  const [avisosItem, setAvisosItem] = useState({});
  const [avisoDemo, setAvisoDemo] = useState(null);

  const marcarPendiente = (clave, activo) => setPendientes((actual) => {
    const nuevo = new Set(actual);
    if (activo) nuevo.add(clave); else nuevo.delete(clave);
    return nuevo;
  });

  async function ejecutar(clave, accion) {
    if (enCurso.current.has(clave)) return null;
    enCurso.current.add(clave);
    marcarPendiente(clave, true);
    try {
      return await accion();
    } finally {
      enCurso.current.delete(clave);
      marcarPendiente(clave, false);
    }
  }

  const fijarAvisoItem = (id, texto) => setAvisosItem((a) => {
    const nuevo = { ...a };
    if (texto) nuevo[id] = texto; else delete nuevo[id];
    return nuevo;
  });

  async function cambiarCantidad(id, cantidad) {
    if (cantidad < 1) return; // La cantidad mínima es 1: no se envía la petición.
    setAviso(null);
    try {
      const respuesta = await ejecutar(`cantidad-${id}`, () => actualizarCantidad(id, cantidad));
      if (!respuesta) return;
      setCarrito(respuesta);
      fijarAvisoItem(id, null);
    } catch (e) {
      if (e.status === 409) {
        fijarAvisoItem(id, `Stock insuficiente. Cantidad máxima disponible: ${maximoDisponible(e)}.`);
      } else {
        setAviso({ tipo: 'error', titulo: 'No se pudo actualizar la cantidad', texto: textoError(e) });
      }
    }
  }

  async function quitar(id) {
    setAviso(null);
    try {
      const respuesta = await ejecutar(`quitar-${id}`, () => eliminarItem(id));
      if (!respuesta) return;
      setCarrito(respuesta);
      fijarAvisoItem(id, null);
    } catch (e) {
      setAviso({ tipo: 'error', titulo: 'No se pudo quitar el producto', texto: textoError(e) });
    }
  }

  // DELETE /carrito solo devuelve { mensaje }, por eso se vuelve a pedir el carrito a la API.
  async function vaciar() {
    setAviso(null);
    try {
      const respuesta = await ejecutar('vaciar', async () => {
        await vaciarCarrito();
        return obtenerCarrito();
      });
      if (!respuesta) return;
      setCarrito(respuesta);
      setAvisosItem({});
    } catch (e) {
      setAviso({ tipo: 'error', titulo: 'No se pudo vaciar el carrito', texto: textoError(e) });
    }
  }

  async function agregar(idVariacion, cantidad) {
    setAviso(null);
    setAvisoDemo(null);
    try {
      const respuesta = await ejecutar('agregar', () => agregarItem({ id_variacion: idVariacion, cantidad }));
      if (!respuesta) return;
      setCarrito(respuesta);
      setAvisoDemo({ tipo: 'exito', titulo: 'Producto agregado', texto: respuesta.mensaje || 'El carrito se actualizó.' });
    } catch (e) {
      if (e.status === 409) {
        setAvisoDemo({
          tipo: 'error',
          titulo: 'Stock insuficiente',
          texto: `Cantidad máxima disponible: ${maximoDisponible(e)} (solicitó ${e.detalles?.cantidad_solicitada ?? cantidad} en total).`,
        });
      } else {
        setAvisoDemo({ tipo: 'error', titulo: 'No se pudo agregar el producto', texto: textoError(e) });
      }
    }
  }

  const [pantalla, setPantalla] = useState(() => {
    if (typeof window === 'undefined') return 'carrito';
    const p = new URLSearchParams(window.location.search).get('pantalla');
    if (p === 'comercio' || p === 'panel-comercio') return 'panel-comercio';
    if (p === 'mis-pedidos') return 'mis-pedidos';
    if (p === 'deseos') return 'deseos';
    return 'carrito';
  });
  const [pedidoCreado, setPedidoCreado] = useState(null); // { pedido: respuesta 201, grupos: grupos_comercio }
  const [aviso, setAviso] = useState(null);

  // Carga inicial. Si la URL trae ?agregar=, primero agrega el producto y muestra el carrito con el aviso.
  // El ref impide que el doble montaje de React (StrictMode en desarrollo) agregue el producto dos veces.
  const cargaInicialHecha = useRef(false);
  useEffect(() => {
    if (cargaInicialHecha.current) return;
    cargaInicialHecha.current = true;

    const solicitud = leerAgregarDeUrl();
    if (!solicitud) { cargarCarrito(); return; }

    limpiarAgregarDeUrl();
    setPantalla('carrito');
    if (solicitud.invalido) {
      setAviso({ tipo: 'error', titulo: 'Enlace no válido', texto: 'El enlace para agregar al carrito no indica una variación y cantidad válidas.' });
      cargarCarrito();
      return;
    }

    (async () => {
      setCargando(true);
      setError(null);
      try {
        const respuesta = await agregarItem({ id_variacion: solicitud.idVariacion, cantidad: solicitud.cantidad });
        setCarrito(respuesta);
        setAviso({
          tipo: 'exito',
          titulo: 'Producto agregado al carrito',
          texto: `Se ${solicitud.cantidad === 1 ? 'agregó 1 unidad' : `agregaron ${solicitud.cantidad} unidades`} al carrito.`,
        });
        setCargando(false);
      } catch (e) {
        setAviso(avisoErrorAgregar(e));
        await cargarCarrito(); // Muestra el carrito tal como está, con el aviso de error.
      }
    })();
  }, [cargarCarrito]);

  // Vuelve al carrito y lo recarga, porque el checkout pudo haber cambiado su estado (reservas, stock, pedido).
  function volverAlCarrito(nuevoAviso = null) {
    setAviso(nuevoAviso);
    setPantalla('carrito');
    cargarCarrito();
  }

  // Mis pedidos → detalle: "Ver detalle" guarda el id_pedido y abre DetallePedidoView.
  const [idPedidoSeleccionado, setIdPedidoSeleccionado] = useState(null);
  function verDetalle(idPedido) {
    setIdPedidoSeleccionado(idPedido);
    setPantalla('detalle');
  }
  const navegacion = useMemo(() => ({
    abrirMisPedidos: () => { setAviso(null); setPantalla('mis-pedidos'); },
    enMisPedidos: pantalla === 'mis-pedidos',
    abrirDeseos: () => { setAviso(null); setPantalla('deseos'); },
    enDeseos: pantalla === 'deseos',
    abrirPanelComercio: () => { setAviso(null); setPantalla('panel-comercio'); },
    enPanelComercio: pantalla === 'panel-comercio',
  }), [pantalla]);

  function mostrarPedido(pedido, grupos) {
    setPedidoCreado({ pedido, grupos });
    setPantalla('pedido');
  }

  const [modalLogin, setModalLogin] = useState(false);

  // Si el usuario es invitado (sin sesión iniciada), no se le permite ir a checkout:
  // se muestra un modal amigable explicando el motivo y ofreciendo ingresar o registrarse
  // sin vaciar su carrito.
  async function procederACheckout() {
    setAviso(null);
    try {
      const resp = await obtenerUsuario();
      if (!resp.ok || !resp.datos?.user) {
        setModalLogin(true);
        return;
      }
    } catch {
      setModalLogin(true);
      return;
    }
    setPantalla('checkout');
  }

  return (
    <NavegacionM5.Provider value={navegacion}>
    <div className="min-h-screen bg-brandLightBg text-gray-800 font-sans antialiased flex flex-col">
      <div className="flex-1 w-full">
        {pantalla === 'carrito' && (
          <CarritoPage carrito={carrito} cargando={cargando} error={error} onReintentar={cargarCarrito} aviso={aviso}
            avisosItem={avisosItem} avisoDemo={avisoDemo} pendientes={pendientes}
            onCantidad={cambiarCantidad} onQuitar={quitar} onVaciar={vaciar} onAgregar={agregar}
            onContinuar={procederACheckout} />
        )}
        {pantalla === 'checkout' && (
          <CheckoutPage onVolver={() => volverAlCarrito()} onVolverConAviso={volverAlCarrito} onPedidoCreado={mostrarPedido} />
        )}
        {pantalla === 'pedido' && pedidoCreado && (
          <PedidoPendientePage pedido={pedidoCreado.pedido} grupos={pedidoCreado.grupos}
            onSeguirComprando={() => volverAlCarrito()} onVerMisPedidos={navegacion.abrirMisPedidos} />
        )}
        {pantalla === 'mis-pedidos' && (
          <MisPedidosView idSeleccionado={idPedidoSeleccionado} onVerDetalle={verDetalle} onVolver={() => volverAlCarrito()} />
        )}
        {pantalla === 'detalle' && idPedidoSeleccionado != null && (
          <DetallePedidoView idPedido={idPedidoSeleccionado} onVolver={navegacion.abrirMisPedidos} />
        )}
        {pantalla === 'deseos' && (
          <ListaDeseosView onVolver={() => volverAlCarrito()} onProductoMovido={() => cargarCarrito()} />
        )}
        {pantalla === 'panel-comercio' && <PanelComercioView onVolver={() => volverAlCarrito()} />}
      </div>

      <ModalLoginRequerido abierto={modalLogin} onCerrar={() => setModalLogin(false)} />
    </div>
    </NavegacionM5.Provider>
  );
}