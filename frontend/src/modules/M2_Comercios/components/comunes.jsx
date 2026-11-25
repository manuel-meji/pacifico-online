
import { createContext, useContext } from 'react';
import { AlertCircle, CheckCircle2, Info, ShieldCheck, Loader2, LogOut, Trash2, User } from 'lucide-react';
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


// Tipos de documento de la solicitud (hallazgos 5 y 12): el valor que guarda el backend y el nombre
// que se muestra. La constancia de Hacienda respalda la inscripción como contribuyente (RN-001).
export const TIPOS_DOCUMENTO = [
  ['cedula_juridica', 'Cédula jurídica'], ['patente', 'Patente comercial'], ['permiso_sanitario', 'Permiso sanitario'],
  ['constancia_hacienda', 'Constancia de inscripción en Hacienda'], ['otro', 'Otro'],
];
const NOMBRES_DOCUMENTO = Object.fromEntries(TIPOS_DOCUMENTO);
export const nombreDocumento = (tipo) => NOMBRES_DOCUMENTO[tipo] ?? 'Documento';

// Fechas como las ve una persona en Costa Rica (21/11/2026). Las fechas sin hora se toman como locales.
export const fechaCorta = (valor) => {
  if (!valor) return '—';
  const texto = String(valor);
  return new Date(texto.length === 10 ? `${texto}T00:00:00` : texto).toLocaleDateString('es-CR');
};

// Medidas de una imagen elegida, para avisar antes de enviarla (hallazgo 28).
export const medirImagen = (archivo) => new Promise((resolver) => {
  if (!archivo || typeof URL?.createObjectURL !== 'function') { resolver(null); return; }
  const url = URL.createObjectURL(archivo);
  const img = new Image();
  img.onload = () => { resolver({ ancho: img.naturalWidth, alto: img.naturalHeight }); URL.revokeObjectURL(url); };
  img.onerror = () => { resolver(null); URL.revokeObjectURL(url); };
  img.src = url;
});

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

 // Aviso neutro, para resultados que no son un éxito ni un error (por ejemplo, un rechazo ya hecho).
export const AvisoInfo = ({ mensaje }) =>
  mensaje ? (
    <p role="status" className="mt-3 flex items-start gap-2 rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700">
      <Info size={18} className="mt-0.5 shrink-0" /> {mensaje}
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
  // Acción negativa que no es la principal (hallazgo 13): borde rojo, sin relleno.
  peligroBorde: 'bg-white text-red-700 ring-1 ring-red-300 hover:bg-red-50',
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

// Hallazgo 43: todos los basureros iguales, como «Quitar logotipo» pero solo con el ícono. Conservan su
// tamaño también en celular (w-fit): en una cuadrícula de una columna no se estiran a todo lo ancho.
export const BotonQuitar = ({ etiqueta, className = '', ...p }) => (
  <Boton variante="peligroBorde" aria-label={etiqueta} title={etiqueta} className={`w-fit justify-self-start px-2.5 ${className}`} {...p}>
    <Trash2 size={16} aria-hidden="true" />
  </Boton>
);

// Hallazgo 42: en las listas de «uno por tipo», la opción ya usada sigue visible pero deshabilitada y dice
// por qué. «femenino» es para las que concuerdan en femenino (política, zona).
export const textoOpcion = (texto, usada, femenino = false) => (usada ? `${texto} (ya agregad${femenino ? 'a' : 'o'})` : texto);

const estiloCampo = 'mt-1 w-full font-normal rounded-lg border border-slate-300 bg-white p-2.5 text-base text-slate-800 focus:border-[#1E3A8A] focus:outline-none focus:ring-2 focus:ring-[#1E3A8A]/20 aria-[invalid=true]:border-red-500';
// ayuda: texto corto debajo del campo que dice qué escribir (se oculta cuando hay error).
const Ayuda = ({ texto }) => (texto ? <span className="mt-1 block text-xs font-normal text-slate-500">{texto}</span> : null);
export const Campo = ({ etiqueta, error, ayuda, ...p }) => (
  <label className="mt-4 block text-sm font-semibold text-slate-700">{etiqueta}
    <input className={estiloCampo} aria-invalid={error ? 'true' : undefined} {...p} />
    {error ? <MensajeCampo mensaje={error} /> : <Ayuda texto={ayuda} />}
  </label>
);
export const AreaTexto = ({ etiqueta, error, ayuda, ...p }) => (
  <label className="mt-4 block text-sm font-semibold text-slate-700">{etiqueta}
    <textarea rows="3" className={estiloCampo} aria-invalid={error ? 'true' : undefined} {...p} />
    {error ? <MensajeCampo mensaje={error} /> : <Ayuda texto={ayuda} />}
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


export const Encabezado = ({ usuario, onIngresar, onSalir }) => {
  const esComercio = usuario?.rol === 'encargado_comercio';
  return (
    <header className="border-b border-slate-200 bg-white">
      <div className="mx-auto flex max-w-6xl items-center justify-between px-4 py-3">
        <a href={esComercio ? '/m2.html' : '/m1.html'} aria-label={esComercio ? 'Pacíficos Online, ir a Mi comercio' : 'Pacíficos Online, ir al inicio'}
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
};