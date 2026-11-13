// @vitest-environment jsdom
import { describe, it, expect, vi, afterEach } from 'vitest';
import { render, screen, fireEvent, cleanup, waitFor } from '@testing-library/react';
import EncabezadoPortal, { textoInsignia } from '../components/EncabezadoPortal';
import { CategoriaConSubcategorias } from '../components/FiltrosComercio';
import { CampoConIcono } from '../components/componentes';
import CuponesPortada, { textoValorCupon } from '../components/CuponesPortada';
import { obtenerCantidadCarrito } from '../services/carritoApi';

afterEach(() => {
  cleanup();
  vi.restoreAllMocks();
});

describe('Insignia del carrito', () => {
  it('muestra el número hasta 30 y +30 desde 31', () => {
    expect(textoInsignia(1)).toBe('1');
    expect(textoInsignia(30)).toBe('30');
    expect(textoInsignia(31)).toBe('+30');
    expect(textoInsignia(120)).toBe('+30');
  });

  it('no se muestra con 0 ni sin dato', () => {
    const { rerender } = render(<EncabezadoPortal cantidadCarrito={0} />);
    expect(screen.getByRole('link', { name: 'Carrito' })).toBeTruthy();
    rerender(<EncabezadoPortal cantidadCarrito={null} />);
    expect(screen.getByRole('link', { name: 'Carrito' })).toBeTruthy();
  });

  it('con más de 30 líneas anuncia "más de 30"', () => {
    render(<EncabezadoPortal cantidadCarrito={45} />);
    const enlace = screen.getByRole('link', { name: 'Carrito, más de 30 productos' });
    expect(enlace.textContent).toContain('+30');
  });

  it('cuenta líneas del carrito de M5, no unidades', async () => {
    vi.spyOn(globalThis, 'fetch').mockResolvedValue({
      ok: true,
      json: async () => ({ items: [{ cantidad: 3 }, { cantidad: 1 }, { cantidad: 0 }] }),
    });
    await expect(obtenerCantidadCarrito()).resolves.toEqual({ ok: true, cantidad: 2 });
  });
});

describe('Buscador del encabezado', () => {
  it('busca productos por defecto y comercios al elegir la opción', () => {
    const onBuscar = vi.fn();
    render(<EncabezadoPortal onBuscar={onBuscar} />);
    const campo = screen.getByLabelText('Buscar productos');
    fireEvent.change(campo, { target: { value: ' ceiba ' } });
    fireEvent.submit(campo.closest('form'));
    expect(onBuscar).toHaveBeenLastCalledWith('ceiba', 'productos');

    fireEvent.change(screen.getByLabelText('Buscar en'), { target: { value: 'comercios' } });
    expect(screen.getByPlaceholderText('Buscar comercios...')).toBeTruthy();
    fireEvent.submit(campo.closest('form'));
    expect(onBuscar).toHaveBeenLastCalledWith('ceiba', 'comercios');
  });
});

describe('Subcategorías plegables', () => {
  const categoria = {
    id_categoria: 1,
    nombre: 'Ropa',
    subcategorias: [{ id_categoria: 11, nombre: 'Camisas' }, { id_categoria: 12, nombre: 'Sombreros' }],
  };

  it('empiezan plegadas y se despliegan con la flecha', () => {
    render(<CategoriaConSubcategorias categoria={categoria} idMarcada={null} onElegir={() => {}} />);
    expect(screen.queryByText('Camisas')).toBeNull();
    fireEvent.click(screen.getByRole('button', { name: 'Desplegar subcategorías de Ropa' }));
    expect(screen.getByText('Camisas')).toBeTruthy();
    fireEvent.click(screen.getByRole('button', { name: 'Plegar subcategorías de Ropa' }));
    expect(screen.queryByText('Camisas')).toBeNull();
  });

  it('quedan desplegadas si la elegida es una subcategoría', () => {
    render(<CategoriaConSubcategorias categoria={categoria} idMarcada={12} onElegir={() => {}} />);
    expect(screen.getByText('Sombreros')).toBeTruthy();
  });
});

describe('Campo de contraseña', () => {
  it('el ojo muestra y oculta lo escrito', () => {
    render(<CampoConIcono id="contrasena" etiqueta="Contraseña" icono="candado" type="password" value="secreta123" onChange={() => {}} />);
    const campo = screen.getByLabelText('Contraseña');
    expect(campo.getAttribute('type')).toBe('password');
    fireEvent.click(screen.getByRole('button', { name: 'Mostrar contraseña' }));
    expect(campo.getAttribute('type')).toBe('text');
    fireEvent.click(screen.getByRole('button', { name: 'Ocultar contraseña' }));
    expect(campo.getAttribute('type')).toBe('password');
  });

  it('los demás campos no llevan ojo', () => {
    render(<CampoConIcono id="nombre" etiqueta="Nombre" icono="persona" value="" onChange={() => {}} />);
    expect(screen.queryByRole('button')).toBeNull();
  });
});

describe('Cupones de la portada', () => {
  const cupones = [
    { codigo: 'PACIFICO10', descripcion: '10 % en toda la compra', tipo_descuento: 'porcentaje', valor: 10, monto_minimo_compra: null, fecha_fin: '2026-11-30T23:59:59Z', limite_usos_por_cliente: 1 },
    { codigo: 'ENVIO3000', descripcion: null, tipo_descuento: 'monto_fijo', valor: 3000, monto_minimo_compra: 15000, fecha_fin: '2026-11-20T23:59:59Z', limite_usos_por_cliente: null },
  ];

  it('muestra el valor, el mínimo y el código de cada cupón', () => {
    render(<CuponesPortada cupones={cupones} />);
    expect(textoValorCupon(cupones[0])).toBe('10 %');
    expect(screen.getByText('PACIFICO10')).toBeTruthy();
    expect(screen.getByText('ENVIO3000')).toBeTruthy();
    expect(screen.getByText(/Compra mínima de/)).toBeTruthy();
    expect(screen.getByText('Un uso por cliente')).toBeTruthy();
  });

  it('copia el código al portapapeles', async () => {
    const writeText = vi.fn().mockResolvedValue();
    Object.defineProperty(navigator, 'clipboard', { value: { writeText }, configurable: true });
    render(<CuponesPortada cupones={cupones} />);
    fireEvent.click(screen.getByRole('button', { name: 'Copiar el código PACIFICO10' }));
    expect(writeText).toHaveBeenCalledWith('PACIFICO10');
    await waitFor(() => expect(screen.getByText('Copiado')).toBeTruthy());
  });
});
