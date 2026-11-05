import { useCallback, useEffect, useRef, useState } from 'react';
import { actualizarCantidad, agregarItem, eliminarItem, obtenerCarrito, vaciarCarrito } from './services/m5Api';
import CarritoPage from './views/CarritoView';
import CheckoutPage from './views/CheckoutView';
import PedidoPendientePage from './views/PedidoPendienteView';

const textoError = (e) => `${e.mensaje || e.message}${e.codigo ? ` (${e.codigo})` : ''}`;
const maximoDisponible = (e) => e.detalles?.existencia_disponible ?? 0;

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

  useEffect(() => { cargarCarrito(); }, [cargarCarrito]);

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

  const [pantalla, setPantalla] = useState('carrito');
  const [pedidoCreado, setPedidoCreado] = useState(null); // { pedido: respuesta 201, grupos: grupos_comercio }
  const [aviso, setAviso] = useState(null);

  // Vuelve al carrito y lo recarga, porque el checkout pudo haber cambiado su estado (reservas, stock, pedido).
  function volverAlCarrito(nuevoAviso = null) {
    setAviso(nuevoAviso);
    setPantalla('carrito');
    cargarCarrito();
  }

  function mostrarPedido(pedido, grupos) {
    setPedidoCreado({ pedido, grupos });
    setPantalla('pedido');
  }

  return (
    <div className="min-h-screen bg-brandLightBg text-gray-800 font-sans antialiased flex flex-col">
      <div className="flex-1 w-full">
        {pantalla === 'carrito' && (
          <CarritoPage carrito={carrito} cargando={cargando} error={error} onReintentar={cargarCarrito} aviso={aviso}
            avisosItem={avisosItem} avisoDemo={avisoDemo} pendientes={pendientes}
            onCantidad={cambiarCantidad} onQuitar={quitar} onVaciar={vaciar} onAgregar={agregar}
            onContinuar={() => { setAviso(null); setPantalla('checkout'); }} />
        )}
        {pantalla === 'checkout' && (
          <CheckoutPage onVolver={() => volverAlCarrito()} onVolverConAviso={volverAlCarrito} onPedidoCreado={mostrarPedido} />
        )}
        {pantalla === 'pedido' && pedidoCreado && (
          <PedidoPendientePage pedido={pedidoCreado.pedido} grupos={pedidoCreado.grupos} onSeguirComprando={() => volverAlCarrito()} />
        )}
      </div>
    </div>
  );
}