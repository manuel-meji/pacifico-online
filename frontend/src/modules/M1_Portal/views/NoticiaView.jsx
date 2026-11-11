import PaginaPortal, { usePortal } from '../components/PaginaPortal';
import ArticuloNoticia from '../components/ArticuloNoticia';
import { CargandoBloque, ErrorVista } from '../components/EstadosVista';
import Icono from '../components/IconoComercio';
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
        <ArticuloNoticia noticia={noticia} onAbrirProducto={(producto) => ir('producto', { id: producto.id_producto })} />
      )}
    </PaginaPortal>
  );
}
