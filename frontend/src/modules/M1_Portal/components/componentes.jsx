import Logo from './Logo';

export function Encabezado({ pantalla, onIr }) {
  const enlace = (destino, texto) => (
    <button type="button" onClick={() => onIr(destino)} aria-current={pantalla === destino ? 'page' : undefined}
      className={`min-h-[44px] border-b-2 px-1 text-sm font-medium text-white hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white ${pantalla === destino ? 'border-brandBlue' : 'border-transparent'}`}>
      {texto}
    </button>
  );
  return (
    <header className="bg-brandDarkBlue">
      <div className="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-x-4 gap-y-1 px-4 py-2">
        <Logo />
        <nav aria-label="Cuenta" className="flex items-center gap-4">
          {enlace('crear', 'Crea tu cuenta')}
          {enlace('ingresar', 'Inicia sesión')}
        </nav>
      </div>
    </header>
  );
}

export function MigasDePan({ actual }) {
  return (
    <nav aria-label="Migas de pan" className="text-sm text-gray-600">
      <ol className="flex flex-wrap items-center gap-1.5">
        <li>Inicio</li>
        <li aria-hidden>›</li>
        <li>Cuenta</li>
        <li aria-hidden>›</li>
        <li aria-current="page" className="text-gray-800">{actual}</li>
      </ol>
    </nav>
  );
}

// Migas, título centrado y tarjeta blanca con el formulario.
export function PaginaCuenta({ migas, titulo, children }) {
  return (
    <main className="mx-auto w-full max-w-5xl px-4 py-5">
      <MigasDePan actual={migas} />
      <h1 className="mt-5 text-center text-2xl font-bold text-brandDarkBlue">{titulo}</h1>
      <section className="mx-auto mt-5 w-full max-w-md rounded-xl border border-gray-100 bg-white p-4 shadow-sm sm:p-8">
        {children}
      </section>
    </main>
  );
}

const trazos = {
  correo: <><rect x="3" y="5" width="18" height="14" rx="2" /><path d="m3 7 9 6 9-6" /></>,
  candado: <><rect x="5" y="11" width="14" height="10" rx="2" /><path d="M8 11V7a4 4 0 0 1 8 0v4" /></>,
  persona: <><circle cx="12" cy="8" r="4" /><path d="M4 21a8 8 0 0 1 16 0" /></>,
  telefono: <path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2" />,
  ubicacion: <><path d="M12 21s-7-6.1-7-12a7 7 0 0 1 14 0c0 5.9-7 12-7 12z" /><circle cx="12" cy="9" r="2.5" /></>,
};

export function Icono({ nombre }) {
  return (
    <svg aria-hidden viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5">
      {trazos[nombre]}
    </svg>
  );
}

// Campo con ícono a la izquierda y el mensaje de error debajo.
export function CampoConIcono({ id, etiqueta, icono, error, multilinea, ...props }) {
  const clases = `w-full min-h-[44px] rounded-lg border bg-white py-2 pl-10 pr-3 text-base font-medium text-gray-800 focus:border-brandBlue focus:outline-none focus:ring-2 focus:ring-brandBlue/30 disabled:cursor-not-allowed disabled:bg-gray-100 ${error ? 'border-red-600' : 'border-gray-200 hover:border-gray-300'}`;
  const accesibilidad = { id, 'aria-invalid': !!error, 'aria-describedby': error ? `${id}-error` : undefined };
  return (
    <div>
      <label htmlFor={id} className="mb-1 block text-sm font-medium text-gray-800">{etiqueta}</label>
      <div className="relative">
        <span className="pointer-events-none absolute left-3 top-3 text-brandGreen"><Icono nombre={icono} /></span>
        {multilinea
          ? <textarea rows={3} {...accesibilidad} {...props} className={clases} />
          : <input {...accesibilidad} {...props} className={clases} />}
      </div>
      {error && <p id={`${id}-error`} className="mt-1 text-xs text-red-600 break-words">{error}</p>}
    </div>
  );
}

export function Aviso({ tipo = 'error', children }) {
  const estilos = tipo === 'error' ? 'bg-red-50 border-red-200 text-red-700' : 'bg-green-50 border-green-200 text-green-700';
  return <div role={tipo === 'error' ? 'alert' : 'status'} className={`rounded-lg border px-3 py-2 text-sm break-words ${estilos}`}>{children}</div>;
}

// Botón principal / institucional (design.md, sección 5.1).
export function BotonPrincipal({ children, ...props }) {
  return (
    <button {...props} className="flex w-full min-h-[44px] items-center justify-center gap-2 rounded-lg bg-brandDarkBlue px-4 py-2.5 text-base font-bold text-white transition duration-200 enabled:hover:bg-brandBlue enabled:active:brightness-95 focus:outline-none focus:ring-2 focus:ring-brandBlue focus:ring-offset-2 disabled:cursor-not-allowed disabled:bg-gray-300 disabled:text-gray-500">
      {children}
    </button>
  );
}

export function EnlaceCuenta({ pregunta, texto, onClick }) {
  return (
    <p className="mt-5 text-center text-sm text-gray-600">
      {pregunta}{' '}
      <button type="button" onClick={onClick} className="rounded-sm font-medium text-brandBlue hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brandBlue">{texto}</button>
    </p>
  );
}
