import { useEffect, useState } from 'react';
import {
  Search,
  SlidersHorizontal,
  RotateCcw,
  PackageSearch,
  CheckCircle2,
  AlertCircle,
  ArrowRight,
} from 'lucide-react';
import {
  buscarProductos,
  listarCategorias,
  listarMarcas,
} from '../services/m3Api';

const CAMPO =
  'mt-1 block w-full min-w-0 rounded-lg border border-slate-300 bg-white px-3 py-2 font-normal text-slate-900 focus:border-[#3b82f6] focus:outline-none focus:ring-2 focus:ring-[#3b82f6]/20 disabled:cursor-not-allowed disabled:bg-slate-100';

const BOTON_SECUNDARIO =
  'inline-flex items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-[#3b82f6]';

const dinero = (precio) => {
  if (precio === null || precio === undefined || precio === '') {
    return 'Precio no disponible';
  }

  return new Intl.NumberFormat('es-CR', {
    style: 'currency',
    currency: 'CRC',
    maximumFractionDigits: 0,
  }).format(Number(precio));
};

function obtenerDatos(respuesta) {
  if (!respuesta?.ok) {
    throw new Error(
      respuesta?.error?.mensaje || 'No fue posible consultar el catálogo.'
    );
  }

  return respuesta.datos;
}

function obtenerLista(respuesta, posiblesClaves = []) {
  const datos = obtenerDatos(respuesta);

  if (Array.isArray(datos)) return datos;

  for (const clave of posiblesClaves) {
    if (Array.isArray(datos?.[clave])) return datos[clave];
  }

  return [];
}

function nombreRelacionado(valor) {
  if (!valor) return '';
  if (typeof valor === 'string') return valor;
  return valor.nombre || valor.descripcion || '';
}

