import FichaComercioView from './views/FichaComercioView';

// Punto de entrada del módulo M1 para el prototipo.
// Por ahora muestra la ficha de comercio; las demás pantallas (portal principal, ficha de producto, inicio de sesión
// y crear cuenta) se agregan aquí cuando cada integrante suba la suya.
// `simular` fuerza las alternativas del CU-009: 'suspendido', 'vacio' o 'sininventario'.
export default function M1App({ simular = null }) {
  return <FichaComercioView idComercio={7} simular={simular} />;
}
