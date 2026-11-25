// @vitest-environment jsdom
// Revisión exhaustiva de M2 (21 y 22/11): pantallas corregidas. Cada prueba nombra su hallazgo.
import { test, expect, vi, beforeEach } from 'vitest';
import { createRoot } from 'react-dom/client';
import { act } from 'react';

vi.mock('../services/m2Api', () => ({
  registrarSolicitud: vi.fn(),
  corregirMiSolicitud: vi.fn(),
  miSolicitud: vi.fn(),
  listarSolicitudes: vi.fn(),
  consultarSolicitud: vi.fn(),
  aprobarSolicitud: vi.fn(),
  rechazarSolicitud: vi.fn(),
  evaluarDocumento: vi.fn(),
  listarPublicidadPendiente: vi.fn(),
  listarPublicidadVigente: vi.fn(),
  listarComercios: vi.fn(),
  gestionarPublicidad: vi.fn(),
  solicitarPublicidad: vi.fn(),
  consultarMicrositio: vi.fn(),
  consultarPublicidad: vi.fn(),
  actualizarInformacion: vi.fn(),
  guardarLogotipo: vi.fn(),
  quitarLogotipo: vi.fn(),
  guardarHorarios: vi.fn(),
  guardarContactos: vi.fn(),
  guardarPoliticas: vi.fn(),
  gestionarBanners: vi.fn(),
  gestionarGaleria: vi.fn(),
}));
vi.mock('../../Nucleo/services/sesionApi', () => ({
  obtenerUsuario: vi.fn(async () => ({ ok: true, datos: { user: { rol: 'encargado_comercio', id_comercio: 5, nombre: 'X' } } })),
  cerrarSesion: async () => ({ ok: true }),
}));

import * as api from '../services/m2Api';
import { SesionContexto } from '../components/comunes';
import SolicitudIncorporacion, { formatearCedula } from '../views/SolicitudIncorporacion';
import SolicitudesPlataforma from '../components/SolicitudesPlataforma';
import DestacadosPlataforma from '../components/DestacadosPlataforma';
import PanelComercio from '../views/PanelComercio';
import M2App from '../M2App';

beforeEach(() => { vi.clearAllMocks(); });

async function montar(elemento, usuario = null) {
  globalThis.IS_REACT_ACT_ENVIRONMENT = true;
  const div = document.createElement('div');
  document.body.appendChild(div);
  await act(async () => createRoot(div).render(
    <SesionContexto.Provider value={{ usuario, mensajeDeError: (r) => r?.error?.mensaje || '' }}>{elemento}</SesionContexto.Provider>,
  ));
  await act(async () => {});
  return div;
}

const escribir = async (input, valor) => {
  const tipo = input.tagName === 'TEXTAREA' ? window.HTMLTextAreaElement : window.HTMLInputElement;
  Object.getOwnPropertyDescriptor(tipo.prototype, 'value').set.call(input, valor);
  await act(async () => input.dispatchEvent(new Event('input', { bubbles: true })));
};
const clic = async (el) => act(async () => el.dispatchEvent(new MouseEvent('click', { bubbles: true })));
const boton = (div, texto) => [...div.querySelectorAll('button')].find((b) => b.textContent.includes(texto));
const campo = (div, etiqueta) => [...div.querySelectorAll('label')].find((l) => l.textContent.startsWith(etiqueta))?.querySelector('input,select,textarea');

test('hallazgo 7: la cédula se escribe con sus guiones', () => {
  expect(formatearCedula('3101123456')).toBe('3-101-123456');
  expect(formatearCedula('3-101')).toBe('3-101');
  expect(formatearCedula('abc31011234567')).toBe('3-101-123456');
});

test('hallazgos 2 y 4: avisa sin la casilla de contribuyente y explica los documentos', async () => {
  const d = await montar(<SolicitudIncorporacion />);
  expect(d.textContent).toContain('Para aprobar el comercio tiene que estar inscrito');
  expect(d.textContent).toContain('Respaldo legal que la plataforma revisa antes de aprobar tu comercio');
  await clic(d.querySelector('input[type="checkbox"]'));
  expect(d.textContent).not.toContain('Para aprobar el comercio tiene que estar inscrito');
});

