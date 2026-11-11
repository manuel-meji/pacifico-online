// @vitest-environment jsdom
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent, cleanup, waitFor, within } from '@testing-library/react';
import NoticiasGestion from '../views/gestion/NoticiasGestion';
import { SECCIONES_GESTION, seccionesPara } from '../views/gestion/secciones';
import { listarPromociones } from '../services/m1Api';
import {
  actualizarNoticia, archivarNoticia, crearNoticia, listarNoticiasAdministracion,
  publicarNoticia,
} from '../services/noticiasApi';
import { listarPromocionesAdministracion } from '../services/promocionesApi';

vi.mock('../services/noticiasApi', () => ({
  listarNoticiasAdministracion: vi.fn(),
  crearNoticia: vi.fn(),
  actualizarNoticia: vi.fn(),
  publicarNoticia: vi.fn(),
  archivarNoticia: vi.fn(),
}));

// Promociones vigentes del portal, para la vista previa. Las demás funciones de m1Api son las reales
// (otras secciones del panel las importan al cargar secciones.js).
vi.mock('../services/m1Api', async (original) => ({ ...(await original()), listarPromociones: vi.fn() }));

// Promociones para enlazar: la función de la pestaña de Promociones (recibe { estado, idComercio }).
vi.mock('../services/promocionesApi', async (original) => ({ ...(await original()), listarPromocionesAdministracion: vi.fn() }));

afterEach(cleanup);

const encargado = { id_usuario: 1, nombre: 'Ana', rol: 'encargado_plataforma', id_comercio: null };

const noticia = (extra = {}) => ({
  id_noticia: 1,
  titulo: 'Feria de noviembre',
  slug: 'feria-de-noviembre',
  resumen: 'Resumen de la feria',
  contenido: 'Contenido de la feria',
  imagen_url: null,
  estado: 'borrador',
  fecha_publicacion: null,
  fecha_fin: '2027-03-15T18:30:00Z',
  visible_en_portal: false,
  ids_promocion: [],
  id_usuario_autor: 1,
  ...extra,
});

const ok = (datos) => ({ ok: true, datos });
const fallo = (estado, codigo, mensaje, detalles = null) => ({ ok: false, estado, error: { codigo, mensaje, detalles } });

const promocion = (id, titulo) => ({
  id_promocion: id, id_comercio: 1, id_producto: 100 + id, titulo, tipo: 'porcentaje', valor: 10, alcance: 'producto', variaciones: [],
  fecha_inicio: '2026-11-01T06:00:00Z', fecha_fin: '2026-12-01T06:00:00Z', estado: 'vigente',
});

const producto = (id, nombre) => ({
  id_producto: id, nombre, imagen_principal: null, precio: 6500, precio_hasta: null, moneda: 'CRC', disponibilidad: 'Disponible',
  comercio: { id_comercio: 1, nombre_comercial: 'Tostadora del Sur' },
});

beforeEach(() => {
  vi.clearAllMocks();
  listarNoticiasAdministracion.mockResolvedValue(ok([]));
  listarPromocionesAdministracion.mockResolvedValue(ok([]));
  listarPromociones.mockResolvedValue(ok([]));
});

const tarjeta = (titulo) => screen.getByRole('heading', { level: 3, name: titulo }).closest('li');
const sinCodigos = () => expect(document.body.textContent).not.toMatch(/ERR-/);

function llenarFormulario({ titulo = 'Nueva feria', resumen = 'Un resumen', contenido = 'Un contenido' } = {}) {
  fireEvent.change(screen.getByLabelText('Título'), { target: { value: titulo } });
  fireEvent.change(screen.getByLabelText('Resumen'), { target: { value: resumen } });
  fireEvent.change(screen.getByLabelText('Contenido'), { target: { value: contenido } });
}

const abrirNueva = async () => fireEvent.click(await screen.findByRole('button', { name: /Nueva noticia/ }));

