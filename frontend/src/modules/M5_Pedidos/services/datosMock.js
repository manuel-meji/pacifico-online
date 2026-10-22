// DATOS DE EJEMPLO para el prototipo (no provienen de M3, M2 ni M4).
// Estructura inspirada en los campos del contrato de M5 y en el doble de M3 del repositorio
// (id_variacion, nombre, precio, tarifa_impuesto, id_comercio).
export const carritoInicial = {
  items: [
    { id_item_carrito: 1, id_variacion: 1, nombre: 'Producto de prueba A', variacion: 'Variación 1',
      precio_unitario: 15000, cantidad: 2, stock: 5, tarifa_impuesto: 0.13, id_comercio: 1 },
    { id_item_carrito: 2, id_variacion: 2, nombre: 'Producto de prueba B', variacion: 'Variación 1',
      precio_unitario: 8000, cantidad: 1, stock: 3, tarifa_impuesto: 0.13, id_comercio: 1 },
    { id_item_carrito: 3, id_variacion: 3, nombre: 'Producto de prueba C', variacion: 'Variación 2',
      precio_unitario: 22000, cantidad: 1, stock: 2, tarifa_impuesto: 0.13, id_comercio: 2 },
  ],
};

// Costo de envío por comercio (decisión 3.2 del Comité); montos de ejemplo.
export const comercios = {
  1: { id_comercio: 1, nombre: 'Comercio 1', costo_envio: 2500 },
  2: { id_comercio: 2, nombre: 'Comercio 2', costo_envio: 3000 },
};

// Medios de pago de la pasarela simulada de M6 (Tarjeta, SINPE y PayPal).
export const metodosPago = ['Tarjeta', 'SINPE', 'PayPal'];
