// Campos de una dirección de envío (esquema DatosDireccionCliente del contrato de M1, versión 1.2.0) con los
// límites de la tabla direcciones_cliente. Los usan el registro (CrearCuentaView) y Mi cuenta (DireccionesSeccion).

// Mismo formato que direcciones_cliente.telefono_destinatario (chk_direcciones_cliente_telefono).
export const TELEFONO_VALIDO = /^[0-9 +-]+$/;

export const camposDireccion = [
  { id: 'alias_direccion', etiqueta: 'Nombre de la dirección (por ejemplo, Casa)', icono: 'ubicacion', max: 40, falta: 'Ingrese un nombre para la dirección.', autoComplete: 'off' },
  { id: 'nombre_destinatario', etiqueta: 'Persona que recibe', icono: 'persona', max: 150, falta: 'Ingrese quién recibe el pedido.', autoComplete: 'name' },
  { id: 'telefono_destinatario', etiqueta: 'Teléfono de quien recibe', icono: 'telefono', max: 20, falta: 'Ingrese el teléfono de quien recibe.', type: 'tel', autoComplete: 'tel' },
  { id: 'provincia', etiqueta: 'Provincia', icono: 'ubicacion', max: 40, falta: 'Ingrese la provincia.', autoComplete: 'address-level1' },
  { id: 'canton', etiqueta: 'Cantón', icono: 'ubicacion', max: 60, falta: 'Ingrese el cantón.', autoComplete: 'address-level2' },
  { id: 'distrito', etiqueta: 'Distrito', icono: 'ubicacion', max: 60, falta: 'Ingrese el distrito.', autoComplete: 'address-level3' },
  { id: 'otras_senas', etiqueta: 'Otras señas', icono: 'ubicacion', max: 300, falta: 'Ingrese las otras señas.', multilinea: true, autoComplete: 'street-address' },
];

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
  return errores;
}

// Datos de la dirección listos para enviar: cada campo sin espacios al inicio ni al final.
export function datosDireccion(f) {
  return Object.fromEntries(camposDireccion.map((c) => [c.id, (f[c.id] || '').trim()]));
}
