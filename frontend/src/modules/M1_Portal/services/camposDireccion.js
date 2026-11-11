import { PROVINCIAS, cantonesDe, conValorActual } from './divisionTerritorial';

// Campos de una dirección de envío (esquema DatosDireccionCliente del contrato de M1, versión 1.2.0) con los
// límites de la tabla direcciones_cliente. Los usan el registro (CrearCuentaView) y Mi cuenta (DireccionesSeccion).
//
// Provincia y cantón son listas desplegables (tipo 'seleccion') con la división territorial de Costa Rica; el
// cantón depende de la provincia elegida. `completo` ocupa las dos columnas del formulario en pantallas anchas.

// Mismo formato que direcciones_cliente.telefono_destinatario (chk_direcciones_cliente_telefono).
export const TELEFONO_VALIDO = /^[0-9 +-]+$/;

export const camposDireccion = [
  { id: 'alias_direccion', etiqueta: 'Nombre de la dirección (por ejemplo, Casa)', icono: 'ubicacion', max: 40, falta: 'Ingrese un nombre para la dirección.', autoComplete: 'off' },
  { id: 'nombre_destinatario', etiqueta: 'Persona que recibe', icono: 'persona', max: 150, falta: 'Ingrese quién recibe el pedido.', autoComplete: 'name' },
  { id: 'telefono_destinatario', etiqueta: 'Teléfono de quien recibe', icono: 'telefono', max: 20, falta: 'Ingrese el teléfono de quien recibe.', type: 'tel', autoComplete: 'tel' },
  {
    id: 'provincia', etiqueta: 'Provincia', icono: 'ubicacion', max: 40, falta: 'Elija la provincia.', autoComplete: 'address-level1',
    tipo: 'seleccion', opciones: (f) => conValorActual(PROVINCIAS, f.provincia), indicacion: 'Elija la provincia',
  },
  {
    id: 'canton', etiqueta: 'Cantón', icono: 'ubicacion', max: 60, falta: 'Elija el cantón.', autoComplete: 'address-level2',
    tipo: 'seleccion', opciones: (f) => conValorActual(cantonesDe(f.provincia), f.canton),
    indicacion: 'Elija el cantón', sinOpciones: 'Elija primero la provincia',
  },
  { id: 'distrito', etiqueta: 'Distrito', icono: 'ubicacion', max: 60, falta: 'Ingrese el distrito.', autoComplete: 'address-level3' },
  { id: 'otras_senas', etiqueta: 'Otras señas', icono: 'ubicacion', max: 300, falta: 'Ingrese las otras señas.', multilinea: true, autoComplete: 'street-address', completo: true },
];

// Nuevo estado del formulario al cambiar un campo. Si cambia la provincia y el cantón elegido no es de la
// nueva provincia, el cantón se vacía para que se elija uno de la lista correcta.
export function cambiarCampo(f, id, valor) {
  const nuevo = { ...f, [id]: valor };
  if (id === 'provincia' && !cantonesDe(valor).includes(f.canton)) nuevo.canton = '';
  return nuevo;
}

// Errores por campo de los datos de una dirección (objeto con los ids de camposDireccion).
export function validarDireccion(f) {
  const errores = {};
  for (const c of camposDireccion) {
    const valor = (f[c.id] || '').trim();
    if (!valor) errores[c.id] = c.falta;
    else if (valor.length > c.max) errores[c.id] = `Máximo ${c.max} caracteres.`;
  }
  if (!errores.telefono_destinatario && !TELEFONO_VALIDO.test(f.telefono_destinatario.trim())) {
    errores.telefono_destinatario = 'Use solo dígitos, espacios, + y -.';
  }
  // El cantón debe ser de la provincia elegida (si la provincia es una de la lista).
  if (!errores.canton && PROVINCIAS.includes(f.provincia) && !cantonesDe(f.provincia).includes(f.canton)) {
    errores.canton = `Elija un cantón de ${f.provincia}.`;
  }
  return errores;
}

// Datos de la dirección listos para enviar: cada campo sin espacios al inicio ni al final.
export function datosDireccion(f) {
  return Object.fromEntries(camposDireccion.map((c) => [c.id, (f[c.id] || '').trim()]));
}
