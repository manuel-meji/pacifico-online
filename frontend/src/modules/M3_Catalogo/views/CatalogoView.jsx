import { useMemo, useState } from 'react';

const productosIniciales = [
  {
    id: 1,
    nombre: 'Camiseta deportiva',
    marca: 'Pacific Sport',
    categoria: 'Ropa',
    etiquetas: ['deporte', 'verano'],
    descripcion: 'Camiseta ligera para actividades deportivas.',
    variaciones: [
      { sku: 'CAM-AZU-M', atributos: 'Color azul, talla M', precio: 8500, disponible: true },
      { sku: 'CAM-ROJ-L', atributos: 'Color rojo, talla L', precio: 9000, disponible: false },
    ],
  },
  {
    id: 2,
    nombre: 'Botella reutilizable',
    marca: 'EcoVida',
    categoria: 'Accesorios',
    etiquetas: ['ecologico', 'oficina'],
    descripcion: 'Botella reutilizable para llevar bebidas.',
    variaciones: [
      { sku: 'BOT-VER-750', atributos: 'Color verde, 750 ml', precio: 5500, disponible: true },
      { sku: 'BOT-AZU-500', atributos: 'Color azul, 500 ml', precio: 4200, disponible: true },
    ],
  },
  {
    id: 3,
    nombre: 'Mochila urbana',
    marca: 'Pacific Sport',
    categoria: 'Accesorios',
    etiquetas: ['viaje', 'oficina'],
    descripcion: 'Mochila para uso diario.',
    variaciones: [
      { sku: 'MOC-NEG-20', atributos: 'Color negro, 20 litros', precio: 14500, disponible: true },
    ],
  },
  {
    id: 4,
    nombre: 'Taza cerámica',
    marca: 'Hogar Tico',
    categoria: 'Hogar',
    etiquetas: ['oficina', 'regalo'],
    descripcion: 'Taza de cerámica para bebidas calientes.',
    variaciones: [
      { sku: 'TAZ-BLA-01', atributos: 'Color blanco, 300 ml', precio: 3000, disponible: false },
    ],
  },
];

const dinero = (precio) =>
  new Intl.NumberFormat('es-CR', {
    style: 'currency',
    currency: 'CRC',
    maximumFractionDigits: 0,
  }).format(precio);

export default function CatalogoView() {
  const [busqueda, setBusqueda] = useState('');
  const [marca, setMarca] = useState('');
  const [categoria, setCategoria] = useState('');
  const [etiqueta, setEtiqueta] = useState('');
  const [soloDisponibles, setSoloDisponibles] = useState(false);

  const marcas = [...new Set(productosIniciales.map((p) => p.marca))];
  const categorias = [...new Set(productosIniciales.map((p) => p.categoria))];
  const etiquetas = [...new Set(productosIniciales.flatMap((p) => p.etiquetas))];

  const productosFiltrados = useMemo(() => {
    const texto = busqueda.trim().toLowerCase();

    return productosIniciales.filter((producto) => {
      const coincideTexto =
        !texto ||
        producto.nombre.toLowerCase().includes(texto) ||
        producto.marca.toLowerCase().includes(texto) ||
        producto.variaciones.some((v) =>
          v.sku.toLowerCase().includes(texto) ||
          v.atributos.toLowerCase().includes(texto)
        );

      const coincideMarca = !marca || producto.marca === marca;
      const coincideCategoria = !categoria || producto.categoria === categoria;
      const coincideEtiqueta = !etiqueta || producto.etiquetas.includes(etiqueta);

      const tieneDisponibles = producto.variaciones.some((v) => v.disponible);
      const coincideDisponibilidad = !soloDisponibles || tieneDisponibles;

      return coincideTexto && coincideMarca && coincideCategoria &&
        coincideEtiqueta && coincideDisponibilidad;
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
              Buscar producto o SKU
              <input
                type="search"
                value={busqueda}
                onChange={(e) => setBusqueda(e.target.value)}
                placeholder="Nombre, marca, SKU..."
                className="mt-1 block w-full min-w-0 rounded-lg border border-slate-300 px-3 py-2 font-normal"
              />
            </label>

            <label className="text-sm font-medium">
              Marca
              <select
                value={marca}
                onChange={(e) => setMarca(e.target.value)}
                className="mt-1 block w-full min-w-0 rounded-lg border border-slate-300 px-3 py-2 font-normal"
              >
                <option value="">Todas las marcas</option>
                {marcas.map((m) => <option key={m}>{m}</option>)}
              </select>
            </label>

            <label className="text-sm font-medium">
              Categoría
              <select
                value={categoria}
                onChange={(e) => setCategoria(e.target.value)}
                className="mt-1 block w-full min-w-0 rounded-lg border border-slate-300 px-3 py-2 font-normal"
              >
                <option value="">Todas las categorías</option>
                {categorias.map((c) => <option key={c}>{c}</option>)}
              </select>
            </label>

            <label className="text-sm font-medium">
              Etiqueta
              <select
                value={etiqueta}
                onChange={(e) => setEtiqueta(e.target.value)}
                className="mt-1 block w-full min-w-0 rounded-lg border border-slate-300 px-3 py-2 font-normal"
              >
                <option value="">Todas las etiquetas</option>
                {etiquetas.map((e) => <option key={e}>{e}</option>)}
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
            Mostrar solo productos con variaciones disponibles
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
              {productosFiltrados.length} productos encontrados
            </p>
          </div>
        </section>

        {productosFiltrados.length === 0 ? (
          <section className="rounded-xl border border-slate-200 bg-white p-8 text-center">
            <h2 className="font-semibold">No encontramos productos</h2>
            <p className="mt-2 text-sm text-slate-600">
              Probá con otra búsqueda o cambiá los filtros.
            </p>
          </section>
        ) : (
          <section className="grid grid-cols-1 gap-4 md:grid-cols-2">
            {productosFiltrados.map((producto) => (
              <article
                key={producto.id}
                className="min-w-0 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5"
              >
                <div className="flex flex-wrap items-start justify-between gap-2">
                  <div className="min-w-0">
                    <p className="text-sm font-medium text-sky-700">{producto.marca}</p>
                    <h2 className="mt-1 break-words text-lg font-bold">{producto.nombre}</h2>
                  </div>
                  <span className="rounded-full bg-slate-100 px-3 py-1 text-xs text-slate-700">
                    {producto.categoria}
                  </span>
                </div>

                <p className="mt-2 text-sm text-slate-600">{producto.descripcion}</p>

                <div className="mt-3 flex flex-wrap gap-2">
                  {producto.etiquetas.map((e) => (
                    <span key={e} className="rounded bg-sky-50 px-2 py-1 text-xs text-sky-800">
                      #{e}
                    </span>
                  ))}
                </div>

                <div className="mt-4 space-y-3">
                  {producto.variaciones.map((variacion) => (
                    <div key={variacion.sku} className="rounded-lg border border-slate-200 p-3">
                      <div className="flex flex-wrap items-start justify-between gap-2">
                        <div className="min-w-0">
                          <p className="break-all text-sm font-semibold">{variacion.sku}</p>
                          <p className="mt-1 text-sm text-slate-600">{variacion.atributos}</p>
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
              </article>
            ))}
          </section>
        )}

        <p className="mt-6 text-xs text-slate-500">
          Prototipo de consulta M3 con datos de prueba. No consulta todavía el servicio
          real de catálogo ni la base de datos.
        </p>
      </div>
    </main>
  );
}