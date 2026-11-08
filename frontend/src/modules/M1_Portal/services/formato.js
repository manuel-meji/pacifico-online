// Formatos de presentación del portal.

// Monto en colones sin decimales: 18500 → "₡18 500".
export const colones = (monto) => '₡' + Math.round(Number(monto) || 0).toLocaleString('es-CR', { maximumFractionDigits: 0 });

// dia_semana del contrato: 1 = lunes … 7 = domingo.
const DIAS = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
export const nombreDia = (diaSemana) => DIAS[diaSemana - 1] ?? '';

// Texto de una variación a partir de sus atributos: "Talla: M · Color: Azul".
export const textoAtributos = (atributos = []) => atributos.map((a) => `${a.tipo_atributo}: ${a.valor}`).join(' · ');
