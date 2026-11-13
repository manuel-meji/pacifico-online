// Consulta del carrito de M5 para la insignia del encabezado del portal.
// Usa la ruta pública del contrato de M5 (GET /api/m5/v1/carrito, operación obtenerCarrito, esquema Carrito);
// M1 solo cuenta las líneas de items[] (cada producto o variación distinta cuenta 1, sin importar su cantidad)
// y no modifica el carrito.
// Respuesta: { ok: true, cantidad } o { ok: false }. Cualquier fallo de M5 se trata como "sin dato":
// el encabezado oculta el número y el enlace al carrito sigue funcionando.
export async function obtenerCantidadCarrito() {
  try {
    const resp = await fetch('/api/m5/v1/carrito', {
      credentials: 'include',
      headers: { Accept: 'application/json' },
    });
    if (!resp.ok) return { ok: false };

    const carrito = await resp.json();
    const items = Array.isArray(carrito?.items) ? carrito.items : [];
    const cantidad = items.filter((item) => (Number(item?.cantidad) || 0) > 0).length;

    return { ok: true, cantidad };
  } catch {
    return { ok: false };
  }
}
