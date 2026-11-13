// @vitest-environment jsdom
import { describe, it, expect, afterEach } from 'vitest';
import { render, screen, cleanup, within } from '@testing-library/react';
import PaginaPortal, { PortalContext } from '../components/PaginaPortal';

const NOMBRE = 'Vendé en Pacíficos Online';

function montar(contexto = {}) {
  const portal = { ir: () => {}, categorias: [], usuario: null, cargandoUsuario: false, cantidadCarrito: null, ...contexto };
  return render(
    <PortalContext.Provider value={portal}>
      <PaginaPortal><p>Contenido</p></PaginaPortal>
    </PortalContext.Provider>,
  );
}

const usuario = (rol) => ({ id_usuario: 1, nombre: 'Ana', correo: 'ana@example.com', rol, id_comercio: rol === 'encargado_comercio' ? 7 : null });

describe('Enlace "Vendé en Pacíficos Online"', () => {
  afterEach(cleanup);

  it.each([
    ['sin sesión', null],
    ['con sesión de cliente', usuario('cliente')],
  ])('%s aparece en la franja superior y en el pie, hacia la solicitud de M2', (_caso, persona) => {
    montar({ usuario: persona });

    const enlaces = screen.getAllByRole('link', { name: NOMBRE });
    expect(enlaces).toHaveLength(2);
    enlaces.forEach((enlace) => expect(enlace.getAttribute('href')).toBe('/m2.html'));

    // El del pie no depende de la franja, que se oculta en celular.
    const enlacePie = within(screen.getByRole('contentinfo')).getByRole('link', { name: NOMBRE });
    expect(enlacePie.className).toContain('min-h-[44px]');
    expect(enlacePie.closest('.hidden')).toBeNull();
  });

  it.each([
    ['encargado_comercio'],
    ['encargado_plataforma'],
  ])('no aparece para quien inició sesión como %s', (rol) => {
    montar({ usuario: usuario(rol) });

    expect(screen.queryByRole('link', { name: NOMBRE })).toBeNull();
  });

  it('no aparece mientras se consulta la sesión', () => {
    montar({ cargandoUsuario: true });

    expect(screen.queryByRole('link', { name: NOMBRE })).toBeNull();
  });
});
