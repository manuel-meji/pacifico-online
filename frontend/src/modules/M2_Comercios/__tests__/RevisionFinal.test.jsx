// @vitest-environment jsdom
// Revisión final de M2 (24/11): hallazgos 40 a 47. Cada prueba nombra su hallazgo.
import { test, expect, vi, beforeEach } from 'vitest';
import { createRoot } from 'react-dom/client';
import { act } from 'react';

vi.mock('../services/m2Api', () => ({
  registrarSolicitud: vi.fn(),
  corregirMiSolicitud: vi.fn(),
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
  solicitarPublicidad: vi.fn(),
}));
// La imagen elegida «mide» 1536 × 864: no sirve como banner (hallazgo 28), así se produce el error.
vi.mock('../components/comunes', async (original) => ({
  ...(await original()),
  medirImagen: vi.fn(async () => ({ ancho: 1536, alto: 864 })),
}));

import * as api from '../services/m2Api';
import { SesionContexto } from '../components/comunes';
import SolicitudIncorporacion, { MENSAJE_CEDULA, revisarPesoDocumentos, validarSolicitudEnCliente } from '../views/SolicitudIncorporacion';
import PanelComercio from '../views/PanelComercio';

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
  Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set.call(input, valor);
  await act(async () => input.dispatchEvent(new Event('input', { bubbles: true })));
};
const elegirArchivo = async (input, nombre, megas = 0) => {
  const archivo = new File(['x'], nombre, { type: nombre.endsWith('.pdf') ? 'application/pdf' : 'image/png' });
  if (megas) Object.defineProperty(archivo, 'size', { value: megas * 1024 * 1024 });
  Object.defineProperty(input, 'files', { configurable: true, value: [archivo] });
  await act(async () => input.dispatchEvent(new Event('change', { bubbles: true })));
  await act(async () => {});
};
const clic = async (el) => act(async () => el.dispatchEvent(new MouseEvent('click', { bubbles: true })));
const enviar = async (d) => act(async () => d.querySelector('form').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true })));
const boton = (div, texto) => [...div.querySelectorAll('button')].find((b) => b.textContent.includes(texto));
const campo = (div, etiqueta) => [...div.querySelectorAll('label')].find((l) => l.textContent.startsWith(etiqueta))?.querySelector('input,select,textarea');

const MICROSITIO = {
  nombre_comercial: 'Estudio', descripcion: 'Hola, este es mi negocio en Cañas.', logotipo: null, horarios: [],
  contactos: [{ tipo: 'telefono', valor: '8888-1234' }],
  politicas: [{ tipo: 'envio', contenido: 'Enviamos a todo el país.', configuracion_envio: [{ zona: 'GAM', costo: 2500, moneda: 'CRC' }] }],
  banners: [{ id_banner: 1, ruta: '/b1.png', texto_alternativo: 'Fachada del local', activo: true }],
  galeria: [],
};
const montarPanel = async () => {
  api.consultarMicrositio.mockResolvedValue({ ok: true, datos: MICROSITIO });
  api.consultarPublicidad.mockResolvedValue({ ok: true, datos: { publicidades: [], destacado: false } });
  return montar(<PanelComercio />, { rol: 'encargado_comercio', id_comercio: 44 });
};

// --- Hallazgo 40: la contraseña no esconde los demás errores ---

test('hallazgo 40: con la contraseña corta se marcan también los demás campos y no se envía', async () => {
  const d = await montar(<SolicitudIncorporacion />);
  await escribir(campo(d, 'Contraseña para ingresar'), 'corta');
  await enviar(d);

  expect(api.registrarSolicitud).not.toHaveBeenCalled();
  expect(d.textContent).toContain('La contraseña debe tener al menos 8 caracteres.');
  expect(d.textContent).toContain('Completá el nombre comercial.');
  expect(d.textContent).toContain('Completá la cédula jurídica.');
  expect(d.textContent).toContain('Completá el correo de la persona encargada.');
});

test('hallazgo 40: con la contraseña bien, la validación la hace el servidor', async () => {
  api.registrarSolicitud.mockResolvedValue({ ok: false, estado: 422, error: { detalles: { nombre_comercial: ['Completá el nombre comercial.'] } } });
  const d = await montar(<SolicitudIncorporacion />);
  await escribir(campo(d, 'Contraseña para ingresar'), 'MiClave2026');
  await escribir(campo(d, 'Confirmá la contraseña'), 'MiClave2026');
  await enviar(d);

  expect(api.registrarSolicitud).toHaveBeenCalledTimes(1);
});

// --- Hallazgo 46: cédula jurídica que empieza con 2, 3, 4 o 5 ---

test('hallazgo 46: la revisión en el navegador acepta 2 a 5 y usa el mismo mensaje del servidor', () => {
  const base = { cedula_juridica: '' };
  for (const cedula of ['2-100-042005', '3-101-123456', '4-000-123456', '5-200-654321']) {
    expect(validarSolicitudEnCliente({ ...base, cedula_juridica: cedula }).cedula_juridica).toBeUndefined();
  }
  for (const cedula of ['1-101-123456', '9-101-123456']) {
    expect(validarSolicitudEnCliente({ ...base, cedula_juridica: cedula }).cedula_juridica).toEqual([MENSAJE_CEDULA]);
  }
});

