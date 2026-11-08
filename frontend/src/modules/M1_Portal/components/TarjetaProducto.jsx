import { colones } from '../services/formato';
import Icono from './IconoComercio';
import Imagen from './Imagen';

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

// Precio de un ProductoResumen: "Desde ₡X" si sus variaciones cuestan distinto (precio_hasta > precio).
export function PrecioProducto({ producto, className = 'text-base' }) {
  const rango = producto.precio_hasta != null && producto.precio_hasta > producto.precio;
  return (
    <p className={`font-extrabold text-brandDarkBlue ${className}`}>
      {rango && <span className="text-xs font-normal text-gray-500">Desde </span>}
      {colones(producto.precio)}
    </p>
  );
}

// Tarjeta de un ProductoResumen del contrato (id_producto, nombre, imagen_principal, precio, precio_hasta,
// moneda, disponibilidad, comercio { id_comercio, nombre_comercial }). Estructura base del design.md (5.2).
export default function TarjetaProducto({ producto, onAbrir, mostrarComercio = true }) {
  const agotado = producto.disponibilidad === 'Agotado';
  return (
    <article className="group flex min-w-0 flex-col rounded-xl border border-gray-100 bg-white p-3 shadow-sm transition hover:shadow-md sm:p-4">
      <button type="button" onClick={() => onAbrir?.(producto)} className="rounded-lg text-left focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brandBlue" aria-label={`Ver ficha de ${producto.nombre}`}>
        <div className="grid aspect-square place-items-center overflow-hidden rounded-lg bg-gray-50 text-gray-300">
          <Imagen src={producto.imagen_principal} loading="lazy" className={`h-full w-full object-contain ${agotado ? 'opacity-60' : ''}`} />
        </div>
        <h3 className="mt-3 line-clamp-2 min-h-10 break-words text-sm font-medium text-gray-800 group-hover:text-brandBlue">{producto.nombre}</h3>
      </button>
      {mostrarComercio && producto.comercio?.nombre_comercial && (
        <p className="mt-1 truncate text-xs text-gray-500">{producto.comercio.nombre_comercial}</p>
      )}
      <PrecioProducto producto={producto} className="mt-2 text-base" />
      <div className="mt-2">
        {producto.disponibilidad === 'Disponible' && <BadgeDisponible />}
        {agotado && <BadgeAgotado />}
      </div>
    </article>
  );
}
