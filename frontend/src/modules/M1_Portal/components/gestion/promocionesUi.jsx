import { useEffect, useRef, useState } from 'react';
import Icono from '../IconoComercio';
import { listarComercios, obtenerFichaProducto } from '../../services/m1Api';
import { mensajeDeDatosInvalidos } from '../EstadosVista';

// Piezas comunes de la pestaña "Promociones" del panel de gestión (contrato M1 1.4.0).
// Campos con letra de 16 px y botones de 44 px de alto (design.md, celular).

// Estados de una promoción (esquema Promocion) con su etiqueta y colores de badge.
export const ESTADOS_PROMOCION = {
  programada: { etiqueta: 'Programada', filtro: 'Programadas', clases: 'border-blue-200 bg-blue-50 text-brandDarkBlue', icono: 'reloj' },
  vigente: { etiqueta: 'Vigente', filtro: 'Vigentes', clases: 'border-green-200 bg-green-50 text-green-700', icono: 'disponible' },
  finalizada: { etiqueta: 'Finalizada', filtro: 'Finalizadas', clases: 'border-gray-200 bg-gray-100 text-gray-600', icono: 'reloj' },
  desactivada: { etiqueta: 'Desactivada', filtro: 'Desactivadas', clases: 'border-gray-200 bg-gray-100 text-gray-600', icono: 'cerrar' },
  oculta: { etiqueta: 'Oculta por la plataforma', filtro: 'Ocultas', clases: 'border-amber-300 bg-amber-50 text-amber-900', icono: 'alerta' },
};

export function BadgeEstado({ estado }) {
  const info = ESTADOS_PROMOCION[estado] ?? ESTADOS_PROMOCION.finalizada;
  return (
    <span className={`inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-semibold ${info.clases}`}>
      <Icono nombre={info.icono} className="h-3.5 w-3.5" /> {info.etiqueta}
    </span>
  );
}

// Texto claro para un error de la API (formato del núcleo). Los 422 se muestran por campo; aquí va el general.
export function mensajeErrorPromocion(resultado) {
  const { estado, error } = resultado || {};
  switch (error?.codigo) {
    case 'ERR-M1-004':
      return 'Ya hay otra promoción activa para ese producto o variación en esas fechas. Cambie las fechas o las variaciones.';
    case 'ERR-M1-005':
      return 'Esta promoción ya inició, terminó o se desactivó: solo se puede adelantar su fin o desactivarla mientras está vigente.';
    case 'ERR-004':
      return 'La promoción o el producto no existe, o no pertenece a su comercio.';
    case 'ERR-005':
      return mensajeDeDatosInvalidos(error);
    case 'ERR-001':
      return 'Su sesión expiró. Vuelva a ingresar.';
    case 'ERR-002':
      return 'Su cuenta no tiene permiso para esta acción.';
    default:
      if (estado === 503 || estado === 0 || error?.codigo === 'ERR-006') return 'Un servicio no respondió. Intente de nuevo en unos segundos.';
      return error?.mensaje || 'No se pudo completar la acción.';
  }
}

// Detalle por campo de un 422 (ERR-005), o {}.
export const erroresPorCampo = (resultado) => (resultado?.error?.codigo === 'ERR-005' && resultado.error.detalles) || {};

// Mensaje general de un error. En un 422 cuyos detalles ya se ven junto a cada campo, solo se remite a ellos;
// los detalles de campos que el formulario no muestra van en el mensaje para que no se pierdan.
export function mensajeGeneral(resultado, camposVisibles) {
  const detalles = erroresPorCampo(resultado);
  const claves = Object.keys(detalles);
  if (!claves.length) return mensajeErrorPromocion(resultado);
  const ocultos = claves.filter((clave) => !camposVisibles.includes(clave)).map((clave) => detalles[clave]);
  return ['Revise los campos marcados.', ...ocultos].join(' ');
}

const claseCampo = (error) => `w-full min-h-[44px] rounded-lg border bg-white px-3 py-2 text-base text-gray-800 focus:border-brandBlue focus:outline-none focus:ring-2 focus:ring-brandBlue/30 disabled:cursor-not-allowed disabled:bg-gray-100 ${error ? 'border-red-600' : 'border-gray-200 hover:border-gray-300'}`;

