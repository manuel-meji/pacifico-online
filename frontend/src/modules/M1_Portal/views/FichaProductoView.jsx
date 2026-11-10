import { useEffect, useState } from 'react';
import PaginaPortal, { usePortal } from '../components/PaginaPortal';
import TarjetaProducto, { BadgeAgotado, BadgeDisponible } from '../components/TarjetaProducto';
import { CargandoBloque, ErrorVista } from '../components/EstadosVista';
import Icono from '../components/IconoComercio';
import Imagen from '../components/Imagen';
import { obtenerFichaProducto } from '../services/m1Api';
import { colones, fechaCorta, textoAtributos } from '../services/formato';
import useConsulta from '../services/useConsulta';

// M5 agrega al carrito desde un enlace: /app.html?agregar=<id_variacion>&cantidad=<n>
const urlAgregarAlCarrito = (idVariacion, cantidad = 1) => `/app.html?agregar=${idVariacion}&cantidad=${cantidad}`;

// CU-003 Consultar ficha de producto: GET /api/m1/v1/productos/{id_producto} (esquema FichaProducto).
// Una variación solo se puede comprar si comprable es true; si no, se muestra "Agotado" y el botón queda deshabilitado.
export default function FichaProductoView({ idProducto }) {
  const { ir } = usePortal();
  const consulta = useConsulta(() => obtenerFichaProducto(idProducto), [idProducto], !!idProducto);
  const ficha = consulta.datos;

  return (
    <PaginaPortal>
      <nav aria-label="Ruta de navegación" className="py-3 text-sm text-gray-500">
        <ol className="flex flex-wrap items-center gap-1">
          <li><button type="button" onClick={() => ir('inicio')} className="inline-flex min-h-[44px] items-center gap-1 hover:text-brandBlue sm:min-h-0"><Icono nombre="inicio" className="h-4 w-4" /> Inicio</button></li>
          {ficha && consulta.estado !== 'error' && (
            <>
              <li aria-hidden><Icono nombre="derecha" className="h-3.5 w-3.5 text-gray-400" /></li>
              <li><button type="button" onClick={() => ir('comercio', { id: ficha.comercio.id_comercio })} className="inline-flex min-h-[44px] items-center hover:text-brandBlue sm:min-h-0">{ficha.comercio.nombre_comercial}</button></li>
              <li aria-hidden><Icono nombre="derecha" className="h-3.5 w-3.5 text-gray-400" /></li>
              <li aria-current="page" className="min-w-0 truncate text-gray-800">{ficha.nombre}</li>
            </>
          )}
        </ol>
      </nav>

      {!idProducto && <ErrorVista resultado={{ estado: 404 }} noEncontrado="Este producto no está disponible" onVolver={() => ir('inicio')} />}
      {idProducto && consulta.estado === 'cargando' && <CargandoBloque etiqueta="Cargando producto" />}
      {idProducto && consulta.estado === 'error' && (
        <ErrorVista resultado={consulta.resultado} noEncontrado="Este producto no está disponible" onReintentar={consulta.reintentar} onVolver={() => ir('inicio')} />
      )}
      {idProducto && ficha && (consulta.estado === 'listo' || consulta.estado === 'actualizando') && (
        <Ficha ficha={ficha} onVerComercio={() => ir('comercio', { id: ficha.comercio.id_comercio })} onVerProducto={(id) => ir('producto', { id })} />
      )}
    </PaginaPortal>
  );
}

