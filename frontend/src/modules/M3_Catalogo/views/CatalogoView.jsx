
import { useMemo, useState } from 'react';
import {
  Search,
  SlidersHorizontal,
  RotateCcw,
  PackageSearch,
  Tag,
  CheckCircle2,
  XCircle,
  ArrowRight,
} from 'lucide-react';

// Datos de demostración para el prototipo.
// Las etiquetas se conservan porque RN-M3-13 las menciona.
// Su correspondencia con el modelo de datos queda pendiente de definición
// con el Comité. Este catálogo no consulta ni guarda datos en el servidor.
const productosIniciales = [
  {
    id: 1,
    nombre: 'Camiseta deportiva',
    marca: 'Pacific Sport',
    categoria: 'Ropa',
    etiquetas: ['deporte', 'verano'],
    descripcion: 'Camiseta ligera para actividades deportivas.',
    destacado: true,
    variaciones: [
      {
        sku: 'CAM-AZU-M',
        atributos: [
          { tipo_atributo: 'Color', valor: 'Azul' },
          { tipo_atributo: 'Talla', valor: 'M' },
        ],
        precio: 8500,
        tarifa_impuesto: 13,
        disponible: true,
      },
      {
        sku: 'CAM-ROJ-L',
        atributos: [
          { tipo_atributo: 'Color', valor: 'Rojo' },
          { tipo_atributo: 'Talla', valor: 'L' },
        ],
        precio: 9000,
        tarifa_impuesto: 13,
        disponible: false,
      },
    ],
  },
  {
    id: 2,
    nombre: 'Botella reutilizable',
    marca: 'EcoVida',
    categoria: 'Accesorios',
    etiquetas: ['ecologico', 'oficina'],
    descripcion: 'Botella reutilizable para llevar bebidas.',
    destacado: false,
    variaciones: [
      {
        sku: 'BOT-VER-750',
        atributos: [
          { tipo_atributo: 'Color', valor: 'Verde' },
          { tipo_atributo: 'Capacidad', valor: '750 ml' },
        ],
        precio: 5500,
        tarifa_impuesto: 13,
        disponible: true,
      },
      {
        sku: 'BOT-AZU-500',
        atributos: [
          { tipo_atributo: 'Color', valor: 'Azul' },
          { tipo_atributo: 'Capacidad', valor: '500 ml' },
        ],
        precio: 4200,
        tarifa_impuesto: 13,
        disponible: true,
      },
    ],
  },
  {
    id: 3,
    nombre: 'Mochila urbana',
    marca: 'Pacific Sport',
    categoria: 'Accesorios',
    etiquetas: ['viaje', 'oficina'],
    descripcion: 'Mochila para uso diario.',
    destacado: false,
    variaciones: [
      {
        sku: 'MOC-NEG-20',
        atributos: [
          { tipo_atributo: 'Color', valor: 'Negro' },
          { tipo_atributo: 'Capacidad', valor: '20 litros' },
        ],
        precio: 14500,
        tarifa_impuesto: 13,
        disponible: true,
      },
    ],
  },
  {
    id: 4,
    nombre: 'Taza cerámica',
    marca: 'Hogar Tico',
    categoria: 'Hogar',
    etiquetas: ['oficina', 'regalo'],
    descripcion: 'Taza de cerámica para bebidas calientes.',
    destacado: false,
    variaciones: [
      {
        sku: 'TAZ-BLA-01',
        atributos: [
          { tipo_atributo: 'Color', valor: 'Blanco' },
          { tipo_atributo: 'Capacidad', valor: '300 ml' },
        ],
        precio: 3000,
        tarifa_impuesto: 13,
        disponible: false,
      },
    ],
  },
];

const dinero = (precio) =>
  new Intl.NumberFormat('es-CR', {
    style: 'currency',
    currency: 'CRC',
    maximumFractionDigits: 0,
  }).format(precio);

const CAMPO =
  'mt-1 block w-full min-w-0 rounded-lg border border-slate-300 bg-white px-3 py-2 font-normal text-slate-900 focus:border-[#3b82f6] focus:outline-none focus:ring-2 focus:ring-[#3b82f6]/20';

const BOTON_SECUNDARIO =
  'inline-flex items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-[#3b82f6]';

