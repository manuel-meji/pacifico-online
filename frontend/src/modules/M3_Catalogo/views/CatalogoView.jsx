import { useEffect, useMemo, useState } from 'react';
import {
  buscarProductos,
  listarCategorias,
  listarMarcas,
  obtenerProducto,
  textoDeError,
} from '../services/m3Api';

// Consulta del catálogo contra el servicio real de M3 (GET /api/m3/v1/productos, categorias y marcas).
// El servicio solo devuelve productos publicados de comercios activos. Las variaciones de cada producto
// se piden al abrirlo (GET /api/m3/v1/productos/{id_producto}).

const POR_PAGINA = 10;

const dinero = (precio) =>
  new Intl.NumberFormat('es-CR', {
    style: 'currency',
    currency: 'CRC',
    maximumFractionDigits: 0,
  }).format(precio);

function rangoDePrecio(producto) {
  if (producto.precio_desde === null || producto.precio_desde === undefined) return null;
  if (producto.precio_hasta && producto.precio_hasta !== producto.precio_desde) {
    return `${dinero(producto.precio_desde)} – ${dinero(producto.precio_hasta)}`;
  }
  return dinero(producto.precio_desde);
}

const textoAtributos = (atributos = []) =>
  atributos.map((a) => `${a.tipo_atributo}: ${a.valor}`).join(', ');

