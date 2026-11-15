// @vitest-environment jsdom
import { describe, it, expect, vi, afterEach } from 'vitest';
import { render, screen, cleanup, waitFor, fireEvent } from '@testing-library/react';
import M3Principal from '../M3Principal';

// Respuesta mínima de fetch: solo lo que leen m3Api.js y sesionApi.js.
const respuesta = (cuerpo, status = 200) =>
  Promise.resolve({ ok: status >= 200 && status < 300, status, json: () => Promise.resolve(cuerpo) });

const usuario = (rol, idComercio = null) => ({
  id_usuario: 1, nombre: 'Ana', correo: 'ana@example.com', rol, id_comercio: idComercio,
});

// Simula el backend sin red: /api/user del núcleo y las lecturas públicas de M3.
function simularBackend(sesion) {
  const fetchSimulado = vi.fn((url) => {
    const ruta = String(url);
    if (ruta.startsWith('/api/user')) {
      return sesion
        ? respuesta({ user: sesion })
        : respuesta({ error: { codigo: 'ERR-001', mensaje: 'Sin sesión', detalles: null } }, 401);
    }
    if (ruta.startsWith('/api/m3/v1/categorias') || ruta.startsWith('/api/m3/v1/marcas')) return respuesta([]);
    if (ruta.startsWith('/api/m3/v1/productos')) {
      return respuesta({ pagina: 1, por_pagina: 10, total_resultados: 0, productos: [] });
    }
    return respuesta({ error: { codigo: 'ERR-004', mensaje: 'No encontrado', detalles: null } }, 404);
  });
  vi.stubGlobal('fetch', fetchSimulado);
  return fetchSimulado;
}

const consultasDeProductos = (fetchSimulado) =>
  fetchSimulado.mock.calls
    .map(([url]) => new URL(String(url), 'http://localhost'))
    .filter((url) => url.pathname === '/api/m3/v1/productos');

afterEach(() => {
  cleanup();
  vi.unstubAllGlobals();
  window.location.hash = '';
});

describe('M3: catálogo del encargado del comercio', () => {
  it('con un encargado del comercio 4, cada búsqueda lleva id_comercio=4 y el título es "Mis productos"', async () => {
    const fetchSimulado = simularBackend(usuario('encargado_comercio', 4));
    render(<M3Principal />);

    expect(await screen.findByRole('heading', { name: 'Mis productos' })).toBeTruthy();
    await waitFor(() => expect(consultasDeProductos(fetchSimulado).length).toBeGreaterThan(0));
    expect(await screen.findByText('Aún no tienes productos publicados.')).toBeTruthy();
    expect(screen.getByText('Los productos en borrador aparecen aquí solo cuando estén publicados.')).toBeTruthy();

    // Al cambiar un filtro de la pantalla, el comercio se mantiene.
    fireEvent.change(screen.getByLabelText('Ordenar por'), { target: { value: 'novedad' } });
    await waitFor(() => expect(consultasDeProductos(fetchSimulado).some((u) => u.searchParams.get('orden') === 'novedad')).toBe(true));

    consultasDeProductos(fetchSimulado).forEach((url) => {
      expect(url.searchParams.get('id_comercio')).toBe('4');
    });
  });

  it.each([
    ['un cliente', usuario('cliente')],
    ['una persona sin sesión', null],
  ])('con %s, la búsqueda no lleva id_comercio y el título no cambia', async (_caso, sesion) => {
    const fetchSimulado = simularBackend(sesion);
    render(<M3Principal />);

    expect(await screen.findByRole('heading', { name: 'Catálogo de productos' })).toBeTruthy();
    await waitFor(() => expect(consultasDeProductos(fetchSimulado).length).toBeGreaterThan(0));

    consultasDeProductos(fetchSimulado).forEach((url) => {
      expect(url.searchParams.has('id_comercio')).toBe(false);
    });
    expect(screen.queryByText('Aún no tienes productos publicados.')).toBeNull();
  });

  it('el botón "Mi comercio" aparece para el encargado y lleva a /m2.html; el enlace "Inicio" se conserva', async () => {
    simularBackend(usuario('encargado_comercio', 4));
    render(<M3Principal />);

    const boton = await screen.findByRole('link', { name: 'Mi comercio' });
    expect(boton.getAttribute('href')).toBe('/m2.html');
    expect(screen.getByRole('link', { name: 'Inicio' }).getAttribute('href')).toBe('/m2.html');
  });

  it.each([
    ['un cliente', usuario('cliente')],
    ['una persona sin sesión', null],
  ])('el botón "Mi comercio" no aparece para %s', async (_caso, sesion) => {
    simularBackend(sesion);
    render(<M3Principal />);

    await screen.findByRole('heading', { name: 'Catálogo de productos' });
    expect(screen.queryByRole('link', { name: 'Mi comercio' })).toBeNull();
    expect(screen.getByRole('link', { name: 'Inicio' }).getAttribute('href')).toBe('/m1.html');
  });
});