export default function CatalogoView() {
  const [busqueda, setBusqueda] = useState('');
  const [marca, setMarca] = useState('');
  const [categoria, setCategoria] = useState('');
  const [etiqueta, setEtiqueta] = useState('');
  const [soloDisponibles, setSoloDisponibles] = useState(false);

  const marcas = [
    ...new Set(productosIniciales.map((producto) => producto.marca)),
  ];

  const categorias = [
    ...new Set(productosIniciales.map((producto) => producto.categoria)),
  ];

  const etiquetas = [
    ...new Set(productosIniciales.flatMap((producto) => producto.etiquetas)),
  ];

  const productosFiltrados = useMemo(() => {
    const texto = busqueda.trim().toLowerCase();

    return productosIniciales.filter((producto) => {
      const coincideTexto =
        !texto ||
        producto.nombre.toLowerCase().includes(texto) ||
        producto.marca.toLowerCase().includes(texto) ||
        producto.variaciones.some((variacion) => {
          const textoAtributos = variacion.atributos
            .map(
              (atributo) =>
                `${atributo.tipo_atributo} ${atributo.valor}`
            )
            .join(' ')
            .toLowerCase();

          return (
            variacion.sku.toLowerCase().includes(texto) ||
            textoAtributos.includes(texto)
          );
        });

      const coincideMarca = !marca || producto.marca === marca;
      const coincideCategoria =
        !categoria || producto.categoria === categoria;
      const coincideEtiqueta =
        !etiqueta || producto.etiquetas.includes(etiqueta);

      const tieneDisponibles = producto.variaciones.some(
        (variacion) => variacion.disponible
      );

      const coincideDisponibilidad =
        !soloDisponibles || tieneDisponibles;

      return (
        coincideTexto &&
        coincideMarca &&
        coincideCategoria &&
        coincideEtiqueta &&
        coincideDisponibilidad
      );
    });
  }, [busqueda, marca, categoria, etiqueta, soloDisponibles]);

  function limpiarFiltros() {
    setBusqueda('');
    setMarca('');
    setCategoria('');
    setEtiqueta('');
    setSoloDisponibles(false);
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
            Consulta los productos, sus variaciones, precios y disponibilidad.
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

          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <label className="text-sm font-medium">
              <span className="inline-flex items-center gap-2">
                <Search size={16} aria-hidden="true" />
                Buscar producto o SKU
              </span>
              <input
                type="search"
                value={busqueda}
                onChange={(evento) => setBusqueda(evento.target.value)}
                placeholder="Nombre, marca, SKU..."
                className={CAMPO}
              />
            </label>

            <label className="text-sm font-medium">
              Marca
              <select
                value={marca}
                onChange={(evento) => setMarca(evento.target.value)}
                className={CAMPO}
              >
                <option value="">Todas las marcas</option>
                {marcas.map((nombreMarca) => (
                  <option key={nombreMarca} value={nombreMarca}>
                    {nombreMarca}
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
              >
                <option value="">Todas las categorías</option>
                {categorias.map((nombreCategoria) => (
                  <option key={nombreCategoria} value={nombreCategoria}>
                    {nombreCategoria}
                  </option>
                ))}
              </select>
            </label>

            <label className="text-sm font-medium">
              <span className="inline-flex items-center gap-2">
                <Tag size={16} aria-hidden="true" />
                Etiqueta
              </span>
              <select
                value={etiqueta}
                onChange={(evento) => setEtiqueta(evento.target.value)}
                className={CAMPO}
              >
                <option value="">Todas las etiquetas</option>
                {etiquetas.map((nombreEtiqueta) => (
                  <option key={nombreEtiqueta} value={nombreEtiqueta}>
                    {nombreEtiqueta}
                  </option>
                ))}
              </select>
            </label>
          </div>

          <label className="mt-4 flex items-center gap-2 text-sm">
            <input
              type="checkbox"
              checked={soloDisponibles}
              onChange={(evento) =>
                setSoloDisponibles(evento.target.checked)
              }
              className="h-4 w-4 accent-[#1e3a8a]"
            />
            Mostrar solo productos con variaciones disponibles
          </label>

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
              {productosFiltrados.length}{' '}
              {productosFiltrados.length === 1
                ? 'producto encontrado'
                : 'productos encontrados'}
            </p>
          </div>
        </section>

        {productosFiltrados.length === 0 ? (
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
            {productosFiltrados.map((producto) => (
              <article
                key={producto.id}
                className="min-w-0 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5"
              >
                <div className="flex flex-wrap items-start justify-between gap-2">
                  <div className="min-w-0">
                    <p className="text-sm font-medium text-[#1e3a8a]">
                      {producto.marca}
                    </p>

                    <h2 className="mt-1 break-words text-lg font-bold">
                      {producto.nombre}
                    </h2>
                  </div>

                  <span className="rounded-full bg-slate-100 px-3 py-1 text-xs text-slate-700">
                    {producto.categoria}
                  </span>
                </div>

                {producto.destacado && (
                  <p className="mt-2 inline-block rounded-md bg-[#a3be4d]/20 px-2 py-1 text-xs font-semibold text-slate-800">
                    Producto destacado
                  </p>
                )}

                <p className="mt-2 text-sm text-slate-600">
                  {producto.descripcion}
                </p>

                <div className="mt-3 flex flex-wrap gap-2">
                  {producto.etiquetas.map((nombreEtiqueta) => (
                    <span
                      key={nombreEtiqueta}
                      className="inline-flex items-center gap-1 rounded bg-blue-50 px-2 py-1 text-xs text-[#1e3a8a]"
                    >
                      <Tag size={12} aria-hidden="true" />
                      {nombreEtiqueta}
                    </span>
                  ))}
                </div>

                <div className="mt-4 space-y-3">
                  {producto.variaciones.map((variacion) => (
                    <div
                      key={variacion.sku}
                      className="rounded-lg border border-slate-200 p-3"
                    >
                      <div className="flex flex-wrap items-start justify-between gap-2">
                        <div className="min-w-0">
                          <p className="break-all text-sm font-semibold">
                            {variacion.sku}
                          </p>

                          <ul className="mt-1 space-y-1 text-sm text-slate-600">
                            {variacion.atributos.map((atributo) => (
                              <li key={atributo.tipo_atributo}>
                                {atributo.tipo_atributo}: {atributo.valor}
                              </li>
                            ))}
                          </ul>
                        </div>

                        <p className="font-bold">
                          {dinero(variacion.precio)}
                        </p>
                      </div>

                      <p
                        className={`mt-2 flex items-center gap-2 text-sm font-medium ${
                          variacion.disponible
                            ? 'text-emerald-700'
                            : 'text-red-700'
                        }`}
                      >
                        {variacion.disponible ? (
                          <CheckCircle2 size={16} aria-hidden="true" />
                        ) : (
                          <XCircle size={16} aria-hidden="true" />
                        )}
                        {variacion.disponible ? 'Disponible' : 'Agotado'}
                      </p>
                    </div>
                  ))}
                </div>
              </article>
            ))}
          </section>
        )}

        <p className="mt-6 text-xs text-slate-500">
          Prototipo de consulta M3 con datos de demostración. Todavía no consulta
          el servicio real de catálogo ni la base de datos.
        </p>
      </div>
    </main>
  );
}
