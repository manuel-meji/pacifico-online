import { colones } from '../services/micrositio';

// Tarjeta de un ProductoResumenDTO. Se puede reutilizar en la búsqueda, los destacados y los relacionados.
export default function TarjetaProducto({ producto, icono = '📦', onAbrir }) {
  const agotado = producto.disponibilidad === 'Agotado';
  return (
    <article className="group flex flex-col rounded-lg border border-gray-200 bg-white p-3 transition hover:shadow-md">
      <button onClick={() => onAbrir?.(producto)} className="text-left" aria-label={`Ver ficha de ${producto.nombre}`}>
        <div className="relative grid aspect-square place-items-center rounded-md bg-gray-100 text-5xl">
          <span aria-hidden className={agotado ? 'opacity-40 grayscale' : ''}>{icono}</span>
          {agotado && (
            <span className="absolute left-2 top-2 rounded bg-gray-700 px-2 py-0.5 text-xs font-semibold text-white">AGOTADO</span>
          )}
        </div>
        <h3 className="mt-3 line-clamp-2 min-h-10 text-sm text-gray-800 group-hover:text-brandBlue">{producto.nombre}</h3>
      </button>
      <p className="mt-1 text-xs text-gray-500">{producto.marca}</p>
      <p className="mt-2 text-base font-extrabold text-brandDarkBlue">
        {producto.precioHasta ? <><span className="text-xs font-normal text-gray-500">Desde </span>{colones(producto.precio)}</> : colones(producto.precio)}
      </p>
      {producto.disponibilidad === 'Disponible' && <p className="mt-1 text-xs font-medium text-green-600">Disponible</p>}
      {agotado && <p className="mt-1 text-xs text-gray-500">Sin existencias por ahora</p>}
    </article>
  );
}