export default function CatalogoView() {
  const [busqueda, setBusqueda] = useState('');
  const [marca, setMarca] = useState('');
  const [categoria, setCategoria] = useState('');

  const [productos, setProductos] = useState([]);
  const [marcas, setMarcas] = useState([]);
  const [categorias, setCategorias] = useState([]);

  const [cargandoCatalogo, setCargandoCatalogo] = useState(true);
  const [cargandoFiltros, setCargandoFiltros] = useState(true);
  const [errorCatalogo, setErrorCatalogo] = useState('');
  const [errorFiltros, setErrorFiltros] = useState('');
  const [totalResultados, setTotalResultados] = useState(0);

  useEffect(() => {
    let activo = true;

    async function cargarFiltros() {
      setCargandoFiltros(true);
      setErrorFiltros('');

      try {
        const [respuestaCategorias, respuestaMarcas] = await Promise.all([
          listarCategorias(),
          listarMarcas(),
        ]);

        const listaCategorias = obtenerLista(respuestaCategorias, [
          'categorias',
        ]);
        const listaMarcas = obtenerLista(respuestaMarcas, ['marcas']);

        if (activo) {
          setCategorias(listaCategorias);
          setMarcas(listaMarcas);
        }
      } catch (error) {
        if (activo) {
          setErrorFiltros(
            error.message || 'No fue posible cargar las categorías y marcas.'
          );
        }
      } finally {
        if (activo) setCargandoFiltros(false);
      }
    }

    cargarFiltros();

    return () => {
      activo = false;
    };
  }, []);

  useEffect(() => {
    let activo = true;

    async function cargarProductos() {
      setCargandoCatalogo(true);
      setErrorCatalogo('');

      try {
        const filtros = {
          q: busqueda.trim() || undefined,
          id_marca: marca || undefined,
          id_categoria: categoria || undefined,
          pagina: 1,
          por_pagina: 100,
        };

        const respuesta = await buscarProductos(filtros);
        const datos = obtenerDatos(respuesta);

        const listaProductos = Array.isArray(datos)
          ? datos
          : datos?.productos || datos?.data || [];

        if (activo) {
          setProductos(listaProductos);
          setTotalResultados(
            datos?.total_resultados ??
              datos?.total ??
              listaProductos.length
          );
        }
      } catch (error) {
        if (activo) {
          setProductos([]);
          setTotalResultados(0);
          setErrorCatalogo(
            error.message || 'No fue posible cargar los productos.'
          );
        }
      } finally {
        if (activo) setCargandoCatalogo(false);
      }
    }

    cargarProductos();

    return () => {
      activo = false;
    };
  }, [busqueda, marca, categoria]);

  function limpiarFiltros() {
    setBusqueda('');
    setMarca('');
    setCategoria('');
  }

  return (
    <main className="min-h-screen bg-[#f8fafc] px-4 py-6 text-slate-900 sm:px-6">
      <div className="mx-auto max-w-6xl">
        <header className="mb-6">
          <p className="text-sm font-semibold uppercase tracking-wide text-[#1e3a8a]">
            Pacíficos Online · Módulo M3
          </p>

          <h1 className="mt-2 text-2xl font-bold text-[#1e3a8a] sm:text-3xl">
            Catálogo de productos
          </h1>

          <p className="mt-2 text-sm text-slate-600">
            Consulta los productos publicados en el catálogo.
          </p>

          <a
            href="/prototipos/M3_Catalogos/m3.html"
            className="mt-3 inline-flex items-center gap-2 text-sm font-semibold text-[#3b82f6] underline underline-offset-2 focus:outline-none focus:ring-2 focus:ring-[#3b82f6]"
          >
            Registrar un producto
            <ArrowRight size={16} aria-hidden="true" />
          </a>
        </header>

        <section className="mb-6 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
          <h2 className="mb-4 flex items-center gap-2 text-lg font-semibold text-[#1e3a8a]">
            <SlidersHorizontal size={20} aria-hidden="true" />
            Buscar y filtrar
          </h2>

          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <label className="text-sm font-medium">
              <span className="inline-flex items-center gap-2">
                <Search size={16} aria-hidden="true" />
                Buscar producto o SKU
              </span>
              <input
                type="search"
                value={busqueda}
                onChange={(evento) => setBusqueda(evento.target.value)}
                placeholder="Nombre o SKU..."
                className={CAMPO}
              />
            </label>

            <label className="text-sm font-medium">
              Marca
              <select
                value={marca}
                onChange={(evento) => setMarca(evento.target.value)}
                className={CAMPO}
                disabled={cargandoFiltros || Boolean(errorFiltros)}
              >
                <option value="">Todas las marcas</option>
                {marcas.map((item) => (
                  <option key={item.id_marca} value={item.id_marca}>
                    {item.nombre}
                  </option>
                ))}
              </select>
            </label>

            <label className="text-sm font-medium">
              Categoría
              <select
                value={categoria}
                onChange={(evento) => setCategoria(evento.target.value)}
                className={CAMPO}
                disabled={cargandoFiltros || Boolean(errorFiltros)}
              >
                <option value="">Todas las categorías</option>
                {categorias.map((item) => (
                  <option key={item.id_categoria} value={item.id_categoria}>
                    {item.nombre}
                  </option>
                ))}
              </select>
            </label>
          </div>

          {errorFiltros && (
            <p className="mt-3 flex items-start gap-2 text-sm text-red-700">
              <AlertCircle size={17} className="mt-0.5 shrink-0" />
              {errorFiltros}
            </p>
          )}

          <div className="mt-4 flex flex-wrap items-center gap-3">
            <button
              type="button"
              onClick={limpiarFiltros}
              className={BOTON_SECUNDARIO}
            >
              <RotateCcw size={16} aria-hidden="true" />
              Limpiar filtros
            </button>

            <p
              className="text-sm text-slate-600"
              aria-live="polite"
              aria-atomic="true"
            >
              {cargandoCatalogo
                ? 'Consultando catálogo...'
                : `${totalResultados} ${
                    totalResultados === 1
                      ? 'producto encontrado'
                      : 'productos encontrados'
                  }`}
            </p>
          </div>
        </section>

        {cargandoCatalogo ? (
          <section
            className="rounded-xl border border-slate-200 bg-white p-8 text-center"
            aria-live="polite"
          >
            <div className="mx-auto h-8 w-8 animate-spin rounded-full border-4 border-slate-200 border-t-[#1e3a8a]" />
            <p className="mt-3 text-sm text-slate-600">
              Cargando productos...
            </p>
          </section>
        ) : errorCatalogo ? (
          <section
            className="rounded-xl border border-red-200 bg-white p-8 text-center"
            role="alert"
          >
            <AlertCircle
              size={36}
              className="mx-auto text-red-600"
              aria-hidden="true"
            />
            <h2 className="mt-3 font-semibold text-[#1e3a8a]">
              No se pudo cargar el catálogo
            </h2>
            <p className="mt-2 text-sm text-slate-600">{errorCatalogo}</p>
            <button
              type="button"
              onClick={() => {
                setBusqueda((valor) => `${valor}`);
                setMarca((valor) => `${valor}`);
                setCategoria((valor) => `${valor}`);
              }}
              className={`${BOTON_SECUNDARIO} mt-4`}
            >
              <RotateCcw size={16} aria-hidden="true" />
              Volver a intentar
            </button>
          </section>
        ) : productos.length === 0 ? (
          <section className="rounded-xl border border-slate-200 bg-white p-8 text-center">
            <PackageSearch
              size={36}
              className="mx-auto text-[#1e3a8a]"
              aria-hidden="true"
            />
            <h2 className="mt-3 font-semibold text-[#1e3a8a]">
              No se encontraron productos
            </h2>
            <p className="mt-2 text-sm text-slate-600">
              Prueba con otra búsqueda o cambia los filtros.
            </p>
          </section>
        ) : (
          <section
            aria-label="Resultados del catálogo"
            className="grid grid-cols-1 gap-4 md:grid-cols-2"
          >
            {productos.map((producto) => {
              const marcaNombre =
                nombreRelacionado(producto.marca) || producto.nombre_marca;
              const categoriaNombre =
                nombreRelacionado(producto.categoria) ||
                producto.nombre_categoria;
              const precioDesde =
                producto.precio_desde ?? producto.precio_minimo;
              const precioHasta =
                producto.precio_hasta ?? producto.precio_maximo;
              const imagen =
                producto.imagen_principal_url || producto.imagen_url;

              return (
                <article
                  key={producto.id_producto}
                  className="min-w-0 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"
                >
                  {imagen && (
                    <img
                      src={imagen}
                      alt={`Imagen de ${producto.nombre}`}
                      className="h-48 w-full bg-slate-100 object-contain"
                      loading="lazy"
                    />
                  )}

                  <div className="p-4 sm:p-5">
                    <div className="flex flex-wrap items-start justify-between gap-2">
                      <div className="min-w-0">
                        {marcaNombre && (
                          <p className="text-sm font-medium text-[#1e3a8a]">
                            {marcaNombre}
                          </p>
                        )}
                        <h2 className="mt-1 break-words text-lg font-bold">
                          {producto.nombre}
                        </h2>
                      </div>

                      {categoriaNombre && (
                        <span className="rounded-full bg-slate-100 px-3 py-1 text-xs text-slate-700">
                          {categoriaNombre}
                        </span>
                      )}
                    </div>

                    {producto.destacado && (
                      <p className="mt-2 inline-block rounded-md bg-[#a3be4d]/20 px-2 py-1 text-xs font-semibold text-slate-800">
                        Producto destacado
                      </p>
                    )}

                    {producto.descripcion && (
                      <p className="mt-2 text-sm text-slate-600">
                        {producto.descripcion}
                      </p>
                    )}

                    <div className="mt-4 border-t border-slate-100 pt-3">
                      <p className="text-xs font-medium uppercase tracking-wide text-slate-500">
                        Precio
                      </p>
                      <p className="mt-1 text-lg font-bold text-[#1e3a8a]">
                        {precioDesde === undefined && precioHasta === undefined
                          ? 'Precio no disponible'
                          : precioHasta === undefined ||
                              Number(precioDesde) === Number(precioHasta)
                            ? dinero(precioDesde)
                            : `${dinero(precioDesde)} - ${dinero(precioHasta)}`}
                      </p>
                    </div>

                    {producto.estado_producto && (
                      <p className="mt-3 flex items-center gap-2 text-sm text-emerald-700">
                        <CheckCircle2 size={16} aria-hidden="true" />
                        {producto.estado_producto}
                      </p>
                    )}
                  </div>
                </article>
              );
            })}
          </section>
        )}

        <p className="mt-6 text-xs text-slate-500">
          Los productos mostrados provienen del servicio de catálogo M3.
        </p>
      </div>
    </main>
  );
}
