import PaginaPortal, { usePortal } from '../components/PaginaPortal';
import FiltrosComercio, { LARGO_MINIMO_TERMINO } from '../components/FiltrosComercio';
import ListadoProductos, { POR_PAGINA } from '../components/ListadoProductos';
import { Vacio } from '../components/EstadosVista';
import Icono from '../components/IconoComercio';
import { buscarProductos } from '../services/m1Api';
import useConsulta from '../services/useConsulta';

// CU-002 Buscar productos: GET /api/m1/v1/productos/busqueda.
// `criterio` llega de la URL con los nombres del contrato (q, id_categoria, precio_minimo, precio_maximo,
// orden, pagina, por_pagina). RN-M1-08: sin categoría, el término necesita 2 caracteres; si no los
// tiene, se avisa sin llamar a la API.
export default function BusquedaView({ criterio }) {
  const { ir, categorias } = usePortal();
  const termino = (criterio.q ?? '').trim();
  const cumpleRegla = !!criterio.id_categoria || termino.length >= LARGO_MINIMO_TERMINO;
  const parametros = { ...criterio, q: termino || null, pagina: criterio.pagina ?? 1, por_pagina: criterio.por_pagina ?? POR_PAGINA[0] };

  const consulta = useConsulta(() => buscarProductos(parametros), [JSON.stringify(parametros)], cumpleRegla);
  const cambiar = (nuevo) => ir('busqueda', { ...nuevo, pagina: nuevo.pagina ?? 1 }, { reemplazar: true });

  const categoria = buscarCategoria(categorias, criterio.id_categoria);
  const titulo = termino ? `Resultados para "${termino}"` : categoria ? categoria.nombre : 'Buscar productos';

  return (
    <PaginaPortal terminoBusqueda={termino}>
      <nav aria-label="Ruta de navegación" className="py-3 text-sm text-gray-500">
        <ol className="flex flex-wrap items-center gap-1">
          <li><button type="button" onClick={() => ir('inicio')} className="inline-flex items-center gap-1 hover:text-brandBlue"><Icono nombre="inicio" className="h-4 w-4" /> Inicio</button></li>
          <li aria-hidden><Icono nombre="derecha" className="h-3.5 w-3.5 text-gray-400" /></li>
          <li aria-current="page" className="text-gray-800">Búsqueda</li>
        </ol>
      </nav>

      <h1 className="break-words text-2xl font-bold text-brandDarkBlue">{titulo}</h1>

      {!cumpleRegla ? (
        <div className="mt-6">
          <Vacio icono="buscar" titulo="Escribí al menos 2 caracteres o elegí una categoría">
            Usá el buscador de arriba o elegí una categoría del menú para ver productos.
          </Vacio>
        </div>
      ) : (
        <ListadoProductos
          consulta={consulta}
          resultado={consulta.datos}
          criterio={parametros}
          onCambio={cambiar}
          onAbrirProducto={(id) => ir('producto', { id })}
          filtros={<FiltrosComercio categorias={categorias} criterio={parametros} onCambio={(f) => cambiar({ ...f, pagina: 1 })} />}
          vacio={(
            <Vacio icono="buscar" titulo="No encontramos productos"
              accion={(criterio.precio_minimo != null || criterio.precio_maximo != null || criterio.id_categoria) && (
                <button type="button" onClick={() => cambiar({ q: termino || null, pagina: 1 })} className="flex items-center gap-2 rounded-lg border border-brandBlue px-4 py-2 font-semibold text-brandBlue transition hover:bg-blue-50">Quitar filtros</button>
              )}>
              Probá con otras palabras o quitá algún filtro.
            </Vacio>
          )}
        />
      )}
    </PaginaPortal>
  );
}

// Categoría o subcategoría del menú por su id.
export function buscarCategoria(categorias, idCategoria) {
  if (!idCategoria) return null;
  for (const c of categorias) {
    if (c.id_categoria === idCategoria) return c;
    const sub = (c.subcategorias ?? []).find((s) => s.id_categoria === idCategoria);
    if (sub) return sub;
  }
  return null;
}