describe('NoticiasGestion: sección del panel', () => {
  it('está registrada en el panel solo para el encargado de la plataforma', () => {
    const seccion = SECCIONES_GESTION.find((s) => s.clave === 'noticias');

    expect(seccion.Vista).toBe(NoticiasGestion);
    expect(seccion.roles).toEqual(['encargado_plataforma']);
    expect(seccionesPara('encargado_plataforma').map((s) => s.clave)).toContain('noticias');
    expect(seccionesPara('encargado_comercio').map((s) => s.clave)).not.toContain('noticias');
  });

  it('se monta fuera del portal, con o sin la prop usuario, y no trae su propio <main> ni <h1>', async () => {
    const { container, unmount } = render(<NoticiasGestion />);
    expect(await screen.findByText('Todavía no hay noticias')).toBeTruthy();
    expect(container.querySelector('main')).toBeNull();
    expect(container.querySelector('h1')).toBeNull();
    unmount();

    render(<NoticiasGestion usuario={encargado} />);
    expect(await screen.findByText('Todavía no hay noticias')).toBeTruthy();
  });

  it('pide solo las promociones vigentes para enlazar', async () => {
    render(<NoticiasGestion />);

    await waitFor(() => expect(listarPromocionesAdministracion).toHaveBeenCalledWith({ estado: 'vigente' }));
  });
});

describe('NoticiasGestion: lista', () => {
  it('lista las noticias con título, estado, fecha de publicación y fecha de fin', async () => {
    listarNoticiasAdministracion.mockResolvedValue(ok([
      noticia({ id_noticia: 1, titulo: 'Feria de noviembre', estado: 'borrador' }),
      noticia({ id_noticia: 2, titulo: 'Cierre de año', estado: 'publicada', fecha_publicacion: '2026-11-01T12:00:00Z', ids_promocion: [4, 5] }),
    ]));
    render(<NoticiasGestion />);

    expect(await screen.findByRole('heading', { name: 'Feria de noviembre' })).toBeTruthy();
    expect(within(tarjeta('Feria de noviembre')).getByText('Borrador')).toBeTruthy();
    expect(within(tarjeta('Feria de noviembre')).getByText('Sin publicar')).toBeTruthy();
    expect(within(tarjeta('Feria de noviembre')).queryByText('Promociones enlazadas:')).toBeNull();
    expect(within(tarjeta('Cierre de año')).getByText('Publicada')).toBeTruthy();
    expect(within(tarjeta('Cierre de año')).queryByText('Sin publicar')).toBeNull();
    expect(within(tarjeta('Cierre de año')).getByText('Fin:')).toBeTruthy();
    expect(within(tarjeta('Cierre de año')).getByText('Promociones enlazadas:').nextSibling.textContent).toBe('2');
  });

  it('pide la lista filtrada cuando se elige un estado', async () => {
    render(<NoticiasGestion />);
    await screen.findByText('Todavía no hay noticias');
    expect(listarNoticiasAdministracion).toHaveBeenLastCalledWith('');
    expect(screen.getByRole('button', { name: 'Todas' }).getAttribute('aria-pressed')).toBe('true');

    fireEvent.click(screen.getByRole('button', { name: 'Publicadas' }));

    await waitFor(() => expect(listarNoticiasAdministracion).toHaveBeenLastCalledWith('publicada'));
    expect(screen.getByRole('button', { name: 'Publicadas' }).getAttribute('aria-pressed')).toBe('true');
    expect(await screen.findByText('No hay noticias en este estado')).toBeTruthy();

    fireEvent.click(screen.getByRole('button', { name: 'Borradores' }));
    await waitFor(() => expect(listarNoticiasAdministracion).toHaveBeenLastCalledWith('borrador'));
    fireEvent.click(screen.getByRole('button', { name: 'Archivadas' }));
    await waitFor(() => expect(listarNoticiasAdministracion).toHaveBeenLastCalledWith('archivada'));
  });

  it('muestra el error con Reintentar y luego el estado vacío', async () => {
    listarNoticiasAdministracion.mockResolvedValueOnce(fallo(503, 'ERR-006', 'No disponible'));
    render(<NoticiasGestion />);

    expect(await screen.findByText('No disponible temporalmente')).toBeTruthy();
    sinCodigos();
    fireEvent.click(screen.getByRole('button', { name: 'Reintentar' }));

    expect(await screen.findByText('Todavía no hay noticias')).toBeTruthy();
  });

  it('una noticia archivada no ofrece acciones', async () => {
    listarNoticiasAdministracion.mockResolvedValue(ok([noticia({ estado: 'archivada' })]));
    render(<NoticiasGestion />);

    await screen.findByRole('heading', { name: 'Feria de noviembre' });
    const fila = within(tarjeta('Feria de noviembre'));
    expect(fila.getByText('Archivada')).toBeTruthy();
    expect(fila.queryByRole('button', { name: 'Editar' })).toBeNull();
    expect(fila.queryByRole('button', { name: /Publicar/ })).toBeNull();
    expect(fila.queryByRole('button', { name: 'Archivar' })).toBeNull();
  });
});

