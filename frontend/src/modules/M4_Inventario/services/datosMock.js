// Datos de prueba del prototipo. Los identificadores y nombres son los de tests/Doubles/M4/DatosPruebaM4.php (sección 9):
// comercio 104 (Activo), sedes 12 y 13, producto 301 con las variaciones 501 y 502, pedidos 9001/9002 con sus
// subpedidos 9101/9102, las reservas RESERVA_PAGADA y RESERVA_PENDIENTE, y autorizacion-0001.
// Regla de coherencia: cada existencia es el último saldo de su Kardex y su cantidad_reservada es la suma de las
// reservas ACTIVA o CONFIRMADA de esa existencia. Las existencias muestran las tres insignias: Disponible,
// Bajo mínimo y Agotado.
export const ubicaciones = [
  { id_ubicacion: 12, nombre: 'Bodega central' },
  { id_ubicacion: 13, nombre: 'Tienda Liberia centro' },
];

export const variaciones = [
  { id_variacion: 501, sku: 'CAM-PAC-AZ-M', nombre: 'Camiseta algodón Pacífico', atributos: 'Azul, talla M' },
  { id_variacion: 502, sku: 'CAM-PAC-AZ-L', nombre: 'Camiseta algodón Pacífico', atributos: 'Azul, talla L' },
];

// Cantidades enteras; costo_promedio con 4 decimales como numeric(14,4) de la sección 6.5.
export const existenciasIniciales = [
  { id_existencia: 1, id_comercio: 104, id_producto: 301, id_variacion: 501, id_ubicacion: 12, cantidad_fisica: 40, cantidad_reservada: 2, cantidad_defectuosa: 2, costo_promedio: 8500, stock_minimo: 10 },
  { id_existencia: 2, id_comercio: 104, id_producto: 301, id_variacion: 501, id_ubicacion: 13, cantidad_fisica: 14, cantidad_reservada: 0, cantidad_defectuosa: 0, costo_promedio: 8500, stock_minimo: 10 },
  { id_existencia: 3, id_comercio: 104, id_producto: 301, id_variacion: 502, id_ubicacion: 12, cantidad_fisica: 6, cantidad_reservada: 1, cantidad_defectuosa: 0, costo_promedio: 9000, stock_minimo: 8 },
  { id_existencia: 4, id_comercio: 104, id_producto: 301, id_variacion: 502, id_ubicacion: 13, cantidad_fisica: 0, cantidad_reservada: 0, cantidad_defectuosa: 0, costo_promedio: 9000, stock_minimo: 5 },
];

// Usuarios del núcleo que aparecen en el Kardex. El contrato entrega id_usuario e id_autorizador; la vista
// muestra el nombre. Mismos ids que NucleoDobleM4 (usuario 1) y DatosPruebaM4 (autorizador 2), sección 9.
// El usuario 0 representa las llamadas de M5 por la interfaz (no hay persona en la sesión).
export const usuarios = { 0: 'M5 · Carrito y Pedidos', 1: 'Ana Rojas', 2: 'Carlos Mora' };

// Autorizaciones emitidas por el Administrador del comercio, con la misma forma y los mismos datos que
// DatosPruebaM4::autorizaciones() (sección 9). Se consumen al usarse (RN-M4-012): ver inventarioApi.js.
export const autorizaciones = {
  'autorizacion-0001': { operacion: 'transferencia', id_comercio: 104, id_variacion: 501, ubicaciones: [12, 13], cantidad: 5,
    id_autorizador: 2, fecha_autorizacion: '2026-10-19T14:00:00Z', vence: '2099-12-31T23:59:59Z' },
  'autorizacion-vencida': { operacion: 'transferencia', id_comercio: 104, id_variacion: 501, ubicaciones: [12, 13], cantidad: 5,
    id_autorizador: 2, fecha_autorizacion: '2026-01-10T09:00:00Z', vence: '2026-01-10T10:00:00Z' },
};

// Pedidos y subpedidos de M5 que usa el doble (DatosPruebaM4::pedidos() y ::subpedidos()).
export const pedidos = [
  { id_pedido: 9001, id_subpedido: 9101, estado_pedido: 'pagado', etiqueta: 'Pedido 9001 · pagado' },
  { id_pedido: 9002, id_subpedido: 9102, estado_pedido: 'pendiente', etiqueta: 'Pedido 9002 · pago pendiente' },
];

