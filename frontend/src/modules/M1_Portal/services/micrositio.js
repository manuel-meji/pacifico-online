import { comercio, productos, categorias, marcas } from './datosMockComercio';

export const colones = (n) => '₡' + Math.round(n).toLocaleString('es-CR', { maximumFractionDigits: 0 });

export const ORDENES = [
  { valor: 'predeterminado', etiqueta: 'Predeterminado' },
  { valor: 'precio_asc', etiqueta: 'Precio: de más bajo a más alto' },
  { valor: 'precio_desc', etiqueta: 'Precio: de más alto a más bajo' },
  { valor: 'nombre', etiqueta: 'Nombre: A a Z' },
];

export const POR_PAGINA = [12, 24, 36];

// Rangos de precio de la barra lateral (los calcula M1 sobre los precios del comercio).
export const RANGOS_PRECIO = [
  { id: 'r1', etiqueta: 'Menos de ₡10 000', min: 0, max: 9999 },
  { id: 'r2', etiqueta: '₡10 000 - ₡25 000', min: 10000, max: 25000 },
  { id: 'r3', etiqueta: '₡25 000 - ₡50 000', min: 25001, max: 50000 },
  { id: 'r4', etiqueta: 'Más de ₡50 000', min: 50001, max: Infinity },
];

export class ComercioNoDisponible extends Error {}

// Doble de PortalServiceInterface::obtenerMicrositio(idComercio, pagina, porPagina) con los filtros de la búsqueda
// (id_comercio fijo). Simula las respuestas de M2, M3 y M4. Se reemplaza por fetch a /m1/comercios/{id}/micrositio
// cuando el backend esté listo.
//   simular = 'suspendido'    → M2 responde 404 (ERR-004): el comercio no está Activo (RN-M1-01).
//   simular = 'vacio'         → el comercio no tiene productos publicados.
//   simular = 'sininventario' → M4 no responde en 3 s: se muestran los productos sin disponibilidad (RNF-M1-05).
export async function obtenerMicrositio(idComercio, { pagina = 1, porPagina = 12, orden = 'predeterminado', filtros = {} }, simular = null) {
  await new Promise((r) => setTimeout(r, 350));
  if (simular === 'suspendido') {
    throw new ComercioNoDisponible('ERR-004');
  }

  const base = simular === 'vacio' ? [] : productos;
  let lista = base.filter((x) => {
    if (filtros.categorias?.length && !filtros.categorias.includes(x.idCategoria)) return false;
    if (filtros.marcas?.length && !filtros.marcas.includes(x.marca)) return false;
    if (filtros.rango) {
      const r = RANGOS_PRECIO.find((y) => y.id === filtros.rango);
      if (r && (x.precio < r.min || x.precio > r.max)) return false;
    }
    if (filtros.precioMin != null && x.precio < filtros.precioMin) return false;
    if (filtros.precioMax != null && x.precio > filtros.precioMax) return false;
    if (filtros.soloDisponibles && x.disponibilidad === 'Agotado') return false;
    return true;
  });

  lista = [...lista];
  if (orden === 'precio_asc') lista.sort((a, b) => a.precio - b.precio);
  if (orden === 'precio_desc') lista.sort((a, b) => b.precio - a.precio);
  if (orden === 'nombre') lista.sort((a, b) => a.nombre.localeCompare(b.nombre, 'es'));

  const totalResultados = lista.length;
  const desde = (pagina - 1) * porPagina;
  let pag = lista.slice(desde, desde + porPagina);
  if (simular === 'sininventario') pag = pag.map((x) => ({ ...x, disponibilidad: null }));

  // Conteo por categoría y marca sobre todos los productos del comercio, para la barra lateral.
  const conteoCategorias = Object.fromEntries(categorias.map((c) => [c.idCategoria, base.filter((x) => x.idCategoria === c.idCategoria).length]));
  const conteoMarcas = Object.fromEntries(marcas.map((m) => [m, base.filter((x) => x.marca === m).length]));

  return {
    comercio: { ...comercio, id_comercio: idComercio },
    productos: { productos: pag, totalResultados, pagina, porPagina },
    categorias: categorias.filter((c) => conteoCategorias[c.idCategoria] > 0),
    marcas: marcas.filter((m) => conteoMarcas[m] > 0),
    conteoCategorias,
    conteoMarcas,
    avisos: simular === 'sininventario' ? ['No pudimos confirmar la disponibilidad en este momento. Se mostrará al abrir cada producto.'] : [],
  };
}
