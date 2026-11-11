// Componentes comunes del módulo M4 (sistema de diseño de Pacíficos Online, design.md).
// Íconos Lucide en SVG propio (components/iconos.jsx); sin emojis.
import { IconoCirculoAlerta, IconoCirculoCheck, IconoInfo, IconoTrianguloAlerta } from './iconos';

// Montos con dos decimales cuando los tienen (₡8 640,10); los enteros se muestran sin decimales (₡8 500).
export const formatoColones = (n) => {
  if (n == null) return '—';
  const decimales = Number.isInteger(Math.round(n * 100) / 100) ? 0 : 2;
  return new Intl.NumberFormat('es-CR', { style: 'currency', currency: 'CRC', minimumFractionDigits: decimales, maximumFractionDigits: decimales }).format(n);
};

export const formatoFecha = (iso) =>
  new Intl.DateTimeFormat('es-CR', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' }).format(new Date(iso));

export function Tarjeta({ children, className = '' }) {
  return <section className={`bg-white rounded-xl shadow-sm border border-gray-100 p-4 ${className}`}>{children}</section>;
}

const ESTILOS_AVISO = {
  error: { caja: 'bg-red-50 border-red-200 text-red-800', Icono: IconoCirculoAlerta },
  aviso: { caja: 'bg-amber-50 border-amber-200 text-amber-900', Icono: IconoTrianguloAlerta },
  exito: { caja: 'bg-green-50 border-green-200 text-green-800', Icono: IconoCirculoCheck },
  info: { caja: 'bg-blue-50 border-blue-200 text-brandDarkBlue', Icono: IconoInfo },
};

export function Aviso({ tipo = 'info', titulo, children, codigo }) {
  const { caja, Icono } = ESTILOS_AVISO[tipo];
  return (
    <div role={tipo === 'error' ? 'alert' : 'status'} className={`flex gap-2 rounded-lg border p-3 text-sm ${caja}`}>
      <Icono className="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
      <div className="min-w-0">
        {titulo && <p className="font-semibold">{titulo}</p>}
        <div className="break-words">{children}</div>
        {codigo && <p className="mt-1 text-xs opacity-75">Código {codigo}</p>}
      </div>
    </div>
  );
}

export function Campo({ id, etiqueta, ayuda, error, children }) {
  return (
    <div className="space-y-1">
      <label htmlFor={id} className="block text-sm font-semibold text-gray-800">{etiqueta}</label>
      {children}
      {ayuda && !error && <p className="text-xs text-gray-500">{ayuda}</p>}
      {error && <p id={`${id}-error`} className="text-xs font-medium text-red-600">{error}</p>}
    </div>
  );
}

export const claseEntrada = (conError) =>
  `block w-full min-w-0 rounded-lg border bg-white px-3 py-2.5 text-base text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-brandBlue/40 ${conError ? 'border-red-400' : 'border-gray-200 focus:border-brandBlue'}`;

export function BotonPrimario({ children, cargando, ...props }) {
  return (
    <button
      {...props}
      disabled={cargando || props.disabled}
      className="flex w-full items-center justify-center gap-2 rounded-lg bg-brandDarkBlue px-4 py-3 font-bold text-white transition hover:bg-brandBlue focus:outline-none focus:ring-2 focus:ring-brandBlue/50 disabled:cursor-not-allowed disabled:bg-gray-300 disabled:text-gray-500"
    >
      {children}
    </button>
  );
}

const ESTILO_TIPO = {
  entrada: 'bg-green-50 text-green-700 border-green-200',
  transferencia_entrada: 'bg-green-50 text-green-700 border-green-200',
  reingreso: 'bg-green-50 text-green-700 border-green-200',
  salida: 'bg-red-50 text-red-700 border-red-200',
  transferencia_salida: 'bg-red-50 text-red-700 border-red-200',
  ajuste: 'bg-amber-50 text-amber-800 border-amber-200',
  reserva: 'bg-blue-50 text-brandDarkBlue border-blue-200',
  confirmacion_reserva: 'bg-blue-50 text-brandDarkBlue border-blue-200',
  liberacion_reserva: 'bg-gray-50 text-gray-700 border-gray-200',
  vencimiento_reserva: 'bg-gray-50 text-gray-700 border-gray-200',
};

export const ESTADO_RESERVA = {
  activa: { nombre: 'Activa', estilo: 'bg-blue-50 text-brandDarkBlue border-blue-200' },
  confirmada: { nombre: 'Confirmada', estilo: 'bg-green-50 text-green-700 border-green-200' },
  consumida: { nombre: 'Despachada', estilo: 'bg-gray-100 text-gray-700 border-gray-200' },
  liberada: { nombre: 'Liberada', estilo: 'bg-gray-50 text-gray-600 border-gray-200' },
  vencida: { nombre: 'Vencida', estilo: 'bg-amber-50 text-amber-800 border-amber-200' },
};

export const NOMBRE_TIPO = {
  entrada: 'Entrada', salida: 'Salida', ajuste: 'Ajuste', transferencia_salida: 'Transferencia (sale)',
  transferencia_entrada: 'Transferencia (entra)', reingreso: 'Reingreso', reserva: 'Reserva',
  confirmacion_reserva: 'Reserva confirmada', liberacion_reserva: 'Reserva liberada', vencimiento_reserva: 'Reserva vencida',
};

export function InsigniaTipo({ tipo }) {
  const estilo = ESTILO_TIPO[tipo] ?? 'bg-gray-50 text-gray-600 border-gray-200';
  return <span className={`inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold ${estilo}`}>{NOMBRE_TIPO[tipo] ?? tipo}</span>;
}
