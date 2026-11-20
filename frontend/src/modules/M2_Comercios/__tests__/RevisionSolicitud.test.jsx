// @vitest-environment jsdom
// Encargado que creó su cuenta con la solicitud: mientras el comercio no se aprueba solo ve el estado.
import { test, expect, vi, beforeEach } from 'vitest';
import { createRoot } from 'react-dom/client';
import { act } from 'react';
let estado = 'Pendiente';
vi.mock('../../Nucleo/services/sesionApi', () => ({ obtenerUsuario: async () => ({ ok: true, datos: { user: { rol: 'encargado_comercio', id_comercio: 5, nombre: 'X' } } }), cerrarSesion: async () => ({ ok: true }) }));
vi.mock('../services/m2Api', async (orig) => ({ ...(await orig()),
  miSolicitud: vi.fn(async () => ({ ok: true, datos: { id_comercio: 5, nombre_comercial: 'Soda Prueba', estado_comercio: estado,
    comercio: { cedula_juridica: '3-101-777777', nombre_comercial: 'Soda Prueba', razon_social: 'Soda S.A.', direccion_fiscal: 'Liberia', inscrito_como_contribuyente: true },
    solicitud: { id_solicitud: 12, estado: estado === 'Rechazado' ? 'Rechazada' : 'Pendiente', motivo_rechazo: 'Falta patente',
      nombre_encargado: 'Persona', correo_encargado: 'persona@example.test', telefono_encargado: null },
    documentos: [{ id_documento_comercio: 3, tipo: 'patente', fecha_vencimiento: '2027-01-01', resultado: 'invalido', observacion: 'Ilegible', debe_reemplazarse: true }] } })),
  corregirMiSolicitud: vi.fn(async () => ({ ok: false, estado: 422, error: { codigo: 'ERR-005', mensaje: 'Datos inválidos',
    detalles: { cedula_juridica: ['La cédula jurídica no coincide con la registrada en tu solicitud; no se puede cambiar al corregirla.'] } } })) }));
vi.mock('../views/PanelComercio', () => ({ default: () => <p>PANEL-MI-COMERCIO</p> }));
import M2App from '../M2App';

async function montar() {
  globalThis.IS_REACT_ACT_ENVIRONMENT = true;
  const div = document.createElement('div');
  await act(async () => createRoot(div).render(<M2App />));
  await act(async () => {});
  return div;
}
test('pendiente: solo estado, sin pestañas ni enlaces', async () => {
  estado = 'Pendiente';
  const d = await montar();
  expect(d.textContent).toContain('Solicitud en revisión');
  expect(d.textContent).toContain('#12');
  expect(d.textContent).not.toContain('PANEL-MI-COMERCIO');
  expect(d.querySelector('a[href="/m3.html"]')).toBeNull();
  expect(d.querySelector('a[href="/m4.html"]')).toBeNull();
});
test('rechazado: motivo y corrección ya llena; número y cédula no se editan, el correo sí', async () => {
  estado = 'Rechazado';
  const d = await montar();
  expect(d.textContent).toContain('Falta patente');
  expect(d.textContent).toContain('Corregir tu solicitud');
  const soloLectura = [...d.querySelectorAll('input[readonly]')].map((i) => i.value);
  expect(soloLectura).toEqual(['#12', '3-101-777777']);
  const correo = d.querySelector('input[type="email"]');
  expect(correo.value).toBe('persona@example.test');
  expect(correo.readOnly).toBe(false);
  expect(d.textContent).toContain('Rechazado');
  expect(d.textContent).toContain('Subí uno nuevo para reemplazarlo.');
});
test('rechazado: sin documento nuevo no envía y dice por qué', async () => {
  estado = 'Rechazado';
  const { corregirMiSolicitud } = await import('../services/m2Api');
  const d = await montar();
  await act(async () => d.querySelector('form').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true })));
  expect(corregirMiSolicitud).not.toHaveBeenCalled();
  expect(d.textContent).toContain('Tenés documentos rechazados o vencidos');
});
test('activo: panel normal', async () => {
  estado = 'Activo';
  const d = await montar();
  expect(d.textContent).toContain('PANEL-MI-COMERCIO');
  expect(d.querySelector('a[href="/m3.html"]')).not.toBeNull();
});
