import { readFileSync } from 'node:fs';
import { describe, it, expect } from 'vitest';
import { LIMITES, MAX_PROMOCIONES, esUrlDeImagen, validarNoticia } from '../services/validacionNoticia';

// El cliente no debe rechazar nada que el backend acepte: los límites salen del esquema DatosNoticia del
// contrato (contracts/m1-portal/v1/openapi.yaml), que se lee aquí para compararlos.
const contrato = readFileSync(new URL('../../../../../contracts/m1-portal/v1/openapi.yaml', import.meta.url), 'utf8').replace(/\r\n/g, '\n');

// Propiedades de un esquema de components.schemas: { propiedad: texto de su definición }.
function propiedadesDe(esquema) {
  const inicio = contrato.indexOf(`\n    ${esquema}:\n`);
  if (inicio < 0) throw new Error(`El contrato no tiene el esquema ${esquema}`);
  const resto = contrato.slice(inicio + 1).split('\n').slice(1);
  const fin = resto.findIndex((linea) => /^ {0,4}\S/.test(linea));
  const lineas = fin < 0 ? resto : resto.slice(0, fin);
  const propiedades = {};
  let actual = null;
  for (const linea of lineas) {
    const nombre = linea.match(/^ {8}(\w+):\s*$/);
    if (nombre) { actual = nombre[1]; propiedades[actual] = ''; } else if (actual) propiedades[actual] += `${linea}\n`;
  }
  return propiedades;
}

const numero = (definicion, clave) => Number(definicion.match(new RegExp(`${clave}: (\\d+)`))?.[1]);

const formulario = (extra = {}) => ({
  titulo: 'Feria de noviembre', resumen: 'Un resumen', contenido: 'Un contenido', imagen_url: '', fecha_fin: '', ids_promocion: [], ...extra,
});

describe('límites del formulario de noticias frente al contrato (DatosNoticia)', () => {
  const datosNoticia = propiedadesDe('DatosNoticia');

  it('el contrato sigue teniendo los campos que valida el formulario', () => {
    expect(Object.keys(datosNoticia).sort()).toEqual(['contenido', 'fecha_fin', 'ids_promocion', 'imagen_url', 'resumen', 'titulo']);
  });

  it.each(['titulo', 'resumen', 'contenido', 'imagen_url'])('el máximo de %s es el maxLength del contrato', (campo) => {
    expect(numero(datosNoticia[campo], 'maxLength')).toBeGreaterThan(0);
    expect(LIMITES[campo]).toBe(numero(datosNoticia[campo], 'maxLength'));
  });

  it('el máximo de promociones es el maxItems del contrato', () => {
    expect(MAX_PROMOCIONES).toBe(numero(datosNoticia.ids_promocion, 'maxItems'));
  });

  it.each(['titulo', 'resumen', 'contenido'])('%s acepta justo el máximo del contrato y rechaza un carácter más', (campo) => {
    const maximo = numero(datosNoticia[campo], 'maxLength');

    expect(validarNoticia(formulario({ [campo]: 'a'.repeat(maximo) }))[campo]).toBeUndefined();
    expect(validarNoticia(formulario({ [campo]: 'a'.repeat(maximo + 1) }))[campo]).toBe(`Máximo ${maximo} caracteres.`);
  });

  it('cuenta caracteres como el backend: sin los espacios de los extremos y un emoji como uno solo', () => {
    const maximo = numero(datosNoticia.titulo, 'maxLength');

    expect(validarNoticia(formulario({ titulo: `  ${'a'.repeat(maximo)}  ` })).titulo).toBeUndefined();
    expect(validarNoticia(formulario({ titulo: '\u{1F389}'.repeat(maximo) })).titulo).toBeUndefined();
    expect(validarNoticia(formulario({ titulo: '\u{1F389}'.repeat(maximo + 1) })).titulo).toBeDefined();
  });

  it('imagen_url es opcional, y solo titulo, resumen y contenido son obligatorios como en el contrato', () => {
    const requeridos = contrato.slice(contrato.indexOf('\n    DatosNoticia:\n')).match(/required: \[([^\]]+)\]/)[1].split(',').map((c) => c.trim());

    expect(requeridos.sort()).toEqual(['contenido', 'resumen', 'titulo']);
    expect(validarNoticia(formulario())).toEqual({});
    expect(Object.keys(validarNoticia(formulario({ titulo: ' ', resumen: '', contenido: '\n' }))).sort()).toEqual(['contenido', 'resumen', 'titulo']);
  });
});

describe('validación de la URL de la imagen', () => {
  it('el contrato pide una URL http o https de hasta 255 caracteres', () => {
    const imagen = propiedadesDe('DatosNoticia').imagen_url;

    expect(imagen).toMatch(/format: uri/);
    expect(imagen).toMatch(/URL http o https/);
  });

  // Direcciones que el backend acepta (filter_var FILTER_VALIDATE_URL con esquema http o https).
  it.each([
    'https://ejemplo.com/a.jpg',
    'http://ejemplo.com/a.jpg',
    'HTTPS://EJEMPLO.COM/A.JPG',
    'https://ejemplo.com:8443/img/a.png?ancho=800&v=2#x',
    'https://cdn.ejemplo.co.cr/fotos/feria%20de%20noviembre.jpg',
  ])('no rechaza una URL que el backend acepta (%s)', (url) => {
    expect(esUrlDeImagen(url)).toBe(true);
    expect(validarNoticia(formulario({ imagen_url: url })).imagen_url).toBeUndefined();
  });

  it('acepta una URL de justo 255 caracteres y con espacios alrededor, y rechaza una de 256', () => {
    const base = 'https://ejemplo.com/';
    const de255 = base + 'a'.repeat(LIMITES.imagen_url - base.length);

    expect(validarNoticia(formulario({ imagen_url: de255 })).imagen_url).toBeUndefined();
    expect(validarNoticia(formulario({ imagen_url: `  ${de255}  ` })).imagen_url).toBeUndefined();
    expect(validarNoticia(formulario({ imagen_url: `${de255}a` })).imagen_url).toBe('Máximo 255 caracteres.');
  });

  it.each(['ftp://ejemplo.com/a.jpg', 'foto.jpg', '//ejemplo.com/a.jpg', 'javascript:alert(1)', 'https://ejemplo.com/feria de noviembre.jpg'])(
    'avisa antes de enviar si la dirección no es http o https, o tiene espacios (%s)',
    (url) => {
      expect(validarNoticia(formulario({ imagen_url: url })).imagen_url).toMatch(/http:\/\/ o https:\/\//);
    },
  );
});

describe('otros campos', () => {
  it('rechaza más promociones que el máximo y una fecha de fin que no es una fecha', () => {
    const ids = Array.from({ length: MAX_PROMOCIONES + 1 }, (_, i) => i + 1);

    expect(validarNoticia(formulario({ ids_promocion: ids })).ids_promocion).toBe(`Elija como máximo ${MAX_PROMOCIONES} promociones.`);
    expect(validarNoticia(formulario({ ids_promocion: ids.slice(0, MAX_PROMOCIONES) })).ids_promocion).toBeUndefined();
    expect(validarNoticia(formulario({ fecha_fin: 'mañana' })).fecha_fin).toBe('Ingrese una fecha válida.');
    expect(validarNoticia(formulario({ fecha_fin: '2027-06-15T08:05' })).fecha_fin).toBeUndefined();
  });
});
