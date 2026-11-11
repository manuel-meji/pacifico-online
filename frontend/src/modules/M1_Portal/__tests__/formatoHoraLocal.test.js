import { describe, it, expect } from 'vitest';
import { horaLocalAIso, isoAHoraLocal } from '../services/formato';

// La hora del formulario siempre es de Costa Rica (UTC-6), así que los resultados son fijos y no dependen
// de la zona horaria de la máquina que corre las pruebas.
describe('conversión entre datetime-local (hora de Costa Rica) e ISO 8601', () => {
  it.each([
    ['2026-12-31T23:30', '2026-12-31T23:30:00-06:00'],
    ['2027-01-01T12:00', '2027-01-01T12:00:00-06:00'],
    ['2027-06-15T08:05:30', '2027-06-15T08:05:30-06:00'],
  ])('horaLocalAIso agrega el desfase de Costa Rica (%s)', (local, iso) => {
    expect(horaLocalAIso(local)).toBe(iso);
  });

  it.each([
    ['2027-03-15T18:30:00Z', '2027-03-15T12:30'],
    ['2027-03-15T12:30:00-06:00', '2027-03-15T12:30'],
    ['2027-03-15T12:30:00.000000Z', '2027-03-15T06:30'],
  ])('isoAHoraLocal muestra la hora de Costa Rica (%s)', (iso, local) => {
    expect(isoAHoraLocal(iso)).toBe(local);
  });

  it('una fecha de UTC pasada la medianoche cae en el día anterior de Costa Rica, también entre años', () => {
    expect(isoAHoraLocal('2027-01-01T03:00:00Z')).toBe('2026-12-31T21:00');
  });

  it.each(['2027-03-15T18:30:00Z', '2027-03-15T00:00:00Z', '2026-12-31T23:59:00Z', '2027-03-15T12:30:00-06:00'])(
    'el ida y vuelta conserva el mismo instante (%s)',
    (iso) => {
      expect(new Date(horaLocalAIso(isoAHoraLocal(iso))).getTime()).toBe(new Date(iso).getTime());
    },
  );

  it('un valor vacío o inválido no produce fecha', () => {
    expect(horaLocalAIso('')).toBeNull();
    expect(horaLocalAIso('no es una fecha')).toBeNull();
    expect(horaLocalAIso('2027-13-45T99:99')).toBeNull();
    expect(isoAHoraLocal(null)).toBe('');
    expect(isoAHoraLocal('no es una fecha')).toBe('');
  });
});