export const RESERVA_PAGADA = '3f2b8a52-6c1e-4d0b-9a3e-1f6f0d9b7a10';
export const RESERVA_PENDIENTE = '7c1d9e40-2b7a-4f3e-8d61-5a0c2e9b4f21';

// Esquema Reserva del contrato v1.2.0. Ambas están CONFIRMADA (sin vencimiento automático), como en DatosPruebaM4.
export const reservasIniciales = [
  { id_reserva: RESERVA_PAGADA, id_comercio: 104, id_existencia: 1, id_variacion: 501, id_ubicacion: 12, cantidad: 2,
    estado_reserva: 'confirmada', id_pedido: 9001, id_subpedido: 9101, fecha_creacion: '2026-10-15T18:00:00-06:00',
    fecha_expiracion: '2026-10-15T18:15:00-06:00', fecha_resolucion: null, motivo_cierre: null },
  { id_reserva: RESERVA_PENDIENTE, id_comercio: 104, id_existencia: 3, id_variacion: 502, id_ubicacion: 12, cantidad: 1,
    estado_reserva: 'confirmada', id_pedido: 9002, id_subpedido: 9102, fecha_creacion: '2026-10-16T09:00:00-06:00',
    fecha_expiracion: '2026-10-16T09:15:00-06:00', fecha_resolucion: null, motivo_cierre: null },
];

// Kardex inicial (esquema LineaKardex). Montos con 2 decimales y costos con 4 (sección 6.5). Las líneas de
// reserva y confirmación no cambian el físico (efecto_stock sin_cambio_fisico) ni tienen costo.
const L = (d) => ({
  id_comercio: 104, id_producto: 301, metodo_valorizacion: 'promedio_ponderado', referencia_tipo: null, referencia_id: null,
  id_usuario: 1, id_autorizador: null, fecha_autorizacion: null, fecha_registro: d.fecha_movimiento, ...d, id_movimiento: d.id_kardex,
});
const reservaRef = (id) => ({ referencia_tipo: 'reserva', referencia_id: id, id_usuario: 0, costo_unitario: null, monto_total: null, efecto_stock: 'sin_cambio_fisico' });

