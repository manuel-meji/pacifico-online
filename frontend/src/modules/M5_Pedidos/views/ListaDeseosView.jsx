import { useEffect, useState } from 'react';
import { ArrowLeft, Heart, LoaderCircle, Package, RefreshCw, ShoppingCart, Trash2 } from 'lucide-react';
import { Alerta, Boton, Encabezado } from '../components/componentes';
import { colones } from '../services/calculos';
import { eliminarDeseo, moverDeseoAlCarrito, obtenerDeseos } from '../services/m5Api';

export default function ListaDeseosView({ onVolver, onProductoMovido }) {
  const [deseos, setDeseos] = useState([]);
  const [cargando, setCargando] = useState(true);
  const [error, setError] = useState(null);
  const [procesandoId, setProcesandoId] = useState(null);
  const [aviso, setAviso] = useState(null);

  const cargar = async () => {
    setCargando(true);
    setError(null);
    try {
      const res = await obtenerDeseos();
      setDeseos(res.items ?? []);
    } catch (e) {
      setError(e);
    } finally {
      setCargando(false);
    }
  };

  useEffect(() => {
    cargar();
  }, []);

  const handleEliminar = async (idDeseo) => {
    setProcesandoId(idDeseo);
    setAviso(null);
    try {
      await eliminarDeseo(idDeseo);
      setDeseos((prev) => prev.filter((d) => d.id_deseo !== idDeseo));
      setAviso({ tipo: 'exito', titulo: 'Eliminado', texto: 'El producto se quitó de su lista de deseos.' });
    } catch (e) {
      setAviso({ tipo: 'error', titulo: 'Error', texto: e.mensaje || 'No se pudo eliminar el producto.' });
    } finally {
      setProcesandoId(null);
    }
  };

  const handleMover = async (idDeseo) => {
    setProcesandoId(idDeseo);
    setAviso(null);
    try {
      await moverDeseoAlCarrito(idDeseo);
      setDeseos((prev) => prev.filter((d) => d.id_deseo !== idDeseo));
      setAviso({ tipo: 'exito', titulo: '¡Movido al carrito!', texto: 'El producto se agregó a su carrito de compras.' });
      onProductoMovido?.();
    } catch (e) {
      setAviso({ tipo: 'error', titulo: 'Error', texto: e.mensaje || 'No se pudo mover el producto al carrito.' });
    } finally {
      setProcesandoId(null);
    }
  };

  return (
    <>
      <Encabezado titulo="Mi Lista de Deseos" onVolver={onVolver} etiquetaVolver="Volver al carrito" />
      <main className="max-w-4xl mx-auto px-4 sm:px-6 py-6 pb-24 space-y-6">
        {aviso && <Alerta tipo={aviso.tipo} titulo={aviso.titulo}>{aviso.texto}</Alerta>}

        {cargando && (
          <div className="py-16 flex flex-col items-center gap-3 text-gray-500">
            <LoaderCircle className="w-8 h-8 animate-spin text-brandBlue" aria-hidden="true" />
            <p className="text-sm font-medium">Cargando su lista de deseos…</p>
          </div>
        )}

        {error && (
          <div className="bg-white rounded-xl p-6 border border-red-200 text-center space-y-3">
            <p className="text-red-700 font-semibold">{error.mensaje || 'Error al cargar la lista de deseos'}</p>
            <Boton variante="oscuro" icono={RefreshCw} onClick={cargar}>Reintentar</Boton>
          </div>
        )}

        {!cargando && !error && deseos.length === 0 && (
          <div className="bg-white rounded-2xl shadow-xs border border-gray-200 p-8 sm:p-12 text-center max-w-md mx-auto">
            <Heart className="w-16 h-16 stroke-1 text-rose-300 mx-auto" aria-hidden="true" />
            <h2 className="mt-4 text-xl font-bold text-gray-800">Su lista de deseos está vacía</h2>
            <p className="mt-1 text-sm text-gray-500">
              Guarde aquí los artículos que le interesen para comprarlos más adelante.
            </p>
            <div className="mt-6 flex justify-center">
              <a
                href="/app.html"
                className="inline-flex items-center justify-center px-4 py-2 text-sm font-bold text-white bg-brandBlue hover:bg-brandDarkBlue rounded-lg transition"
              >
                Explorar productos
              </a>
            </div>
          </div>
        )}

        {!cargando && !error && deseos.length > 0 && (
          <div className="bg-white rounded-2xl shadow-xs border border-gray-200 divide-y divide-gray-100 overflow-hidden">
            {deseos.map((d) => {
              const producto = d.producto ?? {};
              const nombre = producto.nombre_producto ?? producto.nombre ?? `Variación #${d.id_variacion}`;
              const precio = Number(producto.precio ?? producto.precio_unitario ?? 0);
              const ocupado = procesandoId === d.id_deseo;

              return (
                <div key={d.id_deseo} className="p-4 sm:p-5 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 hover:bg-gray-50/50 transition">
                  <div className="flex items-center gap-3.5 min-w-0">
                    <div className="w-12 h-12 rounded-xl bg-gray-100 border border-gray-200 flex items-center justify-center shrink-0">
                      <Package className="w-6 h-6 text-gray-400 stroke-1" aria-hidden="true" />
                    </div>
                    <div className="min-w-0">
                      <h3 className="font-bold text-gray-900 text-sm sm:text-base truncate">{nombre}</h3>
                      <p className="text-xs text-gray-500">Guardado el {new Date(d.fecha_agregado || Date.now()).toLocaleDateString()}</p>
                      {precio > 0 && <p className="text-sm font-extrabold text-brandDarkBlue mt-0.5">{colones(precio)}</p>}
                    </div>
                  </div>

                  <div className="flex items-center gap-2 self-end sm:self-center shrink-0">
                    <button
                      type="button"
                      disabled={ocupado}
                      onClick={() => handleEliminar(d.id_deseo)}
                      className="p-2 text-gray-400 hover:text-red-600 rounded-lg transition hover:bg-red-50 cursor-pointer disabled:opacity-50"
                      title="Eliminar de lista de deseos"
                    >
                      <Trash2 className="w-4 h-4" aria-hidden="true" />
                    </button>

                    <button
                      type="button"
                      disabled={ocupado}
                      onClick={() => handleMover(d.id_deseo)}
                      className="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-bold text-white bg-brandGreen hover:bg-emerald-600 rounded-lg transition shadow-xs cursor-pointer disabled:opacity-50"
                    >
                      {ocupado ? <LoaderCircle className="w-3.5 h-3.5 animate-spin" /> : <ShoppingCart className="w-3.5 h-3.5" />}
                      Mover al carrito
                    </button>
                  </div>
                </div>
              );
            })}
          </div>
        )}
      </main>
    </>
  );
}
