import { useEffect, useState } from 'react';
import EncabezadoPortal from '../components/EncabezadoPortal';
import TarjetaProducto from '../components/TarjetaProducto';
import FiltrosComercio from '../components/FiltrosComercio';
import Paginacion from '../components/Paginacion';
import InfoComercio from '../components/InfoComercio';
import Icono from '../components/IconoComercio';
import { obtenerMicrositio, ComercioNoDisponible, ORDENES, POR_PAGINA, RANGOS_PRECIO, colones } from '../services/micrositio';
import { categorias as categoriasPortal } from '../services/datosMockComercio';

const FILTROS_VACIOS = { categorias: [], marcas: [], rango: null, precioMin: null, precioMax: null, soloDisponibles: false };

// CU-009 Consultar información pública de un comercio (micrositio). Ruta del PC3: GET /m1/comercios/{idComercio}/micrositio.
// Flujo normal: datos del comercio (M2) + productos paginados (M3) + disponibilidad (M4).
// Alternativas: comercio no disponible (404 de M2), comercio sin productos, filtros sin resultados, M4 sin respuesta.
export default function FichaComercioView({ idComercio = 7, simular = null }) {
  const [estado, setEstado] = useState('cargando');
  const [datos, setDatos] = useState(null);
  const [pestana, setPestana] = useState('productos');
  const [filtros, setFiltros] = useState(FILTROS_VACIOS);
  const [orden, setOrden] = useState('predeterminado');
  const [porPagina, setPorPagina] = useState(12);
  const [pagina, setPagina] = useState(1);
  const [filtrosMovil, setFiltrosMovil] = useState(false);

  useEffect(() => {
    let vigente = true;
    setEstado((e) => (e === 'listo' ? 'actualizando' : 'cargando'));
    obtenerMicrositio(idComercio, { pagina, porPagina, orden, filtros }, simular)
      .then((d) => { if (vigente) { setDatos(d); setEstado('listo'); } })
      .catch((err) => { if (vigente) setEstado(err instanceof ComercioNoDisponible ? 'no_disponible' : 'error'); });
    return () => { vigente = false; };
  }, [idComercio, pagina, porPagina, orden, filtros, simular]);

  const cambiarFiltros = (f) => { setFiltros(f); setPagina(1); };
  const icono = (idCategoria) => categoriasPortal.find((c) => c.idCategoria === idCategoria)?.icono;

  if (estado === 'cargando') {
    return (
      <Pagina>
        <div className="mx-auto max-w-7xl animate-pulse px-4 py-8" aria-busy="true" aria-label="Cargando comercio">
          <div className="h-40 rounded-xl bg-gray-200" />
          <div className="mt-6 grid grid-cols-2 gap-4 md:grid-cols-4">
            {Array.from({ length: 8 }, (_, i) => <div key={i} className="aspect-[3/4] rounded-lg bg-gray-200" />)}
          </div>
        </div>
      </Pagina>
    );
  }

  if (estado === 'no_disponible' || estado === 'error') {
    return (
      <Pagina>
        <div className="mx-auto max-w-xl px-4 py-16 text-center">
          <span className="mx-auto grid h-16 w-16 place-items-center rounded-full bg-blue-50 text-brandDarkBlue"><Icono nombre="tienda" className="h-8 w-8" /></span>
          <h1 className="mt-4 text-2xl font-bold text-gray-900">Este comercio no está disponible</h1>
          <p className="mt-2 text-gray-600">
            {estado === 'no_disponible'
              ? 'El comercio que buscás no existe o no está habilitado en este momento.'
              : 'No pudimos cargar el comercio. Intentá de nuevo en unos minutos.'}
          </p>
          <a href="#" className="mt-6 inline-flex items-center justify-center gap-2 rounded-lg bg-brandDarkBlue px-4 py-2.5 font-bold text-white transition duration-200 hover:bg-brandBlue">Volver al inicio</a>
        </div>
      </Pagina>
    );
  }

  const { comercio, productos: res, categorias, marcas, conteoCategorias, conteoMarcas, avisos } = datos;
  const totalProductos = Object.values(conteoCategorias).reduce((s, n) => s + n, 0);
  const totalPaginas = Math.max(1, Math.ceil(res.totalResultados / res.porPagina));
  const desde = res.totalResultados ? (res.pagina - 1) * res.porPagina + 1 : 0;
  const hasta = Math.min(res.pagina * res.porPagina, res.totalResultados);
  const hayFiltros = filtros.categorias.length || filtros.marcas.length || filtros.rango || filtros.precioMin != null || filtros.precioMax != null || filtros.soloDisponibles;

  const chips = [
    ...filtros.categorias.map((id) => ({ texto: categorias.find((c) => c.idCategoria === id)?.nombre, quitar: () => cambiarFiltros({ ...filtros, categorias: filtros.categorias.filter((x) => x !== id) }) })),
    ...filtros.marcas.map((m) => ({ texto: m, quitar: () => cambiarFiltros({ ...filtros, marcas: filtros.marcas.filter((x) => x !== m) }) })),
    ...(filtros.rango ? [{ texto: RANGOS_PRECIO.find((r) => r.id === filtros.rango)?.etiqueta, quitar: () => cambiarFiltros({ ...filtros, rango: null }) }] : []),
    ...(filtros.precioMin != null || filtros.precioMax != null
      ? [{ texto: `${filtros.precioMin != null ? colones(filtros.precioMin) : '₡0'} - ${filtros.precioMax != null ? colones(filtros.precioMax) : 'máx.'}`, quitar: () => cambiarFiltros({ ...filtros, precioMin: null, precioMax: null }) }]
      : []),
    ...(filtros.soloDisponibles ? [{ texto: 'Solo disponibles', quitar: () => cambiarFiltros({ ...filtros, soloDisponibles: false }) }] : []),
  ];

  const barraFiltros = (
    <FiltrosComercio categorias={categorias} marcas={marcas} conteoCategorias={conteoCategorias} conteoMarcas={conteoMarcas}
      filtros={filtros} onCambio={cambiarFiltros} />
  );

  return (
    <Pagina categorias={categorias} onCategoria={(id) => { setPestana('productos'); cambiarFiltros({ ...FILTROS_VACIOS, categorias: [id] }); }}>
      <div className="mx-auto max-w-7xl px-4 pb-12">
        <nav aria-label="Ruta de navegación" className="py-3 text-sm text-gray-500">
          <ol className="flex flex-wrap items-center gap-1">
            <li><a href="#" className="inline-flex items-center gap-1 hover:text-brandBlue"><Icono nombre="inicio" className="h-4 w-4" /> Inicio</a></li>
            <li aria-hidden><Icono nombre="derecha" className="h-3.5 w-3.5 text-gray-400" /></li>
            <li><a href="#" className="hover:text-brandBlue">Comercios</a></li>
            <li aria-hidden><Icono nombre="derecha" className="h-3.5 w-3.5 text-gray-400" /></li>
            <li aria-current="page" className="text-gray-800">{comercio.nombre_comercial}</li>
          </ol>
        </nav>

        {/* Banner y encabezado del comercio (M2: banners, logo_url, nombre_comercial, descripcion) */}
        <section className="overflow-hidden rounded-xl border border-gray-200 bg-white">
          {comercio.banners?.[0] && (
            <div className="relative overflow-hidden bg-gradient-to-r from-brandDarkBlue to-brandBlue px-6 py-8 text-white sm:py-10">
              <span aria-hidden className="absolute -right-10 -top-16 hidden h-48 w-48 rounded-full sm:block bg-brandGreen/90" />
              <span aria-hidden className="absolute right-24 top-10 hidden h-20 w-20 rounded-full sm:block bg-brandGreen/50" />
              <p className="relative text-2xl font-bold sm:text-3xl">{comercio.banners[0].titulo}</p>
              <p className="relative mt-1 max-w-[70%] text-sm text-blue-50 sm:text-base">{comercio.banners[0].subtitulo}</p>
            </div>
          )}
          <div className="flex flex-col gap-4 p-5 sm:flex-row sm:items-center">
            <div className="grid h-20 w-20 shrink-0 place-items-center rounded-full border-4 border-white bg-blue-100 text-2xl font-black text-brandDarkBlue shadow sm:-mt-12">
              {comercio.logo_url ? <img src={comercio.logo_url} alt={`Logotipo de ${comercio.nombre_comercial}`} className="h-full w-full rounded-full object-cover" /> : comercio.nombre_comercial.slice(0, 2).toUpperCase()}
            </div>
            <div className="min-w-0 flex-1">
              <div className="flex flex-wrap items-center gap-2">
                <h1 className="text-2xl font-bold text-brandDarkBlue">{comercio.nombre_comercial}</h1>
                <span className="inline-flex items-center gap-1 rounded-full border border-green-200 bg-green-50 px-2.5 py-1 text-xs font-semibold text-green-700"><Icono nombre="verificado" className="h-3.5 w-3.5" /> Verificado</span>
              </div>
              {comercio.descripcion && <p className="mt-1 text-sm text-gray-600">{comercio.descripcion}</p>}
              <p className="mt-1 text-xs text-gray-500">{totalProductos} productos publicados</p>
            </div>
          </div>
          <div role="tablist" className="flex border-t border-gray-200 px-5">
            {[['productos', 'Productos'], ['informacion', 'Información del comercio']].map(([id, texto]) => (
              <button key={id} role="tab" aria-selected={pestana === id} onClick={() => setPestana(id)}
                className={`-mb-px border-b-2 px-3 py-3 text-sm font-medium ${pestana === id ? 'border-brandBlue font-semibold text-brandDarkBlue' : 'border-transparent text-gray-500 hover:text-gray-800'}`}>
                {texto}
              </button>
            ))}
          </div>
        </section>

        {pestana === 'informacion' && <div className="mt-6"><InfoComercio comercio={comercio} /></div>}

        {pestana === 'productos' && totalProductos === 0 && (
          <div className="mt-6 rounded-xl border border-dashed border-gray-300 bg-white px-6 py-14 text-center">
            <span className="mx-auto grid h-14 w-14 place-items-center rounded-full bg-blue-50 text-brandDarkBlue"><Icono nombre="paquete" className="h-7 w-7" /></span>
            <h2 className="mt-3 text-lg font-semibold text-gray-900">Este comercio todavía no tiene productos publicados</h2>
            <p className="mt-1 text-sm text-gray-600">Podés revisar su información de contacto o volver más tarde.</p>
            <button onClick={() => setPestana('informacion')} className="mt-4 text-sm rounded-lg border border-brandBlue px-4 py-2 font-semibold text-brandBlue transition hover:bg-blue-50">Ver información del comercio</button>
          </div>
        )}

        {pestana === 'productos' && totalProductos > 0 && (
          <>
            {/* Categorías del comercio en burbujas (acceso rápido al filtro "Tipo de producto") */}
            <div className="mt-6 flex gap-4 overflow-x-auto pb-2">
              {categorias.map((c) => {
                const activa = filtros.categorias.length === 1 && filtros.categorias[0] === c.idCategoria;
                return (
                  <button key={c.idCategoria} onClick={() => cambiarFiltros({ ...filtros, categorias: activa ? [] : [c.idCategoria] })}
                    className="flex w-24 shrink-0 flex-col items-center gap-2 text-center" aria-pressed={activa}>
                    <span className={`grid h-20 w-20 place-items-center rounded-full border-2 bg-white text-brandDarkBlue shadow-sm transition ${activa ? 'border-brandBlue bg-blue-50' : 'border-gray-100 hover:border-brandBlue'}`} aria-hidden><Icono nombre={c.icono} className="h-8 w-8" /></span>
                    <span className="text-xs font-medium leading-tight text-gray-700">{c.nombre}</span>
                  </button>
                );
              })}
            </div>

            <div className="mt-4 flex gap-6">
              <aside className="hidden w-60 shrink-0 self-start rounded-xl border border-gray-100 bg-white px-4 py-1 shadow-sm lg:block" aria-label="Filtros">
                {barraFiltros}
              </aside>

              <div className="min-w-0 flex-1">
                {avisos.map((a) => (
                  <div key={a} role="status" className="mb-3 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900">{a}</div>
                ))}

                <div className="flex flex-wrap items-center gap-3 rounded-xl border border-gray-100 bg-white px-3 py-2 shadow-sm">
                  <button onClick={() => setFiltrosMovil(true)} className="inline-flex items-center gap-2 rounded-lg border border-brandBlue px-3 py-1.5 text-sm font-semibold text-brandBlue transition hover:bg-blue-50 lg:hidden"><Icono nombre="filtros" className="h-4 w-4" /> Filtros{chips.length ? ` (${chips.length})` : ''}</button>
                  <p className="text-sm text-gray-600" aria-live="polite">
                    Mostrando {desde} a {hasta} de {res.totalResultados} ({totalPaginas} {totalPaginas === 1 ? 'página' : 'páginas'})
                  </p>
                  <div className="ml-auto flex flex-wrap items-center gap-3 text-sm">
                    <label className="flex items-center gap-2">
                      <span className="text-gray-600">Ordenar por:</span>
                      <select value={orden} onChange={(e) => { setOrden(e.target.value); setPagina(1); }} className="rounded-lg border border-gray-200 bg-white px-2 py-1.5 focus:border-brandBlue focus:outline-none">
                        {ORDENES.map((o) => <option key={o.valor} value={o.valor}>{o.etiqueta}</option>)}
                      </select>
                    </label>
                    <label className="flex items-center gap-2">
                      <span className="text-gray-600">Mostrar:</span>
                      <select value={porPagina} onChange={(e) => { setPorPagina(Number(e.target.value)); setPagina(1); }} className="rounded-lg border border-gray-200 bg-white px-2 py-1.5 focus:border-brandBlue focus:outline-none">
                        {POR_PAGINA.map((n) => <option key={n} value={n}>{n}</option>)}
                      </select>
                    </label>
                  </div>
                </div>

                {chips.length > 0 && (
                  <div className="mt-3 flex flex-wrap items-center gap-2">
                    {chips.map((c) => (
                      <button key={c.texto} onClick={c.quitar} className="inline-flex items-center gap-1 rounded-full bg-blue-50 px-3 py-1 text-xs font-medium text-brandDarkBlue hover:bg-blue-100" aria-label={`Quitar filtro ${c.texto}`}>
                        {c.texto} <Icono nombre="cerrar" className="h-3.5 w-3.5" />
                      </button>
                    ))}
                    <button onClick={() => cambiarFiltros(FILTROS_VACIOS)} className="text-xs text-gray-600 underline">Restablecer</button>
                  </div>
                )}

                {res.productos.length === 0 ? (
                  <div className="mt-6 rounded-xl border border-dashed border-gray-300 bg-white px-6 py-12 text-center">
                    <h2 className="text-lg font-semibold text-gray-900">No hay productos con esos filtros</h2>
                    <p className="mt-1 text-sm text-gray-600">Probá quitar algún filtro para ver más productos de {comercio.nombre_comercial}.</p>
                    {hayFiltros && <button onClick={() => cambiarFiltros(FILTROS_VACIOS)} className="mt-4 text-sm rounded-lg bg-brandDarkBlue px-4 py-2.5 font-bold text-white transition duration-200 hover:bg-brandBlue">Restablecer filtros</button>}
                  </div>
                ) : (
                  <div className={`mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4 ${estado === 'actualizando' ? 'opacity-60' : ''}`}>
                    {res.productos.map((pr) => <TarjetaProducto key={pr.idProducto} producto={pr} icono={icono(pr.idCategoria)} />)}
                  </div>
                )}

                <Paginacion pagina={res.pagina} totalPaginas={totalPaginas} onPagina={(n) => { setPagina(n); window.scrollTo({ top: 0, behavior: 'smooth' }); }} />
              </div>
            </div>
          </>
        )}
      </div>

      {filtrosMovil && (
        <div className="fixed inset-0 z-30 lg:hidden" role="dialog" aria-modal="true" aria-label="Filtros">
          <div className="absolute inset-0 bg-black/40" onClick={() => setFiltrosMovil(false)} />
          <div className="absolute inset-y-0 left-0 flex w-80 max-w-[85%] flex-col bg-white">
            <div className="flex items-center justify-between border-b border-gray-200 px-4 py-3">
              <h2 className="font-semibold">Filtros</h2>
              <button onClick={() => setFiltrosMovil(false)} className="grid h-10 w-10 place-items-center rounded-full text-gray-600 hover:bg-gray-100" aria-label="Cerrar filtros"><Icono nombre="cerrar" className="h-5 w-5" /></button>
            </div>
            <div className="flex-1 overflow-y-auto px-4">{barraFiltros}</div>
            <div className="border-t border-gray-200 p-3">
              <button onClick={() => setFiltrosMovil(false)} className="w-full rounded-lg bg-brandDarkBlue px-4 py-2.5 font-bold text-white transition duration-200 hover:bg-brandBlue">Ver {res.totalResultados} productos</button>
            </div>
          </div>
        </div>
      )}
    </Pagina>
  );
}

function Pagina({ categorias, onCategoria, children }) {
  return (
    <div className="min-h-screen overflow-x-hidden bg-brandLightBg font-sans text-gray-800 antialiased">
      <EncabezadoPortal categorias={categorias ?? categoriasPortal} cantidadCarrito={2} onCategoria={onCategoria} />
      <main>{children}</main>
      <footer className="border-t border-gray-200 bg-white py-6 text-center text-xs text-gray-500">
        Pacíficos Online · Prototipo M1 Portal público · Datos de ejemplo
      </footer>
    </div>
  );
}