// Campo con etiqueta, ayuda y error. `como`: input (por defecto), textarea o select.
export function Campo({ id, etiqueta, ayuda, error, como = 'input', children, clase = '', ...props }) {
  const descripcion = [error && `${id}-error`, ayuda && `${id}-ayuda`].filter(Boolean).join(' ') || undefined;
  const comunes = { id, 'aria-invalid': !!error, 'aria-describedby': descripcion, className: claseCampo(error), ...props };
  return (
    <div className={clase}>
      <label htmlFor={id} className="mb-1 block text-sm font-medium text-gray-800">{etiqueta}</label>
      {como === 'textarea' && <textarea rows={3} {...comunes} />}
      {como === 'select' && <select {...comunes}>{children}</select>}
      {como === 'input' && <input {...comunes} />}
      {ayuda && <p id={`${id}-ayuda`} className="mt-1 text-xs text-gray-500">{ayuda}</p>}
      {error && <p id={`${id}-error`} role="alert" className="mt-1 break-words text-xs text-red-600">{error}</p>}
    </div>
  );
}

// Botones de 44 px: principal (azul), secundario (borde) y peligro (rojo).
export function Boton({ variante = 'secundario', icono, children, className = '', ...props }) {
  const estilos = {
    principal: 'bg-brandDarkBlue text-white enabled:hover:bg-brandBlue',
    secundario: 'border border-brandBlue bg-white text-brandBlue enabled:hover:bg-blue-50',
    peligro: 'border border-red-300 bg-white text-red-700 enabled:hover:bg-red-50',
  };
  return (
    <button type="button" {...props}
      className={`inline-flex min-h-[44px] items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brandBlue focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 ${estilos[variante]} ${className}`}>
      {icono && <Icono nombre={icono} className="h-4 w-4" />}{children}
    </button>
  );
}

// Diálogo modal: pantalla completa en celular, centrado en PC. Se cierra con Escape o el botón.
export function Dialogo({ titulo, onCerrar, children, pie }) {
  const referencia = useRef(null);
  const cerrar = useRef(onCerrar);
  cerrar.current = onCerrar;
  // Solo al abrir: enfocar el diálogo, escuchar Escape y bloquear el desplazamiento de la página.
  useEffect(() => {
    const anterior = document.activeElement;
    referencia.current?.focus();
    const alPresionar = (evento) => { if (evento.key === 'Escape') cerrar.current(); };
    document.addEventListener('keydown', alPresionar);
    document.body.style.overflow = 'hidden';
    return () => { document.removeEventListener('keydown', alPresionar); document.body.style.overflow = ''; anterior?.focus?.(); };
  }, []);

  return (
    <div className="fixed inset-0 z-40 flex items-stretch justify-center bg-black/40 sm:items-center sm:p-4" role="dialog" aria-modal="true" aria-labelledby="titulo-dialogo">
      <div ref={referencia} tabIndex={-1} className="flex max-h-full w-full flex-col bg-white outline-none sm:max-w-2xl sm:rounded-xl sm:shadow-xl">
        <div className="flex items-center justify-between gap-2 border-b border-gray-200 px-4 py-3">
          <h2 id="titulo-dialogo" className="text-lg font-bold text-brandDarkBlue">{titulo}</h2>
          <button type="button" onClick={onCerrar} className="grid h-11 w-11 shrink-0 place-items-center rounded-full text-gray-600 hover:bg-gray-100" aria-label="Cerrar">
            <Icono nombre="cerrar" className="h-5 w-5" />
          </button>
        </div>
        <div className="flex-1 overflow-y-auto px-4 py-4">{children}</div>
        {pie && <div className="flex flex-col-reverse gap-2 border-t border-gray-200 px-4 py-3 sm:flex-row sm:justify-end">{pie}</div>}
      </div>
    </div>
  );
}

// Nombres de los productos por id (ficha de M1), con caché en memoria mientras la página esté abierta.
const cacheProductos = new Map();

export function useNombresProductos(ids) {
  const [, setVersion] = useState(0);
  const clave = [...new Set(ids)].sort().join(',');

  useEffect(() => {
    let vigente = true;
    const faltantes = [...new Set(ids)].filter((id) => !cacheProductos.has(id));
    faltantes.forEach((id) => cacheProductos.set(id, null));
    Promise.all(faltantes.map((id) => obtenerFichaProducto(id).then((r) => cacheProductos.set(id, r.ok ? r.datos.nombre : `Producto ${id}`))))
      .then(() => { if (vigente && faltantes.length) setVersion((v) => v + 1); });
    return () => { vigente = false; };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [clave]);

  return (id) => cacheProductos.get(id) ?? `Producto ${id}`;
}

// Comercios habilitados de M2 (para el filtro y los nombres en la vista de la plataforma).
export function useComercios() {
  const [comercios, setComercios] = useState([]);
  useEffect(() => {
    let vigente = true;
    listarComercios(1, 50).then((r) => { if (vigente && r.ok) setComercios(r.datos.comercios); });
    return () => { vigente = false; };
  }, []);
  return comercios;
}
