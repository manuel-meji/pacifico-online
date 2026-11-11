// División territorial administrativa de Costa Rica: 7 provincias y sus 84 cantones, en el orden oficial
// (incluye Río Cuarto, Monteverde y Puerto Jiménez). La usan las listas desplegables de la dirección de envío.
// Los nombres se guardan tal cual en direcciones_cliente.provincia y .canton (texto, contrato M1).

export const CANTONES_POR_PROVINCIA = {
  'San José': [
    'San José', 'Escazú', 'Desamparados', 'Puriscal', 'Tarrazú', 'Aserrí', 'Mora', 'Goicoechea', 'Santa Ana',
    'Alajuelita', 'Vázquez de Coronado', 'Acosta', 'Tibás', 'Moravia', 'Montes de Oca', 'Turrubares', 'Dota',
    'Curridabat', 'Pérez Zeledón', 'León Cortés Castro',
  ],
  Alajuela: [
    'Alajuela', 'San Ramón', 'Grecia', 'San Mateo', 'Atenas', 'Naranjo', 'Palmares', 'Poás', 'Orotina',
    'San Carlos', 'Zarcero', 'Sarchí', 'Upala', 'Los Chiles', 'Guatuso', 'Río Cuarto',
  ],
  Cartago: ['Cartago', 'Paraíso', 'La Unión', 'Jiménez', 'Turrialba', 'Alvarado', 'Oreamuno', 'El Guarco'],
  Heredia: [
    'Heredia', 'Barva', 'Santo Domingo', 'Santa Bárbara', 'San Rafael', 'San Isidro', 'Belén', 'Flores',
    'San Pablo', 'Sarapiquí',
  ],
  Guanacaste: [
    'Liberia', 'Nicoya', 'Santa Cruz', 'Bagaces', 'Carrillo', 'Cañas', 'Abangares', 'Tilarán', 'Nandayure',
    'La Cruz', 'Hojancha',
  ],
  Puntarenas: [
    'Puntarenas', 'Esparza', 'Buenos Aires', 'Montes de Oro', 'Osa', 'Quepos', 'Golfito', 'Coto Brus', 'Parrita',
    'Corredores', 'Garabito', 'Monteverde', 'Puerto Jiménez',
  ],
  Limón: ['Limón', 'Pococí', 'Siquirres', 'Talamanca', 'Matina', 'Guácimo'],
};

export const PROVINCIAS = Object.keys(CANTONES_POR_PROVINCIA);

// Cantones de una provincia; [] si la provincia no está en la lista.
export const cantonesDe = (provincia) => CANTONES_POR_PROVINCIA[provincia] ?? [];

// Opciones de una lista sin perder un valor guardado que no esté en ella (por ejemplo, una dirección
// escrita a mano antes de que existieran las listas): ese valor se agrega al inicio para poder verlo y cambiarlo.
export const conValorActual = (opciones, valor) => (valor && !opciones.includes(valor) ? [valor, ...opciones] : opciones);
