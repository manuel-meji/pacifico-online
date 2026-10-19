import { useState } from 'react';
import { RANGOS_PRECIO } from '../services/micrositio';

function Grupo({ titulo, onLimpiar, children }) {
  const [abierto, setAbierto] = useState(true);
  return (
    <section className="border-b border-gray-200 py-3 last:border-0">
      <button onClick={() => setAbierto(!abierto)} aria-expanded={abierto}
        className="flex w-full items-center justify-between text-xs font-bold uppercase tracking-wide text-gray-800">
        {titulo}<span aria-hidden>{abierto ? '▾' : '▸'}</span>
      </button>
      {abierto && (
        <div className="mt-2 space-y-1.5">
          {onLimpiar && <button onClick={onLimpiar} className="text-xs text-brandBlue hover:underline">❮ Limpiar</button>}
          {children}
        </div>
      )}
    </section>
  );
}

function Casilla({ marcado, onCambio, etiqueta, cantidad }) {
  return (
    <label className="flex cursor-pointer items-center gap-2 text-sm text-gray-700">
      <input type="checkbox" checked={marcado} onChange={onCambio} className="h-4 w-4 accent-brandDarkBlue" />
      <span className="min-w-0 flex-1 truncate">{etiqueta}</span>
      {cantidad != null && <span className="text-xs text-gray-400">({cantidad})</span>}
    </label>
  );
}

// Barra lateral de filtros: tipo de producto (categorías de M3), marca, precio y disponibilidad.
export default function FiltrosComercio({ categorias, marcas, conteoCategorias, conteoMarcas, filtros, onCambio }) {
  const [min, setMin] = useState(filtros.precioMin ?? '');
  const [max, setMax] = useState(filtros.precioMax ?? '');
  const alternar = (campo, valor) => {
    const actual = filtros[campo] ?? [];
    onCambio({ ...filtros, [campo]: actual.includes(valor) ? actual.filter((v) => v !== valor) : [...actual, valor] });
  };
  const aplicarPrecio = (e) => {
    e.preventDefault();
    onCambio({ ...filtros, rango: null, precioMin: min === '' ? null : Number(min), precioMax: max === '' ? null : Number(max) });
  };
  const limpiarPrecio = () => { setMin(''); setMax(''); onCambio({ ...filtros, rango: null, precioMin: null, precioMax: null }); };

  return (
    <div>
      <Grupo titulo="Tipo de producto" onLimpiar={filtros.categorias?.length ? () => onCambio({ ...filtros, categorias: [] }) : null}>
        {categorias.map((c) => (
          <Casilla key={c.idCategoria} etiqueta={c.nombre} cantidad={conteoCategorias[c.idCategoria]}
            marcado={filtros.categorias?.includes(c.idCategoria) ?? false} onCambio={() => alternar('categorias', c.idCategoria)} />
        ))}
      </Grupo>

      <Grupo titulo="Marca" onLimpiar={filtros.marcas?.length ? () => onCambio({ ...filtros, marcas: [] }) : null}>
        {marcas.map((m) => (
          <Casilla key={m} etiqueta={m} cantidad={conteoMarcas[m]}
            marcado={filtros.marcas?.includes(m) ?? false} onCambio={() => alternar('marcas', m)} />
        ))}
      </Grupo>

      <Grupo titulo="Precio" onLimpiar={filtros.rango || filtros.precioMin != null || filtros.precioMax != null ? limpiarPrecio : null}>
        {RANGOS_PRECIO.map((r) => (
          <label key={r.id} className="flex cursor-pointer items-center gap-2 text-sm text-gray-700">
            <input type="radio" name="rango-precio" checked={filtros.rango === r.id} className="h-4 w-4 accent-brandDarkBlue"
              onChange={() => { setMin(''); setMax(''); onCambio({ ...filtros, rango: r.id, precioMin: null, precioMax: null }); }} />
            {r.etiqueta}
          </label>
        ))}
        <form onSubmit={aplicarPrecio} className="flex items-center gap-1 pt-1">
          <label className="sr-only" htmlFor="precio-min">Precio mínimo</label>
          <input id="precio-min" inputMode="numeric" placeholder="Mín" value={min} onChange={(e) => setMin(e.target.value.replace(/\D/g, ''))}
            className="w-full min-w-0 rounded border border-gray-300 px-2 py-1 text-sm" />
          <span className="text-gray-400">-</span>
          <label className="sr-only" htmlFor="precio-max">Precio máximo</label>
          <input id="precio-max" inputMode="numeric" placeholder="Máx" value={max} onChange={(e) => setMax(e.target.value.replace(/\D/g, ''))}
            className="w-full min-w-0 rounded border border-gray-300 px-2 py-1 text-sm" />
          <button className="shrink-0 rounded bg-brandDarkBlue px-2 py-1 text-sm font-semibold text-white hover:bg-brandBlue" aria-label="Aplicar rango de precio">›</button>
        </form>
      </Grupo>

      <Grupo titulo="Disponibilidad">
        <Casilla etiqueta="Solo productos disponibles" marcado={!!filtros.soloDisponibles}
          onCambio={() => onCambio({ ...filtros, soloDisponibles: !filtros.soloDisponibles })} />
      </Grupo>
    </div>
  );
}