describe('NoticiasGestion: formulario', () => {
  it('crea la noticia como borrador, sin fecha_fin si se deja vacía y con la imagen vacía como null', async () => {
    crearNoticia.mockResolvedValue(ok(noticia({ titulo: 'Nueva feria' })));
    render(<NoticiasGestion />);
    await abrirNueva();

    llenarFormulario();
    fireEvent.click(screen.getByRole('button', { name: 'Guardar borrador' }));

    expect(await screen.findByText(/«Nueva feria» se creó como borrador/)).toBeTruthy();
    expect(crearNoticia).toHaveBeenCalledWith({
      titulo: 'Nueva feria', resumen: 'Un resumen', contenido: 'Un contenido', imagen_url: null, ids_promocion: [],
    });
    expect(publicarNoticia).not.toHaveBeenCalled();
    // El formulario se cierra y la lista se vuelve a pedir.
    expect(screen.queryByRole('form')).toBeNull();
    await waitFor(() => expect(listarNoticiasAdministracion.mock.calls.length).toBeGreaterThan(1));
  });

  it('conserva los párrafos del contenido y manda la URL de la imagen tal como se escribió', async () => {
    crearNoticia.mockResolvedValue(ok(noticia()));
    render(<NoticiasGestion />);
    await abrirNueva();

    llenarFormulario({ contenido: 'Primer párrafo.\n\nSegundo párrafo.' });
    fireEvent.change(screen.getByLabelText('Imagen (URL, opcional)'), { target: { value: ' https://ejemplo.test/feria.jpg ' } });
    fireEvent.click(screen.getByRole('button', { name: 'Guardar borrador' }));

    await waitFor(() => expect(crearNoticia).toHaveBeenCalled());
    expect(crearNoticia.mock.calls[0][0].contenido).toBe('Primer párrafo.\n\nSegundo párrafo.');
    expect(crearNoticia.mock.calls[0][0].imagen_url).toBe('https://ejemplo.test/feria.jpg');
  });

  it('valida en el cliente los campos obligatorios y la URL sin llamar a la API', async () => {
    render(<NoticiasGestion />);
    await abrirNueva();

    fireEvent.change(screen.getByLabelText('Imagen (URL, opcional)'), { target: { value: 'foto.jpg' } });
    fireEvent.click(screen.getByRole('button', { name: 'Guardar borrador' }));

    expect(await screen.findByText('Ingrese el título.')).toBeTruthy();
    expect(screen.getByText('Ingrese el resumen.')).toBeTruthy();
    expect(screen.getByText('Ingrese el contenido.')).toBeTruthy();
    expect(screen.getByText(/empiece con http:\/\/ o https:\/\/\.$/).id).toBe('campo-imagen_url-error');
    expect(crearNoticia).not.toHaveBeenCalled();
  });

  it('los campos de texto no cortan lo escrito y muestran el contador con el límite del contrato', async () => {
    render(<NoticiasGestion />);
    await abrirNueva();

    expect(screen.getByLabelText('Título').hasAttribute('maxlength')).toBe(false);
    fireEvent.change(screen.getByLabelText('Título'), { target: { value: 'a'.repeat(151) } });

    expect(screen.getByText('151 de 150')).toBeTruthy();
    fireEvent.click(screen.getByRole('button', { name: 'Guardar borrador' }));
    expect(await screen.findByText('Máximo 150 caracteres.')).toBeTruthy();
    expect(crearNoticia).not.toHaveBeenCalled();
  });

  it('muestra los errores 422 debajo de cada campo, solo con el mensaje', async () => {
    crearNoticia.mockResolvedValue(fallo(422, 'ERR-005', 'Datos inválidos', {
      titulo: ['El título ya existe.'],
      imagen_url: 'Debe ser una URL http o https de hasta 255 caracteres.',
      fecha_fin: ['Debe ser una fecha y hora futura (ISO 8601).'],
      'ids_promocion.0': 'No existe la promoción 9.',
    }));
    render(<NoticiasGestion />);
    await abrirNueva();

    llenarFormulario();
    fireEvent.click(screen.getByRole('button', { name: 'Guardar borrador' }));

    const errorTitulo = await screen.findByText('El título ya existe.');
    expect(errorTitulo.id).toBe('campo-titulo-error');
    expect(screen.getByLabelText('Título').getAttribute('aria-invalid')).toBe('true');
    expect(screen.getByText('Debe ser una URL http o https de hasta 255 caracteres.').id).toBe('campo-imagen_url-error');
    expect(screen.getByText('Debe ser una fecha y hora futura (ISO 8601).').id).toBe('campo-fecha_fin-error');
    expect(screen.getByText('No existe la promoción 9.').id).toBe('campo-ids_promocion-error');
    expect(screen.getByLabelText('Resumen').getAttribute('aria-invalid')).toBe('false');
    // El formulario sigue abierto con lo escrito.
    expect(screen.getByLabelText('Título').value).toBe('Nueva feria');
    sinCodigos();
  });

  it('un 422 sin detalles por campo se muestra como aviso general', async () => {
    crearNoticia.mockResolvedValue(fallo(422, 'ERR-005', 'Los datos enviados no son válidos.'));
    render(<NoticiasGestion />);
    await abrirNueva();

    llenarFormulario();
    fireEvent.click(screen.getByRole('button', { name: 'Guardar borrador' }));

    expect((await screen.findByRole('alert')).textContent).toBe('Los datos enviados no son válidos.');
  });

  it('ERR-M1-006 (409) al guardar se muestra con un texto claro, sin el código, y recarga la lista', async () => {
    actualizarNoticia.mockResolvedValue(fallo(409, 'ERR-M1-006', 'La noticia está archivada y no se puede editar.'));
    listarNoticiasAdministracion.mockResolvedValue(ok([noticia()]));
    render(<NoticiasGestion />);
    fireEvent.click(await screen.findByRole('button', { name: 'Editar' }));
    const pedidas = listarNoticiasAdministracion.mock.calls.length;

    fireEvent.click(screen.getByRole('button', { name: 'Guardar cambios' }));

    const aviso = await screen.findByText('Esta noticia está archivada y ya no se puede modificar.');
    expect(aviso.closest('[role="status"]')).not.toBeNull();
    expect(screen.queryByRole('alert')).toBeNull();
    sinCodigos();
    await waitFor(() => expect(listarNoticiasAdministracion.mock.calls.length).toBeGreaterThan(pedidas));
  });

  it('otros errores al guardar muestran solo el mensaje, nunca el código', async () => {
    crearNoticia.mockResolvedValueOnce(fallo(500, 'ERR-006', 'El servicio no está disponible.'));
    render(<NoticiasGestion />);
    await abrirNueva();
    llenarFormulario();

    fireEvent.click(screen.getByRole('button', { name: 'Guardar borrador' }));
    expect((await screen.findByRole('alert')).textContent).toBe('El servicio no está disponible.');

    crearNoticia.mockResolvedValueOnce(fallo(403, 'ERR-002', 'Rol sin permiso'));
    fireEvent.click(screen.getByRole('button', { name: 'Guardar borrador' }));
    expect(await screen.findByText('Su cuenta no tiene permiso para esta acción.')).toBeTruthy();

    crearNoticia.mockResolvedValueOnce(fallo(401, 'ERR-001', 'No autenticado'));
    fireEvent.click(screen.getByRole('button', { name: 'Guardar borrador' }));
    expect(await screen.findByText('Su sesión expiró. Vuelva a ingresar.')).toBeTruthy();
    sinCodigos();
  });

  it('Cancelar cierra el formulario sin guardar', async () => {
    render(<NoticiasGestion />);
    await abrirNueva();
    llenarFormulario();

    fireEvent.click(screen.getByRole('button', { name: 'Cancelar' }));

    expect(screen.queryByLabelText('Título')).toBeNull();
    expect(crearNoticia).not.toHaveBeenCalled();
  });
});

