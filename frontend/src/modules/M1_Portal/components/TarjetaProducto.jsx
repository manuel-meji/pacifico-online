import { colones } from '../services/micrositio';
import Icono from './IconoComercio';

// Badges de estado con las clases del design.md (sección 5.3).
export function BadgeDisponible() {
  return (
    <span className="inline-flex items-center gap-1.5 rounded-full border border-green-200 bg-green-50 px-2.5 py-1 text-xs font-semibold text-green-700">
      <Icono nombre="disponible" className="h-3.5 w-3.5" /> Disponible
    </span>
  );
}

export function BadgeAgotado() {
  return (
    <span className="inline-flex items-center gap-1.5 rounded-full border border-red-200 bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-700">
      <Icono nombre="alerta" className="h-3.5 w-3.5" /> Agotado
    </span>
  );
}

// Tarjeta de un ProductoResumenDTO (estructura base del design.md, sección 5.2).
// Se puede reutilizar en la búsqueda, los destacados y los relacionados.
// `icono` es el ícono de la categoría, que ocupa el lugar de la foto mientras no haya imágenes reales.
export default function TarjetaProducto({ producto, icono = 'paquete', onAbrir }) {
  const agotado = producto.disponibilidad === 'Agotado';
  return (
    <article className="group flex flex-col rounded-xl border border-gray-100 bg-white p-4 shadow-sm transition hover:shadow-md">
      <button onClick={() => onAbrir?.(producto)} className="rounded-lg text-left focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brandBlue" aria-label={`Ver ficha de ${producto.nombre}`}>
        <div className="grid aspect-square place-items-center rounded-lg bg-gray-50 text-gray-300">
          <Icono nombre={icono} className={`h-14 w-14 ${agotado ? 'opacity-50' : 'text-brandBlue/40'}`} />
        </div>
        <h3 className="mt-3 line-clamp-2 min-h-10 text-sm font-medium text-gray-800 group-hover:text-brandBlue">{producto.nombre}</h3>
      </button>
      <p className="mt-1 text-xs text-gray-500">{producto.marca}</p>
      <p className="mt-2 text-base font-extrabold text-brandDarkBlue">
        {producto.precioHasta ? <><span className="text-xs font-normal text-gray-500">Desde </span>{colones(producto.precio)}</> : colones(producto.precio)}
      </p>
      <div className="mt-2">
        {producto.disponibilidad === 'Disponible' && <BadgeDisponible />}
        {agotado && <BadgeAgotado />}
      </div>
    </article>
  );
}
