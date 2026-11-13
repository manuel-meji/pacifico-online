// @vitest-environment jsdom
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent, cleanup, waitFor } from '@testing-library/react';
import M1App from '../M1App';
import { crearContrasena, iniciarSesion, obtenerUsuario } from '../../Nucleo/services/sesionApi';

vi.mock('../../Nucleo/services/sesionApi', () => ({
  crearContrasena: vi.fn(),
  iniciarSesion: vi.fn(),
  cerrarSesion: vi.fn(),
  obtenerUsuario: vi.fn(),
}));

vi.mock('../services/m1Api', async (original) => ({
  ...(await original()),
  listarCategorias: vi.fn().mockResolvedValue({ ok: true, datos: [] }),
}));

const ENLACE = '/app.html?pantalla=crear-contrasena&token=token-de-invitacion&correo=encargado%40example.com';

describe('M1App, pantalla crear-contrasena', () => {
  beforeEach(() => {
    window.history.replaceState(null, '', ENLACE);
    obtenerUsuario.mockResolvedValue({ ok: false, estado: 401, error: { codigo: 'ERR-001', mensaje: 'Sin sesión.', detalles: null } });
    crearContrasena.mockResolvedValue({ ok: true, datos: { mensaje: 'Contraseña definida. Ya puede iniciar sesión.' } });
  });

  afterEach(() => {
    cleanup();
    vi.clearAllMocks();
  });

  it('lee token y correo del enlace y, al ir a ingresar, los saca de la dirección y del historial', async () => {
    render(<M1App pantalla="crear-contrasena" />);

    expect(screen.getByText('encargado@example.com')).toBeTruthy();
    fireEvent.change(screen.getByLabelText('Contraseña'), { target: { value: 'clave-nueva-123' } });
    fireEvent.change(screen.getByLabelText('Confirmar contraseña'), { target: { value: 'clave-nueva-123' } });
    fireEvent.click(screen.getByRole('button', { name: 'Definir contraseña' }));

    await waitFor(() => expect(screen.getByRole('status').textContent).toBe('Contraseña definida. Ya puede iniciar sesión.'));
    expect(crearContrasena).toHaveBeenCalledWith({
      token: 'token-de-invitacion',
      correo: 'encargado@example.com',
      contrasena: 'clave-nueva-123',
      contrasenaConfirmacion: 'clave-nueva-123',
    });
    // No se inicia sesión automáticamente.
    expect(iniciarSesion).not.toHaveBeenCalled();

    const entradasAntes = window.history.length;
    fireEvent.click(screen.getByRole('button', { name: 'Iniciar sesión' }));

    expect(screen.getByRole('heading', { name: 'Iniciar sesión' })).toBeTruthy();
    expect(window.location.search).toBe('?pantalla=ingresar');
    const consulta = new URLSearchParams(window.location.search);
    expect(consulta.has('token')).toBe(false);
    expect(consulta.has('correo')).toBe(false);
    expect(window.location.href).not.toContain('token-de-invitacion');
    // Se reemplaza la entrada del enlace: "Atrás" no vuelve a la dirección con el token.
    expect(window.history.length).toBe(entradasAntes);
    expect(iniciarSesion).not.toHaveBeenCalled();
  });

  it('con el enlace incompleto, "Ir al inicio" tampoco deja el correo en la dirección', async () => {
    window.history.replaceState(null, '', '/app.html?pantalla=crear-contrasena&correo=encargado%40example.com');
    render(<M1App pantalla="crear-contrasena" />);

    expect(screen.getByRole('alert').textContent).toMatch(/enlace de invitación está incompleto/);
    fireEvent.click(screen.getByRole('button', { name: 'Ir al inicio' }));

    await waitFor(() => expect(window.location.search).toBe('?pantalla=inicio'));
    expect(crearContrasena).not.toHaveBeenCalled();
  });
});
