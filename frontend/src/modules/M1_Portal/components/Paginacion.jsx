import Icono from './IconoComercio';

// Paginación numérica con primera, anterior, siguiente y última página.
export default function Paginacion({ pagina, totalPaginas, onPagina }) {
  if (totalPaginas <= 1) return null;
  const paginas = Array.from({ length: totalPaginas }, (_, i) => i + 1);
  const boton = 'grid h-10 min-w-10 place-items-center rounded-lg border px-2 text-sm transition';
  return (
    <nav aria-label="Paginación" className="mt-8 flex flex-wrap justify-center gap-1">
      <button className={`${boton} border-gray-200 bg-white hover:border-brandBlue hover:text-brandBlue disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:border-gray-200 disabled:hover:text-inherit`} disabled={pagina === 1} onClick={() => onPagina(1)} aria-label="Primera página"><Icono nombre="primera" className="h-4 w-4" /></button>
      <button className={`${boton} border-gray-200 bg-white hover:border-brandBlue hover:text-brandBlue disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:border-gray-200 disabled:hover:text-inherit`} disabled={pagina === 1} onClick={() => onPagina(pagina - 1)} aria-label="Página anterior"><Icono nombre="izquierda" className="h-4 w-4" /></button>
      {paginas.map((n) => (
        <button key={n} onClick={() => onPagina(n)} aria-current={n === pagina ? 'page' : undefined}
          className={`${boton} ${n === pagina ? 'border-brandDarkBlue bg-brandDarkBlue font-semibold text-white' : 'border-gray-200 bg-white hover:border-brandBlue hover:text-brandBlue'}`}>
          {n}
        </button>
      ))}
      <button className={`${boton} border-gray-200 bg-white hover:border-brandBlue hover:text-brandBlue disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:border-gray-200 disabled:hover:text-inherit`} disabled={pagina === totalPaginas} onClick={() => onPagina(pagina + 1)} aria-label="Página siguiente"><Icono nombre="derecha" className="h-4 w-4" /></button>
      <button className={`${boton} border-gray-200 bg-white hover:border-brandBlue hover:text-brandBlue disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:border-gray-200 disabled:hover:text-inherit`} disabled={pagina === totalPaginas} onClick={() => onPagina(totalPaginas)} aria-label="Última página"><Icono nombre="ultima" className="h-4 w-4" /></button>
    </nav>
  );
}