test('hallazgo 46: el texto de ayuda dice con qué puede empezar la cédula', async () => {
  const d = await montar(<SolicitudIncorporacion />);
  expect(d.textContent).toContain('empieza con 2, 3, 4 o 5');
});

// --- Hallazgos 42 y 43: opciones usadas y basureros ---

test('hallazgo 42: la opción ya usada dice «(ya agregado)»', async () => {
  const d = await montarPanel();
  await clic(boton(d, 'Contacto'));
  await clic(boton(d, 'Agregar contacto'));
  const segundo = [...d.querySelectorAll('select')][1];
  const telefono = segundo.querySelector('option[value="telefono"]');
  expect(telefono.disabled).toBe(true);
  expect(telefono.textContent).toBe('Teléfono (ya agregado)');

  await clic(boton(d, 'Políticas'));
  const zona = [...d.querySelectorAll('select')].find((s) => s.value === 'GAM');
  expect(zona.querySelector('option[value="GAM"]').textContent).toBe('GAM');
});

test('hallazgo 43: el basurero es solo un ícono con borde rojo y no se estira', async () => {
  const d = await montarPanel();
  await clic(boton(d, 'Contacto'));
  const quitar = d.querySelector('button[aria-label="Quitar contacto"]');
  expect(quitar.textContent.trim()).toBe('');
  expect(quitar.className).toContain('ring-red-300');
  expect(quitar.className).toContain('w-fit');
  expect(d.querySelector('button[aria-label="Bajar una posición"]')).toBeNull();
});

// --- Hallazgo 45: la página del encargado no se desborda en celular ---

test('hallazgo 45: la cuadrícula del panel tiene una columna limitada y la navegación min-w-0', async () => {
  const d = await montarPanel();
  const nav = d.querySelector('nav[aria-label="Secciones del micrositio"]');
  expect(nav.className).toContain('min-w-0');
  expect(nav.parentElement.className).toContain('grid-cols-1');
});

// --- Hallazgo 47: los errores no se quedan al quitar filas ---

test('hallazgo 47: al quitar un banner con error y agregar otro, el nuevo sale limpio', async () => {
  const d = await montarPanel();
  await clic(boton(d, 'Banners'));
  await clic(boton(d, 'Agregar imagen'));
  const archivos = () => [...d.querySelectorAll('input[type="file"]')];
  await elegirArchivo(archivos()[1], 'pequena.png');
  expect(d.textContent).toContain('Tu imagen mide 1536 × 864 px.');

  await clic(d.querySelectorAll('button[aria-label="Quitar imagen"]')[1]);
  expect(d.textContent).not.toContain('Tu imagen mide 1536 × 864 px.');
  await clic(boton(d, 'Agregar imagen'));
  expect(d.textContent).not.toContain('Tu imagen mide 1536 × 864 px.');
});

test('hallazgo 47: el error de imagen viaja con su fila al moverla', async () => {
  const d = await montarPanel();
  await clic(boton(d, 'Banners'));
  await clic(boton(d, 'Agregar imagen'));
  await elegirArchivo([...d.querySelectorAll('input[type="file"]')][1], 'pequena.png');

  await clic(d.querySelectorAll('button[aria-label="Subir una posición"]')[1]);
  const tarjetas = [...d.querySelectorAll('input[type="file"]')].map((i) => i.closest('div.rounded-xl'));
  expect(tarjetas[0].textContent).toContain('Tu imagen mide 1536 × 864 px.');
  expect(tarjetas[1].textContent).not.toContain('Tu imagen mide');
});

test('hallazgo 47: al quitar un contacto se borran los errores del servidor que eran por posición', async () => {
  api.guardarContactos.mockResolvedValue({ ok: false, estado: 422, error: { detalles: { 'contactos.1.valor': ['Escribí un correo válido, por ejemplo nombre@dominio.com.'] } } });
  const d = await montarPanel();
  await clic(boton(d, 'Contacto'));
  await clic(boton(d, 'Agregar contacto'));
  await clic(boton(d, 'Guardar'));
  expect(d.textContent).toContain('Escribí un correo válido');

  await clic(d.querySelectorAll('button[aria-label="Quitar contacto"]')[1]);
  await clic(boton(d, 'Agregar contacto'));
  expect(d.textContent).not.toContain('Escribí un correo válido');
});

test('hallazgo 47: al quitar un documento, su error no pasa al que queda en su lugar', async () => {
  api.registrarSolicitud.mockResolvedValue({ ok: false, estado: 422, error: { detalles: { 'documentos.0.archivo': ['Adjuntá el archivo del documento.'] } } });
  const d = await montar(<SolicitudIncorporacion />);
  await escribir(campo(d, 'Contraseña para ingresar'), 'MiClave2026');
  await escribir(campo(d, 'Confirmá la contraseña'), 'MiClave2026');
  await clic(boton(d, 'Agregar documento'));
  await clic(boton(d, 'Agregar documento'));
  await enviar(d);
  expect(d.textContent).toContain('Adjuntá el archivo del documento.');

  await clic(d.querySelectorAll('button[aria-label="Quitar documento"]')[0]);
  expect(d.querySelectorAll('button[aria-label="Quitar documento"]')).toHaveLength(1);
  expect(d.textContent).not.toContain('Adjuntá el archivo del documento.');
});

