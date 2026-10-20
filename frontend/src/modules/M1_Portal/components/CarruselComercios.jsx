
import { comerciosTemporales } from '../services/datosMockPortal';

export default function CarruselComercios({ onVerComercio }) {  
  const comercios = comerciosTemporales.filter(
    (comercio) => comercio.habilitado === true
  );

  return (
    <section className="mt-12">
      <div className="mb-6">
        <h2 className="text-2xl font-bold text-brandDarkBlue">
          Explorá nuestros comercios
        </h2>

        <p className="mt-2 text-sm text-gray-600">
          Conocé los comercios que forman parte de Pacíficos Online.
        </p>
      </div>

      <div className="flex gap-5 overflow-x-auto pb-5">
        {comercios.map((comercio) => (
          <article
            key={comercio.idComercio}
            className="w-64 shrink-0 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm"
          >
            <div className="flex h-40 items-center justify-center bg-blue-50">
              <span className="px-4 text-center text-lg font-semibold text-brandDarkBlue">
                {comercio.nombre}
              </span>
            </div>

            <div className="p-5">
              <span className="text-xs font-semibold text-brandGreen">
                {comercio.categoria}
              </span>

              <h3 className="mt-2 text-lg font-bold text-gray-800">
                {comercio.nombre}
              </h3>

              <p className="mt-2 min-h-12 text-sm text-gray-600">
                {comercio.descripcion}
              </p>

              
{comercio.nombre === 'TecnoPacífico' ? (
  <button
    type="button"
    onClick={() => onVerComercio?.()}
    className="mt-4 inline-block text-sm font-semibold text-brandDarkBlue hover:underline"
  >
    Conocer comercio →
  </button>
) : (
  <span className="mt-4 inline-block text-sm text-gray-400">
    Próximamente
  </span>
)}

            </div>
          </article>
        ))}
      </div>
    </section>
  );
}
