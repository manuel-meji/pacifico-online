// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { cleanup, fireEvent, render as renderReact, screen, waitFor, within } from '@testing-library/react';
import CuentasPorCobrarPagarView from '../views/CuentasPorCobrarPagarView';
import CentrosCostoView from '../views/CentrosCostoView';
import M7App from '../M7App';
import { SesionM7Context } from '../context/SesionM7Context';

function render(elemento) {
  return renderReact(
    <SesionM7Context.Provider value={{ usuario: { rol: 'encargado_comercio' } }}>
      {elemento}
    </SesionM7Context.Provider>
  );
}

beforeEach(() => {
  Object.defineProperty(HTMLDialogElement.prototype, 'showModal', {
    configurable: true,
    value() { this.setAttribute('open', ''); },
  });
  Object.defineProperty(HTMLDialogElement.prototype, 'close', {
    configurable: true,
    value() { this.removeAttribute('open'); },
  });
});

afterEach(() => {
  cleanup();
  vi.unstubAllGlobals();
  delete HTMLDialogElement.prototype.showModal;
  delete HTMLDialogElement.prototype.close;
});

const sinPermisoComercios = { ok: false, status: 403, json: async () => ({ message: 'This action is unauthorized.' }) };

/** Responde la lista de comercios por separado; por defecto simula un encargado de comercio (403). */
function conComercios(consultas, comercios = sinPermisoComercios) {
  return vi.fn((url, opciones) => (url.startsWith('/api/m7/v1/comercios') ? Promise.resolve(comercios) : consultas(url, opciones)));
}

