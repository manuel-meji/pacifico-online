
import { useEffect, useMemo, useState } from 'react';
import {
  Search,
  SlidersHorizontal,
  RotateCcw,
  Package,
  ChevronDown,
  ChevronUp,
  ChevronLeft,
  ChevronRight,
  Tag,
  CheckCircle2,
  XCircle,
  LoaderCircle,
  PlusCircle,
} from 'lucide-react';

import {
  buscarProductos,
  listarCategorias,
  listarMarcas,
  obtenerProducto,
  textoDeError,
} from '../services/m3Api';

const POR_PAGINA = 10;

const CAMPO =
  'mt-1 block w-full min-w-0 rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-800 outline-none transition focus:border-[#1E3A8A] focus:ring-2 focus:ring-[#1E3A8A]/15';

const BOTON =
  'inline-flex items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50';

const dinero = (precio) =>
  new Intl.NumberFormat('es-CR', {
    style: 'currency',
    currency: 'CRC',
    maximumFractionDigits: 0,
  }).format(precio);

function rangoDePrecio(producto) {
  if (producto.precio_desde === null || producto.precio_desde === undefined) {
    return null;
  }

  if (
    producto.precio_hasta &&
    producto.precio_hasta !== producto.precio_desde
  ) {
    return `${dinero(producto.precio_desde)} – ${dinero(producto.precio_hasta)}`;
  }

  return dinero(producto.precio_desde);
}

const textoAtributos = (atributos = []) =>
  atributos.map((a) => `${a.tipo_atributo}: ${a.valor}`).join(', ');

