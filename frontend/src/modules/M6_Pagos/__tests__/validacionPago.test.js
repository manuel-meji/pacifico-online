import { describe, it, expect } from 'vitest';
import { contieneDatosSensibles, validarSolicitudPago } from '../lib/validacionPago';

const pedido = { id_pedido: 8942, estado: 'confirmado', monto_total: 59840 };
const ok = { pedido, montoIngresado: 59840, medioPago: 'tarjeta', token: 'tok_sim_aprobada' };

describe('validarSolicitudPago (RN-M6-01)', () => {
  it('acepta una solicitud correcta', () => expect(validarSolicitudPago(ok).valido).toBe(true));
  it('rechaza monto distinto al del pedido', () =>
    expect(validarSolicitudPago({ ...ok, montoIngresado: 59000 }).errores.monto).toMatch(/no coincide/));
  it('rechaza monto cero o negativo', () =>
    expect(validarSolicitudPago({ ...ok, montoIngresado: 0 }).errores.monto).toBeDefined());
  it('rechaza pedido no confirmado', () =>
    expect(validarSolicitudPago({ ...ok, pedido: { ...pedido, estado: 'cancelado' } }).errores.pedido).toBeDefined());
  it('rechaza medio de pago inexistente', () =>
    expect(validarSolicitudPago({ ...ok, medioPago: 'cheque' }).errores.medioPago).toBeDefined());
  it('rechaza token que no corresponde al medio', () =>
    expect(validarSolicitudPago({ ...ok, medioPago: 'sinpe' }).errores.token).toBeDefined());
  it('rechaza un pedido sin identificador válido', () =>
    expect(validarSolicitudPago({ ...ok, pedido: { ...pedido, id_pedido: null } }).errores.pedido).toBeDefined());
  it('rechaza un monto total inválido en el pedido', () =>
    expect(validarSolicitudPago({ ...ok, pedido: { ...pedido, monto_total: 'no-numero' } }).errores.monto).toBeDefined());
  it('rechaza datos sensibles de tarjeta en el payload', () =>
    expect(validarSolicitudPago({ ...ok, datosPago: { cvv: '123' } }).errores.datosSensibles).toBeDefined());
  it('detecta PAN y CVV como campos prohibidos', () => {
    expect(contieneDatosSensibles({ numero_tarjeta: '4111111111111111' })).toBe(true);
    expect(contieneDatosSensibles({ cvv: '123' })).toBe(true);
    expect(contieneDatosSensibles({ token_medio_pago: 'tok_sim_aprobada' })).toBe(false);
  });
});
