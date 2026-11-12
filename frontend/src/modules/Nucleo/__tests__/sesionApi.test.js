// @vitest-environment jsdom
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { crearContrasena } from '../services/sesionApi';

const DATOS = {
  token: 'token-de-invitacion',
  correo: 'encargado@example.com',
  contrasena: 'clave-nueva-123',
  contrasenaConfirmacion: 'clave-nueva-123',
};

const respuesta = (estado, cuerpo) => ({ ok: estado >= 200 && estado < 300, status: estado, json: async () => cuerpo });

describe('crearContrasena', () => {
  beforeEach(() => {
    // Con la cookie presente no hace falta pedir /sanctum/csrf-cookie.
    document.cookie = 'XSRF-TOKEN=token-csrf';
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('envía POST /crear-contrasena con la confirmación como contrasena_confirmation y la cabecera CSRF', async () => {
    const fetchFalso = vi.fn().mockResolvedValue(respuesta(200, { mensaje: 'Contraseña definida. Ya puede iniciar sesión.' }));
    vi.stubGlobal('fetch', fetchFalso);

    const resultado = await crearContrasena(DATOS);

    expect(resultado).toEqual({ ok: true, datos: { mensaje: 'Contraseña definida. Ya puede iniciar sesión.' } });
    expect(fetchFalso).toHaveBeenCalledTimes(1);
    const [ruta, opciones] = fetchFalso.mock.calls[0];
    expect(ruta).toBe('/crear-contrasena');
    expect(opciones.method).toBe('POST');
    expect(opciones.headers['X-XSRF-TOKEN']).toBe('token-csrf');
    expect(JSON.parse(opciones.body)).toEqual({
      token: 'token-de-invitacion',
      correo: 'encargado@example.com',
      contrasena: 'clave-nueva-123',
      contrasena_confirmation: 'clave-nueva-123',
    });
  });

  it('devuelve el error del núcleo cuando el token no es válido (422)', async () => {
    const error = { codigo: 'ERR-005', mensaje: 'Los datos enviados no son válidos.', detalles: { token: ['El enlace de invitación no es válido o ya venció.'] } };
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(respuesta(422, { error })));

    expect(await crearContrasena(DATOS)).toEqual({ ok: false, estado: 422, error });
  });

  it('traduce el 429 al mensaje de demasiados intentos', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(respuesta(429, { message: 'Too Many Attempts.' })));

    const resultado = await crearContrasena(DATOS);

    expect(resultado.ok).toBe(false);
    expect(resultado.error.mensaje).toBe('Demasiados intentos. Espere un minuto y vuelva a intentarlo.');
  });
});
