// Fechas y horas de las promociones en hora de Costa Rica (UTC-6, sin horario de verano).
// El campo <input type="datetime-local"> da "2026-11-15T08:30" sin zona: siempre se interpreta
// en hora de Costa Rica y se envía en ISO 8601 con -06:00, aunque la compu esté en otra zona.

export const ZONA_COSTA_RICA = 'America/Costa_Rica';

const DESFASE_MS = -6 * 60 * 60 * 1000;
const FORMATO_CAMPO = /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})$/;

// "2026-11-15T08:30" (hora de Costa Rica) → "2026-11-15T08:30:00-06:00". Vacío o inválido → null.
export function aIsoCostaRica(valorCampo) {
  if (!valorCampo || !FORMATO_CAMPO.test(valorCampo)) return null;
  return `${valorCampo}:00-06:00`;
}

// ISO 8601 en cualquier zona → "2026-11-15T08:30" en hora de Costa Rica, para el campo. Inválido → ''.
export function aCampoCostaRica(iso) {
  const instante = new Date(iso);
  if (!iso || Number.isNaN(instante.getTime())) return '';
  return new Date(instante.getTime() + DESFASE_MS).toISOString().slice(0, 16);
}

// Momento actual en hora de Costa Rica, para el atributo min del campo.
export const ahoraCampoCostaRica = () => aCampoCostaRica(new Date().toISOString());

// "15 nov 2026, 08:30" en hora de Costa Rica, para mostrar.
export function fechaHoraCostaRica(iso) {
  const instante = new Date(iso);
  if (!iso || Number.isNaN(instante.getTime())) return '';
  return instante.toLocaleString('es-CR', { timeZone: ZONA_COSTA_RICA, day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: false });
}
