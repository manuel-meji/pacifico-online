// Íconos SVG de la ficha de comercio, con los trazos de Lucide (https://lucide.dev, licencia ISC).
// Siguen el design.md: íconos vectoriales con stroke="currentColor" y tamaño por clase, sin emojis
// y sin agregar dependencias al package.json compartido.
const trazos = {
  ubicacion: <><path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0" /> <circle cx="12" cy="10" r="3" /></>,
  telefono: <><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z" /></>,
  correo: <><rect width="20" height="16" x="2" y="4" rx="2" /> <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7" /></>,
  buscar: <><circle cx="11" cy="11" r="8" /> <path d="m21 21-4.3-4.3" /></>,
  usuario: <><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2" /> <circle cx="12" cy="7" r="4" /></>,
  carrito: <><circle cx="8" cy="21" r="1" /> <circle cx="19" cy="21" r="1" /> <path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12" /></>,
  inicio: <><path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8" /> <path d="M3 10a2 2 0 0 1 .709-1.528l7-5.999a2 2 0 0 1 2.582 0l7 5.999A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" /></>,
  tienda: <><path d="m2 7 4.41-4.41A2 2 0 0 1 7.83 2h8.34a2 2 0 0 1 1.42.59L22 7" /> <path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8" /> <path d="M15 22v-4a2 2 0 0 0-2-2h-2a2 2 0 0 0-2 2v4" /> <path d="M2 7h20" /> <path d="M22 7v3a2 2 0 0 1-2 2a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 16 12a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 12 12a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 8 12a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 4 12a2 2 0 0 1-2-2V7" /></>,
  paquete: <><path d="M11 21.73a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73z" /> <path d="M12 22V12" /> <path d="m3.3 7 7.703 4.734a2 2 0 0 0 1.994 0L20.7 7" /> <path d="m7.5 4.27 9 5.15" /></>,
  filtros: <><line x1="21" x2="14" y1="4" y2="4" /> <line x1="10" x2="3" y1="4" y2="4" /> <line x1="21" x2="12" y1="12" y2="12" /> <line x1="8" x2="3" y1="12" y2="12" /> <line x1="21" x2="16" y1="20" y2="20" /> <line x1="12" x2="3" y1="20" y2="20" /> <line x1="14" x2="14" y1="2" y2="6" /> <line x1="8" x2="8" y1="10" y2="14" /> <line x1="16" x2="16" y1="18" y2="22" /></>,
  cerrar: <><path d="M18 6 6 18" /> <path d="m6 6 12 12" /></>,
  check: <><path d="M20 6 9 17l-5-5" /></>,
  verificado: <><path d="M3.85 8.62a4 4 0 0 1 4.78-4.77 4 4 0 0 1 6.74 0 4 4 0 0 1 4.78 4.78 4 4 0 0 1 0 6.74 4 4 0 0 1-4.77 4.78 4 4 0 0 1-6.75 0 4 4 0 0 1-4.78-4.77 4 4 0 0 1 0-6.76Z" /> <path d="m9 12 2 2 4-4" /></>,
  abajo: <><path d="m6 9 6 6 6-6" /></>,
  derecha: <><path d="m9 18 6-6-6-6" /></>,
  izquierda: <><path d="m15 18-6-6 6-6" /></>,
  primera: <><path d="m11 17-5-5 5-5" /> <path d="m18 17-5-5 5-5" /></>,
  ultima: <><path d="m6 17 5-5-5-5" /> <path d="m13 17 5-5-5-5" /></>,
  alerta: <><circle cx="12" cy="12" r="10" /> <line x1="12" x2="12" y1="8" y2="12" /> <line x1="12" x2="12.01" y1="16" y2="16" /></>,
  disponible: <><circle cx="12" cy="12" r="10" /> <path d="m9 12 2 2 4-4" /></>,
  camara: <><path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z" /> <circle cx="12" cy="13" r="3" /></>,
  wifi: <><path d="M12 20h.01" /> <path d="M2 8.82a15 15 0 0 1 20 0" /> <path d="M5 12.859a10 10 0 0 1 14 0" /> <path d="M8.5 16.429a5 5 0 0 1 7 0" /></>,
  red: <><rect x="16" y="16" width="6" height="6" rx="1" /> <rect x="2" y="16" width="6" height="6" rx="1" /> <rect x="9" y="2" width="6" height="6" rx="1" /> <path d="M5 16v-3a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v3" /> <path d="M12 12V8" /></>,
  bombillo: <><path d="M15 14c.2-1 .7-1.7 1.5-2.5 1-.9 1.5-2.2 1.5-3.5A6 6 0 0 0 6 8c0 1 .2 2.2 1.5 3.5.7.7 1.3 1.5 1.5 2.5" /> <path d="M9 18h6" /> <path d="M10 22h4" /></>,
  audifonos: <><path d="M3 14h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-7a9 9 0 0 1 18 0v7a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3" /></>,
  usb: <><circle cx="10" cy="7" r="1" /> <circle cx="4" cy="20" r="1" /> <path d="M4.7 19.3 19 5" /> <path d="m21 3-3 1 2 2Z" /> <path d="M9.26 7.68 5 12l2 5" /> <path d="m10 14 5 2 3.5-3.5" /> <path d="m18 12 1-1 1 1-1 1Z" /></>,
};

export default function Icono({ nombre, className = 'h-4 w-4' }) {
  return (
    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"
      strokeLinecap="round" strokeLinejoin="round" className={`shrink-0 ${className}`}>
      {trazos[nombre]}
    </svg>
  );
}
