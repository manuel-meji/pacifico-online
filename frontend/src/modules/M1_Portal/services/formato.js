// Formatos de presentación del portal.

// Monto en colones: sin decimales si es entero (18500 → "₡18 500") y con 2 si no
// (precios con promoción, 11041.5 → "₡11 041,50").
export const colones = (monto) => {
  const valor = Math.round((Number(monto) || 0) * 100) / 100;
  const decimales = Number.isInteger(valor) ? 0 : 2;
  return '₡' + valor.toLocaleString('es-CR', { minimumFractionDigits: decimales, maximumFractionDigits: decimales });
};

// Descuento de una promoción (contrato M1 1.4.0): "-20 %" o "Precio especial".
export const textoDescuento = (promocion) =>
  promocion?.tipo === 'porcentaje' ? `-${Number(promocion.valor).toLocaleString('es-CR')} %` : 'Precio especial';

// Fecha corta para "Hasta el 30 nov.".
// Fecha de publicación de una noticia: "10 de noviembre de 2026".
export const fechaLarga = (iso) => new Date(iso).toLocaleDateString('es-CR', { day: 'numeric', month: 'long', year: 'numeric' });

export const fechaCorta = (iso) => new Date(iso).toLocaleDateString('es-CR', { day: 'numeric', month: 'short' });

// dia_semana del contrato: 1 = lunes … 7 = domingo.
const DIAS = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
export const nombreDia = (diaSemana) => DIAS[diaSemana - 1] ?? '';

// Texto de una variación a partir de sus atributos: "Talla: M · Color: Azul".
export const textoAtributos = (atributos = []) => atributos.map((a) => `${a.tipo_atributo}: ${a.valor}`).join(' · ');

// Un <input type="datetime-local"> entrega la fecha y hora sin zona ("2026-11-10T14:30"). En el panel de
// gestión siempre es hora de Costa Rica (UTC-6, sin horario de verano), igual que aIsoCostaRica de
// destacadosApi.js, sin importar la zona horaria de la máquina.
const DESFASE_COSTA_RICA_MS = 6 * 60 * 60 * 1000;
const FORMATO_HORA_LOCAL = /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?$/;

// ISO 8601 del contrato → valor del campo en hora de Costa Rica. '' si la fecha no es válida.
export const isoAHoraLocal = (iso) => {
  const fecha = new Date(iso);
  if (!iso || Number.isNaN(fecha.getTime())) return '';
  return new Date(fecha.getTime() - DESFASE_COSTA_RICA_MS).toISOString().slice(0, 16);
};

// Valor del campo (hora de Costa Rica) → ISO 8601 con desfase -06:00. null si la fecha no es válida.
export const horaLocalAIso = (valor) => {
  if (!valor || !FORMATO_HORA_LOCAL.test(valor)) return null;
  const iso = `${valor.length === 16 ? `${valor}:00` : valor}-06:00`;
  return Number.isNaN(new Date(iso).getTime()) ? null : iso;
};
