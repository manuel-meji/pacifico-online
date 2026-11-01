import { describe, it, expect } from 'vitest';
import { validarSolicitudPago } from '../lib/validacionPago';

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
});
