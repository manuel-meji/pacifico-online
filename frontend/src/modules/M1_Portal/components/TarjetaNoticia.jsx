import { fechaLarga } from '../services/formato';
import Icono from './IconoComercio';
import Imagen from './Imagen';

// Tarjeta de una NoticiaResumen del contrato (id_noticia, titulo, slug, resumen, imagen_url,
// fecha_publicacion). Si la imagen falta o no carga, se muestra el ícono de noticia.
export default function TarjetaNoticia({ noticia, onAbrir }) {
  return (
    <article className="flex h-full min-w-0 flex-col overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm transition hover:shadow-md">
      <button type="button" onClick={() => onAbrir?.(noticia)} className="flex h-full flex-col text-left focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brandBlue"
        aria-label={`Leer la noticia ${noticia.titulo}`}>
        <span className="grid aspect-[16/9] w-full place-items-center overflow-hidden bg-blue-50">
          <Imagen src={noticia.imagen_url} loading="lazy" className="h-full w-full object-cover" icono="noticia" claseIcono="h-10 w-10 text-brandDarkBlue/60" />
        </span>
        <span className="flex flex-1 flex-col p-4">
          <span className="inline-flex items-center gap-1.5 text-xs text-gray-500">
            <Icono nombre="calendario" className="h-3.5 w-3.5" />
            <time dateTime={noticia.fecha_publicacion}>{fechaLarga(noticia.fecha_publicacion)}</time>
          </span>
          <span className="mt-2 line-clamp-2 break-words font-bold text-gray-900">{noticia.titulo}</span>
          <span className="mt-1 line-clamp-3 text-sm text-gray-600">{noticia.resumen}</span>
          <span className="mt-auto inline-flex items-center gap-1 pt-3 text-sm font-semibold text-brandBlue">
            Leer más <Icono nombre="derecha" className="h-4 w-4" />
          </span>
        </span>
      </button>
    </article>
  );
}
