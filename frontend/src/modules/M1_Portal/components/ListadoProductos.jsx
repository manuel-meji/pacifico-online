import { useState } from 'react';
import TarjetaProducto from './TarjetaProducto';
import Paginacion from './Paginacion';
import Icono from './IconoComercio';
import { CargandoTarjetas, ErrorVista } from './EstadosVista';

// Valores de orden del contrato (buscarProductos). Sin orden, M3 ordena por relevancia.
export const ORDENES = [
  { valor: '', etiqueta: 'Más relevantes' },
  { valor: 'precio_asc', etiqueta: 'Precio: de menor a mayor' },
  { valor: 'precio_desc', etiqueta: 'Precio: de mayor a menor' },
  { valor: 'novedad', etiqueta: 'Más recientes' },
];

export const POR_PAGINA = [12, 24, 36];

// Listado de productos con barra lateral de filtros, orden, cantidad por página y paginación.
//   consulta: estado de useConsulta ('cargando' | 'actualizando' | 'listo' | 'error') y su resultado
//   resultado: ResultadoBusqueda del contrato ({ pagina, por_pagina, total_resultados, productos })
//   ordenHabilitado: false cuando el orden no se puede usar (micrositio sin término ni categoría, RN-M1-08)
export default function ListadoProductos({
  consulta, resultado, criterio, onCambio, filtros, ordenHabilitado = true,
  onAbrirProducto, vacio, aviso, mostrarComercio = true,
}) {
  const [filtrosMovil, setFiltrosMovil] = useState(false);
  const pagina = resultado?.pagina ?? 1;
  const porPagina = resultado?.por_pagina ?? criterio.por_pagina ?? POR_PAGINA[0];
  const total = resultado?.total_resultados ?? 0;
  const totalPaginas = Math.max(1, Math.ceil(total / porPagina));
  const desde = total ? (pagina - 1) * porPagina + 1 : 0;
  const hasta = Math.min(pagina * porPagina, total);

  let contenido;
  if (consulta.estado === 'cargando') {
    contenido = <CargandoTarjetas etiqueta="Cargando productos" />;
  } else if (consulta.estado === 'error') {
    contenido = <ErrorVista resultado={consulta.resultado} onReintentar={consulta.reintentar} />;
  } else if (!resultado || resultado.productos.length === 0) {
    contenido = vacio;
  } else {
    contenido = (
      <>
        <ul className={`grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4 ${consulta.estado === 'actualizando' ? 'opacity-60' : ''}`} aria-busy={consulta.estado === 'actualizando'}>
          {resultado.productos.map((producto) => (
            <li key={producto.id_producto} className="min-w-0">
              <TarjetaProducto producto={producto} mostrarComercio={mostrarComercio} onAbrir={() => onAbrirProducto(producto.id_producto)} />
            </li>
          ))}
        </ul>
        <Paginacion pagina={pagina} totalPaginas={totalPaginas} onPagina={(n) => { onCambio({ ...criterio, pagina: n }); window.scrollTo({ top: 0, behavior: 'smooth' }); }} />
      </>
    );
  }

  return (
    <div className="mt-4 flex gap-6">
      <aside className="hidden w-60 shrink-0 self-start rounded-xl border border-gray-100 bg-white px-4 py-1 shadow-sm lg:block" aria-label="Filtros">
        {filtros}
      </aside>

      <div className="min-w-0 flex-1 space-y-3">
        {aviso}

        <div className="flex flex-wrap items-center gap-3 rounded-xl border border-gray-100 bg-white px-3 py-2 shadow-sm">
          <button type="button" onClick={() => setFiltrosMovil(true)} className="inline-flex items-center gap-2 rounded-lg border border-brandBlue px-3 py-1.5 text-sm font-semibold text-brandBlue transition hover:bg-blue-50 lg:hidden">
            <Icono nombre="filtros" className="h-4 w-4" /> Filtros
          </button>
          <p className="text-sm text-gray-600" aria-live="polite">
            {(consulta.estado === 'listo' || consulta.estado === 'actualizando') && total > 0 ? `Mostrando ${desde} a ${hasta} de ${total}` : ' '}
          </p>
          <div className="flex w-full flex-wrap items-center gap-3 text-sm sm:ml-auto sm:w-auto">
            <label className="flex min-w-0 items-center gap-2">
              <span className="shrink-0 text-gray-600">Ordenar:</span>
              <select value={criterio.orden ?? ''} disabled={!ordenHabilitado} title={ordenHabilitado ? undefined : 'Elegí una categoría o buscá un producto para ordenar'}
                onChange={(e) => onCambio({ ...criterio, orden: e.target.value || null, pagina: 1 })}
                className="min-w-0 rounded-lg border border-gray-200 bg-white px-2 py-1.5 focus:border-brandBlue focus:outline-none disabled:cursor-not-allowed disabled:bg-gray-100">
                {ORDENES.map((o) => <option key={o.valor} value={o.valor}>{o.etiqueta}</option>)}
              </select>
            </label>
            <label className="flex items-center gap-2">
              <span className="text-gray-600">Mostrar:</span>
              <select value={porPagina} onChange={(e) => onCambio({ ...criterio, por_pagina: Number(e.target.value), pagina: 1 })}
                className="rounded-lg border border-gray-200 bg-white px-2 py-1.5 focus:border-brandBlue focus:outline-none">
                {POR_PAGINA.map((n) => <option key={n} value={n}>{n}</option>)}
              </select>
            </label>
          </div>
        </div>

        {contenido}
      </div>

      {filtrosMovil && (
        <div className="fixed inset-0 z-30 lg:hidden" role="dialog" aria-modal="true" aria-label="Filtros">
          <div className="absolute inset-0 bg-black/40" onClick={() => setFiltrosMovil(false)} />
          <div className="absolute inset-y-0 left-0 flex w-80 max-w-[85%] flex-col bg-white">
            <div className="flex items-center justify-between border-b border-gray-200 px-4 py-3">
              <h2 className="font-semibold">Filtros</h2>
              <button type="button" onClick={() => setFiltrosMovil(false)} className="grid h-10 w-10 place-items-center rounded-full text-gray-600 hover:bg-gray-100" aria-label="Cerrar filtros"><Icono nombre="cerrar" className="h-5 w-5" /></button>
            </div>
            <div className="flex-1 overflow-y-auto px-4">{filtros}</div>
            <div className="border-t border-gray-200 p-3">
              <button type="button" onClick={() => setFiltrosMovil(false)} className="flex w-full items-center justify-center gap-2 rounded-lg bg-brandDarkBlue px-4 py-2.5 font-bold text-white transition duration-200 hover:bg-brandBlue">Ver productos</button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
