import { useEffect, useState } from 'react';
import Icono from './IconoComercio';

// Barra lateral de filtros de productos. La usan la búsqueda y el micrositio.
// `criterio` usa los nombres del contrato de buscarProductos: { q, id_categoria, precio_minimo, precio_maximo }.
// `categorias` es el menú de GET /categorias (principales con subcategorias).
//
// RN-M1-08: la búsqueda necesita un término de al menos 2 caracteres o una categoría. Por eso:
//   - el buscador pide 2 caracteres si no hay categoría, sin llamar a la API;
//   - con `requiereCriterio` (micrositio), el precio queda deshabilitado hasta que haya término o categoría.

export const LARGO_MINIMO_TERMINO = 2;

export const tieneCriterio = (criterio) => !!criterio.id_categoria || (criterio.q ?? '').trim().length >= LARGO_MINIMO_TERMINO;

function Grupo({ titulo, onLimpiar, children }) {
  const [abierto, setAbierto] = useState(true);
  return (
    <section className="border-b border-gray-200 py-1 last:border-0 lg:py-3">
      <button type="button" onClick={() => setAbierto(!abierto)} aria-expanded={abierto}
        className="flex min-h-[44px] w-full items-center justify-between text-xs font-bold uppercase tracking-wide text-gray-800 lg:min-h-0">
        {titulo}<Icono nombre={abierto ? 'abajo' : 'derecha'} className="h-4 w-4 text-gray-500" />
      </button>
      {abierto && (
        <div className="mb-2 mt-1 space-y-1 lg:mb-0 lg:mt-2 lg:space-y-1.5">
          {onLimpiar && <button type="button" onClick={onLimpiar} className="inline-flex min-h-[36px] items-center gap-1 text-xs font-medium text-brandBlue hover:underline lg:min-h-0"><Icono nombre="izquierda" className="h-3.5 w-3.5" /> Limpiar</button>}
          {children}
        </div>
      )}
    </section>
  );
}

function OpcionCategoria({ categoria, marcada, onElegir, sangria = false }) {
  return (
    <label className={`flex min-h-[40px] cursor-pointer items-center gap-2 text-sm text-gray-700 lg:min-h-0 ${sangria ? 'pl-5' : ''}`}>
      <input type="radio" name="categoria-filtro" checked={marcada} onChange={onElegir} className="h-4 w-4 accent-brandDarkBlue" />
      <span className="min-w-0 flex-1 truncate">{categoria.nombre}</span>
    </label>
  );
}