describe('Consultas contables reales', () => {
  it('exige seleccionar comercio y conserva ese ámbito al navegar o cambiar de comercio', async () => {
    const fetch = vi.fn(async (url) => {
      if (url === '/api/user') {
        return { ok: true, json: async () => ({ user: { rol: 'encargado_plataforma', nombre: 'Plataforma' } }) };
      }
      if (url === '/api/m7/v1/comercios') {
        return { ok: true, json: async () => ({ success: true, data: { comercios: [
          { id_comercio: 1, nombre: 'Comercio uno' },
          { id_comercio: 2, nombre: 'Comercio dos' },
        ] } }) };
      }
      return { ok: false, status: 500, json: async () => ({ error: { mensaje: 'Consulta no disponible.' } }) };
    });
    vi.stubGlobal('fetch', fetch);
    render(<M7App />);

    await screen.findByRole('option', { name: 'Comercio uno' });
    expect(fetch.mock.calls.filter(([url]) => url.startsWith('/api/m7/v1/') && url !== '/api/m7/v1/comercios')).toHaveLength(0);
    expect(screen.getByText('Seleccione un comercio para consultar sus datos contables.')).toBeTruthy();

    fireEvent.change(screen.getByLabelText('Comercio'), { target: { value: '1' } });
    await waitFor(() => expect(fetch.mock.calls.some(([url]) => url.startsWith('/api/m7/v1/libro-diario?') && url.includes('id_comercio=1'))).toBe(true));
    await waitFor(() => expect(fetch.mock.calls.some(([url]) => url.startsWith('/api/m7/v1/reporte-gerencial?') && url.includes('id_comercio=1'))).toBe(true));

    fireEvent.change(screen.getByLabelText('Comercio'), { target: { value: '2' } });
    await waitFor(() => expect(fetch.mock.calls.some(([url]) => url.startsWith('/api/m7/v1/libro-diario?') && url.includes('id_comercio=2'))).toBe(true));

    for (const [seccion, ruta] of [
      ['Plan de cuentas', '/api/m7/v1/cuentas?'],
      ['Libro diario', '/api/m7/v1/libro-diario?'],
      ['Libro mayor', '/api/m7/v1/libro-mayor/cuentas?'],
      ['Balance general', '/api/m7/v1/balance-general?'],
      ['Estado de resultados', '/api/m7/v1/reporte-gerencial?'],
      ['Cuentas por cobrar y pagar', '/api/m7/v1/saldos-cobrar-pagar?'],
      ['Centros de costo', '/api/m7/v1/centros-costo?'],
    ]) {
      fireEvent.click(within(screen.getByRole('navigation', { name: 'Secciones contables' })).getByRole('button', { name: seccion }));
      await waitFor(() => expect(fetch.mock.calls.some(([url]) => url.startsWith(ruta) && url.includes('id_comercio=2'))).toBe(true));
    }
  });

  it.each([
    {
      Vista: CentrosCostoView,
      boton: 'Imprimir reporte',
      busqueda: 'Buscar centro',
      datos: {
        id_comercio: 5, fecha_desde: null, fecha_hasta: null, hay_movimientos: true, sin_centro: null,
        centros: Array.from({ length: 11 }, (_, indice) => ({
          id_centro_costo: indice + 1, codigo: `C${indice + 1}`, nombre: `Elemento ${indice + 1}`, activo: true,
          ingresos: '10', costos: '5', utilidad: '5', margen: '50',
        })),
        resumen: { cantidad: 11, activos: 11, ingresos: '110', costos: '55', utilidad: '55', margen: '50' },
      },
    },
    {
      Vista: CuentasPorCobrarPagarView,
      boton: 'Imprimir listado',
      busqueda: 'Buscar cuenta',
      datos: {
        id_comercio: 5,
        cuentas: Array.from({ length: 11 }, (_, indice) => ({
          id_cuenta_contable: indice + 1, codigo: `C${indice + 1}`, nombre: `Elemento ${indice + 1}`,
          tipo: 'Por cobrar', saldo: '10',
        })),
        resumen: { por_cobrar: '110', por_pagar: '0' },
      },
    },
  ])('prepara la impresión de todas las filas filtradas de $boton', async ({ Vista, boton, busqueda, datos }) => {
    vi.stubGlobal('fetch', conComercios(vi.fn().mockResolvedValue({
      ok: true, json: async () => ({ success: true, data: datos }),
    })));
    vi.stubGlobal('print', vi.fn());
    const { container } = render(<Vista />);

    await screen.findByText('Elemento 1');
    const reporte = container.querySelector('.m7-print-area');
    expect(reporte).toBeTruthy();
    expect(within(reporte).getByText('Elemento 11').closest('tr').classList.contains('m7-solo-impresion')).toBe(true);
    fireEvent.click(screen.getByRole('button', { name: boton }));
    expect(window.print).toHaveBeenCalledOnce();

    fireEvent.change(screen.getByLabelText(busqueda), { target: { value: 'Elemento 1' } });
    expect(within(reporte).getByText('Elemento 11')).toBeTruthy();
    expect(within(reporte).queryByText('Elemento 2')).toBeNull();
  });

  it('consulta y filtra los saldos por cuenta, sin documentos ficticios', async () => {
    const fetch = conComercios(vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({ success: true, data: {
        cuentas: [
          { id_cuenta_contable: 1, codigo: '1.1.04', nombre: 'Fondos por cobrar a plataforma', tipo: 'Por cobrar', saldo: '-10.25' },
          { id_cuenta_contable: 2, codigo: '2.1.01', nombre: 'Proveedores', tipo: 'Por pagar', saldo: '60.00' },
        ],
        resumen: { por_cobrar: '-10.25', por_pagar: '60.00' },
      } }),
    }));
    vi.stubGlobal('fetch', fetch);
    render(<CuentasPorCobrarPagarView />);
    expect(screen.getByRole('status').textContent).toContain('Cargando');
    expect(await screen.findByText('Fondos por cobrar a plataforma')).toBeTruthy();
    expect(screen.queryByLabelText('Comercio')).toBeNull();
    expect(fetch).toHaveBeenCalledWith('/api/m7/v1/saldos-cobrar-pagar', expect.objectContaining({ credentials: 'include' }));
    expect(screen.queryByRole('columnheader', { name: /vencimiento|abonado|tercero/i })).toBeNull();
    fireEvent.change(screen.getByLabelText('Tipo'), { target: { value: 'Por pagar' } });
    expect(screen.queryByText('Fondos por cobrar a plataforma')).toBeNull();
    expect(screen.getByText('Proveedores')).toBeTruthy();
  });

  it('muestra la rentabilidad por centro, el grupo sin centro y filtra el estado', async () => {
    vi.stubGlobal('fetch', conComercios(vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({ success: true, data: {
        id_comercio: 1, fecha_desde: null, fecha_hasta: null, hay_movimientos: true,
        centros: [
          { id_centro_costo: 1, codigo: 'LOG', nombre: 'Logística', activo: true, ingresos: '500.00', costos: '100.20', utilidad: '399.80', margen: '79.96' },
          { id_centro_costo: 2, codigo: 'SIN', nombre: 'Sin movimientos', activo: false, ingresos: '0.00', costos: '0.00', utilidad: '0.00', margen: null },
        ],
        sin_centro: { ingresos: '50.00', costos: '300.00', utilidad: '-250.00', margen: '-500.00' },
        resumen: { cantidad: 2, activos: 1, ingresos: '550.00', costos: '400.20', utilidad: '149.80', margen: '27.24' },
      } }),
    })));
    render(<CentrosCostoView />);
    const fila = (await screen.findByText('Logística')).closest('tr');
    expect(within(fila).getByText(/79,96/)).toBeTruthy();
    expect(within(screen.getByText('Sin movimientos').closest('tr')).getByText('—')).toBeTruthy();
    const sinCentro = screen.getByText('Sin centro de costo asignado').closest('tr');
    expect(within(sinCentro).getByText(/500,00 %/)).toBeTruthy();
    expect(within(screen.getByRole('region', { name: 'Resumen de rentabilidad' })).getByText(/27,24/)).toBeTruthy();
    expect(screen.getByText('Todo el historial')).toBeTruthy();
    expect(screen.queryByText('Sin movimientos en el período consultado.')).toBeNull();
    expect(screen.queryByRole('columnheader', { name: /presupuesto|responsable/i })).toBeNull();
    fireEvent.change(screen.getByLabelText('Estado'), { target: { value: 'Inactivo' } });
    expect(screen.queryByText('Logística')).toBeNull();
    expect(screen.getByText('Sin movimientos')).toBeTruthy();
  });

  it.each([CuentasPorCobrarPagarView, CentrosCostoView])('muestra el error de consulta sin simular saldos cero', async (Vista) => {
    vi.stubGlobal('fetch', conComercios(vi.fn().mockResolvedValue({
      ok: false,
      json: async () => ({ error: { mensaje: 'La consulta no está disponible.' } }),
    })));
    render(<Vista />);
    expect((await screen.findByRole('alert')).textContent).toBe('La consulta no está disponible.');
    expect(screen.queryByRole('table')).toBeNull();
  });

  it('plataforma elige el comercio por nombre y consulta el primero de la lista al cargar', async () => {
    const vacio = { ok: true, json: async () => ({ success: true, data: {
      hay_movimientos: false, centros: [], sin_centro: null,
      resumen: { cantidad: 0, activos: 0, ingresos: '0.00', costos: '0.00', utilidad: '0.00', margen: null },
    } }) };
    const fetch = conComercios(
      vi.fn((url) => Promise.resolve(url.includes('id_comercio=') ? vacio
        : { ok: false, status: 422, json: async () => ({ error: { mensaje: 'Debe indicar el comercio.' } }) })),
      { ok: true, json: async () => ({ success: true, data: { comercios: [
        { id_comercio: 5, nombre: 'Café Volcán', nombre_disponible: true },
        { id_comercio: 1, nombre: 'Soluciones del Milenio', nombre_disponible: true },
      ] } }) },
    );
    vi.stubGlobal('fetch', fetch);
    render(<CentrosCostoView />);
    expect(await screen.findByText('No hay centros para los filtros seleccionados.')).toBeTruthy();
    expect(fetch).toHaveBeenLastCalledWith('/api/m7/v1/centros-costo?id_comercio=5', expect.anything());
    const selector = screen.getByLabelText('Comercio');
    expect(within(selector).getByRole('option', { name: 'Soluciones del Milenio' })).toBeTruthy();
    fireEvent.change(selector, { target: { value: '1' } });
    fireEvent.click(screen.getByRole('button', { name: 'Actualizar consulta' }));
    await vi.waitFor(() => expect(fetch).toHaveBeenLastCalledWith('/api/m7/v1/centros-costo?id_comercio=1', expect.objectContaining({ credentials: 'include' })));
    expect(await screen.findByText('No hay centros para los filtros seleccionados.')).toBeTruthy();
    expect(screen.queryByRole('alert')).toBeNull();
  });

  it('usa el número de comercio si no se pueden cargar los nombres', async () => {
    const fetch = conComercios(
      vi.fn().mockResolvedValue({ ok: false, status: 422, json: async () => ({ error: { mensaje: 'Debe indicar el comercio.' } }) }),
      { ok: false, status: 500, json: async () => ({ message: 'Server Error' }) },
    );
    vi.stubGlobal('fetch', fetch);
    render(<CuentasPorCobrarPagarView />);
    fireEvent.change(await screen.findByLabelText('Número de comercio'), { target: { value: '3' } });
    fireEvent.click(screen.getByRole('button', { name: 'Actualizar consulta' }));
    await vi.waitFor(() => expect(fetch).toHaveBeenLastCalledWith('/api/m7/v1/saldos-cobrar-pagar?id_comercio=3', expect.anything()));
  });

  it('abre el libro mayor de la cuenta con el comercio de la consulta y conserva los filtros al cerrar', async () => {
    const fetch = conComercios(vi.fn()
      .mockResolvedValueOnce({ ok: true, json: async () => ({ success: true, data: {
        id_comercio: 2,
        cuentas: [{ id_cuenta_contable: 14, codigo: '1.1.04', nombre: 'Fondos', tipo: 'Por cobrar', saldo: '-10.25' }],
        resumen: { por_cobrar: '-10.25', por_pagar: '0.00' },
      } }) })
      .mockResolvedValueOnce({ ok: true, json: async () => ({ success: true, data: {
        total_debe: '40.50', total_haber: '50.75', saldo_final: '-10.25',
        movimientos: [{ id_asiento: 5, numero_linea: 1, fecha: '2026-11-14 12:00:00', tipo: 'REEMBOLSO',
          descripcion: 'Reembolso del subpedido', debe: '0.00', haber: '50.75', saldo: '-10.25' }],
      } }) }));
    vi.stubGlobal('fetch', fetch);
    render(<CuentasPorCobrarPagarView />);
    await screen.findByText('Fondos');
    fireEvent.change(screen.getByLabelText('Buscar cuenta'), { target: { value: 'Fondos' } });
    fireEvent.click(screen.getByRole('button', { name: 'Ver movimientos de 1.1.04' }));
    const dialogo = within(screen.getByRole('dialog'));
    expect(await dialogo.findByText('Reembolso del subpedido')).toBeTruthy();
    expect(dialogo.getByText('ASI-0005')).toBeTruthy();
    expect(fetch).toHaveBeenLastCalledWith('/api/m7/v1/libro-mayor?id_cuenta_contable=14&id_comercio=2', expect.objectContaining({ credentials: 'include' }));
    fireEvent.keyDown(screen.getByRole('dialog'), { key: 'Escape' });
    expect(screen.queryByRole('dialog')).toBeNull();
    expect(screen.getByLabelText('Buscar cuenta').value).toBe('Fondos');
  });

  it('abre el asiento completo desde el detalle y Escape solo cierra el asiento', async () => {
    const fetch = conComercios(vi.fn()
      .mockResolvedValueOnce({ ok: true, json: async () => ({ success: true, data: {
        id_comercio: 2,
        cuentas: [{ id_cuenta_contable: 14, codigo: '1.1.02', nombre: 'Cuentas por cobrar', tipo: 'Por cobrar', saldo: '10.00' }],
        resumen: { por_cobrar: '10.00', por_pagar: '0.00' },
      } }) })
      .mockResolvedValueOnce({ ok: true, json: async () => ({ success: true, data: {
        total_debe: '10.00', total_haber: '0.00', saldo_final: '10.00',
        movimientos: [{ id_asiento: 5, numero_linea: 1, fecha: '2026-11-14 12:00:00', tipo: 'VENTA',
          descripcion: 'Venta del subpedido', debe: '10.00', haber: '0.00', saldo: '10.00' }],
      } }) })
      .mockResolvedValueOnce({ ok: true, json: async () => ({ success: true, data: {
        id_asiento: 5, id_comercio: 2, fecha: '2026-11-14 12:00:00', tipo: 'VENTA', tipo_evento: 'PedidoConfirmado',
        descripcion: 'Venta del pedido 9', estado: 'REGISTRADO', id_pedido: 9, id_subpedido: 12, id_pago: null, id_asiento_original: null,
        total_debe: '10.00', total_haber: '10.00', cuadrado: true,
        lineas: [
          { numero_linea: 1, codigo_cuenta: '1.1.02', nombre_cuenta: 'Cuentas por cobrar', tipo_cuenta: 'ACTIVO', centro: null, descripcion: null, debe: '10.00', haber: '0.00' },
          { numero_linea: 2, codigo_cuenta: '4.1.01', nombre_cuenta: 'Ventas', tipo_cuenta: 'INGRESO', centro: { codigo: 'GEN', nombre: 'General' }, descripcion: null, debe: '0.00', haber: '10.00' },
        ],
      } }) }));
    vi.stubGlobal('fetch', fetch);
    render(<CuentasPorCobrarPagarView />);
    fireEvent.click(await screen.findByRole('button', { name: 'Ver movimientos de 1.1.02' }));
    fireEvent.click(await screen.findByRole('button', { name: 'Ver asiento ASI-0005' }));
    const asiento = within(screen.getByRole('dialog', { name: 'Asiento ASI-0005' }));
    expect(await asiento.findByText('4.1.01 · Ventas')).toBeTruthy();
    expect(asiento.getByText('GEN · General')).toBeTruthy();
    expect(asiento.getByText('Pedido #9 · Subpedido #12')).toBeTruthy();
    expect(asiento.getByText(/Asiento cuadrado/)).toBeTruthy();
    expect(fetch).toHaveBeenLastCalledWith('/api/m7/v1/asientos/5?id_comercio=2', expect.objectContaining({ credentials: 'include' }));
    fireEvent.keyDown(screen.getByRole('dialog', { name: 'Asiento ASI-0005' }), { key: 'Escape' });
    expect(screen.queryByRole('dialog', { name: 'Asiento ASI-0005' })).toBeNull();
    expect(screen.getByRole('dialog', { name: /^Movimientos de/ })).toBeTruthy();
  });

  it('abre el detalle del centro, muestra sus referencias y permite reintentar errores del detalle', async () => {
    const fetch = conComercios(vi.fn()
      .mockResolvedValueOnce({ ok: true, json: async () => ({ success: true, data: {
        id_comercio: 1, hay_movimientos: true, sin_centro: null,
        centros: [{ id_centro_costo: 3, codigo: 'LOG', nombre: 'Logística', activo: true, ingresos: '0.00', costos: '120.30', utilidad: '-120.30', margen: null }],
        resumen: { cantidad: 1, activos: 1, ingresos: '0.00', costos: '120.30', utilidad: '-120.30', margen: null },
      } }) })
      .mockResolvedValueOnce({ ok: false, json: async () => ({ error: { mensaje: 'No se pudo cargar el detalle.' } }) })
      .mockResolvedValueOnce({ ok: true, json: async () => ({ success: true, data: {
        ingresos: '0.00', costos: '120.30', utilidad: '-120.30',
        movimientos: [{ id_asiento: 8, numero_linea: 1, fecha: '2026-11-14 12:00:00', tipo: 'COSTO_VENTA',
          codigo_cuenta: '5.1.01', nombre_cuenta: 'Costo de ventas', descripcion: 'Costo del pedido',
          id_pedido: 10, id_subpedido: 11, id_pago: null, debe: '120.30', haber: '0.00', saldo: '120.30' }],
      } }) }));
    vi.stubGlobal('fetch', fetch);
    render(<CentrosCostoView />);
    fireEvent.click(await screen.findByRole('button', { name: 'Ver movimientos de LOG' }));
    const dialogo = within(screen.getByRole('dialog'));
    expect((await dialogo.findByRole('alert')).textContent).toBe('No se pudo cargar el detalle.');
    expect(dialogo.queryByRole('table')).toBeNull();
    fireEvent.click(dialogo.getByRole('button', { name: 'Reintentar detalle' }));
    expect(await dialogo.findByText('5.1.01 · Costo de ventas')).toBeTruthy();
    expect(dialogo.getByText('Pedido #10 · Subpedido #11')).toBeTruthy();
    expect(fetch).toHaveBeenLastCalledWith('/api/m7/v1/centros-costo/3/movimientos?id_comercio=1', expect.objectContaining({ credentials: 'include' }));
  });

  it('consulta el período y abre el grupo sin centro con las mismas fechas', async () => {
    const vacio = { ingresos: '0.00', costos: '0.00', utilidad: '0.00', margen: null };
    const fetch = conComercios(vi.fn()
      .mockResolvedValueOnce({ ok: true, json: async () => ({ success: true, data: {
        id_comercio: 1, hay_movimientos: true, centros: [], sin_centro: null, resumen: { cantidad: 0, activos: 0, ...vacio },
      } }) })
      .mockResolvedValueOnce({ ok: true, json: async () => ({ success: true, data: {
        id_comercio: 1, fecha_desde: '2026-11-01', fecha_hasta: '2026-11-30', hay_movimientos: true, centros: [],
        sin_centro: { ingresos: '50.00', costos: '0.00', utilidad: '50.00', margen: '100.00' },
        resumen: { cantidad: 0, activos: 0, ingresos: '50.00', costos: '0.00', utilidad: '50.00', margen: '100.00' },
      } }) })
      .mockResolvedValueOnce({ ok: true, json: async () => ({ success: true, data: {
        centro: null, ingresos: '50.00', costos: '0.00', utilidad: '50.00',
        movimientos: [{ id_asiento: 9, numero_linea: 2, fecha: '2026-11-14 12:00:00', tipo: 'VENTA',
          codigo_cuenta: '4.1.01', nombre_cuenta: 'Ventas', descripcion: 'Venta anterior', id_pedido: 10,
          id_subpedido: null, id_pago: null, debe: '0.00', haber: '50.00', saldo: '50.00' }],
      } }) }));
    vi.stubGlobal('fetch', fetch);
    render(<CentrosCostoView />);
    await screen.findByText('Todo el historial');
    fireEvent.change(screen.getByLabelText('Desde'), { target: { value: '2026-11-01' } });
    fireEvent.change(screen.getByLabelText('Hasta'), { target: { value: '2026-11-30' } });
    fireEvent.click(screen.getByRole('button', { name: 'Actualizar consulta' }));
    expect(await screen.findByText('Del 2026-11-01 al 2026-11-30')).toBeTruthy();
    expect(fetch).toHaveBeenLastCalledWith('/api/m7/v1/centros-costo?fecha_desde=2026-11-01&fecha_hasta=2026-11-30', expect.anything());
    fireEvent.click(screen.getByRole('button', { name: 'Ver movimientos sin centro asignado' }));
    const dialogo = within(screen.getByRole('dialog'));
    expect(await dialogo.findByText('Venta anterior')).toBeTruthy();
    expect(dialogo.getByRole('columnheader', { name: 'Utilidad acumulada' })).toBeTruthy();
    expect(fetch).toHaveBeenLastCalledWith('/api/m7/v1/centros-costo/sin-asignar/movimientos?id_comercio=1&fecha_desde=2026-11-01&fecha_hasta=2026-11-30', expect.anything());
  });

  it('limpia las fechas y vuelve a consultar todo el historial', async () => {
    const fetch = vi.fn().mockResolvedValue({ ok: true, json: async () => ({ success: true, data: {
      id_comercio: 1, fecha_desde: null, fecha_hasta: null, hay_movimientos: true, centros: [], sin_centro: null,
      resumen: { cantidad: 0, activos: 0, ingresos: '0.00', costos: '0.00', utilidad: '0.00', margen: null },
    } }) });
    vi.stubGlobal('fetch', fetch);
    render(<CentrosCostoView />);
    await screen.findByText('Todo el historial');
    const limpiar = screen.getByRole('button', { name: 'Limpiar filtros' });
    expect(limpiar.disabled).toBe(true);
    fireEvent.change(screen.getByLabelText('Desde'), { target: { value: '2026-11-01' } });
    fireEvent.click(screen.getByRole('button', { name: 'Actualizar consulta' }));
    await waitFor(() => expect(fetch).toHaveBeenLastCalledWith('/api/m7/v1/centros-costo?fecha_desde=2026-11-01', expect.anything()));
    await screen.findByText('Todo el historial');

    fireEvent.click(screen.getByRole('button', { name: 'Limpiar filtros' }));

    expect(screen.getByLabelText('Desde').value).toBe('');
    await waitFor(() => expect(fetch).toHaveBeenLastCalledWith('/api/m7/v1/centros-costo', expect.anything()));
    expect(await screen.findByRole('button', { name: 'Limpiar filtros' })).toHaveProperty('disabled', true);
  });

  it('avisa cuando no hay movimientos en el período consultado', async () => {
    vi.stubGlobal('fetch', conComercios(vi.fn().mockResolvedValue({ ok: true, json: async () => ({ success: true, data: {
      id_comercio: 1, fecha_desde: '2026-01-01', fecha_hasta: '2026-01-31', hay_movimientos: false, sin_centro: null,
      centros: [{ id_centro_costo: 1, codigo: 'GEN', nombre: 'General', activo: true, ingresos: '0.00', costos: '0.00', utilidad: '0.00', margen: null }],
      resumen: { cantidad: 1, activos: 1, ingresos: '0.00', costos: '0.00', utilidad: '0.00', margen: null },
    } }) })));
    render(<CentrosCostoView />);
    expect(await screen.findByText('Sin movimientos en el período consultado.')).toBeTruthy();
    expect(screen.queryByText('Sin centro de costo asignado')).toBeNull();
  });
});
