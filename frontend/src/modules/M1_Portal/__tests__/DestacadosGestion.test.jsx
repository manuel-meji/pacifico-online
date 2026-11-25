// @vitest-environment jsdom
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent, cleanup } from '@testing-library/react';
import DestacadosGestion from '../views/gestion/DestacadosGestion';
import { obtenerFichaProducto } from '../services/m1Api';
import {
  aprobarSolicitud, listarSolicitudesAdministracion, listarTarifas, obtenerResumenComerciosLote, rechazarSolicitud,
} from '../services/destacadosApi';

// Solicitudes de la plataforma: las funciones de la API de destacados y la ficha del producto (para su nombre).
vi.mock('../services/destacadosApi', async (original) => ({
  ...(await original()),
  listarSolicitudesAdministracion: vi.fn(),
  aprobarSolicitud: vi.fn(),
  rechazarSolicitud: vi.fn(),
  listarTarifas: vi.fn(),
  obtenerResumenComerciosLote: vi.fn(),
}));
vi.mock('../services/m1Api', async (original) => ({ ...(await original()), obtenerFichaProducto: vi.fn() }));

afterEach(cleanup);

const plataforma = { id_usuario: 2, nombre: 'Ana', rol: 'encargado_plataforma', id_comercio: null };

const ok = (datos) => ({ ok: true, datos });
const fallo = (estado) => ({ ok: false, estado, error: { codigo: 'ERR-006', mensaje: 'Proveedor no disponible', detalles: null } });

const solicitud = (id, idProducto, idComercio, extra = {}) => ({
  id_solicitud_destacado: id, id_producto: idProducto, id_comercio: idComercio, periodo_dias: 7, monto: 5000,
  estado: 'pendiente', fecha_solicitud: '2026-11-20T15:00:00Z', fecha_inicio: null, fecha_fin: null, motivo_rechazo: null,
  ...extra,
});

const NOMBRES_PRODUCTO = { 10: 'Agua', 11: 'Café molido', 12: 'Taza' };

beforeEach(() => {
  vi.clearAllMocks();
  listarTarifas.mockResolvedValue(ok([]));
  obtenerFichaProducto.mockImplementation(async (id) => ok({ id_producto: id, nombre: NOMBRES_PRODUCTO[id] }));
  listarSolicitudesAdministracion.mockResolvedValue(ok([
    solicitud(1, 10, 7),
    solicitud(2, 11, 7, { periodo_dias: 15 }),
    solicitud(3, 12, 9),
  ]));
  obtenerResumenComerciosLote.mockResolvedValue(ok({
    comercios: [
      { id_comercio: 7, nombre_comercial: 'Café Volcán', estado: 'Activo', destacado: false },
      { id_comercio: 9, nombre_comercial: 'Temulandia', estado: 'Activo', destacado: false },
    ],
  }));
});

describe('Solicitudes de destacados (plataforma): nombre del comercio', () => {
  it('muestra el nombre comercial de M2 en cada tarjeta, con una sola consulta en lote para toda la lista', async () => {
    render(<DestacadosGestion usuario={plataforma} />);

    expect(await screen.findAllByText(/Café Volcán ·/)).toHaveLength(2);
    expect(screen.getByText(/Temulandia ·/)).toBeTruthy();
    expect(screen.queryByText(/Comercio 7/)).toBeNull();

    expect(obtenerResumenComerciosLote).toHaveBeenCalledTimes(1);
    expect(obtenerResumenComerciosLote).toHaveBeenCalledWith([7, 9]);
  });

  it('si M2 no responde, deja "Comercio {id}" como respaldo', async () => {
    obtenerResumenComerciosLote.mockResolvedValue(fallo(503));
    render(<DestacadosGestion usuario={plataforma} />);

    expect(await screen.findAllByText(/Comercio 7 ·/)).toHaveLength(2);
    expect(screen.getByText(/Comercio 9 ·/)).toBeTruthy();
  });

  it('si M2 omite un comercio (no está Activo), ese queda como "Comercio {id}"', async () => {
    obtenerResumenComerciosLote.mockResolvedValue(ok({
      comercios: [{ id_comercio: 7, nombre_comercial: 'Café Volcán', estado: 'Activo', destacado: false }],
    }));
    render(<DestacadosGestion usuario={plataforma} />);

    expect(await screen.findAllByText(/Café Volcán ·/)).toHaveLength(2);
    expect(screen.getByText(/Comercio 9 ·/)).toBeTruthy();
  });
});

describe('Solicitudes de destacados (plataforma): avisos con el producto', () => {
  it('al aprobar, el aviso dice qué producto queda destacado y por cuántos días', async () => {
    aprobarSolicitud.mockResolvedValue(ok({}));
    render(<DestacadosGestion usuario={plataforma} />);
    await screen.findByText('Agua');

    fireEvent.click(screen.getAllByRole('button', { name: /Aprobar/ })[0]);

    expect(await screen.findByText('Solicitud aprobada: Agua queda destacado por 7 días.')).toBeTruthy();
    expect(aprobarSolicitud).toHaveBeenCalledWith(1);
  });

  it('al rechazar, el aviso dice qué producto no se destacará y por cuántos días', async () => {
    rechazarSolicitud.mockResolvedValue(ok({}));
    render(<DestacadosGestion usuario={plataforma} />);
    await screen.findByText('Café molido');

    fireEvent.click(screen.getAllByRole('button', { name: /Rechazar/ })[1]);
    fireEvent.change(screen.getByLabelText(/Motivo del rechazo/), { target: { value: 'Imagen poco clara' } });
    fireEvent.click(screen.getByRole('button', { name: 'Confirmar rechazo' }));

    expect(await screen.findByText('Solicitud rechazada: Café molido no se destacará por 15 días.')).toBeTruthy();
    expect(rechazarSolicitud).toHaveBeenCalledWith(2, 'Imagen poco clara');
  });
});