function Ficha({ ficha, onVerComercio, onVerProducto }) {
  const variaciones = ficha.variaciones ?? [];
  const inicial = variaciones.find((v) => v.comprable) ?? variaciones[0] ?? null;
  const [idVariacion, setIdVariacion] = useState(inicial?.id_variacion ?? null);
  const [imagen, setImagen] = useState(0);

  useEffect(() => {
    setIdVariacion(inicial?.id_variacion ?? null);
    setImagen(0);
    // Al cambiar de producto (relacionados) se vuelve a la primera variación comprable.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [ficha.id_producto]);

  const variacion = variaciones.find((v) => v.id_variacion === idVariacion) ?? inicial;
  const imagenes = ficha.imagenes ?? [];
  const comprable = !!variacion?.comprable;

  return (
    <>
      <section className="grid grid-cols-1 gap-6 rounded-xl bg-white p-4 shadow-md sm:p-6 md:grid-cols-2 md:gap-8 md:p-8">
        <div className="min-w-0">
          <div className="grid aspect-square place-items-center overflow-hidden rounded-lg border border-gray-100 bg-white p-4">
            <Imagen key={imagenes[imagen]} src={imagenes[imagen]} alt={ficha.nombre} className="max-h-full max-w-full object-contain" claseIcono="h-24 w-24 text-brandBlue/40" />
          </div>
          {imagenes.length > 1 && (
            <div className="mt-3 flex gap-2 overflow-x-auto">
              {imagenes.map((url, i) => (
                <button type="button" key={url} onClick={() => setImagen(i)} aria-label={`Ver imagen ${i + 1}`} aria-pressed={i === imagen}
                  className={`h-16 w-16 shrink-0 overflow-hidden rounded-lg border-2 ${i === imagen ? 'border-brandBlue' : 'border-gray-100'}`}>
                  <span className="grid h-full w-full place-items-center"><Imagen src={url} className="h-full w-full object-cover" claseIcono="h-6 w-6 text-brandBlue/40" /></span>
                </button>
              ))}
            </div>
          )}
        </div>

        <div className="min-w-0">
          <button type="button" onClick={onVerComercio} className="mb-2 inline-flex items-center gap-1 text-sm font-semibold text-brandDarkBlue hover:text-brandBlue">
            <Icono nombre="tienda" className="h-4 w-4" /> {ficha.comercio.nombre_comercial}
          </button>
          <h1 className="break-words text-2xl font-bold text-gray-900">{ficha.nombre}</h1>
          {ficha.marca && <p className="mt-1 text-sm text-gray-500">Marca: {ficha.marca}</p>}

          {variacion && (
            <div className="my-5 rounded-lg border-l-4 border-brandGreen bg-gray-50 p-4">
              <span className="text-xs font-semibold uppercase text-gray-500">Precio</span>
              {variacion.precio_promocional != null ? (
                <>
                  <p className="flex flex-wrap items-baseline gap-x-3">
                    <span className="text-3xl font-extrabold text-red-700">{colones(variacion.precio_promocional)}</span>
                    <span className="text-base text-gray-500 line-through">{colones(variacion.precio)}</span>
                  </p>
                  <p className="mt-1 text-sm font-semibold text-red-700">Precio de promoción hasta el {fechaCorta(variacion.fin_promocion)}</p>
                </>
              ) : (
                <p className="text-3xl font-extrabold text-brandDarkBlue">{colones(variacion.precio)}</p>
              )}
              <div className="mt-2">{variacion.disponibilidad === 'Disponible' && comprable ? <BadgeDisponible /> : <BadgeAgotado />}</div>
            </div>
          )}

          {variaciones.length > 1 && (
            <fieldset className="mb-5">
              <legend className="mb-2 text-sm font-semibold text-gray-800">Opciones</legend>
              <div className="flex flex-wrap gap-2">
                {variaciones.map((v) => (
                  <button type="button" key={v.id_variacion} onClick={() => setIdVariacion(v.id_variacion)} aria-pressed={v.id_variacion === variacion?.id_variacion}
                    className={`max-w-full rounded-lg border px-3 py-2 text-left text-sm transition ${v.id_variacion === variacion?.id_variacion ? 'border-brandDarkBlue bg-blue-50 font-semibold text-brandDarkBlue' : 'border-gray-200 bg-white hover:border-brandBlue'} ${v.comprable ? '' : 'text-gray-400 line-through'}`}>
                    {textoAtributos(v.atributos) || v.sku}
                  </button>
                ))}
              </div>
            </fieldset>
          )}

          {variacion && comprable ? (
            <a href={urlAgregarAlCarrito(variacion.id_variacion)}
              className="flex w-full items-center justify-center gap-2 rounded-lg bg-brandGreen px-6 py-3 font-bold text-white shadow-sm transition hover:brightness-105">
              <Icono nombre="carrito" className="h-5 w-5" /> Agregar al carrito
            </a>
          ) : (
            <button type="button" disabled className="w-full cursor-not-allowed rounded-lg bg-gray-300 px-4 py-3 font-bold text-gray-500">
              Agotado
            </button>
          )}

          {ficha.descripcion && <p className="mt-6 whitespace-pre-line text-sm leading-relaxed text-gray-600">{ficha.descripcion}</p>}
          {ficha.video_url && (
            <a href={ficha.video_url} target="_blank" rel="noreferrer" className="mt-3 inline-flex items-center gap-1 text-sm font-semibold text-brandBlue hover:underline">
              Ver video del producto <Icono nombre="derecha" className="h-4 w-4" />
            </a>
          )}
        </div>
      </section>

      {(ficha.relacionados ?? []).length > 0 && (
        <section className="mt-10">
          <h2 className="mb-4 text-xl font-bold text-brandDarkBlue">También te puede interesar</h2>
          <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
            {ficha.relacionados.map((p) => (
              <li key={p.id_producto} className="min-w-0"><TarjetaProducto producto={p} onAbrir={() => onVerProducto(p.id_producto)} /></li>
            ))}
          </ul>
        </section>
      )}
    </>
  );
}
