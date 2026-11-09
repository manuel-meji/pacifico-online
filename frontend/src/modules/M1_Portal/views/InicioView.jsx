import PaginaPortal, { usePortal } from '../components/PaginaPortal';
import CarruselComercios from '../components/CarruselComercios';
import TarjetaNoticia from '../components/TarjetaNoticia';
import TarjetaProducto from '../components/TarjetaProducto';
import { CargandoTarjetas, ErrorVista, Vacio } from '../components/EstadosVista';
import Icono from '../components/IconoComercio';
import Imagen from '../components/Imagen';
import { fechaCorta } from '../services/formato';
import { listarComercios, listarDestacados, listarNoticias, listarPromociones } from '../services/m1Api';
import useConsulta from '../services/useConsulta';

// La portada muestra hasta 12 destacados (contrato M1 1.4.0); en la cuadrícula de 4 columnas son 3 filas.
const LIMITE_DESTACADOS = 12;

// Últimas noticias de la plataforma que se muestran en la portada.
const NOTICIAS_EN_PORTADA = 3;

// Página principal: GET /api/m1/v1/destacados.
// Si secciones_no_disponibles incluye "comercios" o "productos", esa sección se oculta y el resto sigue.
// Si no hay comercios destacados vigentes, se muestran los primeros de GET /comercios.
export default function InicioView() {
  const { ir, categorias } = usePortal();
  const destacados = useConsulta(() => listarDestacados(LIMITE_DESTACADOS), []);
  const datos = destacados.datos;
  const noDisponibles = datos?.secciones_no_disponibles ?? [];
  const mostrarComercios = destacados.estado === 'listo' && !noDisponibles.includes('comercios');
  const mostrarProductos = destacados.estado === 'listo' && !noDisponibles.includes('productos');
  const sinComerciosDestacados = mostrarComercios && datos.comercios.length === 0;
  const comercios = useConsulta(() => listarComercios(1, LIMITE_DESTACADOS), [], sinComerciosDestacados);
  // Sección de promociones vigentes (contrato 1.4.0). Si falla o no hay, la sección no se muestra.
  const promociones = useConsulta(() => listarPromociones(), []);
  // Últimas noticias publicadas (contrato 1.4.0). Si falla o no hay, la sección no se muestra.
  const noticias = useConsulta(() => listarNoticias(1, NOTICIAS_EN_PORTADA), []);

  return (
    <PaginaPortal>
      <section className="mt-6 overflow-hidden rounded-xl bg-gradient-to-r from-brandDarkBlue to-brandBlue px-6 py-8 text-white sm:py-10">
        <h1 className="text-2xl font-bold sm:text-3xl">Bienvenido a Pacíficos Online</h1>
        <p className="mt-2 max-w-xl text-sm text-blue-50 sm:text-base">Descubrí productos y comercios de Guanacaste y todo Costa Rica.</p>
      </section>

      {categorias.length > 0 && (
        <section className="mt-8" aria-labelledby="titulo-categorias">
          <h2 id="titulo-categorias" className="mb-3 text-xl font-bold text-brandDarkBlue">Explorá por categoría</h2>
          <div className="flex flex-wrap gap-2">
            {categorias.map((c) => (
              <button type="button" key={c.id_categoria} onClick={() => ir('busqueda', { id_categoria: c.id_categoria })}
                className="flex items-center gap-2 rounded-lg border border-brandBlue px-3 py-2 text-sm font-semibold text-brandBlue transition hover:bg-blue-50">
                <Imagen src={c.icono_url} className="h-4 w-4" claseIcono="h-4 w-4" />
                {c.nombre}
              </button>
            ))}
          </div>
        </section>
      )}

      {destacados.estado === 'cargando' && <div className="mt-10"><CargandoTarjetas etiqueta="Cargando destacados" /></div>}
      {destacados.estado === 'error' && <div className="mt-10"><ErrorVista resultado={destacados.resultado} onReintentar={destacados.reintentar} /></div>}

      {mostrarComercios && datos.comercios.length > 0 && (
        <CarruselComercios comercios={datos.comercios} titulo="Comercios destacados" descripcion="Conocé los comercios que forman parte de Pacíficos Online."
          onVerComercio={(id) => ir('comercio', { id })} />
      )}

      {sinComerciosDestacados && comercios.estado === 'listo' && comercios.datos.comercios.length > 0 && (
        <CarruselComercios comercios={comercios.datos.comercios} titulo="Explorá nuestros comercios" descripcion="Conocé los comercios que forman parte de Pacíficos Online."
          onVerComercio={(id) => ir('comercio', { id })} />
      )}

      {promociones.estado === 'listo' && promociones.datos.length > 0 && (
        <section className="mt-10" aria-labelledby="titulo-promociones">
          <h2 id="titulo-promociones" className="mb-1 flex items-center gap-2 text-2xl font-bold text-brandDarkBlue">
            <Icono nombre="etiqueta" className="h-6 w-6 text-red-600" /> Promociones
          </h2>
          <p className="mb-4 text-sm text-gray-600">Precios rebajados por los comercios, por tiempo limitado.</p>
          <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
            {promociones.datos.map((promo) => (
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

      {noticias.estado === 'listo' && noticias.datos.noticias.length > 0 && (
        <section className="mt-10" aria-labelledby="titulo-noticias">
          <h2 id="titulo-noticias" className="mb-1 flex items-center gap-2 text-2xl font-bold text-brandDarkBlue">
            <Icono nombre="noticia" className="h-6 w-6" /> Noticias
          </h2>
          <p className="mb-4 text-sm text-gray-600">Novedades de Pacíficos Online: comercios nuevos, campañas y avisos.</p>
          <ul className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {noticias.datos.noticias.map((noticia) => (
              <li key={noticia.id_noticia} className="min-w-0">
                <TarjetaNoticia noticia={noticia} onAbrir={() => ir('noticia', { slug: noticia.slug })} />
              </li>
            ))}
          </ul>
        </section>
      )}

      {mostrarProductos && (
        <section className="mt-10">
          <h2 className="mb-4 text-2xl font-bold text-brandDarkBlue">Productos destacados</h2>
          {datos.productos.length > 0 ? (
            <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
              {datos.productos.map((p) => (
                <li key={p.id_producto} className="min-w-0"><TarjetaProducto producto={p} onAbrir={() => ir('producto', { id: p.id_producto })} /></li>
              ))}
            </ul>
          ) : (
            <Vacio titulo="Pronto vas a ver aquí los productos destacados">Mientras tanto, usá el buscador o elegí una categoría.</Vacio>
          )}
        </section>
      )}
    </PaginaPortal>
  );
}
