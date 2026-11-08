   // @vitest-environment jsdom
 import { describe, it, expect, vi, afterEach } from 'vitest';
   import { render, screen, fireEvent, waitFor, cleanup } from '@testing-library/react';
   import FormularioPago from '../components/FormularioPago';

    afterEach(cleanup);
const pedido = { id_pedido: 8942, estado: 'confirmado', monto_total: 59840, subpedidos: [{ id_subpedido: 1, comercio: 'Comercio B', subtotal: 59840 }] };

describe('FormularioPago', () => {
  it('no pide número de tarjeta ni CVV (requisito de seguridad de M6)', () => {
    const { container } = render(<FormularioPago pedido={pedido} />);
    expect(container.querySelector('input[type="password"]')).toBeNull();
    expect(screen.queryByLabelText(/cvv/i)).toBeNull();
    expect(screen.queryByLabelText(/número de tarjeta/i)).toBeNull();
  });

  it('envía token e Idempotency-Key, nunca datos de tarjeta', async () => {
    const procesarPago = vi.fn().mockResolvedValue({ id: 1, estado: 'pendiente' });
    render(<FormularioPago pedido={pedido} api={{ procesarPago }} />);
    fireEvent.click(screen.getByRole('button', { name: /pagar/i }));
    await waitFor(() => expect(procesarPago).toHaveBeenCalledTimes(1));
    const [cuerpo, clave] = procesarPago.mock.calls[0];
    expect(cuerpo).toMatchObject({ id_pedido: 8942, medio_pago: 'tarjeta', monto_total: 59840, token_medio_pago: 'tok_sim_aprobada' });
    expect(cuerpo).not.toHaveProperty('cvv');
    expect(cuerpo).not.toHaveProperty('numero_tarjeta');
    expect(typeof clave).toBe('string');
  });

  it('no llama a la API si el pedido no está confirmado', async () => {
    const procesarPago = vi.fn();
    render(<FormularioPago pedido={{ ...pedido, estado: 'cancelado' }} api={{ procesarPago }} />);
    fireEvent.click(screen.getByRole('button', { name: /pagar/i }));
    expect(await screen.findByText(/pedido confirmado/i)).toBeTruthy();
    expect(procesarPago).not.toHaveBeenCalled();
  });
});
