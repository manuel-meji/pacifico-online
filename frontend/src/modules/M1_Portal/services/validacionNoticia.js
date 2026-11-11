// Validación en el cliente del formulario de noticias (esquema DatosNoticia del contrato M1 1.4.0).
// Usa los mismos límites que el contrato y el backend para no rechazar nada que el backend acepte;
// __tests__/validacionNoticia.test.js los compara contra contracts/m1-portal/v1/openapi.yaml.
import { horaLocalAIso } from './formato';

// maxLength de DatosNoticia.
export const LIMITES = { titulo: 150, resumen: 300, contenido: 20000, imagen_url: 255 };

// maxItems de DatosNoticia.ids_promocion.
export const MAX_PROMOCIONES = 10;

const FALTA = { titulo: 'Ingrese el título.', resumen: 'Ingrese el resumen.', contenido: 'Ingrese el contenido.' };

// Largo en caracteres (no en unidades UTF-16), como lo cuentan el contrato y mb_strlen en el backend.
export const largo = (texto) => [...texto].length;

// El backend acepta una URL http o https (el esquema sin distinguir mayúsculas). Aquí solo se descarta lo
// que seguro rechaza: otro esquema o espacios. Lo demás lo decide el backend (422 en el campo).
export const esUrlDeImagen = (valor) => /^https?:\/\/\S+$/i.test(valor);

// `f` es el estado del formulario: textos, fecha_fin como valor de datetime-local e ids_promocion.
// Devuelve { campo: mensaje } con los campos inválidos. Los textos se validan sin espacios en los extremos.
export function validarNoticia(f) {
  const errores = {};
  for (const campo of ['titulo', 'resumen', 'contenido']) {
    const valor = f[campo].trim();
    if (!valor) errores[campo] = FALTA[campo];
    else if (largo(valor) > LIMITES[campo]) errores[campo] = `Máximo ${LIMITES[campo]} caracteres.`;
  }
  const imagen = f.imagen_url.trim();
  if (largo(imagen) > LIMITES.imagen_url) errores.imagen_url = `Máximo ${LIMITES.imagen_url} caracteres.`;
  else if (imagen && !esUrlDeImagen(imagen)) errores.imagen_url = 'Escriba una dirección que empiece con http:// o https://.';
  if (f.fecha_fin && !horaLocalAIso(f.fecha_fin)) errores.fecha_fin = 'Ingrese una fecha válida.';
  if (f.ids_promocion.length > MAX_PROMOCIONES) errores.ids_promocion = `Elija como máximo ${MAX_PROMOCIONES} promociones.`;
  return errores;
}
