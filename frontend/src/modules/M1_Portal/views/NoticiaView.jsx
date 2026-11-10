import PaginaPortal, { usePortal } from '../components/PaginaPortal';
import TarjetaProducto from '../components/TarjetaProducto';
import { CargandoBloque, ErrorVista } from '../components/EstadosVista';
import Icono from '../components/IconoComercio';
import Imagen from '../components/Imagen';
import { fechaCorta, fechaLarga } from '../services/formato';
import { obtenerNoticia } from '../services/m1Api';
import useConsulta from '../services/useConsulta';

// Detalle de una noticia de la plataforma: GET /api/m1/v1/noticias/{slug} (contrato 1.4.0, NoticiaDetalle).
// La noticia es una entidad aparte de las promociones: solo se muestran las que enlaza y siguen vigentes.
// 404 ERR-004: no existe, es un borrador, está archivada o fuera de su periodo.
export default function NoticiaView({ slug }) {
  const { ir } = usePortal();
  const consulta = useConsulta(() => obtenerNoticia(slug), [slug], !!slug);
  const noticia = consulta.datos;

  return (
    <PaginaPortal>
      <nav aria-label="Ruta de navegación" className="py-3 text-sm text-gray-500">
        <ol className="flex flex-wrap items-center gap-1">
          <li><button type="button" onClick={() => ir('inicio')} className="inline-flex min-h-[44px] items-center gap-1 hover:text-brandBlue sm:min-h-0"><Icono nombre="inicio" className="h-4 w-4" /> Inicio</button></li>
          <li aria-hidden><Icono nombre="derecha" className="h-3.5 w-3.5 text-gray-400" /></li>
          <li className="text-gray-500">Noticias</li>
          {noticia && consulta.estado === 'listo' && (
            <>
              <li aria-hidden><Icono nombre="derecha" className="h-3.5 w-3.5 text-gray-400" /></li>
              <li aria-current="page" className="min-w-0 truncate text-gray-800">{noticia.titulo}</li>
            </>
          )}
        </ol>
      </nav>

      {!slug && <ErrorVista resultado={{ estado: 404 }} noEncontrado="Esta noticia no está disponible" onVolver={() => ir('inicio')} />}
      {slug && consulta.estado === 'cargando' && <CargandoBloque etiqueta="Cargando noticia" />}
      {slug && consulta.estado === 'error' && (
        <ErrorVista resultado={consulta.resultado} noEncontrado="Esta noticia no está disponible" onReintentar={consulta.reintentar} onVolver={() => ir('inicio')} />
      )}

      {slug && noticia && consulta.estado === 'listo' && (
        <article className="mx-auto max-w-3xl">
          <div className="grid aspect-[16/9] w-full place-items-center overflow-hidden rounded-xl bg-blue-50">
            <Imagen src={noticia.imagen_url} alt="" className="h-full w-full object-cover" icono="noticia" claseIcono="h-16 w-16 text-brandDarkBlue/50" />
          </div>
          <header className="mt-6">
            <p className="inline-flex items-center gap-1.5 text-sm text-gray-500">
              <Icono nombre="calendario" className="h-4 w-4" />
              Publicada el <time dateTime={noticia.fecha_publicacion}>{fechaLarga(noticia.fecha_publicacion)}</time>
            </p>
            <h1 className="mt-2 break-words text-2xl font-bold text-brandDarkBlue sm:text-3xl">{noticia.titulo}</h1>
            <p className="mt-2 text-base text-gray-700">{noticia.resumen}</p>
          </header>
          <div className="mt-6 whitespace-pre-line break-words rounded-xl bg-white p-5 text-sm leading-relaxed text-gray-700 shadow-sm sm:text-base">
            {noticia.contenido}
          </div>

          {noticia.promociones.length > 0 && (
            <section className="mt-10" aria-labelledby="titulo-promociones-noticia">
              <h2 id="titulo-promociones-noticia" className="mb-4 flex items-center gap-2 text-xl font-bold text-brandDarkBlue">
                <Icono nombre="etiqueta" className="h-5 w-5 text-red-600" /> Promociones de esta noticia
              </h2>
              <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                {noticia.promociones.map((promo) => (
                  <li key={promo.id_promocion} className="min-w-0">
                    <p className="mb-1 truncate text-xs font-semibold text-red-700" title={promo.titulo}>
                      {promo.titulo} · hasta el {fechaCorta(promo.fecha_fin)}
                    </p>
                    <TarjetaProducto producto={promo.producto} onAbrir={() => ir('producto', { id: promo.producto.id_producto })} />
                  </li>
                ))}
              </ul>
            </section>
          )}
        </article>
      )}
    </PaginaPortal>
  );
}
