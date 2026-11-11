// @vitest-environment jsdom
import { describe, it, expect, vi, afterEach } from 'vitest';
import { render, screen, fireEvent, cleanup, within } from '@testing-library/react';
import ArticuloNoticia from '../components/ArticuloNoticia';

afterEach(cleanup);

const producto = {
  id_producto: 1520,
  nombre: 'Café de altura 500 g',
  imagen_principal: null,
  precio: 6500,
  precio_hasta: null,
  moneda: 'CRC',
  disponibilidad: 'Disponible',
  comercio: { id_comercio: 7, nombre_comercial: 'Tostadora del Sur' },
};

const promocion = (extra = {}) => ({
  id_promocion: 31,
  id_comercio: 7,
  id_producto: 1520,
  titulo: 'Semana del café',
  tipo: 'porcentaje',
  valor: 15,
  alcance: 'producto',
  variaciones: [],
  fecha_inicio: '2026-11-01T06:00:00Z',
  fecha_fin: '2026-11-30T18:00:00Z',
  estado: 'vigente',
  producto,
  ...extra,
});

const noticia = (extra = {}) => ({
  id_noticia: 4,
  titulo: 'Feria de noviembre',
  slug: 'feria-de-noviembre',
  resumen: 'Tres días de ofertas de los comercios de la zona.',
  contenido: 'Primer párrafo de la noticia.\n\nSegundo párrafo de la noticia.',
  imagen_url: 'https://ejemplo.test/feria.jpg',
  fecha_publicacion: '2026-11-10T18:00:00Z',
  promociones: [],
  ...extra,
});

describe('ArticuloNoticia', () => {
  it('muestra el título, el resumen y la fecha de publicación', () => {
    const { container } = render(<ArticuloNoticia noticia={noticia()} />);

    expect(screen.getByRole('heading', { level: 1, name: 'Feria de noviembre' })).toBeTruthy();
    expect(screen.getByText('Tres días de ofertas de los comercios de la zona.')).toBeTruthy();
    const fecha = container.querySelector('time');
    expect(fecha.getAttribute('datetime')).toBe('2026-11-10T18:00:00Z');
    expect(fecha.textContent).toMatch(/10 de noviembre de 2026/);
  });

  it('muestra el contenido como texto plano y conserva los párrafos', () => {
    const { container } = render(<ArticuloNoticia noticia={noticia()} />);

    const contenido = container.querySelector('.whitespace-pre-line');
    expect(contenido.textContent).toBe('Primer párrafo de la noticia.\n\nSegundo párrafo de la noticia.');
  });

  it('no interpreta HTML en el contenido', () => {
    const { container } = render(<ArticuloNoticia noticia={noticia({ contenido: '<b>negrita</b>' })} />);

    expect(container.querySelector('b')).toBeNull();
    expect(screen.getByText('<b>negrita</b>')).toBeTruthy();
  });

  it('muestra la imagen de la noticia y, si falta o no carga, el ícono en su lugar', () => {
    const { container, rerender } = render(<ArticuloNoticia noticia={noticia()} />);

    const imagen = container.querySelector('img');
    expect(imagen.getAttribute('src')).toBe('https://ejemplo.test/feria.jpg');
    fireEvent.error(imagen);
    expect(container.querySelector('img')).toBeNull();
    expect(container.querySelector('svg')).toBeTruthy();

    rerender(<ArticuloNoticia noticia={noticia({ imagen_url: null })} />);
    expect(container.querySelector('img')).toBeNull();
  });

  it('sin promociones enlazadas no muestra la sección', () => {
    render(<ArticuloNoticia noticia={noticia()} />);

    expect(screen.queryByRole('heading', { name: /Promociones de esta noticia/ })).toBeNull();
  });

  it('muestra cada promoción enlazada con su producto y avisa al abrirlo', () => {
    const onAbrirProducto = vi.fn();
    const promociones = [promocion(), promocion({ id_promocion: 32, titulo: 'Liquidación', producto: { ...producto, id_producto: 1600, nombre: 'Taza de cerámica' } })];
    render(<ArticuloNoticia noticia={noticia({ promociones })} onAbrirProducto={onAbrirProducto} />);

    const seccion = screen.getByRole('heading', { level: 2, name: /Promociones de esta noticia/ }).closest('section');
    expect(within(seccion).getAllByRole('listitem')).toHaveLength(2);
    expect(within(seccion).getByText(/Semana del café · hasta el/)).toBeTruthy();
    expect(within(seccion).getByText(/Liquidación · hasta el/)).toBeTruthy();
    expect(within(seccion).getByText('Café de altura 500 g')).toBeTruthy();

    fireEvent.click(within(seccion).getByRole('button', { name: 'Ver ficha de Taza de cerámica' }));
    expect(onAbrirProducto).toHaveBeenCalledWith(expect.objectContaining({ id_producto: 1600 }));
  });

  it('con nivelTitulo cambia solo la etiqueta de los encabezados, no sus clases', () => {
    const datos = noticia({ promociones: [promocion()] });
    const portal = render(<ArticuloNoticia noticia={datos} />);
    const clasesTitulo = portal.container.querySelector('h1').className;
    const clasesPromociones = portal.container.querySelector('h2').className;
    cleanup();

    const { container } = render(<ArticuloNoticia noticia={datos} nivelTitulo={4} />);

    expect(container.querySelector('h1')).toBeNull();
    expect(container.querySelector('h2')).toBeNull();
    const titulo = screen.getByRole('heading', { level: 4, name: 'Feria de noviembre' });
    const promociones = screen.getByRole('heading', { level: 5, name: /Promociones de esta noticia/ });
    expect(titulo.className).toBe(clasesTitulo);
    expect(promociones.className).toBe(clasesPromociones);
    expect(promociones.id).toBe('titulo-promociones-noticia');
  });

  it('funciona sin onAbrirProducto, como en la vista previa del panel', () => {
    render(<ArticuloNoticia noticia={noticia({ promociones: [promocion()] })} />);

    expect(() => fireEvent.click(screen.getByRole('button', { name: 'Ver ficha de Café de altura 500 g' }))).not.toThrow();
  });
});