describe('NoticiasGestion: promociones enlazadas', () => {
  it('si no cargan las promociones avisa y el formulario sigue funcionando', async () => {
    listarPromocionesAdministracion.mockResolvedValue(fallo(503, 'ERR-006', 'No disponible'));
    crearNoticia.mockResolvedValue(ok(noticia({ titulo: 'Nueva feria' })));
    render(<NoticiasGestion />);
    await abrirNueva();

    expect(await screen.findByText(/No se pudieron cargar las promociones/)).toBeTruthy();
    llenarFormulario();
    fireEvent.click(screen.getByRole('button', { name: 'Guardar borrador' }));

    await waitFor(() => expect(crearNoticia).toHaveBeenCalled());
    expect(crearNoticia.mock.calls[0][0].ids_promocion).toEqual([]);
  });

  it('muestra las promociones vigentes por título con casillas y permite elegir hasta 10', async () => {
    listarPromocionesAdministracion.mockResolvedValue(ok(
      Array.from({ length: 11 }, (_, i) => promocion(i + 1, `Promo ${i + 1}`)),
    ));
    crearNoticia.mockResolvedValue(ok(noticia()));
    render(<NoticiasGestion />);
    await abrirNueva();

    const casillas = await screen.findAllByRole('checkbox');
    expect(casillas).toHaveLength(11);
    casillas.slice(0, 10).forEach((c) => fireEvent.click(c));

    expect(screen.getByText('10 de 10 elegidas.')).toBeTruthy();
    expect(screen.getByRole('checkbox', { name: /Promo 11/ }).disabled).toBe(true);
    expect(screen.getByRole('checkbox', { name: /^Promo 1(?!\d)/ }).disabled).toBe(false);

    llenarFormulario();
    fireEvent.click(screen.getByRole('button', { name: 'Guardar borrador' }));
    await waitFor(() => expect(crearNoticia).toHaveBeenCalled());
    expect(crearNoticia.mock.calls[0][0].ids_promocion).toEqual([1, 2, 3, 4, 5, 6, 7, 8, 9, 10]);
  });

  it('al editar conserva una promoción enlazada que ya no está vigente y permite quitarla', async () => {
    listarNoticiasAdministracion.mockResolvedValue(ok([noticia({ ids_promocion: [2, 9] })]));
    listarPromocionesAdministracion.mockResolvedValue(ok([promocion(2, 'Oferta de feria'), promocion(3, 'Liquidación')]));
    actualizarNoticia.mockResolvedValue(ok(noticia()));
    render(<NoticiasGestion />);
    fireEvent.click(await screen.findByRole('button', { name: 'Editar' }));

    expect((await screen.findByRole('checkbox', { name: /Oferta de feria/ })).checked).toBe(true);
    expect(screen.getByRole('checkbox', { name: /Liquidación/ }).checked).toBe(false);
    const vencida = screen.getByRole('checkbox', { name: /Promoción 9.*Ya no está vigente/ });
    expect(vencida.checked).toBe(true);

    fireEvent.click(screen.getByRole('checkbox', { name: /Liquidación/ }));
    fireEvent.click(screen.getByRole('button', { name: 'Guardar cambios' }));
    await waitFor(() => expect(actualizarNoticia).toHaveBeenCalledTimes(1));
    expect(actualizarNoticia.mock.calls[0][1].ids_promocion).toEqual([2, 9, 3]);
  });
});