export const kardexInicial = [
  L({ id_kardex: 1000, id_variacion: 501, id_ubicacion: 13, tipo_movimiento: 'entrada', efecto_stock: 'aumenta', cantidad: 4, costo_unitario: 8500, monto_total: 34000,
    saldo_cantidad: 4, saldo_reservado: 0, saldo_disponible: 4, saldo_valorizado: 34000, fecha_movimiento: '2026-10-10T08:00:00-06:00', justificacion: 'Compra a proveedor, factura 0041' }),
  L({ id_kardex: 1001, id_variacion: 501, id_ubicacion: 12, tipo_movimiento: 'entrada', efecto_stock: 'aumenta', cantidad: 60, costo_unitario: 8500, monto_total: 510000,
    saldo_cantidad: 60, saldo_reservado: 0, saldo_disponible: 60, saldo_valorizado: 510000, fecha_movimiento: '2026-10-12T09:15:00-06:00', justificacion: 'Compra a proveedor, factura 0045' }),
  L({ id_kardex: 1002, id_variacion: 502, id_ubicacion: 12, tipo_movimiento: 'entrada', efecto_stock: 'aumenta', cantidad: 6, costo_unitario: 9000, monto_total: 54000,
    saldo_cantidad: 6, saldo_reservado: 0, saldo_disponible: 6, saldo_valorizado: 54000, fecha_movimiento: '2026-10-13T10:00:00-06:00', justificacion: 'Compra a proveedor, factura 0046' }),
  L({ id_kardex: 1003, id_variacion: 501, id_ubicacion: 12, tipo_movimiento: 'salida', efecto_stock: 'disminuye', cantidad: 8, costo_unitario: 8500, monto_total: 68000,
    saldo_cantidad: 52, saldo_reservado: 0, saldo_disponible: 52, saldo_valorizado: 442000, fecha_movimiento: '2026-10-14T14:40:00-06:00', justificacion: 'Salida por muestras para evento' }),
  L({ id_kardex: 1004, id_variacion: 501, id_ubicacion: 12, tipo_movimiento: 'transferencia_salida', efecto_stock: 'disminuye', cantidad: 10, costo_unitario: 8500, monto_total: 85000,
    saldo_cantidad: 42, saldo_reservado: 0, saldo_disponible: 42, saldo_valorizado: 357000, fecha_movimiento: '2026-10-15T10:20:00-06:00', justificacion: 'Reposición de la tienda',
    referencia_tipo: 'transferencia', referencia_id: 'b9e1c3a4-5d6f-4a7b-8c9d-0e1f2a3b4c5d', id_autorizador: 2, fecha_autorizacion: '2026-10-15T10:00:00-06:00' }),
  L({ id_kardex: 1005, id_variacion: 501, id_ubicacion: 13, tipo_movimiento: 'transferencia_entrada', efecto_stock: 'aumenta', cantidad: 10, costo_unitario: 8500, monto_total: 85000,
    saldo_cantidad: 14, saldo_reservado: 0, saldo_disponible: 14, saldo_valorizado: 119000, fecha_movimiento: '2026-10-15T10:20:00-06:00', justificacion: 'Reposición de la tienda',
    referencia_tipo: 'transferencia', referencia_id: 'b9e1c3a4-5d6f-4a7b-8c9d-0e1f2a3b4c5d', id_autorizador: 2, fecha_autorizacion: '2026-10-15T10:00:00-06:00' }),
  L({ id_kardex: 1006, id_variacion: 501, id_ubicacion: 12, tipo_movimiento: 'reserva', cantidad: 2, ...reservaRef(RESERVA_PAGADA),
    saldo_cantidad: 42, saldo_reservado: 2, saldo_disponible: 40, saldo_valorizado: 357000, fecha_movimiento: '2026-10-15T18:00:00-06:00', justificacion: 'Reserva de M5 para el carrito del pedido 9001' }),
  L({ id_kardex: 1007, id_variacion: 502, id_ubicacion: 12, tipo_movimiento: 'reserva', cantidad: 1, ...reservaRef(RESERVA_PENDIENTE),
    saldo_cantidad: 6, saldo_reservado: 1, saldo_disponible: 5, saldo_valorizado: 54000, fecha_movimiento: '2026-10-16T09:00:00-06:00', justificacion: 'Reserva de M5 para el carrito del pedido 9002' }),
  L({ id_kardex: 1008, id_variacion: 501, id_ubicacion: 12, tipo_movimiento: 'ajuste', efecto_stock: 'disminuye', cantidad: 2, costo_unitario: 8500, monto_total: 17000,
    saldo_cantidad: 40, saldo_reservado: 2, saldo_disponible: 36, saldo_valorizado: 340000, fecha_movimiento: '2026-10-16T16:05:00-06:00',
    justificacion: 'Conteo físico: faltan 2 unidades; otras 2 quedan apartadas como defectuosas', id_autorizador: 2, fecha_autorizacion: '2026-10-16T16:00:00-06:00' }),
  L({ id_kardex: 1009, id_variacion: 501, id_ubicacion: 12, tipo_movimiento: 'confirmacion_reserva', cantidad: 2, ...reservaRef(RESERVA_PAGADA),
    saldo_cantidad: 40, saldo_reservado: 2, saldo_disponible: 36, saldo_valorizado: 340000, fecha_movimiento: '2026-10-17T08:10:00-06:00', justificacion: 'M5 confirmó la reserva: pedido 9001, subpedido 9101' }),
  L({ id_kardex: 1010, id_variacion: 502, id_ubicacion: 12, tipo_movimiento: 'confirmacion_reserva', cantidad: 1, ...reservaRef(RESERVA_PENDIENTE),
    saldo_cantidad: 6, saldo_reservado: 1, saldo_disponible: 5, saldo_valorizado: 54000, fecha_movimiento: '2026-10-17T08:30:00-06:00', justificacion: 'M5 confirmó la reserva: pedido 9002, subpedido 9102' }),
];
