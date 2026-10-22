// Piezas compartidas del prototipo M2 (datos simulados, sin backend).
import { AlertCircle, CheckCircle2, ShieldCheck, ShoppingCart, User, MapPin, Phone } from 'lucide-react';

// Tokens de color de la guía visual.
export const C = {
  marino: 'bg-[#1E3A8A]',
  marinoTexto: 'text-[#1E3A8A]',
  verde: 'bg-[#9DBB45]',
  verdeHover: 'hover:bg-[#8AA83A]',
  verdeTexto: 'text-[#7E9C30]',
};

export const ESTADOS = {
  Pendiente: 'bg-amber-50 text-amber-800 ring-amber-200',
  Activo: 'bg-green-50 text-green-700 ring-green-200',
  Rechazado: 'bg-red-50 text-red-700 ring-red-200',
  Suspendido: 'bg-slate-100 text-slate-700 ring-slate-300',
};

export const Etiqueta = ({ estado }) => (
  <span className={`inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-semibold ring-1 ${ESTADOS[estado]}`}>
    {estado === 'Activo' && <ShieldCheck size={14} />}
    {estado}
  </span>
);

// Reemplazar por el <AvisoError> compartido del núcleo cuando esté en el repo.
export const AvisoError = ({ mensaje }) =>
  mensaje ? (
    <p role="alert" className="mt-3 flex items-start gap-2 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">
      <AlertCircle size={18} className="mt-0.5 shrink-0" /> {mensaje}
    </p>
  ) : null;

export const Aviso = ({ mensaje }) =>
  mensaje ? (
    <p className="mt-3 flex items-start gap-2 rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-800">
      <CheckCircle2 size={18} className="mt-0.5 shrink-0" /> {mensaje}
    </p>
  ) : null;


const VARIANTES = {
  primario: `${C.verde} ${C.verdeHover} text-white`,
  secundario: `${C.marino} hover:bg-[#172E6E] text-white`,
  peligro: 'bg-red-600 hover:bg-red-700 text-white',
  neutro: 'bg-white text-slate-700 ring-1 ring-slate-300 hover:bg-slate-50',
};
export const Boton = ({ variante = 'primario', className = '', children, ...p }) => (
  <button
    className={`inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-bold transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-[#1E3A8A] focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 ${VARIANTES[variante]} ${className}`}
    {...p}
  >
    {children}
  </button>
);

const estiloCampo = 'mt-1 w-full font-normal rounded-lg border border-slate-300 bg-white p-2.5 text-base text-slate-800 focus:border-[#1E3A8A] focus:outline-none focus:ring-2 focus:ring-[#1E3A8A]/20';
export const Campo = ({ etiqueta, ...p }) => (
  <label className="mt-4 block text-sm font-semibold text-slate-700">{etiqueta}
    <input className={estiloCampo} {...p} />
  </label>
);
export const AreaTexto = ({ etiqueta, ...p }) => (
  <label className="mt-4 block text-sm font-semibold text-slate-700">{etiqueta}
    <textarea rows="3" className={estiloCampo} {...p} />
  </label>
);

export const Tarjeta = ({ className = '', children }) => (
  <div className={`rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6 ${className}`}>{children}</div>
);

// Barra superior y encabezado con el logotipo, como en el resto de la plataforma.
export const Encabezado = () => (
  <header>
    <div className={`${C.marino} text-white`}>
      <div className="mx-auto flex max-w-6xl items-center gap-5 px-4 py-2 text-xs">
        <span className="flex items-center gap-1"><MapPin size={14} /> Envíos rápidos a todo Costa Rica</span>
        <span className="hidden items-center gap-1 sm:flex"><Phone size={14} /> Soporte: +506 2000-0000</span>
      </div>
    </div>
    <div className="border-b border-slate-200 bg-white">
      <div className="mx-auto flex max-w-6xl items-center justify-between px-4 py-3">
        <div className="flex items-center gap-2">
          <span className={`flex h-10 w-10 items-center justify-center rounded-full ${C.verde} text-lg font-extrabold text-white`}>P</span>
          <span className="leading-tight">
            <span className={`block text-lg font-extrabold ${C.marinoTexto}`}>Pacíficos</span>
            <span className={`block text-[11px] font-bold tracking-widest ${C.verdeTexto}`}>ONLINE</span>
          </span>
        </div>
        <div className="flex items-center gap-4 text-sm font-semibold text-[#1E3A8A]">
          <span className="flex items-center gap-1"><User size={18} /> Mi Cuenta</span>
          <ShoppingCart size={20} className="hidden sm:block" />
        </div>
      </div>
    </div>
  </header>
);

export const SOLICITUDES = [
  { id: 101, comercio: 'Soda La Pacífica', cedula: '3-101-123456', completa: true, contribuyente: true, estado: 'Pendiente',
    docs: [{ nombre: 'Cédula jurídica', ok: true }, { nombre: 'Patente comercial', ok: true }] },
  { id: 102, comercio: 'Ferretería El Tornillo', cedula: '3-101-654321', completa: true, contribuyente: true, estado: 'Pendiente',
    docs: [{ nombre: 'Cédula jurídica', ok: true }, { nombre: 'Patente comercial (vencida)', ok: false }] },
  { id: 103, comercio: 'Artesanías Guanacaste', cedula: '3-102-222333', completa: true, contribuyente: false, estado: 'Pendiente',
    docs: [{ nombre: 'Cédula jurídica', ok: true }] },
  { id: 104, comercio: 'Panadería Doña Rosa', cedula: '3-101-999888', completa: true, contribuyente: true, estado: 'Activo', pedidosEnCurso: 2,
    docs: [{ nombre: 'Cédula jurídica', ok: true }] },
];