describe('NoticiasGestion: fecha_fin en hora de Costa Rica', () => {
  it('muestra la fecha_fin del backend en hora de Costa Rica y la vuelve a mandar sin correrse', async () => {
    listarNoticiasAdministracion.mockResolvedValue(ok([noticia({ fecha_fin: '2027-03-15T18:30:00Z', imagen_url: 'https://x.test/a.png' })]));
    actualizarNoticia.mockResolvedValue(ok(noticia()));
    render(<NoticiasGestion />);

    fireEvent.click(await screen.findByRole('button', { name: 'Editar' }));

    expect(screen.getByLabelText('Fecha de fin (opcional)').value).toBe('2027-03-15T12:30');
    expect(screen.getByText(/^Hora de Costa Rica\./)).toBeTruthy();

    fireEvent.click(screen.getByRole('button', { name: 'Guardar cambios' }));

    await waitFor(() => expect(actualizarNoticia).toHaveBeenCalled());
    const [id, datos] = actualizarNoticia.mock.calls[0];
    expect(id).toBe(1);
    expect(datos.fecha_fin).toBe('2027-03-15T12:30:00-06:00');
    expect(new Date(datos.fecha_fin).getTime()).toBe(new Date('2027-03-15T18:30:00Z').getTime());
    expect(datos.imagen_url).toBe('https://x.test/a.png');
  });

  it('una fecha_fin que el backend guardó en UTC se muestra en hora de Costa Rica, aunque caiga en el día anterior', async () => {
    // Formato real de la API (toIso8601String en UTC): las 18:00 del 30 de noviembre en Costa Rica.
    listarNoticiasAdministracion.mockResolvedValue(ok([noticia({ fecha_fin: '2026-12-01T00:00:00+00:00' })]));
    actualizarNoticia.mockResolvedValue(ok(noticia()));
    render(<NoticiasGestion />);

    fireEvent.click(await screen.findByRole('button', { name: 'Editar' }));

    expect(screen.getByLabelText('Fecha de fin (opcional)').value).toBe('2026-11-30T18:00');

    fireEvent.click(screen.getByRole('button', { name: 'Guardar cambios' }));
    await waitFor(() => expect(actualizarNoticia).toHaveBeenCalled());
    expect(actualizarNoticia.mock.calls[0][1].fecha_fin).toBe('2026-11-30T18:00:00-06:00');
  });

  it('una fecha_fin escrita en el formulario se manda con el desfase de Costa Rica', async () => {
    crearNoticia.mockResolvedValue(ok(noticia()));
    render(<NoticiasGestion />);
    await abrirNueva();

    llenarFormulario();
    fireEvent.change(screen.getByLabelText('Fecha de fin (opcional)'), { target: { value: '2027-06-15T08:05' } });
    fireEvent.click(screen.getByRole('button', { name: 'Guardar borrador' }));

    await waitFor(() => expect(crearNoticia).toHaveBeenCalled());
    expect(crearNoticia.mock.calls[0][0].fecha_fin).toBe('2027-06-15T08:05:00-06:00');
  });
});

