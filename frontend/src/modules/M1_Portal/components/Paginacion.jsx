// Paginación numérica con primera, anterior, siguiente y última página.
export default function Paginacion({ pagina, totalPaginas, onPagina }) {
  if (totalPaginas <= 1) return null;
  const paginas = Array.from({ length: totalPaginas }, (_, i) => i + 1);
  const boton = 'grid h-10 min-w-10 place-items-center rounded-md border px-2 text-sm';
  return (
    <nav aria-label="Paginación" className="mt-8 flex flex-wrap justify-center gap-1">
      <button className={`${boton} border-gray-300 disabled:opacity-40`} disabled={pagina === 1} onClick={() => onPagina(1)} aria-label="Primera página">|&lt;</button>
      <button className={`${boton} border-gray-300 disabled:opacity-40`} disabled={pagina === 1} onClick={() => onPagina(pagina - 1)} aria-label="Página anterior">&lt;</button>
      {paginas.map((n) => (
        <button key={n} onClick={() => onPagina(n)} aria-current={n === pagina ? 'page' : undefined}
          className={`${boton} ${n === pagina ? 'border-brandDarkBlue bg-brandDarkBlue font-semibold text-white' : 'border-gray-300 hover:bg-gray-100'}`}>
          {n}
        </button>
      ))}
      <button className={`${boton} border-gray-300 disabled:opacity-40`} disabled={pagina === totalPaginas} onClick={() => onPagina(pagina + 1)} aria-label="Página siguiente">&gt;</button>
      <button className={`${boton} border-gray-300 disabled:opacity-40`} disabled={pagina === totalPaginas} onClick={() => onPagina(totalPaginas)} aria-label="Última página">&gt;|</button>
    </nav>
  );
}