export default function CatalogoView() {
  const [busqueda, setBusqueda] = useState('');
  const [termino, setTermino] = useState('');
  const [idMarca, setIdMarca] = useState('');
  const [idCategoria, setIdCategoria] = useState('');
  const [orden, setOrden] = useState('');
  const [soloDisponibles, setSoloDisponibles] = useState(false);
  const [pagina, setPagina] = useState(1);

  const [marcas, setMarcas] = useState([]);
  const [categorias, setCategorias] = useState([]);
  const [resultado, setResultado] = useState({ productos: [], total_resultados: 0 });
  const [cargando, setCargando] = useState(true);
  const [error, setError] = useState(null);
  // Detalle por producto: { [id_producto]: { cargando, variaciones, error } }
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
  }, [termino, idMarca, idCategoria, orden, pagina]);

  const nombreMarca = useMemo(
    () => Object.fromEntries(marcas.map((m) => [m.id_marca, m.nombre])),
    [marcas]
  );
  const nombreCategoria = useMemo(
    () => Object.fromEntries(categorias.map((c) => [c.id_categoria, c.nombre])),
    [categorias]
  );

  // El servicio no filtra por disponibilidad: se aplica sobre la página cargada. Un producto sin
  // precio_desde no tiene ninguna variación disponible.
  const productos = soloDisponibles
    ? resultado.productos.filter((p) => p.precio_desde !== null && p.precio_desde !== undefined)
    : resultado.productos;

  const totalPaginas = Math.max(1, Math.ceil((resultado.total_resultados || 0) / POR_PAGINA));

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

    setDetalles((actuales) => ({ ...actuales, [idProducto]: { cargando: true } }));
    obtenerProducto(idProducto)
      .then((producto) =>
        setDetalles((actuales) => ({
          ...actuales,
          [idProducto]: { cargando: false, variaciones: producto.variaciones ?? [] },
        }))
      )
      .catch((falla) =>
        setDetalles((actuales) => ({
          ...actuales,
          [idProducto]: { cargando: false, error: textoDeError(falla) },
        }))
      );
  }

  return (
    <main className="min-h-screen bg-slate-100 px-4 py-6 text-slate-900 sm:px-6">
      <div className="mx-auto max-w-6xl">
        <header className="mb-6">
          <p className="text-sm font-semibold uppercase tracking-wide text-sky-700">
            Pacíficos Online · Módulo M3
          </p>
          <h1 className="mt-2 text-2xl font-bold sm:text-3xl">
            Catálogo de productos
          </h1>
          <p className="mt-2 text-sm text-slate-600">
            Consultá productos, sus variaciones, precios y disponibilidad.
          </p>
        </header>

        <section className="mb-6 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
          <h2 className="mb-4 text-lg font-semibold">Buscar y filtrar</h2>

          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <label className="text-sm font-medium">
              Buscar producto
              <input
                type="search"
                value={busqueda}
                onChange={(e) => setBusqueda(e.target.value)}
                placeholder="Nombre o descripción..."
                className="mt-1 block w-full min-w-0 rounded-lg border border-slate-300 px-3 py-2 font-normal"
              />
            </label>

            <label className="text-sm font-medium">
              Marca
              <select
                value={idMarca}
                onChange={cambiarFiltro(setIdMarca)}
                className="mt-1 block w-full min-w-0 rounded-lg border border-slate-300 px-3 py-2 font-normal"
              >
                <option value="">Todas las marcas</option>
                {marcas.map((m) => (
                  <option key={m.id_marca} value={m.id_marca}>{m.nombre}</option>
                ))}
              </select>
            </label>

            <label className="text-sm font-medium">
              Categoría
              <select
                value={idCategoria}
                onChange={cambiarFiltro(setIdCategoria)}
                className="mt-1 block w-full min-w-0 rounded-lg border border-slate-300 px-3 py-2 font-normal"
              >
                <option value="">Todas las categorías</option>
                {categorias.map((c) => (
                  <option key={c.id_categoria} value={c.id_categoria}>{c.nombre}</option>
                ))}
              </select>
            </label>

            <label className="text-sm font-medium">
              Ordenar por
              <select
                value={orden}
                onChange={cambiarFiltro(setOrden)}
                className="mt-1 block w-full min-w-0 rounded-lg border border-slate-300 px-3 py-2 font-normal"
              >
                <option value="">Sin orden</option>
                <option value="precio_asc">Precio: menor a mayor</option>
                <option value="precio_desc">Precio: mayor a menor</option>
                <option value="novedad">Más recientes</option>
              </select>
            </label>
          </div>

          <label className="mt-4 flex items-center gap-2 text-sm">
            <input
              type="checkbox"
              checked={soloDisponibles}
              onChange={(e) => setSoloDisponibles(e.target.checked)}
              className="h-4 w-4"
            />
            Mostrar solo productos con variaciones disponibles (en esta página)
          </label>

          <div className="mt-4 flex flex-wrap items-center gap-3">
            <button
              type="button"
              onClick={limpiarFiltros}
              className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold hover:bg-slate-50"
            >
              Limpiar filtros
            </button>
            <p className="text-sm text-slate-600" aria-live="polite">
              {cargando ? 'Buscando...' : `${resultado.total_resultados} productos encontrados`}
            </p>
          </div>
        </section>

        {error && (
          <div role="alert" className="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">
            {error}
          </div>
        )}

        {!cargando && !error && productos.length === 0 ? (
          <section className="rounded-xl border border-slate-200 bg-white p-8 text-center">
            <h2 className="font-semibold">No encontramos productos</h2>
            <p className="mt-2 text-sm text-slate-600">
              Probá con otra búsqueda o cambiá los filtros.
            </p>
          </section>
        ) : (
          <section className="grid grid-cols-1 gap-4 md:grid-cols-2">
            {productos.map((producto) => {
              const detalle = detalles[producto.id_producto];
              const precio = rangoDePrecio(producto);

              return (
                <article
                  key={producto.id_producto}
                  className="min-w-0 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5"
                >
                  <div className="flex flex-wrap items-start justify-between gap-2">
                    <div className="min-w-0">
                      {producto.id_marca && nombreMarca[producto.id_marca] && (
                        <p className="text-sm font-medium text-sky-700">{nombreMarca[producto.id_marca]}</p>
                      )}
                      <h2 className="mt-1 break-words text-lg font-bold">{producto.nombre}</h2>
                    </div>
                    {nombreCategoria[producto.id_categoria] && (
                      <span className="rounded-full bg-slate-100 px-3 py-1 text-xs text-slate-700">
                        {nombreCategoria[producto.id_categoria]}
                      </span>
                    )}
                  </div>

                  {producto.imagen_principal_url && (
                    <img
                      src={producto.imagen_principal_url}
                      alt={producto.nombre}
                      loading="lazy"
                      className="mt-3 h-40 w-full rounded-lg border border-slate-200 object-cover"
                    />
                  )}

                  {producto.descripcion && (
                    <p className="mt-2 text-sm text-slate-600">{producto.descripcion}</p>
                  )}

                  <p className={`mt-3 font-bold ${precio ? '' : 'text-sm font-medium text-red-700'}`}>
                    {precio ?? 'Sin variaciones disponibles'}
                  </p>

                  <button
                    type="button"
                    onClick={() => alternarVariaciones(producto.id_producto)}
                    aria-expanded={Boolean(detalle)}
                    className="mt-3 rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold hover:bg-slate-50"
                  >
                    {detalle ? 'Ocultar variaciones' : 'Ver variaciones'}
                  </button>

                  {detalle?.cargando && (
                    <p className="mt-3 text-sm text-slate-600">Cargando variaciones...</p>
                  )}

                  {detalle?.error && (
                    <p role="alert" className="mt-3 text-sm text-red-700">{detalle.error}</p>
                  )}

                  {detalle?.variaciones && (
                    <div className="mt-4 space-y-3">
                      {detalle.variaciones.length === 0 && (
                        <p className="text-sm text-slate-600">Este producto no tiene variaciones.</p>
                      )}
                      {detalle.variaciones.map((variacion) => (
                        <div key={variacion.id_variacion} className="rounded-lg border border-slate-200 p-3">
                          <div className="flex flex-wrap items-start justify-between gap-2">
                            <div className="min-w-0">
                              <p className="break-all text-sm font-semibold">{variacion.sku}</p>
                              {variacion.atributos?.length > 0 && (
                                <p className="mt-1 text-sm text-slate-600">{textoAtributos(variacion.atributos)}</p>
                              )}
                            </div>
                            <p className="font-bold">{dinero(variacion.precio)}</p>
                          </div>

                          <p className={`mt-2 text-sm font-medium ${
                            variacion.disponible ? 'text-emerald-700' : 'text-red-700'
                          }`}>
                            {variacion.disponible ? 'Disponible' : 'No disponible'}
                          </p>
                        </div>
                      ))}
                    </div>
                  )}
                </article>
              );
            })}
          </section>
        )}

        {resultado.total_resultados > POR_PAGINA && (
          <nav className="mt-6 flex flex-wrap items-center justify-center gap-3" aria-label="Paginación">
            <button
              type="button"
              onClick={() => setPagina((p) => Math.max(1, p - 1))}
              disabled={pagina <= 1 || cargando}
              className="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold hover:bg-slate-50 disabled:opacity-50"
            >
              Anterior
            </button>
            <p className="text-sm text-slate-600">Página {pagina} de {totalPaginas}</p>
            <button
              type="button"
              onClick={() => setPagina((p) => Math.min(totalPaginas, p + 1))}
              disabled={pagina >= totalPaginas || cargando}
              className="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold hover:bg-slate-50 disabled:opacity-50"
            >
              Siguiente
            </button>
          </nav>
        )}

        <p className="mt-6 text-xs text-slate-500">
          Datos del servicio de catálogo de M3. Solo se muestran productos publicados de comercios activos.
        </p>
      </div>
    </main>
  );
}
