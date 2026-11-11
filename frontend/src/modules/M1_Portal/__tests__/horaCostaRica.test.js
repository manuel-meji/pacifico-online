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
});
