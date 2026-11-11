import { fechaCorta, fechaLarga } from '../services/formato';
import Icono from './IconoComercio';
import Imagen from './Imagen';
import TarjetaProducto from './TarjetaProducto';

// Cuerpo de una NoticiaDetalle del contrato (titulo, resumen, contenido, imagen_url, fecha_publicacion y
// promociones: PromocionVigente[]). Lo usan el detalle del portal (NoticiaView) y la vista previa del
// panel de gestión, por eso no depende del contexto del portal.
// `nivelTitulo` es el nivel del encabezado del título (1 en el portal: <h1>); la sección de promociones usa
// el siguiente. Solo cambia la etiqueta, no el tamaño: la vista previa lo ajusta a la jerarquía del panel.
export default function ArticuloNoticia({ noticia, onAbrirProducto, nivelTitulo = 1 }) {
  const Titulo = `h${nivelTitulo}`;
  const TituloPromociones = `h${Math.min(nivelTitulo + 1, 6)}`;
  return (
    <article className="mx-auto max-w-3xl">
      <div className="grid aspect-[16/9] w-full place-items-center overflow-hidden rounded-xl bg-blue-50">
        <Imagen src={noticia.imagen_url} alt="" className="h-full w-full object-cover" icono="noticia" claseIcono="h-16 w-16 text-brandDarkBlue/50" />
      </div>
      <header className="mt-6">
        <p className="inline-flex items-center gap-1.5 text-sm text-gray-500">
          <Icono nombre="calendario" className="h-4 w-4" />
          Publicada el <time dateTime={noticia.fecha_publicacion}>{fechaLarga(noticia.fecha_publicacion)}</time>
        </p>
        <Titulo className="mt-2 break-words text-2xl font-bold text-brandDarkBlue sm:text-3xl">{noticia.titulo}</Titulo>
        <p className="mt-2 text-base text-gray-700">{noticia.resumen}</p>
      </header>
      <div className="mt-6 whitespace-pre-line break-words rounded-xl bg-white p-5 text-sm leading-relaxed text-gray-700 shadow-sm sm:text-base">
        {noticia.contenido}
      </div>

      {noticia.promociones.length > 0 && (
        <section className="mt-10" aria-labelledby="titulo-promociones-noticia">
          <TituloPromociones id="titulo-promociones-noticia" className="mb-4 flex items-center gap-2 text-xl font-bold text-brandDarkBlue">
            <Icono nombre="etiqueta" className="h-5 w-5 text-red-600" /> Promociones de esta noticia
          </TituloPromociones>
          <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3">
            {noticia.promociones.map((promo) => (
              <li key={promo.id_promocion} className="min-w-0">
                <p className="mb-1 truncate text-xs font-semibold text-red-700" title={promo.titulo}>
                  {promo.titulo} · hasta el {fechaCorta(promo.fecha_fin)}
                </p>
                <TarjetaProducto producto={promo.producto} onAbrir={() => onAbrirProducto?.(promo.producto)} />
              </li>
            ))}
          </ul>
        </section>
      )}
    </article>
  );
}