test('hallazgo 8: el error de un campo se borra al corregirlo', async () => {
  api.registrarSolicitud.mockResolvedValue({ ok: false, estado: 422, error: { codigo: 'ERR-005', mensaje: 'x', detalles: { nombre_comercial: ['Completá el nombre comercial.'] } } });
  const d = await montar(<SolicitudIncorporacion />);
  await escribir(campo(d, 'Contraseña para ingresar'), 'MiClave2026');
  await escribir(campo(d, 'Confirmá la contraseña'), 'MiClave2026');
  await act(async () => d.querySelector('form').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true })));
  expect(d.textContent).toContain('Completá el nombre comercial.');
  await escribir(campo(d, 'Nombre comercial'), 'Soda');
  expect(d.textContent).not.toContain('Completá el nombre comercial.');
});

test('hallazgo 25: un documento de cada tipo', async () => {
  const d = await montar(<SolicitudIncorporacion />);
  await clic(boton(d, 'Agregar documento'));
  await clic(boton(d, 'Agregar documento'));
  const [primero, segundo] = [...d.querySelectorAll('select')];
  expect(primero.value).toBe('cedula_juridica');
  expect(segundo.value).toBe('patente');
  expect(segundo.querySelector('option[value="cedula_juridica"]').disabled).toBe(true);
});

test('hallazgos 11 y 51: la plataforma registra el comercio y la contraseña inicial es opcional', async () => {
  const d = await montar(<SolicitudIncorporacion />, { rol: 'encargado_plataforma', nombre: 'Plataforma' });
  expect(d.textContent).toContain('Registrar un comercio');
  expect(d.textContent).toContain('Contraseña inicial del encargado (opcional)');
  expect([...d.querySelectorAll('input[type="password"]')].every((i) => !i.required)).toBe(true);
});

test('hallazgo 10: si falla la consulta del estado no abre el panel completo', async () => {
  api.miSolicitud.mockResolvedValue({ ok: false, estado: 0, error: { mensaje: 'Sin conexión.' } });
  const d = await montar(<M2App />);
  expect(d.textContent).toContain('No se pudo consultar el estado de tu comercio.');
  expect(d.querySelector('a[href="/m3.html"]')).toBeNull();
});

const SOLICITUD = {
  id_solicitud: 13, estado: 'Pendiente', created_at: '2026-11-21T10:00:00Z', nombre_encargado: 'Luis Delgado', correo_encargado: 'luis@x.cr',
  telefono_encargado: null, requiere_documentos: true, cuenta_creada: true, ultimo_motivo_rechazo: 'No está inscrito en Hacienda.',
  fecha_reenvio: '2026-11-21T11:00:00Z', nombre_registrador: null,
  comercio: { id_comercio: 44, nombre_comercial: 'Estudio', cedula_juridica: '3-101-123456', razon_social: 'Taller S.A.', direccion_fiscal: 'Cañas, Guanacaste', inscrito_como_contribuyente: false },
  documentos: [{ id_documento_comercio: 1, tipo: 'cedula_juridica', fecha_vencimiento: '2027-01-01', resultado: 'pendiente', observacion: null }],
};

test('hallazgos 12, 13 y 15: nombre del documento, requisitos, Aprobar deshabilitado e historial', async () => {
  api.listarSolicitudes.mockResolvedValue({ ok: true, datos: { total: 1, solicitudes: [SOLICITUD] } });
  api.consultarSolicitud.mockResolvedValue({ ok: true, datos: SOLICITUD });
  const d = await montar(<SolicitudesPlataforma />, { rol: 'encargado_plataforma' });
  await clic(boton(d, 'Ver'));
  expect(d.textContent).toContain('Cédula jurídica');
  expect(d.textContent).not.toContain('cedula_juridica');
  expect(boton(d, 'Aprobar').disabled).toBe(true);
  expect(d.textContent).toContain('Para aprobar falta: que esté inscrito como contribuyente; revisar todos los documentos.');
  expect(boton(d, 'Rechazar').className).not.toContain('bg-red-600');
  expect(d.textContent).toContain('Se rechazó antes.');
  expect(d.textContent).toContain('No está inscrito en Hacienda.');
});

