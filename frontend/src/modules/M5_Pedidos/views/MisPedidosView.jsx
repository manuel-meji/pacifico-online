import { useEffect, useState } from 'react';
import { ChevronRight, ClipboardList, LoaderCircle, RefreshCw, ShoppingCart } from 'lucide-react';
import { Alerta, Boton, Encabezado } from '../components/componentes';
import { colones } from '../services/calculos';
import { formatearFecha, infoEstado } from '../services/estados';
import { listarPedidos } from '../services/m5Api';

// Etiqueta de color con el estado del pedido.
export function EtiquetaEstado({ estado }) {
  const { etiqueta, clases } = infoEstado(estado);
  return (
    <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border whitespace-nowrap ${clases}`}>
      {etiqueta}
    </span>
  );
}

const fechaOrden = (p) => new Date(p.fecha_creacion ?? p.created_at ?? 0).getTime() || 0;
const ordenarRecientes = (pedidos) =>
  [...pedidos].sort((a, b) => fechaOrden(b) - fechaOrden(a) || Number(b.id_pedido) - Number(a.id_pedido));

// Historial de pedidos del cliente: GET /api/m5/v1/pedidos (cliente 1 por defecto).
// La respuesta es { pagina, por_pagina, total_resultados, pedidos }; hoy el backend devuelve todos los pedidos.
export default function MisPedidosView({ idSeleccionado, onVerDetalle, onVolver }) {
  const [pedidos, setPedidos] = useState([]);
  const [cargando, setCargando] = useState(true);
  const [error, setError] = useState(null);
  const [intento, setIntento] = useState(0);

  useEffect(() => {
    let vigente = true;
    setCargando(true);
    setError(null);
    listarPedidos()
      .then((r) => { if (vigente) setPedidos(ordenarRecientes(r.pedidos ?? [])); })
      .catch((e) => { if (vigente) setError(e); })
      .finally(() => { if (vigente) setCargando(false); });
    return () => { vigente = false; };
  }, [intento]);

  const botonDetalle = (p, clases = '') => {
    const seleccionado = Number(idSeleccionado) === Number(p.id_pedido);
    return (
      <button
        type="button"
        onClick={() => onVerDetalle(p.id_pedido)}
        aria-pressed={seleccionado}
        aria-label={`Ver detalle del pedido ${p.id_pedido}`}
        className={`min-h-11 inline-flex items-center justify-center gap-1 rounded-lg px-4 text-sm font-semibold border transition ${
          seleccionado ? 'bg-brandDarkBlue text-white border-brandDarkBlue' : 'border-gray-200 text-brandDarkBlue hover:bg-blue-50'
        } ${clases}`}
      >
        Ver detalle <ChevronRight className="w-4 h-4" aria-hidden="true" />
      </button>
    );
  };

  let contenido;
  if (cargando) {
    contenido = (
      <div className="py-16 flex flex-col items-center gap-3 text-gray-500" aria-live="polite">
        <LoaderCircle className="w-8 h-8 animate-spin text-brandBlue" aria-hidden="true" />
        <p className="text-sm font-medium">Cargando sus pedidos…</p>
      </div>
    );
  } else if (error) {
    contenido = (
      <div className="space-y-4">
        <Alerta tipo="error" titulo="No se pudieron cargar los pedidos">
          {error.mensaje || error.message}{error.codigo ? ` (${error.codigo})` : ''}
        </Alerta>
        <Boton variante="oscuro" icono={RefreshCw} onClick={() => setIntento((n) => n + 1)}>Reintentar</Boton>
      </div>
    );
  } else if (pedidos.length === 0) {
    contenido = (
      <div className="bg-white rounded-2xl shadow-xs border border-gray-200 p-8 sm:p-12 max-w-md mx-auto text-center">
        <ClipboardList className="w-16 h-16 stroke-1 text-gray-300 mx-auto" aria-hidden="true" />
        <p className="mt-4 text-xl font-bold text-gray-800">Todavía no tiene pedidos</p>
        <p className="mt-1 text-sm text-gray-500">Cuando confirme una compra, aparecerá aquí.</p>
      </div>
    );
  } else {
    contenido = (
      <>
        {/* Móvil y tabletas pequeñas: tarjetas */}
        <ul className="md:hidden space-y-3">
          {pedidos.map((p) => {
            const comercios = p.subpedidos?.length ?? 0;
            return (
              <li key={p.id_pedido} className="bg-white rounded-xl shadow-xs border border-gray-200 p-4 space-y-3">
                <div className="flex items-start justify-between gap-2">
                  <div className="min-w-0">
                    <p className="text-base font-bold text-gray-900">Pedido #{p.id_pedido}</p>
                    <p className="text-xs text-gray-500">{formatearFecha(p.fecha_creacion)}</p>
                  </div>
                  <EtiquetaEstado estado={p.estado_pedido} />
                </div>
                <dl className="flex justify-between gap-2 text-sm">
                  <div>
                    <dt className="text-xs text-gray-500">Comercios</dt>
                    <dd className="font-semibold text-gray-800">{comercios}</dd>
                  </div>
                  <div className="text-right">
                    <dt className="text-xs text-gray-500">Total</dt>
                    <dd className="font-extrabold text-brandDarkBlue">{colones(Number(p.monto_total ?? 0))}</dd>
                  </div>
                </dl>
                {botonDetalle(p, 'w-full')}
              </li>
            );
          })}
        </ul>

        {/* Escritorio: tabla */}
        <div className="hidden md:block bg-white rounded-xl shadow-xs border border-gray-200 overflow-hidden">
          <table className="w-full text-sm">
            <caption className="sr-only">Pedidos del cliente, del más reciente al más antiguo</caption>
            <thead className="bg-gray-50 text-xs font-semibold text-gray-600 uppercase tracking-wider">
              <tr>
                <th scope="col" className="text-left px-4 py-3">Pedido</th>
                <th scope="col" className="text-left px-4 py-3">Fecha</th>
                <th scope="col" className="text-center px-4 py-3">Comercios</th>
                <th scope="col" className="text-right px-4 py-3">Total</th>
                <th scope="col" className="text-left px-4 py-3">Estado</th>
                <th scope="col" className="px-4 py-3"><span className="sr-only">Acciones</span></th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {pedidos.map((p) => (
                <tr key={p.id_pedido} className={Number(idSeleccionado) === Number(p.id_pedido) ? 'bg-blue-50/50' : ''}>
                  <th scope="row" className="text-left px-4 py-3 font-bold text-gray-900">#{p.id_pedido}</th>
                  <td className="px-4 py-3 text-gray-600">{formatearFecha(p.fecha_creacion)}</td>
                  <td className="px-4 py-3 text-center text-gray-800">{p.subpedidos?.length ?? 0}</td>
                  <td className="px-4 py-3 text-right font-bold text-brandDarkBlue">{colones(Number(p.monto_total ?? 0))}</td>
                  <td className="px-4 py-3"><EtiquetaEstado estado={p.estado_pedido} /></td>
                  <td className="px-4 py-3 text-right">{botonDetalle(p)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </>
    );
  }

  return (
    <>
      <Encabezado
        titulo="Mis pedidos"
        onVolver={onVolver}
        paso={!cargando && !error && pedidos.length > 0 ? `${pedidos.length} ${pedidos.length === 1 ? 'pedido' : 'pedidos'}` : undefined}
      />
      <main className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
        {contenido}
        {onVolver && !cargando && (
          <Boton variante="oscuro" icono={ShoppingCart} onClick={onVolver}>Volver al carrito</Boton>
        )}
      </main>
    </>
  );
}
