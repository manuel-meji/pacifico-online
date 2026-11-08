import Icono from './IconoComercio';
import Imagen from './Imagen';

// Comercios en fila horizontal (destacados de GET /destacados o página de GET /comercios).
// Cada comercio trae { id_comercio, nombre_comercial, logotipo } según el contrato.
export default function CarruselComercios({ comercios, titulo, descripcion, onVerComercio }) {
  return (
    <section className="mt-10">
      <div className="mb-4">
        <h2 className="text-2xl font-bold text-brandDarkBlue">{titulo}</h2>
        {descripcion && <p className="mt-1 text-sm text-gray-600">{descripcion}</p>}
      </div>

      <ul className="flex gap-4 overflow-x-auto pb-4">
        {comercios.map((comercio) => (
          <li key={comercio.id_comercio} className="w-52 shrink-0 sm:w-60">
            <button type="button" onClick={() => onVerComercio?.(comercio.id_comercio)}
              className="flex h-full w-full flex-col overflow-hidden rounded-xl border border-gray-100 bg-white text-left shadow-sm transition hover:shadow-md focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brandBlue">
              <span className="grid h-32 w-full place-items-center bg-blue-50">
                <Imagen src={comercio.logotipo} className="h-20 w-20 rounded-full object-cover" icono="tienda" claseIcono="h-10 w-10 text-brandDarkBlue" />
              </span>
              <span className="flex flex-1 flex-col p-4">
                <span className="line-clamp-2 font-bold text-gray-800">{comercio.nombre_comercial}</span>
                <span className="mt-auto inline-flex items-center gap-1 pt-3 text-sm font-semibold text-brandBlue">
                  Conocer comercio <Icono nombre="derecha" className="h-4 w-4" />
                </span>
              </span>
            </button>
          </li>
        ))}
      </ul>
    </section>
  );
}
