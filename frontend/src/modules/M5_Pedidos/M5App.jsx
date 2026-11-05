import { useCallback, useEffect, useRef, useState } from 'react';
// datosMock solo alimenta el checkout y la confirmación simulada; el carrito ya viene de la API.
import { carritoInicial, comercios } from './services/datosMock';
import { actualizarCantidad, agregarItem, eliminarItem, obtenerCarrito, vaciarCarrito } from './services/m5Api';
import { agruparPorComercio, totalGeneral } from './services/calculos';
import CarritoPage from './views/CarritoView';
import CheckoutPage from './views/CheckoutView';
import PedidoPendientePage from './views/PedidoPendienteView';

const textoError = (e) => `${e.mensaje || e.message}${e.codigo ? ` (${e.codigo})` : ''}`;
const maximoDisponible = (e) => e.detalles?.existencia_disponible ?? 0;

// Punto de entrada del modulo M5. `simular` fuerza las alternativas del CU-004 durante la validacion:
//   'stock'    → M4 no puede reservar (alternativa al paso 4, 409 STOCK_INSUFICIENTE)
//   'comercio' → un comercio esta suspendido en M2 (alternativa al paso 5)
export default function M5App({ simular = null }) {
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

  // Items simulados del checkout
  const [items, setItems] = useState(carritoInicial.items);
  const [pantalla, setPantalla] = useState('carrito');
  const [pedido, setPedido] = useState(null);
  const [suspendidos, setSuspendidos] = useState([]);
  const [aviso, setAviso] = useState(null);

  // Doble de POST /api/m5/v1/pedidos. Se sustituye por fetch cuando el modulo esté desplegado.
  async function confirmar() {
    await new Promise((r) => setTimeout(r, 600));
    if (simular === 'stock') {
      const agotado = items[0];
      setItems((xs) => xs.map((i) => (i === agotado ? { ...i, aviso: 'Este producto se agotó. Ajuste la cantidad o quítelo.' } : i)));
      setAviso({ tipo: 'error', titulo: 'Stock insuficiente', texto: `${agotado.nombre} se agotó mientras compraba. Ajuste las cantidades para continuar.` });
      setPantalla('carrito');
      return;
    }
    if (simular === 'comercio' && !suspendidos.length) {
      setSuspendidos([2]);
      setAviso({ tipo: 'aviso', titulo: 'Comercio suspendido', texto: `${comercios[2].nombre} tiene su membresía suspendida. Quite sus productos para continuar con el resto de su compra.` });
      setPantalla('carrito');
      return;
    }
    const grupos = agruparPorComercio(items);
    setPedido({ monto_total: totalGeneral(grupos), subpedidos: grupos.map((g) => ({ comercio: g.comercio.nombre })) });
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
        {pantalla === 'checkout' && <CheckoutPage items={items} onVolver={() => setPantalla('carrito')} onConfirmar={confirmar} />}
        {pantalla === 'pedido' && <PedidoPendientePage pedido={pedido} />}
      </div>
    </div>
  );
}