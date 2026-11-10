// @vitest-environment jsdom
import { describe, it, expect, vi, afterEach } from 'vitest';
import { render, screen, fireEvent, cleanup } from '@testing-library/react';
import HistorialComercioView from '../views/HistorialComercioView';

afterEach(cleanup);

const historial = {
  pagos: [{ id: 1, id_pedido: 8942, medio_pago: 'tarjeta', monto_total: 59840, estado: 'confirmado', fecha_creacion: '2026-11-10T10:00:00-06:00', fecha_confirmacion: '2026-11-10T10:00:05-06:00' }],
  facturas: [{ id: 1, id_pedido: 8942, id_subpedido: 1001, id_comercio: 11, consecutivo: '00100001010000001001', monto_total: 35400, estado_hacienda: 'aceptada', fecha_emision: '2026-11-10T10:00:06-06:00' }],
};

function consultar(tipo, numero) {
  fireEvent.change(screen.getByLabelText(/buscar por/i), { target: { value: tipo } });
  fireEvent.change(screen.getByLabelText(/número/i), { target: { value: numero } });
  fireEvent.click(screen.getByRole('button', { name: /consultar/i }));
}

describe('HistorialComercioView (CU-008)', () => {
  it('muestra los pagos y las facturas del comercio en tarjetas, sin tabla (360 px)', async () => {
    const consultarHistorial = vi.fn().mockResolvedValue(historial);
    const { container } = render(<HistorialComercioView api={{ consultarHistorial }} />);

    consultar('comercio', '11');

    expect(await screen.findByText('Pago #1 · Pedido #8942')).toBeTruthy();
    expect(screen.getByText('Factura 00100001010000001001')).toBeTruthy();
    expect(screen.getByText('Pagos (1)')).toBeTruthy();
    expect(screen.getByText('Facturas (1)')).toBeTruthy();
    expect(container.querySelector('table')).toBeNull();
    expect(consultarHistorial).toHaveBeenCalledWith({ idComercio: 11 });
  });

  it('consulta por cliente cuando se elige "Cliente"', async () => {
    const consultarHistorial = vi.fn().mockResolvedValue(historial);
    render(<HistorialComercioView api={{ consultarHistorial }} />);

    consultar('cliente', '77');

    expect(await screen.findByText('Pago #1 · Pedido #8942')).toBeTruthy();
    expect(consultarHistorial).toHaveBeenCalledWith({ idCliente: 77 });
  });

  it('muestra un aviso (no un error) cuando no hay resultados (ERR-M6-020)', async () => {
    const error = Object.assign(new Error('x'), { codigo: 'ERR-M6-020', mensaje: 'No se encontraron pagos ni facturas para los filtros indicados.' });
    render(<HistorialComercioView api={{ consultarHistorial: vi.fn().mockRejectedValue(error) }} />);

    consultar('comercio', '99');

    expect(await screen.findByText(/No hay pagos ni facturas para este comercio/)).toBeTruthy();
    expect(screen.queryByRole('alert')).toBeNull();
  });

  it('no llama a la API si el número no es válido', async () => {
    const consultarHistorial = vi.fn();
    render(<HistorialComercioView api={{ consultarHistorial }} />);

    consultar('comercio', '0');

    expect(await screen.findByText(/número de comercio o de cliente válido/)).toBeTruthy();
    expect(consultarHistorial).not.toHaveBeenCalled();
  });
});