
import { createContext, useContext } from 'react';
import { AlertCircle, CheckCircle2, ShieldCheck, Loader2, LogOut, User } from 'lucide-react';
import logo from '../../../assets/logo-pacificos.png';


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
  Aprobada: 'bg-green-50 text-green-700 ring-green-200',
  Rechazado: 'bg-red-50 text-red-700 ring-red-200',
  Rechazada: 'bg-red-50 text-red-700 ring-red-200',
  Suspendido: 'bg-slate-100 text-slate-700 ring-slate-300',
};

export const Etiqueta = ({ estado }) => (
  <span className={`inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-semibold ring-1 ${ESTADOS[estado] ?? 'bg-slate-100 text-slate-700 ring-slate-300'}`}>
    {estado === 'Activo' && <ShieldCheck size={14} />}
    {estado}
  </span>
);


export const SesionContexto = createContext({ usuario: null, mensajeDeError: () => '' });
export const useSesion = () => useContext(SesionContexto);


export function textoDeError(resultado) {
  if (!resultado || resultado.ok) return '';
  const { estado, error } = resultado;
  if (estado === 401) return 'Tu sesión venció. Ingresá de nuevo.';
  if (estado === 403) return 'No tenés permiso para hacer esta acción.';
  if (estado === 404) return error?.mensaje || 'No se encontró.';
  return error?.mensaje || 'Ocurrió un error inesperado. Intentá de nuevo.';
}


export const erroresDeCampo = (resultado) =>
  resultado && !resultado.ok && resultado.estado === 422 ? resultado.error?.detalles || {} : {};


export const mensajeDe = (errores, campo) => errores?.[campo]?.[0] || '';

export const MensajeCampo = ({ mensaje }) =>
  mensaje ? <span role="alert" className="mt-1 block text-xs font-normal text-red-700">{mensaje}</span> : null;


export const AvisoError = ({ mensaje }) =>
  mensaje ? (
    <p role="alert" className="mt-3 flex items-start gap-2 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">
      <AlertCircle size={18} className="mt-0.5 shrink-0" /> {mensaje}
    </p>
  ) : null;

export const Aviso = ({ mensaje }) =>
  mensaje ? (
    <p role="status" className="mt-3 flex items-start gap-2 rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-800">
      <CheckCircle2 size={18} className="mt-0.5 shrink-0" /> {mensaje}
    </p>
  ) : null;

export const Cargando = ({ texto = 'Cargando…' }) => (
  <p className="flex items-center gap-2 py-6 text-sm text-slate-500">
    <Loader2 size={18} className="animate-spin" /> {texto}
  </p>
);



const VARIANTES = {
  primario: `${C.verde} ${C.verdeHover} text-white`,
  secundario: `${C.marino} hover:bg-[#172E6E] text-white`,
  peligro: 'bg-red-600 hover:bg-red-700 text-white',
  neutro: 'bg-white text-slate-700 ring-1 ring-slate-300 hover:bg-slate-50',
};
export const Boton = ({ variante = 'primario', className = '', type = 'button', children, ...p }) => (
  <button
    type={type}
    className={`inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-bold transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-[#1E3A8A] focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 ${VARIANTES[variante]} ${className}`}
    {...p}
  >
    {children}
  </button>
);

const estiloCampo = 'mt-1 w-full font-normal rounded-lg border border-slate-300 bg-white p-2.5 text-base text-slate-800 focus:border-[#1E3A8A] focus:outline-none focus:ring-2 focus:ring-[#1E3A8A]/20 aria-[invalid=true]:border-red-500';
export const Campo = ({ etiqueta, error, ...p }) => (
  <label className="mt-4 block text-sm font-semibold text-slate-700">{etiqueta}
    <input className={estiloCampo} aria-invalid={error ? 'true' : undefined} {...p} />
    <MensajeCampo mensaje={error} />
  </label>
);
export const AreaTexto = ({ etiqueta, error, ...p }) => (
  <label className="mt-4 block text-sm font-semibold text-slate-700">{etiqueta}
    <textarea rows="3" className={estiloCampo} aria-invalid={error ? 'true' : undefined} {...p} />
    <MensajeCampo mensaje={error} />
  </label>
);
export const Seleccion = ({ etiqueta, error, children, ...p }) => (
  <label className="mt-4 block text-sm font-semibold text-slate-700">{etiqueta}
    <select className={estiloCampo} aria-invalid={error ? 'true' : undefined} {...p}>{children}</select>
    <MensajeCampo mensaje={error} />
  </label>
);

export const Tarjeta = ({ className = '', children }) => (
  <div className={`rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6 ${className}`}>{children}</div>
);


export const Encabezado = ({ usuario, onIngresar, onSalir }) => (
  <header className="border-b border-slate-200 bg-white">
    <div className="mx-auto flex max-w-6xl items-center justify-between px-4 py-3">
      <a href="/m1.html" aria-label="Pacíficos Online, ir al inicio"
        className="flex items-center gap-2 rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-[#1E3A8A] focus-visible:ring-offset-2">
        {/* Mismo logotipo del portal (M1), para que el panel se vea como parte de la plataforma. */}
        <img src={logo} alt="" className="h-10 w-10 rounded-full" />
        <span className="leading-tight">
          <span className={`block text-lg font-extrabold ${C.marinoTexto}`}>Pacíficos</span>
          <span className={`block text-[11px] font-bold tracking-widest ${C.verdeTexto}`}>ONLINE</span>
        </span>
      </a>
      {usuario ? (
        <div className="flex items-center gap-3 text-sm font-semibold text-[#1E3A8A]">
          <span className="hidden items-center gap-1 sm:flex"><User size={18} /> {usuario.nombre}</span>
          <Boton variante="neutro" className="py-2" onClick={onSalir}><LogOut size={16} /> Salir</Boton>
        </div>
      ) : (
        <Boton variante="secundario" className="py-2" onClick={onIngresar}><User size={16} /> Ingresar</Boton>
      )}
    </div>
  </header>
);