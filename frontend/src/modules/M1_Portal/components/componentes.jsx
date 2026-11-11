import { usePortal } from './PaginaPortal';

export function MigasDePan({ actual }) {
  const { ir } = usePortal();
  return (
    <nav aria-label="Migas de pan" className="text-sm text-gray-600">
      <ol className="flex flex-wrap items-center gap-1.5">
        <li>
          <button type="button" onClick={() => ir('inicio')}
            className="inline-flex min-h-[44px] items-center rounded-sm font-medium text-brandBlue hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brandBlue sm:min-h-0">
            Inicio
          </button>
        </li>
        <li aria-hidden>›</li>
        <li>Cuenta</li>
        <li aria-hidden>›</li>
        <li aria-current="page" className="text-gray-800">{actual}</li>
      </ol>
    </nav>
  );
}

// Migas, título centrado y tarjeta blanca con el formulario. `amplia` es para los formularios largos (crear cuenta,
// Mi cuenta), que en pantallas anchas usan dos columnas; los cortos (iniciar sesión) quedan angostos.
// El <main>, el ancho (max-w-7xl) y el margen lateral los pone PaginaPortal; PaginaCuenta no crea su propio <main>.
export function PaginaCuenta({ migas, titulo, amplia = false, children }) {
  return (
    <div className="pt-5">
      <MigasDePan actual={migas} />
      <h1 className="mt-5 text-center text-2xl font-bold text-brandDarkBlue">{titulo}</h1>
      <section className={`mx-auto mt-5 w-full rounded-xl border border-gray-100 bg-white p-4 shadow-sm sm:p-8 ${amplia ? 'max-w-3xl' : 'max-w-md'}`}>
        {children}
      </section>
    </div>
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
export function CampoConIcono({ id, etiqueta, icono, error, multilinea, clase = '', ...props }) {
  const clases = `w-full min-h-[44px] rounded-lg border bg-white py-2 pl-10 pr-3 text-base font-medium text-gray-800 focus:border-brandBlue focus:outline-none focus:ring-2 focus:ring-brandBlue/30 disabled:cursor-not-allowed disabled:bg-gray-100 ${error ? 'border-red-600' : 'border-gray-200 hover:border-gray-300'}`;
  const accesibilidad = { id, 'aria-invalid': !!error, 'aria-describedby': error ? `${id}-error` : undefined };
  return (
    <div className={clase}>
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

// Lista desplegable con el mismo aspecto que CampoConIcono. Sin opciones (por ejemplo, el cantón antes de elegir
// la provincia) queda deshabilitada y muestra `sinOpciones`.
export function CampoSeleccion({ id, etiqueta, icono, error, opciones, indicacion, sinOpciones, clase = '', value, disabled = false, ...props }) {
  const vacia = opciones.length === 0;
  const clases = `w-full min-h-[44px] appearance-none rounded-lg border bg-white py-2 pl-10 pr-10 text-base font-medium focus:border-brandBlue focus:outline-none focus:ring-2 focus:ring-brandBlue/30 disabled:cursor-not-allowed disabled:bg-gray-100 ${value ? 'text-gray-800' : 'text-gray-500'} ${error ? 'border-red-600' : 'border-gray-200 hover:border-gray-300'}`;
  return (
    <div className={clase}>
      <label htmlFor={id} className="mb-1 block text-sm font-medium text-gray-800">{etiqueta}</label>
      <div className="relative">
        <span className="pointer-events-none absolute left-3 top-3 text-brandGreen"><Icono nombre={icono} /></span>
        <select {...props} id={id} value={value} disabled={vacia || disabled} aria-invalid={!!error}
          aria-describedby={error ? `${id}-error` : undefined} className={clases}>
          <option value="">{vacia && sinOpciones ? sinOpciones : indicacion}</option>
          {opciones.map((opcion) => <option key={opcion} value={opcion}>{opcion}</option>)}
        </select>
        <span className="pointer-events-none absolute right-3 top-3 text-gray-500">
          <svg aria-hidden viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5"><path d="m6 9 6 6 6-6" /></svg>
        </span>
      </div>
      {error && <p id={`${id}-error`} className="mt-1 text-xs text-red-600 break-words">{error}</p>}
    </div>
  );
}

// Un campo de dirección (services/camposDireccion.js): lista desplegable o campo de texto según su tipo.
// En el formulario de dos columnas, `completo` ocupa todo el ancho.
export function CampoDireccion({ campo, id, valor, onCambio, error, formulario }) {
  const clase = campo.completo ? 'md:col-span-2' : '';
  if (campo.tipo === 'seleccion') {
    return (
      <CampoSeleccion id={id} etiqueta={campo.etiqueta} icono={campo.icono} autoComplete={campo.autoComplete}
        opciones={campo.opciones(formulario)} indicacion={campo.indicacion} sinOpciones={campo.sinOpciones}
        value={valor} onChange={(e) => onCambio(e.target.value)} error={error} clase={clase} />
    );
  }
  return (
    <CampoConIcono id={id} etiqueta={campo.etiqueta} icono={campo.icono} type={campo.type} multilinea={campo.multilinea}
      autoComplete={campo.autoComplete} value={valor} onChange={(e) => onCambio(e.target.value)} error={error} clase={clase} />
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
      <button type="button" onClick={onClick} className="inline-flex min-h-[44px] items-center rounded-sm px-1 font-medium text-brandBlue hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brandBlue sm:min-h-0">{texto}</button>
    </p>
  );
}