export default function FiltrosComercio({ categorias = [], criterio, onCambio, conBuscador = false, requiereCriterio = false }) {
  const [termino, setTermino] = useState(criterio.q ?? '');
  const [avisoTermino, setAvisoTermino] = useState('');
  const [min, setMin] = useState(criterio.precio_minimo ?? '');
  const [max, setMax] = useState(criterio.precio_maximo ?? '');
  const [avisoPrecio, setAvisoPrecio] = useState('');

  useEffect(() => { setTermino(criterio.q ?? ''); }, [criterio.q]);
  useEffect(() => { setMin(criterio.precio_minimo ?? ''); setMax(criterio.precio_maximo ?? ''); }, [criterio.precio_minimo, criterio.precio_maximo]);

  const precioHabilitado = !requiereCriterio || tieneCriterio(criterio);

  const buscar = (e) => {
    e.preventDefault();
    const q = termino.trim();
    if (q !== '' && q.length < LARGO_MINIMO_TERMINO && !criterio.id_categoria) {
      setAvisoTermino('Escribí al menos 2 caracteres o elegí una categoría.');
      return;
    }
    setAvisoTermino('');
    onCambio({ ...criterio, q: q || null });
  };

  const aplicarPrecio = (e) => {
    e.preventDefault();
    const minimo = min === '' ? null : Number(min);
    const maximo = max === '' ? null : Number(max);
    if (minimo != null && maximo != null && minimo > maximo) {
      setAvisoPrecio('El mínimo no puede ser mayor que el máximo.');
      return;
    }
    setAvisoPrecio('');
    onCambio({ ...criterio, precio_minimo: minimo, precio_maximo: maximo });
  };

  const limpiarPrecio = () => { setMin(''); setMax(''); setAvisoPrecio(''); onCambio({ ...criterio, precio_minimo: null, precio_maximo: null }); };

  // Quitar la categoría deja sin criterio si tampoco hay término: entonces también se quita el precio.
  const elegirCategoria = (idCategoria) => {
    const nuevo = { ...criterio, id_categoria: idCategoria };
    if (requiereCriterio && !tieneCriterio(nuevo)) Object.assign(nuevo, { precio_minimo: null, precio_maximo: null });
    onCambio(nuevo);
  };

  return (
    <div>
      {conBuscador && (
        <Grupo titulo="Buscar en este comercio">
          <form onSubmit={buscar} className="flex items-center gap-1">
            <label className="sr-only" htmlFor="buscar-comercio">Buscar en este comercio</label>
            <input id="buscar-comercio" type="search" value={termino} maxLength={100} onChange={(e) => setTermino(e.target.value)} placeholder="Nombre del producto"
              className="w-full min-w-0 rounded-lg border border-gray-200 px-2 py-2 text-base focus:border-brandBlue focus:outline-none sm:py-1.5 sm:text-sm" />
            <button className="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-brandDarkBlue text-white transition hover:bg-brandBlue sm:h-8 sm:w-8" aria-label="Buscar"><Icono nombre="buscar" className="h-4 w-4" /></button>
          </form>
          {avisoTermino && <p role="alert" className="text-xs text-red-700">{avisoTermino}</p>}
        </Grupo>
      )}

      {categorias.length > 0 && (
        <Grupo titulo="Categoría" onLimpiar={criterio.id_categoria ? () => elegirCategoria(null) : null}>
          <OpcionCategoria categoria={{ nombre: 'Todas' }} marcada={!criterio.id_categoria} onElegir={() => elegirCategoria(null)} />
          {categorias.map((c) => (
            <div key={c.id_categoria} className="space-y-1.5">
              <OpcionCategoria categoria={c} marcada={criterio.id_categoria === c.id_categoria} onElegir={() => elegirCategoria(c.id_categoria)} />
              {(c.subcategorias ?? []).map((s) => (
                <OpcionCategoria key={s.id_categoria} categoria={s} sangria marcada={criterio.id_categoria === s.id_categoria} onElegir={() => elegirCategoria(s.id_categoria)} />
              ))}
            </div>
          ))}
        </Grupo>
      )}

      <Grupo titulo="Precio" onLimpiar={criterio.precio_minimo != null || criterio.precio_maximo != null ? limpiarPrecio : null}>
        {!precioHabilitado && <p className="text-xs text-gray-500">Elegí una categoría o buscá un producto para filtrar por precio.</p>}
        <form onSubmit={aplicarPrecio} className="flex items-center gap-1 pt-1">
          <label className="sr-only" htmlFor="precio-min">Precio mínimo</label>
          <input id="precio-min" inputMode="numeric" placeholder="Mín" value={min} disabled={!precioHabilitado} onChange={(e) => setMin(e.target.value.replace(/\D/g, ''))}
            className="w-full min-w-0 rounded-lg border border-gray-200 px-2 py-2 text-base focus:border-brandBlue focus:outline-none disabled:cursor-not-allowed disabled:bg-gray-100 sm:py-1 sm:text-sm" />
          <span className="text-gray-400">-</span>
          <label className="sr-only" htmlFor="precio-max">Precio máximo</label>
          <input id="precio-max" inputMode="numeric" placeholder="Máx" value={max} disabled={!precioHabilitado} onChange={(e) => setMax(e.target.value.replace(/\D/g, ''))}
            className="w-full min-w-0 rounded-lg border border-gray-200 px-2 py-2 text-base focus:border-brandBlue focus:outline-none disabled:cursor-not-allowed disabled:bg-gray-100 sm:py-1 sm:text-sm" />
          <button disabled={!precioHabilitado} className="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-brandDarkBlue text-white transition hover:bg-brandBlue sm:h-8 sm:w-8 disabled:cursor-not-allowed disabled:bg-gray-300 disabled:text-gray-500" aria-label="Aplicar rango de precio"><Icono nombre="derecha" className="h-4 w-4" /></button>
        </form>
        {avisoPrecio && <p role="alert" className="text-xs text-red-700">{avisoPrecio}</p>}
      </Grupo>
    </div>
  );
}
