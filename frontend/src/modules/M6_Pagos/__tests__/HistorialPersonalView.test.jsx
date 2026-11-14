// @vitest-environment jsdom
import { describe, it, expect, vi, afterEach } from 'vitest';
import { render, screen, cleanup } from '@testing-library/react';
import HistorialPersonalView from '../views/HistorialPersonalView';

afterEach(cleanup);

const historial = {
  pagos: [{ id: 1, id_pedido: 8942, medio_pago: 'tarjeta', monto_total: 59840, estado: 'confirmado', fecha_creacion: '2026-11-10T10:00:00-06:00', fecha_confirmacion: '2026-11-10T10:00:05-06:00' }],
  facturas: [{ id: 1, id_pedido: 8942, id_subpedido: 1001, id_comercio: 11, consecutivo: '00100001010000001001', monto_total: 35400, estado_hacienda: 'aceptada', fecha_emision: '2026-11-10T10:00:06-06:00' }],
};

describe('HistorialPersonalView', () => {
  it('muestra los pagos del cliente con sus facturas y consulta sin filtros', async () => {
    const consultarHistorial = vi.fn().mockResolvedValue(historial);
    render(<HistorialPersonalView api={{ consultarHistorial }} />);

    expect(await screen.findByText('Pedido #8942 · Pago #1')).toBeTruthy();
    expect(screen.getByText('00100001010000001001')).toBeTruthy();
    expect(consultarHistorial).toHaveBeenCalledWith({});
  });

  it('invita a ingresar cuando no hay sesión (422)', async () => {
    const error = Object.assign(new Error('x'), { estadoHttp: 422 });
    render(<HistorialPersonalView api={{ consultarHistorial: vi.fn().mockRejectedValue(error) }} />);

    expect(await screen.findByText(/ingresá con tu cuenta de cliente/)).toBeTruthy();
  });

  it('muestra un aviso, no un error, cuando no hay pagos (ERR-M6-020)', async () => {
    const error = Object.assign(new Error('x'), { codigo: 'ERR-M6-020' });
    render(<HistorialPersonalView api={{ consultarHistorial: vi.fn().mockRejectedValue(error) }} />);

    expect(await screen.findByText(/Todavía no tenés pagos registrados/)).toBeTruthy();
  });
});