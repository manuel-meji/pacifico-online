import PaginaPortal, { usePortal } from '../components/PaginaPortal';
import { LARGO_MINIMO_TERMINO } from '../components/FiltrosComercio';
import Paginacion from '../components/Paginacion';
import { CargandoTarjetas, ErrorVista, Vacio } from '../components/EstadosVista';
import Icono from '../components/IconoComercio';
import Imagen from '../components/Imagen';
import { listarComercios } from '../services/m1Api';
import useConsulta from '../services/useConsulta';

const POR_PAGINA = 20;

// Búsqueda de comercios por nombre: GET /api/m1/v1/comercios?q= (contrato M1 1.6.0, SC-12).
// `criterio` llega de la URL: { q, pagina }. El término necesita 2 caracteres, igual que la búsqueda de
// productos; si no los tiene, se avisa sin llamar a la API.
export default function BusquedaComerciosView({ criterio }) {
  const { ir } = usePortal();
  const termino = (criterio.q ?? '').trim();
  const pagina = criterio.pagina ?? 1;
  const cumpleRegla = termino.length >= LARGO_MINIMO_TERMINO;

  const consulta = useConsulta(() => listarComercios(pagina, POR_PAGINA, termino), [termino, pagina], cumpleRegla);
  const datos = consulta.datos;
  const totalPaginas = datos ? Math.ceil(datos.total / POR_PAGINA) : 0;

  return (
    <PaginaPortal terminoBusqueda={termino} tipoBusqueda="comercios">
      <nav aria-label="Ruta de navegación" className="py-3 text-sm text-gray-500">
        <ol className="flex flex-wrap items-center gap-1">
          <li><button type="button" onClick={() => ir('inicio')} className="inline-flex min-h-[44px] items-center gap-1 hover:text-brandBlue sm:min-h-0"><Icono nombre="inicio" className="h-4 w-4" /> Inicio</button></li>
          <li aria-hidden><Icono nombre="derecha" className="h-3.5 w-3.5 text-gray-400" /></li>
          <li aria-current="page" className="text-gray-800">Comercios</li>
        </ol>
      </nav>

      <h1 className="break-words text-2xl font-bold text-brandDarkBlue">{termino ? `Comercios para "${termino}"` : 'Buscar comercios'}</h1>

      <div className="mt-6">
        {!cumpleRegla && (
          <Vacio icono="tienda" titulo="Escribí al menos 2 caracteres">
            Usá el buscador de arriba con la opción Comercios para encontrar un comercio por su nombre.
          </Vacio>
        )}
        {cumpleRegla && consulta.estado === 'cargando' && <CargandoTarjetas cantidad={4} etiqueta="Buscando comercios" />}
        {cumpleRegla && consulta.estado === 'error' && <ErrorVista resultado={consulta.resultado} onReintentar={consulta.reintentar} />}
        {cumpleRegla && datos && ['listo', 'actualizando'].includes(consulta.estado) && (
          datos.comercios.length === 0 ? (
            <Vacio icono="tienda" titulo="No encontramos comercios"
              accion={(
                <button type="button" onClick={() => ir('busqueda', { q: termino })} className="flex items-center gap-2 rounded-lg border border-brandBlue px-4 py-2 font-semibold text-brandBlue transition hover:bg-blue-50">
                  <Icono nombre="buscar" className="h-4 w-4" /> Buscar "{termino}" en productos
                </button>
              )}>
              Probá con otra parte del nombre del comercio.
            </Vacio>
          ) : (
            <>
              <p className="mb-4 text-sm text-gray-600" aria-live="polite">
                {datos.total === 1 ? '1 comercio encontrado' : `${datos.total} comercios encontrados`}
              </p>
              <ul className={`grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 ${consulta.estado === 'actualizando' ? 'opacity-60' : ''}`}>
                {datos.comercios.map((comercio) => (
                  <li key={comercio.id_comercio} className="min-w-0">
                    <button type="button" onClick={() => ir('comercio', { id: comercio.id_comercio })}
                      className="flex h-full w-full flex-col overflow-hidden rounded-xl border border-gray-100 bg-white text-left shadow-sm transition hover:shadow-md focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brandBlue">
                      <span className="grid h-28 w-full place-items-center bg-blue-50">
                        <Imagen src={comercio.logotipo} className="h-16 w-16 rounded-full object-cover" icono="tienda" claseIcono="h-9 w-9 text-brandDarkBlue" />
                      </span>
                      <span className="flex flex-1 flex-col p-3 sm:p-4">
                        <span className="line-clamp-2 font-bold text-gray-800">{comercio.nombre_comercial}</span>
                        <span className="mt-auto inline-flex items-center gap-1 pt-3 text-sm font-semibold text-brandBlue">
                          Conocer comercio <Icono nombre="derecha" className="h-4 w-4" />
                        </span>
                      </span>
                    </button>
                  </li>
                ))}
              </ul>
              <Paginacion pagina={pagina} totalPaginas={totalPaginas} onPagina={(n) => ir('comercios', { q: termino, pagina: n })} />
            </>
          )
        )}
      </div>
    </PaginaPortal>
  );
}
