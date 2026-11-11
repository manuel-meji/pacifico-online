import { describe, expect, it } from 'vitest';
import { aCampoCostaRica, aIsoCostaRica } from '../services/horaCostaRica';

// El campo datetime-local se lee siempre en hora de Costa Rica (UTC-6), sin importar la zona del navegador.
describe('horaCostaRica', () => {
  it('convierte el valor del campo a ISO 8601 con -06:00', () => {
    expect(aIsoCostaRica('2026-11-20T08:30')).toBe('2026-11-20T08:30:00-06:00');
  });

  it('rechaza un campo vacío o incompleto', () => {
    expect(aIsoCostaRica('')).toBeNull();
    expect(aIsoCostaRica('2026-11-20')).toBeNull();
  });

  it('muestra en el campo la hora de Costa Rica de un instante en UTC', () => {
    expect(aCampoCostaRica('2026-11-20T14:30:00Z')).toBe('2026-11-20T08:30');
    expect(aCampoCostaRica('2026-11-21T02:00:00+00:00')).toBe('2026-11-20T20:00');
  });

  it('ida y vuelta da el mismo valor del campo', () => {
    expect(aCampoCostaRica(aIsoCostaRica('2026-12-31T23:59'))).toBe('2026-12-31T23:59');
  });

  it('devuelve vacío si la fecha no es válida', () => {
    expect(aCampoCostaRica(null)).toBe('');
    expect(aCampoCostaRica('no es fecha')).toBe('');
  });

  it('rechaza un texto que no tiene forma de fecha y hora', () => {
    expect(aIsoCostaRica('no es una fecha')).toBeNull();
  });

  it('una fecha que ya viene con el desfase de Costa Rica se muestra con la misma hora', () => {
    expect(aCampoCostaRica('2027-03-15T12:30:00-06:00')).toBe('2027-03-15T12:30');
  });

  it('pasada la medianoche de UTC cae en el día anterior de Costa Rica, también entre años', () => {
    expect(aCampoCostaRica('2027-01-01T03:00:00+00:00')).toBe('2026-12-31T21:00');
  });

  it.each(['2027-03-15T18:30:00Z', '2027-03-15T00:00:00+00:00', '2026-12-31T23:59:00Z', '2027-03-15T12:30:00-06:00'])(
    'de ISO al campo y de vuelta conserva el mismo instante (%s)',
    (iso) => {
      expect(new Date(aIsoCostaRica(aCampoCostaRica(iso))).getTime()).toBe(new Date(iso).getTime());
    },
  );
});
