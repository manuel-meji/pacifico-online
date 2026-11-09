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
export const fechaCorta = (iso) => new Date(iso).toLocaleDateString('es-CR', { day: 'numeric', month: 'short' });

// dia_semana del contrato: 1 = lunes … 7 = domingo.
const DIAS = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
export const nombreDia = (diaSemana) => DIAS[diaSemana - 1] ?? '';

// Texto de una variación a partir de sus atributos: "Talla: M · Color: Azul".
export const textoAtributos = (atributos = []) => atributos.map((a) => `${a.tipo_atributo}: ${a.valor}`).join(' · ');