describe('NoticiasGestion: publicar y archivar', () => {
  it('Publicar llama a publicarNoticia con el id, avisa el resultado y recarga la lista', async () => {
    listarNoticiasAdministracion.mockResolvedValue(ok([noticia({ id_noticia: 7 })]));
    publicarNoticia.mockResolvedValue(ok(noticia({ id_noticia: 7, estado: 'publicada' })));
    render(<NoticiasGestion />);

    fireEvent.click(await screen.findByRole('button', { name: /Publicar/ }));

    expect(await screen.findByText(/«Feria de noviembre» se publicó/)).toBeTruthy();
    expect(publicarNoticia).toHaveBeenCalledTimes(1);
    expect(publicarNoticia.mock.calls[0][0]).toBe(7);
    await waitFor(() => expect(listarNoticiasAdministracion.mock.calls.length).toBeGreaterThan(1));
  });

  it('una noticia publicada no ofrece Publicar pero sí Editar y Archivar', async () => {
    listarNoticiasAdministracion.mockResolvedValue(ok([noticia({ estado: 'publicada', fecha_publicacion: '2026-11-01T12:00:00Z' })]));
    render(<NoticiasGestion />);

    await screen.findByRole('button', { name: 'Archivar' });
    expect(screen.getByRole('button', { name: 'Editar' })).toBeTruthy();
    expect(screen.queryByRole('button', { name: /Publicar/ })).toBeNull();
  });

  it('Archivar pide confirmación y solo llama a archivarNoticia al confirmar', async () => {
    listarNoticiasAdministracion.mockResolvedValue(ok([noticia({ id_noticia: 7, estado: 'publicada', fecha_publicacion: '2026-11-01T12:00:00Z' })]));
    archivarNoticia.mockResolvedValue(ok(noticia({ id_noticia: 7, estado: 'archivada' })));
    render(<NoticiasGestion />);

    fireEvent.click(await screen.findByRole('button', { name: 'Archivar' }));
    expect(archivarNoticia).not.toHaveBeenCalled();

    // Cancelar no archiva.
    fireEvent.click(screen.getByRole('button', { name: 'Cancelar' }));
    expect(archivarNoticia).not.toHaveBeenCalled();

    fireEvent.click(screen.getByRole('button', { name: 'Archivar' }));
    expect(screen.getByRole('alertdialog').textContent).toMatch(/¿Archivar «Feria de noviembre»\?/);
    fireEvent.click(screen.getByRole('button', { name: 'Sí, archivar' }));

    expect(await screen.findByText(/«Feria de noviembre» se archivó/)).toBeTruthy();
    expect(archivarNoticia).toHaveBeenCalledTimes(1);
    expect(archivarNoticia).toHaveBeenCalledWith(7);
  });

  it('ERR-M1-006 (409) al publicar se muestra con un texto claro, sin el código, y recarga la lista', async () => {
    listarNoticiasAdministracion.mockResolvedValue(ok([noticia()]));
    publicarNoticia.mockResolvedValue(fallo(409, 'ERR-M1-006', 'La noticia ya está publicada.'));
    render(<NoticiasGestion />);

    fireEvent.click(await screen.findByRole('button', { name: /Publicar/ }));

    const aviso = await screen.findByText('Esta noticia ya está publicada o archivada, así que no se puede publicar de nuevo.');
    expect(aviso.closest('[role="status"]')).not.toBeNull();
    sinCodigos();
    await waitFor(() => expect(listarNoticiasAdministracion.mock.calls.length).toBeGreaterThan(1));
  });

  it('ERR-M1-006 (409) al archivar se muestra con un texto claro, sin el código', async () => {
    listarNoticiasAdministracion.mockResolvedValue(ok([noticia()]));
    archivarNoticia.mockResolvedValue(fallo(409, 'ERR-M1-006', 'La noticia ya está archivada.'));
    render(<NoticiasGestion />);

    fireEvent.click(await screen.findByRole('button', { name: 'Archivar' }));
    fireEvent.click(screen.getByRole('button', { name: 'Sí, archivar' }));

    expect(await screen.findByText('Esta noticia ya estaba archivada.')).toBeTruthy();
    expect(screen.queryByRole('alertdialog')).toBeNull();
    sinCodigos();
  });

  it('un 422 al publicar muestra el mensaje del campo', async () => {
    listarNoticiasAdministracion.mockResolvedValue(ok([noticia()]));
    publicarNoticia.mockResolvedValue(fallo(422, 'ERR-005', 'Datos inválidos', { fecha_publicacion: 'Debe ser anterior a la fecha de fin.' }));
    render(<NoticiasGestion />);

    fireEvent.click(await screen.findByRole('button', { name: /Publicar/ }));

    expect((await screen.findByRole('alert')).textContent).toBe('Debe ser anterior a la fecha de fin.');
    sinCodigos();
  });
});

