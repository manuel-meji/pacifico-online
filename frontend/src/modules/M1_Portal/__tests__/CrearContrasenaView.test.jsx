// @vitest-environment jsdom
import { describe, it, expect, vi, afterEach } from 'vitest';
import { render, screen, fireEvent, cleanup, waitFor } from '@testing-library/react';
import CrearContrasenaView from '../views/CrearContrasenaView';

afterEach(cleanup);

const ENLACE = { token: 'token-de-invitacion', correo: 'encargado@example.com' };

const fallo = (estado, codigo, mensaje, detalles = null) => ({ ok: false, estado, error: { codigo, mensaje, detalles } });

function montar(props = {}) {
  const acciones = {
    onCrear: vi.fn().mockResolvedValue({ ok: true, datos: { mensaje: 'Contraseña definida. Ya puede iniciar sesión.' } }),
    onIrIngresar: vi.fn(),
    onIrInicio: vi.fn(),
  };
  render(<CrearContrasenaView {...ENLACE} {...acciones} {...props} />);
  return { ...acciones, ...props };
}

function llenar(contrasena, confirmacion = contrasena) {
  fireEvent.change(screen.getByLabelText('Contraseña'), { target: { value: contrasena } });
  fireEvent.change(screen.getByLabelText('Confirmar contraseña'), { target: { value: confirmacion } });
  fireEvent.click(screen.getByRole('button', { name: 'Definir contraseña' }));
}

// Ningún código de error del núcleo (ERR-005, ERR-429, ...) debe llegar a la pantalla.
const sinCodigos = () => expect(document.body.textContent).not.toMatch(/ERR-/);

describe('CrearContrasenaView', () => {
  it.each([
    ['sin token', { token: undefined }],
    ['sin correo', { correo: undefined }],
    ['sin ninguno', { token: undefined, correo: undefined }],
  ])('con el enlace incompleto (%s) muestra un aviso y el botón al inicio, sin formulario', (_caso, faltantes) => {
    const { onIrInicio, onCrear } = montar(faltantes);

    expect(screen.getByRole('alert').textContent).toMatch(/enlace de invitación está incompleto/);
    expect(screen.queryByLabelText('Contraseña')).toBeNull();
    expect(screen.queryByRole('button', { name: 'Definir contraseña' })).toBeNull();

    fireEvent.click(screen.getByRole('button', { name: 'Ir al inicio' }));
    expect(onIrInicio).toHaveBeenCalledTimes(1);
    expect(onCrear).not.toHaveBeenCalled();
  });

  it('muestra el correo como dato de solo lectura', () => {
    montar();

    expect(screen.getByText('encargado@example.com')).toBeTruthy();
    expect(screen.queryByDisplayValue('encargado@example.com')).toBeNull();
  });

  it('no envía si las contraseñas no coinciden y lo avisa junto a la confirmación', () => {
    const { onCrear } = montar();

    llenar('clave-nueva-123', 'clave-distinta-123');

    expect(onCrear).not.toHaveBeenCalled();
    const confirmacion = screen.getByLabelText('Confirmar contraseña');
    expect(confirmacion.getAttribute('aria-invalid')).toBe('true');
    expect(document.getElementById(confirmacion.getAttribute('aria-describedby')).textContent).toBe('Las contraseñas no coinciden.');
  });

  it('no envía una contraseña de menos de 8 caracteres', () => {
    const { onCrear } = montar();

    llenar('corta12');

    expect(onCrear).not.toHaveBeenCalled();
    expect(screen.getByText('La contraseña debe tener al menos 8 caracteres.')).toBeTruthy();
  });

  it('al definir la contraseña muestra la confirmación y lleva a ingresar, sin iniciar sesión', async () => {
    const { onCrear, onIrIngresar } = montar();

    llenar('clave-nueva-123');

    await waitFor(() => expect(screen.getByRole('status').textContent).toBe('Contraseña definida. Ya puede iniciar sesión.'));
    expect(onCrear).toHaveBeenCalledTimes(1);
    expect(onCrear).toHaveBeenCalledWith({
      token: 'token-de-invitacion',
      correo: 'encargado@example.com',
      contrasena: 'clave-nueva-123',
      contrasenaConfirmacion: 'clave-nueva-123',
    });
    expect(screen.queryByLabelText('Contraseña')).toBeNull();
    // La navegación solo ocurre cuando la persona pulsa el botón.
    expect(onIrIngresar).not.toHaveBeenCalled();

    fireEvent.click(screen.getByRole('button', { name: 'Iniciar sesión' }));
    expect(onIrIngresar).toHaveBeenCalledTimes(1);
  });

  it('con el token vencido muestra el mensaje como aviso general y nunca el código', async () => {
    montar({
      onCrear: vi.fn().mockResolvedValue(fallo(422, 'ERR-005', 'Los datos enviados no son válidos.', {
        token: ['El enlace de invitación no es válido o ya venció.'],
      })),
    });

    llenar('clave-nueva-123');

    await waitFor(() => expect(screen.getByRole('alert').textContent).toBe('El enlace de invitación no es válido o ya venció.'));
    expect(screen.getByLabelText('Contraseña').getAttribute('aria-invalid')).toBe('false');
    // El formulario sigue disponible.
    expect(screen.getByRole('button', { name: 'Definir contraseña' }).disabled).toBe(false);
    sinCodigos();
  });

  it('el error de contraseña del servidor va junto al campo y nunca el código', async () => {
    montar({
      onCrear: vi.fn().mockResolvedValue(fallo(422, 'ERR-005', 'Los datos enviados no son válidos.', {
        contrasena: ['El campo contrasena debe tener al menos 8 caracteres.'],
      })),
    });

    llenar('clave-nueva-123');

    const campo = screen.getByLabelText('Contraseña');
    await waitFor(() => expect(campo.getAttribute('aria-invalid')).toBe('true'));
    expect(document.getElementById(campo.getAttribute('aria-describedby')).textContent).toBe('El campo contrasena debe tener al menos 8 caracteres.');
    expect(screen.queryByRole('alert')).toBeNull();
    sinCodigos();
  });

  it('con demasiados intentos (429) muestra solo el mensaje', async () => {
    montar({
      onCrear: vi.fn().mockResolvedValue(fallo(429, 'ERR-429', 'Demasiados intentos. Espere un minuto y vuelva a intentarlo.')),
    });

    llenar('clave-nueva-123');

    await waitFor(() => expect(screen.getByRole('alert').textContent).toBe('Demasiados intentos. Espere un minuto y vuelva a intentarlo.'));
    sinCodigos();
  });
});