// --- Hallazgo 44: mensajes generales en voseo ---

test('hallazgo 44: los mensajes sin conexión y de demasiados intentos van en voseo', async () => {
  const real = await vi.importActual('../services/m2Api');
  const fetchOriginal = globalThis.fetch;
  globalThis.fetch = vi.fn().mockRejectedValueOnce(new TypeError('Failed to fetch'))
    .mockResolvedValueOnce({ ok: false, status: 429, json: async () => ({}) });

  const sinConexion = await real.consultarMicrositio(1);
  const limite = await real.consultarMicrositio(1);
  globalThis.fetch = fetchOriginal;

  expect(sinConexion.error.mensaje).toBe('No se pudo conectar con la plataforma. Intentá de nuevo en unos minutos.');
  expect(limite.error.mensaje).toBe('Demasiados intentos. Esperá un minuto y volvé a intentarlo.');
});

// --- Hallazgos 48 a 50 (verificación de Mia y pruebas de Abril, 24/11) ---

const llenarContrasena = async (d) => {
  await escribir(campo(d, 'Contraseña para ingresar'), 'MiClave2026');
  await escribir(campo(d, 'Confirmá la contraseña'), 'MiClave2026');
};

test('hallazgo 48: un documento de más de 2 MB no se envía y dice cuánto pesa', async () => {
  const d = await montar(<SolicitudIncorporacion />);
  await llenarContrasena(d);
  await clic(boton(d, 'Agregar documento'));
  await elegirArchivo(d.querySelector('input[type="file"]'), 'patente.pdf', 3);
  await enviar(d);

  expect(api.registrarSolicitud).not.toHaveBeenCalled();
  expect(d.textContent).toContain('Este archivo pesa 3,0 MB. Cada documento puede pesar como máximo 2 MB.');
  expect(d.textContent).toContain('Completá el nombre comercial.');
  expect(d.textContent).toContain('PDF, JPG o PNG de hasta 2 MB cada uno');
});

test('hallazgo 48: varios documentos que juntos pasan de 7 MB se avisan antes de enviar', () => {
  const doc = (megas) => {
    const archivo = new File(['x'], 'a.pdf');
    Object.defineProperty(archivo, 'size', { value: megas * 1024 * 1024 });
    return { archivo };
  };
  const errores = revisarPesoDocumentos([doc(1.9), doc(1.9), doc(1.9), doc(1.9)]);
  expect(errores['documentos.0.archivo']).toBeUndefined();
  expect(errores.documentos[0]).toContain('se pueden enviar hasta 7 MB a la vez');
  expect(revisarPesoDocumentos([doc(1.9), doc(1.9)])).toEqual({});
});

test('hallazgo 48: una imagen de más de 2 MB se avisa al elegirla', async () => {
  const d = await montarPanel();
  await clic(boton(d, 'Banners'));
  await clic(boton(d, 'Agregar imagen'));
  await elegirArchivo([...d.querySelectorAll('input[type="file"]')][1], 'grande.png', 2.5);
  expect(d.textContent).toContain('Tu imagen pesa 2,5 MB. Cada imagen puede pesar como máximo 2 MB.');
});

test('hallazgo 48: un 413 de PHP no se confunde con falta de conexión', async () => {
  const real = await vi.importActual('../services/m2Api');
  const fetchOriginal = globalThis.fetch;
  globalThis.fetch = vi.fn().mockResolvedValueOnce({ ok: false, status: 413, json: async () => { throw new Error('html'); } });
  const r = await real.consultarMicrositio(1);
  globalThis.fetch = fetchOriginal;

  expect(r.estado).toBe(413);
  expect(r.error.mensaje).toContain('Los archivos pesan demasiado para enviarlos juntos.');
});

test('hallazgo 49: «Las contraseñas no coinciden» sale bajo la confirmación', async () => {
  const d = await montar(<SolicitudIncorporacion />);
  await escribir(campo(d, 'Contraseña para ingresar'), 'MiClave2026');
  await escribir(campo(d, 'Confirmá la contraseña'), 'OtraClave2026');
  await enviar(d);

  const etiqueta = (texto) => [...d.querySelectorAll('label')].find((l) => l.textContent.startsWith(texto));
  expect(etiqueta('Confirmá la contraseña').textContent).toContain('Las contraseñas no coinciden.');
  expect(etiqueta('Contraseña para ingresar').textContent).not.toContain('Las contraseñas no coinciden.');
});

test('hallazgo 50: la papelera de documentos tiene separación arriba en celular', async () => {
  const d = await montar(<SolicitudIncorporacion />);
  await clic(boton(d, 'Agregar documento'));
  const quitar = d.querySelector('button[aria-label="Quitar documento"]');
  expect(quitar.className).toMatch(/(^| )mt-3( |$)/);
});
