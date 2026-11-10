import { useRef, useState } from 'react';
import PaginaPortal, { usePortal } from '../components/PaginaPortal';
import FiltrosComercio, { tieneCriterio } from '../components/FiltrosComercio';
import ListadoProductos, { POR_PAGINA } from '../components/ListadoProductos';
import InfoComercio from '../components/InfoComercio';
import { AvisoVista, CargandoBloque, ErrorVista, Vacio } from '../components/EstadosVista';
import Icono from '../components/IconoComercio';
import Imagen from '../components/Imagen';
import { buscarProductos, obtenerMicrositio } from '../services/m1Api';
import useConsulta from '../services/useConsulta';

// CU-009 Ver micrositio de un comercio.
// - Sin filtros: GET /api/m1/v1/comercios/{id_comercio}/micrositio (datos del comercio y sus productos paginados).
// - Con término o categoría: los productos salen de GET /productos/busqueda con id_comercio fijo. La búsqueda
//   exige término de 2 caracteres o categoría (RN-M1-08), por eso precio y orden se habilitan solo entonces.
// - 404 ERR-004: el comercio no existe o no está activo. 503 ERR-006: M2 no respondió (con Reintentar).
// - productos_disponibles false: M3 no respondió; se muestra el comercio con un aviso en lugar de los productos.
export default function FichaComercioView({ idComercio, criterio }) {
  const { ir, categorias } = usePortal();
  const [pestana, setPestana] = useState('productos');
  const pagina = criterio.pagina ?? 1;
  const porPagina = criterio.por_pagina ?? POR_PAGINA[0];
  const filtrando = tieneCriterio(criterio);

  // Mientras se filtra, el micrositio conserva la última página que se vio: así no se vuelve a pedir.
  const paginaMicrositio = useRef([pagina, porPagina]);
  if (!filtrando) paginaMicrositio.current = [pagina, porPagina];
  const [paginaM, porPaginaM] = paginaMicrositio.current;

  const micrositio = useConsulta(() => obtenerMicrositio(idComercio, paginaM, porPaginaM), [idComercio, paginaM, porPaginaM], !!idComercio);
  const parametrosBusqueda = { ...criterio, q: (criterio.q ?? '').trim() || null, id_comercio: idComercio, pagina, por_pagina: porPagina };
  const busqueda = useConsulta(() => buscarProductos(parametrosBusqueda), [JSON.stringify(parametrosBusqueda)], filtrando && !!idComercio);

  const cambiar = (nuevo) => ir('comercio', { ...nuevo, id: idComercio }, { reemplazar: true });
  const restablecer = () => cambiar({ pagina: 1, por_pagina: porPagina });
  const datos = micrositio.datos;

  if (!idComercio || (micrositio.estado === 'error' && !datos)) {
    return (
      <PaginaPortal>
        <div className="mx-auto max-w-xl py-12">
          <ErrorVista resultado={idComercio ? micrositio.resultado : { estado: 404 }} noEncontrado="Este comercio no está disponible"
            onReintentar={micrositio.reintentar} onVolver={() => ir('inicio')} />
        </div>
      </PaginaPortal>
    );
  }

  if (!datos) {
    return <PaginaPortal><div className="py-8"><CargandoBloque etiqueta="Cargando comercio" /></div></PaginaPortal>;
  }

  const { comercio } = datos;
  const banner = comercio.banners?.[0];
  const consultaProductos = filtrando ? busqueda : micrositio;
  const resultado = filtrando ? busqueda.datos : datos.productos;
  const sinProductosDeM3 = !filtrando && datos.productos_disponibles === false;

  return (
    <PaginaPortal>
      <nav aria-label="Ruta de navegación" className="py-3 text-sm text-gray-500">
        <ol className="flex flex-wrap items-center gap-1">
          <li><button type="button" onClick={() => ir('inicio')} className="inline-flex min-h-[44px] items-center gap-1 hover:text-brandBlue sm:min-h-0"><Icono nombre="inicio" className="h-4 w-4" /> Inicio</button></li>
          <li aria-hidden><Icono nombre="derecha" className="h-3.5 w-3.5 text-gray-400" /></li>
          <li aria-current="page" className="min-w-0 truncate text-gray-800">{comercio.nombre_comercial}</li>
        </ol>
      </nav>

      <section className="overflow-hidden rounded-xl border border-gray-200 bg-white">
        {banner ? (
          <div className="grid h-32 place-items-center bg-gradient-to-r from-brandDarkBlue to-brandBlue sm:h-48">
            <Imagen src={banner.imagen_url} alt={banner.texto_alternativo} className="h-full w-full object-cover" icono="tienda" claseIcono="h-10 w-10 text-white/60" />
          </div>
        ) : (
          <div className="h-24 bg-gradient-to-r from-brandDarkBlue to-brandBlue sm:h-32" aria-hidden />
        )}
        <div className="flex flex-col gap-4 p-5 sm:flex-row sm:items-center">
          <div className="-mt-12 grid h-20 w-20 shrink-0 place-items-center overflow-hidden rounded-full border-4 border-white bg-blue-100 text-2xl font-black text-brandDarkBlue shadow">
            <Imagen src={comercio.logo_url} alt={`Logotipo de ${comercio.nombre_comercial}`} className="h-full w-full object-cover" icono="tienda" claseIcono="h-8 w-8 text-brandDarkBlue" />
          </div>
          <div className="min-w-0 flex-1">
            <div className="flex flex-wrap items-center gap-2">
              <h1 className="break-words text-2xl font-bold text-brandDarkBlue">{comercio.nombre_comercial}</h1>
              <span className="inline-flex items-center gap-1 rounded-full border border-green-200 bg-green-50 px-2.5 py-1 text-xs font-semibold text-green-700"><Icono nombre="verificado" className="h-3.5 w-3.5" /> Comercio activo</span>
            </div>
            {comercio.descripcion && <p className="mt-1 text-sm text-gray-600">{comercio.descripcion}</p>}
            {!sinProductosDeM3 && <p className="mt-1 text-xs text-gray-500">{datos.productos.total_resultados} productos publicados</p>}
          </div>
        </div>
        <div role="tablist" className="flex overflow-x-auto border-t border-gray-200 px-5">
          {[['productos', 'Productos'], ['informacion', 'Información del comercio']].map(([id, texto]) => (
            <button type="button" key={id} role="tab" aria-selected={pestana === id} onClick={() => setPestana(id)}
              className={`-mb-px shrink-0 border-b-2 px-3 py-3 text-sm font-medium ${pestana === id ? 'border-brandBlue font-semibold text-brandDarkBlue' : 'border-transparent text-gray-500 hover:text-gray-800'}`}>
              {texto}
            </button>
          ))}
        </div>
      </section>

      {pestana === 'informacion' && <div className="mt-6"><InfoComercio comercio={comercio} /></div>}

      {pestana === 'productos' && (
        <ListadoProductos
          consulta={consultaProductos}
          resultado={resultado}
          criterio={{ ...criterio, pagina, por_pagina: porPagina }}
          onCambio={cambiar}
          ordenHabilitado={filtrando}
          mostrarComercio={false}
          onAbrirProducto={(id) => ir('producto', { id })}
          filtros={(
            <FiltrosComercio conBuscador requiereCriterio categorias={categorias} criterio={criterio}
              onCambio={(f) => cambiar({ ...f, orden: tieneCriterio(f) ? f.orden : null, pagina: 1 })} />
          )}
          aviso={sinProductosDeM3 && (
            <AvisoVista>
              No pudimos cargar los productos de este comercio en este momento.{' '}
              <button type="button" onClick={micrositio.reintentar} className="font-semibold underline">Reintentar</button>
            </AvisoVista>
          )}
          vacio={filtrando ? (
            <Vacio icono="buscar" titulo="No hay productos con esos filtros"
              accion={<button type="button" onClick={restablecer} className="flex items-center justify-center gap-2 rounded-lg bg-brandDarkBlue px-4 py-2.5 font-bold text-white transition duration-200 hover:bg-brandBlue">Restablecer filtros</button>}>
              Probá con otra categoría o quitá algún filtro para ver más productos de {comercio.nombre_comercial}.
            </Vacio>
          ) : sinProductosDeM3 ? null : (
            <Vacio titulo="Este comercio todavía no tiene productos publicados"
              accion={<button type="button" onClick={() => setPestana('informacion')} className="flex items-center gap-2 rounded-lg border border-brandBlue px-4 py-2 font-semibold text-brandBlue transition hover:bg-blue-50">Ver información del comercio</button>}>
              Podés revisar su información de contacto o volver más tarde.
            </Vacio>
          )}
        />
      )}
    </PaginaPortal>
  );
}