// idComercio: comercio del encargado en la sesión. Con un valor, la consulta se limita a ese comercio
// ("Mis productos") y la pantalla no ofrece forma de quitar ese filtro. Sin valor, es el catálogo público.
export default function CatalogoView({ idComercio = null }) {
  const soloPropios = idComercio !== null && idComercio !== undefined;
  const [busqueda, setBusqueda] = useState('');
  const [termino, setTermino] = useState('');
  const [idMarca, setIdMarca] = useState('');
  const [idCategoria, setIdCategoria] = useState('');
  const [orden, setOrden] = useState('');
  const [soloDisponibles, setSoloDisponibles] = useState(false);
  const [pagina, setPagina] = useState(1);

  const [marcas, setMarcas] = useState([]);
  const [categorias, setCategorias] = useState([]);
  const [resultado, setResultado] = useState({
    productos: [],
    total_resultados: 0,
  });
  const [cargando, setCargando] = useState(true);
  const [error, setError] = useState(null);
  const [detalles, setDetalles] = useState({});

  useEffect(() => {
    listarCategorias().then(setCategorias).catch(() => setCategorias([]));
    listarMarcas().then(setMarcas).catch(() => setMarcas([]));
  }, []);

  // Espera a que la persona deje de escribir antes de consultar.
  useEffect(() => {
    const espera = setTimeout(() => {
      setTermino(busqueda.trim());
      setPagina(1);
    }, 350);

    return () => clearTimeout(espera);
  }, [busqueda]);

  // Consulta el catálogo mediante el servicio real de M3.
  useEffect(() => {
    let vigente = true;

    setCargando(true);
    setError(null);

    buscarProductos({
      q: termino,
      id_marca: idMarca,
      id_categoria: idCategoria,
      orden,
      pagina,
      por_pagina: POR_PAGINA,
      id_comercio: soloPropios ? idComercio : undefined,
    })
      .then((respuesta) => {
        if (vigente) setResultado(respuesta);
      })
      .catch((falla) => {
        if (vigente) {
          setResultado({ productos: [], total_resultados: 0 });
          setError(textoDeError(falla));
        }
      })
      .finally(() => {
        if (vigente) setCargando(false);
      });

    return () => {
      vigente = false;
    };
  }, [termino, idMarca, idCategoria, orden, pagina, soloPropios, idComercio]);

  const nombreMarca = useMemo(
    () => Object.fromEntries(marcas.map((m) => [m.id_marca, m.nombre])),
    [marcas]
  );

  const nombreCategoria = useMemo(
    () =>
      Object.fromEntries(
        categorias.map((c) => [c.id_categoria, c.nombre])
      ),
    [categorias]
  );

  // El servicio no filtra por disponibilidad: se aplica sobre la página cargada.
  const productos = soloDisponibles
    ? resultado.productos.filter(
        (p) =>
          p.precio_desde !== null && p.precio_desde !== undefined
      )
    : resultado.productos;

  const totalPaginas = Math.max(
    1,
    Math.ceil((resultado.total_resultados || 0) / POR_PAGINA)
  );

  const hayFiltros = Boolean(termino || idMarca || idCategoria || soloDisponibles);
  const sinProductosPropios = soloPropios && !hayFiltros;

  function cambiarFiltro(asignar) {
    return (evento) => {
      asignar(evento.target.value);
      setPagina(1);
    };
  }

  function limpiarFiltros() {
    setBusqueda('');
    setIdMarca('');
    setIdCategoria('');
    setOrden('');
    setSoloDisponibles(false);
    setPagina(1);
  }

  function alternarVariaciones(idProducto) {
    if (detalles[idProducto]) {
      setDetalles((actuales) => {
        const { [idProducto]: _quitado, ...resto } = actuales;
        return resto;
      });

      return;
    }

    setDetalles((actuales) => ({
      ...actuales,
      [idProducto]: { cargando: true },
    }));

    obtenerProducto(idProducto)
      .then((producto) =>
        setDetalles((actuales) => ({
          ...actuales,
          [idProducto]: {
            cargando: false,
            variaciones: producto.variaciones ?? [],
          },
        }))
      )
      .catch((falla) =>
        setDetalles((actuales) => ({
          ...actuales,
          [idProducto]: {
            cargando: false,
            error: textoDeError(falla),
          },
        }))
      );
  }

  return (
    <section className="mx-auto max-w-6xl px-4 pb-8 text-slate-800">
      <div className="mb-6">
        <div className="flex items-center gap-2 text-sm font-semibold text-[#7E9C30]">
          <Package size={18} />
          <span>Gestión de productos</span>
        </div>

        <h1 className="mt-2 text-2xl font-bold text-[#1E3A8A] sm:text-3xl">
          {soloPropios ? 'Mis productos' : 'Catálogo de productos'}
        </h1>

        <p className="mt-2 text-sm text-slate-600 sm:text-base">
          {soloPropios
            ? 'Productos de tu comercio, con sus variaciones, precios y disponibilidad.'
            : 'Consultá productos, sus variaciones, precios y disponibilidad.'}
        </p>

        {soloPropios && (
          <p className="mt-2 text-xs text-slate-500">
            Los productos en borrador aparecen aquí solo cuando estén publicados.
          </p>
        )}
      </div>

      {/* Búsqueda y filtros */}
      <section
        aria-labelledby="titulo-filtros"
        className="mb-6 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6"
      >
        <div className="mb-5 flex items-center gap-2">
          <SlidersHorizontal className="text-[#1E3A8A]" size={20} />
          <h2
            id="titulo-filtros"
            className="text-lg font-bold text-slate-800"
          >
            Buscar y filtrar
          </h2>
        </div>

        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <label className="block text-sm font-semibold text-slate-700">
            Buscar producto
            <div className="relative">
              <Search
                size={18}
                className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"
              />
              <input
                type="search"
                value={busqueda}
                onChange={(e) => setBusqueda(e.target.value)}
                placeholder="Nombre o descripción..."
                className={`${CAMPO} pl-10`}
                aria-label="Buscar producto por nombre o descripción"
              />
            </div>
          </label>

          <label className="block text-sm font-semibold text-slate-700">
            Marca
            <select
              value={idMarca}
              onChange={cambiarFiltro(setIdMarca)}
              className={CAMPO}
            >
              <option value="">Todas las marcas</option>
              {marcas.map((m) => (
                <option key={m.id_marca} value={m.id_marca}>
                  {m.nombre}
                </option>
              ))}
            </select>
          </label>

          <label className="block text-sm font-semibold text-slate-700">
            Categoría
            <select
              value={idCategoria}
              onChange={cambiarFiltro(setIdCategoria)}
              className={CAMPO}
            >
              <option value="">Todas las categorías</option>
              {categorias.map((c) => (
                <option key={c.id_categoria} value={c.id_categoria}>
                  {c.nombre}
                </option>
              ))}
            </select>
          </label>

          <label className="block text-sm font-semibold text-slate-700">
            Ordenar por
            <select
              value={orden}
              onChange={cambiarFiltro(setOrden)}
              className={CAMPO}
            >
              <option value="">Sin orden</option>
              <option value="precio_asc">Precio: menor a mayor</option>
              <option value="precio_desc">Precio: mayor a menor</option>
              <option value="novedad">Más recientes</option>
            </select>
          </label>
        </div>

        <div className="mt-5 flex flex-col gap-4 border-t border-slate-100 pt-4 sm:flex-row sm:items-center sm:justify-between">
          <label className="flex cursor-pointer items-center gap-2 text-sm text-slate-700">
            <input
              type="checkbox"
              checked={soloDisponibles}
              onChange={(e) => setSoloDisponibles(e.target.checked)}
              className="h-4 w-4 rounded border-slate-300 accent-[#1E3A8A]"
            />
            Mostrar solo productos con variaciones disponibles
            <span className="text-xs text-slate-400">
              (página actual)
            </span>
          </label>

          <button
            type="button"
            onClick={limpiarFiltros}
            className={BOTON}
          >
            <RotateCcw size={16} />
            Limpiar filtros
          </button>
        </div>

        <div
          className="mt-4 flex items-center justify-between border-t border-slate-100 pt-4"
          aria-live="polite"
        >
          <p className="text-sm text-slate-500">
            {cargando
              ? 'Buscando productos...'
              : `${resultado.total_resultados} productos encontrados`}
          </p>

          {cargando && (
            <LoaderCircle
              size={18}
              className="animate-spin text-[#1E3A8A]"
              aria-label="Cargando"
            />
          )}
        </div>
      </section>

      {/* Mensaje de error */}
      {error && (
        <div
          role="alert"
          className="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800"
        >
          <div className="flex items-start gap-2">
            <XCircle size={18} className="mt-0.5 shrink-0" />
            <p>{error}</p>
          </div>
        </div>
      )}

      {/* Estado vacío */}
      {!cargando && !error && productos.length === 0 ? (
        <section className="rounded-2xl border border-slate-200 bg-white px-5 py-12 text-center shadow-sm">
          <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-slate-100 text-slate-400">
            <Package size={28} />
          </div>

          {sinProductosPropios ? (
            <>
              <h2 className="mt-4 text-lg font-bold text-slate-800">
                Aún no tienes productos publicados.
              </h2>

              <p className="mx-auto mt-2 max-w-md text-sm text-slate-500">
                Usa Registrar producto para agregar uno y publicarlo.
              </p>

              <a
                href="#alta"
                className="mt-5 inline-flex items-center gap-2 rounded-lg bg-[#9DBB45] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#8AA83A]"
              >
                <PlusCircle size={16} />
                Registrar producto
              </a>
            </>
          ) : (
            <>
              <h2 className="mt-4 text-lg font-bold text-slate-800">
                No encontramos productos
              </h2>

              <p className="mx-auto mt-2 max-w-md text-sm text-slate-500">
                Probá con otra búsqueda o cambiá los filtros para encontrar
                productos en el catálogo.
              </p>

              <button
                type="button"
                onClick={limpiarFiltros}
                className="mt-5 inline-flex items-center gap-2 rounded-lg bg-[#9DBB45] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#8AA83A]"
              >
                <RotateCcw size={16} />
                Limpiar filtros
              </button>
            </>
          )}
        </section>
      ) : (
        <section
          aria-label="Resultados del catálogo"
          className="grid grid-cols-1 gap-4 md:grid-cols-2"
        >
          {productos.map((producto) => {
            const detalle = detalles[producto.id_producto];
            const precio = rangoDePrecio(producto);

            return (
              <article
                key={producto.id_producto}
                className="flex min-w-0 flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:shadow-md"
              >
                {producto.imagen_principal_url ? (
                  <img
                    src={producto.imagen_principal_url}
                    alt={producto.nombre}
                    loading="lazy"
                    className="h-48 w-full border-b border-slate-100 bg-slate-50 object-cover"
                  />
                ) : (
                  <div className="flex h-40 items-center justify-center border-b border-slate-100 bg-slate-50 text-slate-300">
                    <Package size={44} strokeWidth={1.3} />
                  </div>
                )}

                <div className="flex flex-1 flex-col p-4 sm:p-5">
                  <div className="flex flex-wrap items-start justify-between gap-2">
                    <div className="min-w-0">
                      {producto.id_marca &&
                        nombreMarca[producto.id_marca] && (
                          <p className="text-sm font-semibold text-[#7E9C30]">
                            {nombreMarca[producto.id_marca]}
                          </p>
                        )}

                      <h2 className="mt-1 break-words text-lg font-bold text-slate-800">
                        {producto.nombre}
                      </h2>
                    </div>

                    {nombreCategoria[producto.id_categoria] && (
                      <span className="inline-flex max-w-full items-center gap-1 rounded-full bg-blue-50 px-3 py-1 text-xs font-medium text-[#1E3A8A]">
                        <Tag size={12} />
                        {nombreCategoria[producto.id_categoria]}
                      </span>
                    )}
                  </div>

                  {producto.descripcion && (
                    <p className="mt-3 line-clamp-3 text-sm leading-relaxed text-slate-600">
                      {producto.descripcion}
                    </p>
                  )}

                  <div className="mt-4 border-t border-slate-100 pt-3">
                    <p
                      className={`text-lg font-bold ${
                        precio ? 'text-[#1E3A8A]' : 'text-sm text-red-700'
                      }`}
                    >
                      {precio ?? 'Sin variaciones disponibles'}
                    </p>
                  </div>

                  <div className="mt-auto pt-4">
                    <button
                      type="button"
                      onClick={() =>
                        alternarVariaciones(producto.id_producto)
                      }
                      aria-expanded={Boolean(detalle)}
                      aria-controls={`variaciones-${producto.id_producto}`}
                      className="flex w-full items-center justify-center gap-2 rounded-lg border border-[#1E3A8A] px-4 py-2.5 text-sm font-semibold text-[#1E3A8A] transition hover:bg-blue-50"
                    >
                      {detalle ? (
                        <>
                          Ocultar variaciones
                          <ChevronUp size={17} />
                        </>
                      ) : (
                        <>
                          Ver variaciones
                          <ChevronDown size={17} />
                        </>
                      )}
                    </button>
                  </div>

                  {detalle && (
                    <div
                      id={`variaciones-${producto.id_producto}`}
                      className="mt-4 border-t border-slate-100 pt-4"
                    >
                      {detalle.cargando && (
                        <div className="flex items-center gap-2 text-sm text-slate-500">
                          <LoaderCircle
                            size={17}
                            className="animate-spin text-[#1E3A8A]"
                          />
                          Cargando variaciones...
                        </div>
                      )}

                      {detalle.error && (
                        <p
                          role="alert"
                          className="rounded-lg bg-red-50 p-3 text-sm text-red-700"
                        >
                          {detalle.error}
                        </p>
                      )}

                      {detalle.variaciones && (
                        <div className="space-y-3">
                          <h3 className="text-sm font-bold text-slate-700">
                            Variaciones del producto
                          </h3>

                          {detalle.variaciones.length === 0 && (
                            <p className="text-sm text-slate-500">
                              Este producto no tiene variaciones.
                            </p>
                          )}

                          {detalle.variaciones.map((variacion) => (
                            <div
                              key={variacion.id_variacion}
                              className="rounded-xl border border-slate-200 bg-slate-50 p-3"
                            >
                              <div className="flex flex-wrap items-start justify-between gap-2">
                                <div className="min-w-0">
                                  <p className="break-all text-sm font-bold text-slate-800">
                                    {variacion.sku}
                                  </p>

                                  {variacion.atributos?.length > 0 && (
                                    <p className="mt-1 text-sm text-slate-600">
                                      {textoAtributos(variacion.atributos)}
                                    </p>
                                  )}
                                </div>

                                <p className="font-bold text-[#1E3A8A]">
                                  {dinero(variacion.precio)}
                                </p>
                              </div>

                              <p
                                className={`mt-3 flex items-center gap-1.5 text-sm font-semibold ${
                                  variacion.disponible
                                    ? 'text-emerald-700'
                                    : 'text-red-700'
                                }`}
                              >
                                {variacion.disponible ? (
                                  <CheckCircle2 size={16} />
                                ) : (
                                  <XCircle size={16} />
                                )}

                                {variacion.disponible
                                  ? 'Disponible'
                                  : 'No disponible'}
                              </p>
                            </div>
                          ))}
                        </div>
                      )}
                    </div>
                  )}
                </div>
              </article>
            );
          })}
        </section>
      )}

      {/* Paginación */}
      {resultado.total_resultados > POR_PAGINA && (
        <nav
          className="mt-8 flex flex-wrap items-center justify-center gap-3"
          aria-label="Paginación del catálogo"
        >
          <button
            type="button"
            onClick={() => setPagina((p) => Math.max(1, p - 1))}
            disabled={pagina <= 1 || cargando}
            className={BOTON}
          >
            <ChevronLeft size={17} />
            Anterior
          </button>

          <p
            className="text-sm font-medium text-slate-600"
            aria-current="page"
          >
            Página {pagina} de {totalPaginas}
          </p>

          <button
            type="button"
            onClick={() =>
              setPagina((p) => Math.min(totalPaginas, p + 1))
            }
            disabled={pagina >= totalPaginas || cargando}
            className={BOTON}
          >
            Siguiente
            <ChevronRight size={17} />
          </button>
        </nav>
      )}

      <p className="mt-8 text-center text-xs text-slate-500">
        {soloPropios
          ? 'Datos del servicio de catálogo de M3. Solo se muestran los productos publicados de tu comercio.'
          : 'Datos del servicio de catálogo de M3. Solo se muestran productos publicados de comercios activos.'}
      </p>
    </section>
  );
}
