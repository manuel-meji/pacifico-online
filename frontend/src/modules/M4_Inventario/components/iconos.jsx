// Íconos Lucide (licencia ISC) como SVG en línea con stroke="currentColor", como admite design.md para no
// agregar dependencias al frontend del repositorio (hoy solo declara react y react-dom). Sin emojis.
function Icono({ className = 'h-4 w-4', children, ...props }) {
  return (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"
      strokeLinecap="round" strokeLinejoin="round" className={className} aria-hidden="true" focusable="false" {...props}>
      {children}
    </svg>
  );
}

export const IconoCirculoCheck = (p) => <Icono {...p}><circle cx="12" cy="12" r="10" /><path d="m9 12 2 2 4-4" /></Icono>;
export const IconoCirculoAlerta = (p) => <Icono {...p}><circle cx="12" cy="12" r="10" /><path d="M12 8v4" /><path d="M12 16h.01" /></Icono>;
export const IconoInfo = (p) => <Icono {...p}><circle cx="12" cy="12" r="10" /><path d="M12 16v-4" /><path d="M12 8h.01" /></Icono>;
export const IconoTrianguloAlerta = (p) => <Icono {...p}><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3" /><path d="M12 9v4" /><path d="M12 17h.01" /></Icono>;
export const IconoCandado = (p) => <Icono {...p}><rect width="18" height="11" x="3" y="11" rx="2" ry="2" /><path d="M7 11V7a5 5 0 0 1 10 0v4" /></Icono>;
export const IconoUsuario = (p) => <Icono {...p}><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2" /><circle cx="12" cy="7" r="4" /></Icono>;
export const IconoEntrada = (p) => <Icono {...p}><path d="M12 17V3" /><path d="m6 11 6 6 6-6" /><path d="M19 21H5" /></Icono>;
export const IconoSalida = (p) => <Icono {...p}><path d="m18 9-6-6-6 6" /><path d="M12 3v14" /><path d="M5 21h14" /></Icono>;
export const IconoTransferir = (p) => <Icono {...p}><path d="m16 3 4 4-4 4" /><path d="M20 7H4" /><path d="m8 21-4-4 4-4" /><path d="M4 17h16" /></Icono>;
export const IconoCargando = (p) => <Icono {...p}><path d="M21 12a9 9 0 1 1-6.219-8.56" /></Icono>;
export const IconoEscudo = (p) => <Icono {...p}><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z" /><path d="m9 12 2 2 4-4" /></Icono>;
export const IconoPortapapeles = (p) => <Icono {...p}><rect width="8" height="4" x="8" y="2" rx="1" ry="1" /><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2" /><path d="M12 11h4" /><path d="M12 16h4" /><path d="M8 11h.01" /><path d="M8 16h.01" /></Icono>;
export const IconoHistorial = (p) => <Icono {...p}><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8" /><path d="M3 3v5h5" /><path d="M12 7v5l4 2" /></Icono>;
export const IconoPaquete = (p) => <Icono {...p}><path d="M11 21.73a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73z" /><path d="M12 22V12" /><path d="m3.3 7 7.703 4.734a2 2 0 0 0 1.994 0L20.7 7" /><path d="m7.5 4.27 9 5.15" /></Icono>;
