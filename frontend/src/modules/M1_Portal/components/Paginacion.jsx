import Icono from './IconoComercio';

// Paginación con anterior y siguiente y, a lo sumo, cinco números alrededor de la página actual,
// para que no se desborde en pantallas de 360 px.
export default function Paginacion({ pagina, totalPaginas, onPagina }) {
  if (totalPaginas <= 1) return null;
  const inicio = Math.max(1, Math.min(pagina - 2, totalPaginas - 4));
  const paginas = Array.from({ length: Math.min(5, totalPaginas) }, (_, i) => inicio + i);
  const boton = 'grid h-10 min-w-10 place-items-center rounded-lg border px-2 text-sm transition';
  const flecha = `${boton} border-gray-200 bg-white hover:border-brandBlue hover:text-brandBlue disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:border-gray-200 disabled:hover:text-inherit`;
  return (
    <nav aria-label="Paginación" className="mt-8 flex flex-wrap justify-center gap-1">
      <button type="button" className={flecha} disabled={pagina === 1} onClick={() => onPagina(pagina - 1)} aria-label="Página anterior"><Icono nombre="izquierda" className="h-4 w-4" /></button>
      {paginas.map((n) => (
        <button type="button" key={n} onClick={() => onPagina(n)} aria-current={n === pagina ? 'page' : undefined}
          className={`${boton} ${n === pagina ? 'border-brandDarkBlue bg-brandDarkBlue font-semibold text-white' : 'border-gray-200 bg-white hover:border-brandBlue hover:text-brandBlue'}`}>
          {n}
        </button>
      ))}
      <button type="button" className={flecha} disabled={pagina === totalPaginas} onClick={() => onPagina(pagina + 1)} aria-label="Página siguiente"><Icono nombre="derecha" className="h-4 w-4" /></button>
    </nav>
  );
}
