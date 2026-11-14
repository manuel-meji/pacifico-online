// Almacén local para el carrito de usuarios invitados (sin sesión iniciada).
// Evita escribir en el carrito del cliente 1 en la BD compartida y aisla cada navegador.
const CLAVE_CARRITO_INVITADO = 'm5_carrito_invitado';

export function leerItemsInvitado() {
  if (typeof window === 'undefined' || !window.localStorage) return [];
  try {
    const bruto = window.localStorage.getItem(CLAVE_CARRITO_INVITADO);
    if (!bruto) return [];
    const lista = JSON.parse(bruto);
    if (!Array.isArray(lista)) return [];
    return lista
      .map((item) => ({
        id_variacion: Number(item?.id_variacion || 0),
        cantidad: Number(item?.cantidad || 0),
      }))
      .filter((item) => Number.isInteger(item.id_variacion) && item.id_variacion > 0 && Number.isInteger(item.cantidad) && item.cantidad > 0);
  } catch {
    return [];
  }
}

export function guardarItemsInvitado(items) {
  if (typeof window === 'undefined' || !window.localStorage) return;
  const limpios = (Array.isArray(items) ? items : [])
    .map((item) => ({
      id_variacion: Number(item?.id_variacion || 0),
      cantidad: Number(item?.cantidad || 0),
    }))
    .filter((item) => item.id_variacion > 0 && item.cantidad > 0);

  if (limpios.length === 0) {
    window.localStorage.removeItem(CLAVE_CARRITO_INVITADO);
    return;
  }

  window.localStorage.setItem(CLAVE_CARRITO_INVITADO, JSON.stringify(limpios));
}

export function limpiarCarritoInvitado() {
  if (typeof window === 'undefined' || !window.localStorage) return;
  window.localStorage.removeItem(CLAVE_CARRITO_INVITADO);
}

export function calcularItemsConAgregado(itemsActuales, idVariacion, cantidad) {
  const idVar = Number(idVariacion);
  const cant = Number(cantidad);
  const copia = itemsActuales.map((item) => ({ ...item }));
  const existente = copia.find((item) => item.id_variacion === idVar);

  if (existente) {
    existente.cantidad += cant;
    return { items: copia, cantidadFinal: existente.cantidad };
  }

  copia.push({ id_variacion: idVar, cantidad: cant });
  return { items: copia, cantidadFinal: cant };
}

export function calcularItemsConCantidad(itemsActuales, idVariacion, cantidad) {
  const idVar = Number(idVariacion);
  const cant = Number(cantidad);
  return itemsActuales.map((item) =>
    item.id_variacion === idVar ? { ...item, cantidad: cant } : { ...item }
  );
}

export function calcularItemsSinVariacion(itemsActuales, idVariacion) {
  const idVar = Number(idVariacion);
  return itemsActuales.filter((item) => item.id_variacion !== idVar);
}
