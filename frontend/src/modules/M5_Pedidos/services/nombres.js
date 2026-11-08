// Nombres para mostrar en pedidos y subpedidos. Solo se usan si la respuesta de M5 los trae;
// si no vienen, se deja el identificador. No se consulta a otros módulos.

export function nombreComercio(subpedido) {
  const nombre = subpedido?.nombre_comercio ?? subpedido?.comercio?.nombre ?? subpedido?.comercio?.nombre_comercio;
  return nombre || `Comercio #${subpedido?.id_comercio}`;
}

export function nombreVariacion(linea) {
  const nombre = linea?.nombre ?? linea?.nombre_producto ?? linea?.producto?.nombre ?? linea?.variacion?.nombre;
  return nombre || `Variación #${linea?.id_variacion}`;
}
