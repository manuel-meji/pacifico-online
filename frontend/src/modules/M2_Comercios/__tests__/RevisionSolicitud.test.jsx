// @vitest-environment jsdom
// Encargado que creó su cuenta con la solicitud: mientras el comercio no se aprueba solo ve el estado.
import { test, expect, vi, beforeEach } from 'vitest';
import { createRoot } from 'react-dom/client';
import { act } from 'react';
let estado = 'Pendiente';
vi.mock('../../Nucleo/services/sesionApi', () => ({ obtenerUsuario: async () => ({ ok: true, datos: { user: { rol: 'encargado_comercio', id_comercio: 5, nombre: 'X' } } }), cerrarSesion: async () => ({ ok: true }) }));
vi.mock('../services/m2Api', async (orig) => ({ ...(await orig()),
  miSolicitud: vi.fn(async () => ({ ok: true, datos: { id_comercio: 5, nombre_comercial: 'Soda Prueba', estado_comercio: estado,
    solicitud: { id_solicitud: 12, estado: estado === 'Rechazado' ? 'Rechazada' : 'Pendiente', motivo_rechazo: 'Falta patente' } } })) }));
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
test('rechazado: motivo y formulario de corrección con el número', async () => {
  estado = 'Rechazado';
  const d = await montar();
  expect(d.textContent).toContain('Falta patente');
  expect(d.textContent).toContain('Corregir una solicitud rechazada');
  expect(d.querySelector('input[type="number"]').value).toBe('12');
});
test('activo: panel normal', async () => {
  estado = 'Activo';
  const d = await montar();
  expect(d.textContent).toContain('PANEL-MI-COMERCIO');
  expect(d.querySelector('a[href="/m3.html"]')).not.toBeNull();
});
