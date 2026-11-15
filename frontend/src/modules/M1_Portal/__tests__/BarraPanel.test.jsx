// @vitest-environment jsdom
import { test, expect } from 'vitest';
import { createRoot } from 'react-dom/client';
import { act } from 'react';
import PaginaPortal, { PortalContext } from '../components/PaginaPortal';

// Pantallas de encargados (panel de gestión y vista previa del micrositio): franja azul con un solo
// botón de regreso, sin el buscador, las categorías ni los accesos de cliente del portal.
async function render(props, usuario) {
  globalThis.IS_REACT_ACT_ENVIRONMENT = true;
  const div = document.createElement('div');
  await act(async () => createRoot(div).render(
    <PortalContext.Provider value={{ ir: () => {}, categorias: [{ id_categoria: 1, nombre: 'Tecnología' }], usuario, cargandoUsuario: false, cantidadCarrito: 0 }}>
      <PaginaPortal {...props}><p>contenido</p></PaginaPortal>
    </PortalContext.Provider>));
  return div;
}

test('con barraPanel no muestra el buscador ni las categorías y regresa a /m2.html', async () => {
  const d = await render({ barraPanel: { texto: 'Panel de gestión del portal', href: '/m2.html', etiqueta: 'Volver a mi panel' } }, { rol: 'encargado_plataforma' });
  expect(d.querySelector('input')).toBeNull();
  expect(d.textContent).not.toContain('Tecnología');
  expect(d.textContent).toContain('Volver a mi panel');
  expect(d.querySelector('a').getAttribute('href')).toBe('/m2.html');
});

test('la vista previa del micrositio conserva su texto y su regreso', async () => {
  const d = await render({ vistaPrevia: true }, { rol: 'encargado_comercio' });
  expect(d.textContent).toContain('Vista previa de tu comercio en el portal');
  expect(d.textContent).toContain('Volver a Mi comercio');
});

test('sin barra se muestra el encabezado normal del portal', async () => {
  const d = await render({}, null);
  expect(d.textContent).toContain('Tecnología');
});
