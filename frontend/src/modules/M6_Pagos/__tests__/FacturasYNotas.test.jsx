// @vitest-environment jsdom
import { describe, it, expect, vi, afterEach } from 'vitest';
import { render, screen, fireEvent, waitFor, cleanup } from '@testing-library/react';
import FacturasPedidoView from '../views/FacturasPedidoView';
import FormularioNotaAjuste from '../components/FormularioNotaAjuste';
import { validarNota } from '../lib/documentos';

afterEach(cleanup);

const factura = (extra = {}) => ({
  id: 1,
  id_pedido: 8942,
  id_subpedido: 1001,
  id_comercio: 11,
  consecutivo: '00100001010000001001',
  clave_numerica: '5'.repeat(50),
  monto_total: 35400,
  xml: '<FacturaElectronica/>',
  estado_hacienda: 'aceptada',
  fecha_emision: '2026-11-10T10:00:00-06:00',
  ...extra,
});

const nota = { id: 7, id_factura: 1, tipo: 'credito', monto: 5000, motivo: 'Devolución', xml: '<NotaCreditoElectronica/>', fecha_emision: '2026-11-10T11:00:00-06:00' };

describe('validarNota (RN-004)', () => {
  it('exige monto mayor que cero, con dos decimales, y un motivo', () => {
    expect(validarNota({ monto: '0', motivo: 'x' }).errores.monto).toMatch(/mayor que cero/);
    expect(validarNota({ monto: '10.123', motivo: 'x' }).errores.monto).toMatch(/dos decimales/);
    expect(validarNota({ monto: '10', motivo: '  ' }).errores.motivo).toBeTruthy();
    expect(validarNota({ monto: '10.50', motivo: 'Devolución' }).valido).toBe(true);
  });
});

describe('FacturasPedidoView (CU-004)', () => {
  it('muestra una tarjeta por factura del pedido', async () => {
    const listarFacturasPedido = vi.fn().mockResolvedValue([
      factura(),
      factura({ id: 2, id_subpedido: 1002, id_comercio: 12, consecutivo: '00100001010000001002', monto_total: 24440 }),
    ]);
    render(<FacturasPedidoView idPedido={8942} api={{ listarFacturasPedido, emitirNota: vi.fn() }} />);

    expect(await screen.findByText('Factura 00100001010000001001')).toBeTruthy();
    expect(screen.getByText('Factura 00100001010000001002')).toBeTruthy();
    expect(screen.getByText(/2 factura\(s\)/)).toBeTruthy();
    expect(listarFacturasPedido).toHaveBeenCalledWith(8942);
  });

  it('avisa cuando el pedido todavía no tiene facturas', async () => {
    render(<FacturasPedidoView idPedido={8942} api={{ listarFacturasPedido: vi.fn().mockResolvedValue([]), emitirNota: vi.fn() }} />);
    expect(await screen.findByText(/todavía no tiene facturas/)).toBeTruthy();
  });
});

describe('FormularioNotaAjuste (CU-005 / CU-006)', () => {
  it('emite una nota de crédito con monto numérico y motivo', async () => {
    const emitirNota = vi.fn().mockResolvedValue(nota);
    const onEmitida = vi.fn();
    render(<FormularioNotaAjuste factura={factura()} api={{ emitirNota }} onEmitida={onEmitida} />);

    fireEvent.change(screen.getByLabelText(/monto/i), { target: { value: '5000' } });
    fireEvent.change(screen.getByLabelText(/motivo/i), { target: { value: ' Devolución ' } });
    fireEvent.click(screen.getByRole('button', { name: /emitir nota de crédito/i }));

    await waitFor(() => expect(onEmitida).toHaveBeenCalledWith(nota));
    expect(emitirNota).toHaveBeenCalledWith(1, 'credito', { monto: 5000, motivo: 'Devolución' });
  });

  it('muestra el saldo disponible cuando la nota excede la factura (ERR-M6-017)', async () => {
    const error = Object.assign(new Error('x'), {
      codigo: 'ERR-M6-017',
      mensaje: 'El monto de la nota de crédito no puede superar el de la factura original.',
      detalles: { disponible: 15400 },
    });
    render(<FormularioNotaAjuste factura={factura()} api={{ emitirNota: vi.fn().mockRejectedValue(error) }} />);

    fireEvent.change(screen.getByLabelText(/monto/i), { target: { value: '20000' } });
    fireEvent.change(screen.getByLabelText(/motivo/i), { target: { value: 'Excede' } });
    fireEvent.click(screen.getByRole('button', { name: /emitir/i }));

    expect(await screen.findByText(/Saldo disponible.*15/)).toBeTruthy();
  });

  it('no deja elegir nota de débito si la factura no está aceptada (RN-005)', () => {
    render(<FormularioNotaAjuste factura={factura({ estado_hacienda: 'pendiente' })} api={{ emitirNota: vi.fn() }} />);
    expect(screen.getByRole('radio', { name: /nota de débito/i }).disabled).toBe(true);
    expect(screen.getByText(/se habilita cuando Hacienda acepte/)).toBeTruthy();
  });

  it('no llama a la API si el monto no es válido', async () => {
    const emitirNota = vi.fn();
    render(<FormularioNotaAjuste factura={factura()} api={{ emitirNota }} />);
    fireEvent.change(screen.getByLabelText(/motivo/i), { target: { value: 'x' } });
    fireEvent.click(screen.getByRole('button', { name: /emitir/i }));
    expect(await screen.findByText(/monto numérico/)).toBeTruthy();
    expect(emitirNota).not.toHaveBeenCalled();
  });
});