describe('NoticiasGestion: vista previa', () => {
  it('muestra la tarjeta de la portada y la página con lo escrito, sin guardar nada', async () => {
    render(<NoticiasGestion />);
    await abrirNueva();
    expect(screen.queryByRole('region', { name: 'Vista previa de la noticia' })).toBeNull();

    llenarFormulario({ titulo: 'Feria de fin de año', resumen: 'Tres días de ofertas.', contenido: 'Primer párrafo.\n\nSegundo párrafo.' });
    fireEvent.click(screen.getByRole('button', { name: 'Vista previa' }));

    const previa = within(screen.getByRole('region', { name: 'Vista previa de la noticia' }));
    // Tarjeta de la portada (TarjetaNoticia) y página de la noticia (ArticuloNoticia).
    expect(previa.getByRole('button', { name: 'Leer la noticia Feria de fin de año' })).toBeTruthy();
    expect(previa.getByRole('heading', { level: 4, name: 'Feria de fin de año' })).toBeTruthy();
    expect(previa.getAllByText('Tres días de ofertas.')).toHaveLength(2);
    expect(previa.getByText(/Primer párrafo\.\s+Segundo párrafo\./).className).toMatch(/whitespace-pre-line/);
    expect(previa.getAllByText(/de 20\d\d$/).length).toBeGreaterThan(0);

    // Se actualiza al escribir.
    fireEvent.change(screen.getByLabelText('Título'), { target: { value: 'Feria de enero' } });
    expect(previa.getByRole('heading', { level: 4, name: 'Feria de enero' })).toBeTruthy();

    expect(crearNoticia).not.toHaveBeenCalled();

    fireEvent.click(screen.getByRole('button', { name: 'Ocultar vista previa' }));
    expect(screen.queryByRole('region', { name: 'Vista previa de la noticia' })).toBeNull();
  });

  it('no agrega un <h1> al panel y muestra la imagen escrita', async () => {
    const { container } = render(<NoticiasGestion />);
    await abrirNueva();
    llenarFormulario();
    fireEvent.change(screen.getByLabelText('Imagen (URL, opcional)'), { target: { value: 'https://ejemplo.test/feria.jpg' } });

    fireEvent.click(screen.getByRole('button', { name: 'Vista previa' }));

    expect(container.querySelector('h1')).toBeNull();
    expect(container.querySelector('h2 ~ * h2')).toBeNull();
    const imagenes = [...container.querySelectorAll('img')].map((i) => i.getAttribute('src'));
    expect(imagenes).toEqual(['https://ejemplo.test/feria.jpg', 'https://ejemplo.test/feria.jpg']);
  });

  it('con el formulario vacío muestra textos de ejemplo', async () => {
    render(<NoticiasGestion />);
    await abrirNueva();

    fireEvent.click(screen.getByRole('button', { name: 'Vista previa' }));

    expect(screen.getByRole('heading', { level: 4, name: 'Título de la noticia' })).toBeTruthy();
  });

  it('muestra las promociones enlazadas con su producto y avisa de las que el portal no tiene vigentes', async () => {
    listarPromocionesAdministracion.mockResolvedValue(ok([promocion(2, 'Semana del café'), promocion(3, 'Liquidación')]));
    listarPromociones.mockResolvedValue(ok([
      { ...promocion(2, 'Semana del café'), producto: producto(102, 'Café de altura 500 g') },
      { ...promocion(8, 'Otra promoción'), producto: producto(108, 'Taza de cerámica') },
    ]));
    render(<NoticiasGestion />);
    await abrirNueva();
    llenarFormulario();
    fireEvent.click(await screen.findByRole('checkbox', { name: /Semana del café/ }));
    fireEvent.click(screen.getByRole('checkbox', { name: /Liquidación/ }));
    expect(listarPromociones).not.toHaveBeenCalled();

    fireEvent.click(screen.getByRole('button', { name: 'Vista previa' }));

    const previa = within(screen.getByRole('region', { name: 'Vista previa de la noticia' }));
    expect(await previa.findByRole('heading', { level: 5, name: /Promociones de esta noticia/ })).toBeTruthy();
    expect(previa.getByText('Café de altura 500 g')).toBeTruthy();
    expect(previa.queryByText('Taza de cerámica')).toBeNull();
    expect(previa.getByText(/Una de las promociones enlazadas no se muestra/)).toBeTruthy();
  });

  it('al editar una publicada usa su fecha de publicación', async () => {
    listarNoticiasAdministracion.mockResolvedValue(ok([noticia({ estado: 'publicada', fecha_publicacion: '2026-11-01T18:00:00Z' })]));
    render(<NoticiasGestion />);
    fireEvent.click(await screen.findByRole('button', { name: 'Editar' }));

    fireEvent.click(screen.getByRole('button', { name: 'Vista previa' }));

    const fechas = [...screen.getByRole('region', { name: 'Vista previa de la noticia' }).querySelectorAll('time')];
    expect(fechas.map((t) => t.getAttribute('datetime'))).toEqual(['2026-11-01T18:00:00Z', '2026-11-01T18:00:00Z']);
  });
});
