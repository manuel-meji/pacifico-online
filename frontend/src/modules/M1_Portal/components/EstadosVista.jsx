import Icono from './IconoComercio';

// Estados comunes de las vistas del portal: carga, vacío y error.
// Los errores siguen el formato del núcleo ({ error: { codigo, mensaje, detalles } }):
//   422 ERR-005 → el mensaje del campo (detalles) o el mensaje general
//   404 ERR-004 → "no disponible"
//   503 ERR-006 (o sin conexión) → "no disponible temporalmente", con Reintentar
//   otro código → error general, con Reintentar

// Primer mensaje por campo de un ERR-005, o el mensaje general.
export function mensajeDeDatosInvalidos(error) {
  const detalles = error?.detalles;
  if (detalles && typeof detalles === 'object') {
    const primero = Object.values(detalles)[0];
    if (primero) return Array.isArray(primero) ? primero[0] : primero;
  }
  return error?.mensaje || 'Revise los datos ingresados.';
}

// Esqueleto de tarjetas mientras carga.
export function CargandoTarjetas({ cantidad = 8, etiqueta = 'Cargando' }) {
  return (
    <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4" aria-busy="true" aria-label={etiqueta}>
      {Array.from({ length: cantidad }, (_, i) => (
        <div key={i} className="animate-pulse rounded-xl border border-gray-100 bg-white p-4">
          <div className="aspect-square rounded-lg bg-gray-200" />
          <div className="mt-3 h-4 rounded bg-gray-200" />
          <div className="mt-2 h-4 w-1/2 rounded bg-gray-200" />
        </div>
      ))}
    </div>
  );
}

// Bloque de carga genérico (ficha, encabezado de comercio).
export function CargandoBloque({ etiqueta = 'Cargando' }) {
  return (
    <div className="animate-pulse space-y-4" aria-busy="true" aria-label={etiqueta}>
      <div className="h-40 rounded-xl bg-gray-200" />
      <div className="h-6 w-2/3 rounded bg-gray-200" />
      <div className="h-6 w-1/3 rounded bg-gray-200" />
    </div>
  );
}

// Estado vacío: sin resultados, sin productos, etc.
export function Vacio({ icono = 'paquete', titulo, children, accion }) {
  return (
    <div className="rounded-xl border border-dashed border-gray-300 bg-white px-6 py-12 text-center">
      <span className="mx-auto grid h-14 w-14 place-items-center rounded-full bg-blue-50 text-brandDarkBlue"><Icono nombre={icono} className="h-7 w-7" /></span>
      <h2 className="mt-3 text-lg font-semibold text-gray-900">{titulo}</h2>
      {children && <p className="mt-1 text-sm text-gray-600">{children}</p>}
      {accion && <div className="mt-4 flex justify-center">{accion}</div>}
    </div>
  );
}

// Error de una llamada a la API. `resultado` es { estado, error } de m1Api.
// `noEncontrado` es el texto del 404 de la vista (por ejemplo "Este producto no está disponible").
export function ErrorVista({ resultado, noEncontrado = 'Lo que buscás no está disponible', onReintentar, onVolver }) {
  const { estado, error } = resultado || {};
  const codigo = error?.codigo;
  let titulo;
  let detalle;
  let puedeReintentar = true;

  if (estado === 404 || codigo === 'ERR-004') {
    titulo = noEncontrado;
    detalle = 'No existe o no está habilitado en este momento.';
    puedeReintentar = false;
  } else if (estado === 422 || codigo === 'ERR-005') {
    titulo = 'Revisá los datos de la búsqueda';
    detalle = mensajeDeDatosInvalidos(error);
    puedeReintentar = false;
  } else if (estado === 503 || estado === 0 || codigo === 'ERR-006') {
    titulo = 'No disponible temporalmente';
    detalle = 'Uno de los servicios del portal no respondió. Intentá de nuevo en unos segundos.';
  } else {
    titulo = 'Algo salió mal';
    detalle = error?.mensaje || 'No pudimos cargar la información.';
  }

  return (
    <div role="alert" className="rounded-xl border border-gray-200 bg-white px-6 py-12 text-center">
      <span className="mx-auto grid h-14 w-14 place-items-center rounded-full bg-red-50 text-red-700"><Icono nombre="alerta" className="h-7 w-7" /></span>
      <h2 className="mt-3 text-lg font-semibold text-gray-900">{titulo}</h2>
      <p className="mt-1 text-sm text-gray-600">{detalle}</p>
      <div className="mt-4 flex flex-wrap justify-center gap-2">
        {puedeReintentar && onReintentar && (
          <button type="button" onClick={onReintentar} className="flex items-center justify-center gap-2 rounded-lg bg-brandDarkBlue px-4 py-2.5 font-bold text-white transition duration-200 hover:bg-brandBlue">
            Reintentar
          </button>
        )}
        {onVolver && (
          <button type="button" onClick={onVolver} className="flex items-center gap-2 rounded-lg border border-brandBlue px-4 py-2 font-semibold text-brandBlue transition hover:bg-blue-50">
            Volver al inicio
          </button>
        )}
      </div>
    </div>
  );
}

// Aviso amarillo dentro de una vista (por ejemplo, M3 no respondió en el micrositio).
export function AvisoVista({ children }) {
  return (
    <div role="status" className="rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900">{children}</div>
  );
}