test('hallazgo 24: si otra sesión ya la revisó, se actualiza y lo dice', async () => {
  const lista = { ...SOLICITUD, comercio: { ...SOLICITUD.comercio, inscrito_como_contribuyente: true }, documentos: [] , requiere_documentos: false };
  api.listarSolicitudes.mockResolvedValue({ ok: true, datos: { total: 1, solicitudes: [lista] } });
  api.consultarSolicitud.mockResolvedValueOnce({ ok: true, datos: lista })
    .mockResolvedValue({ ok: true, datos: { ...lista, estado: 'Aprobada', nombre_revisor: 'Otra persona', fecha_revision: '2026-11-22T10:00:00Z' } });
  api.aprobarSolicitud.mockResolvedValue({ ok: false, estado: 422, error: { detalles: { estado: ['Esta solicitud ya fue aprobada en otra sesión. Se actualizó la pantalla con su estado.'] } } });
  const d = await montar(<SolicitudesPlataforma />, { rol: 'encargado_plataforma' });
  await clic(boton(d, 'Ver'));
  await clic(boton(d, 'Aprobar'));
  await clic(boton(d, 'Sí, aprobar'));
  expect(d.textContent).toContain('Esta solicitud ya fue aprobada en otra sesión.');
  expect(d.textContent).toContain('Aprobada el');
  expect(d.textContent).toContain('por Otra persona');
});

test('hallazgo 37: destacados sin window.confirm y el rechazo pide motivo', async () => {
  const confirmar = vi.spyOn(window, 'confirm');
  api.listarPublicidadPendiente.mockResolvedValue({ ok: true, datos: { publicidades: [{ id_publicidad_comercio: 9, nombre_comercial: 'Estudio', dias_solicitados: 7, solicitado_el: '2026-11-22' }] } });
  api.listarPublicidadVigente.mockResolvedValue({ ok: true, datos: { publicidades: [] } });
  api.listarComercios.mockResolvedValue({ ok: true, datos: { comercios: [] } });
  const d = await montar(<DestacadosPlataforma />, { rol: 'encargado_plataforma' });
  await clic(boton(d, 'Rechazar'));
  expect(confirmar).not.toHaveBeenCalled();
  await clic(boton(d, 'Sí, rechazar'));
  expect(d.textContent).toContain('El motivo es obligatorio.');
  expect(api.gestionarPublicidad).not.toHaveBeenCalled();
  confirmar.mockRestore();
});

const MICROSITIO = {
  nombre_comercial: 'Estudio', descripcion: 'Hola, este es mi negocio en Cañas.', logotipo: null, horarios: [],
  contactos: [{ tipo: 'telefono', valor: '8888-1234' }], politicas: [],
  banners: [
    { id_banner: 1, ruta: '/b1.png', texto_alternativo: 'Fachada del local', activo: true },
    { id_banner: 2, ruta: '/b2.png', texto_alternativo: 'Interior del local', activo: true },
  ],
  galeria: [],
};

test('hallazgos 27, 33 y 36: ordenar banners, un contacto por tipo y «Sin guardar»', async () => {
  api.consultarMicrositio.mockResolvedValue({ ok: true, datos: MICROSITIO });
  api.consultarPublicidad.mockResolvedValue({ ok: true, datos: { publicidades: [], destacado: false } });
  const d = await montar(<PanelComercio />, { rol: 'encargado_comercio', id_comercio: 44 });

  await clic(boton(d, 'Contacto'));
  await clic(boton(d, 'Agregar contacto'));
  const tipos = [...d.querySelectorAll('select')];
  expect(tipos[1].value).toBe('correo');
  expect(tipos[1].querySelector('option[value="telefono"]').disabled).toBe(true);
  expect(d.textContent).toContain('Sin guardar');

  await clic(boton(d, 'Banners'));
  expect(d.textContent).toContain('Solo el primer banner visible se muestra');
  const textos = () => [...d.querySelectorAll('input')].filter((i) => i.type === 'text' || !i.type).map((i) => i.value);
  expect(textos()).toEqual(['Fachada del local', 'Interior del local']);
  await clic(d.querySelectorAll('button[aria-label="Bajar una posición"]')[0]);
  expect(textos()).toEqual(['Interior del local', 'Fachada del local']);
});
