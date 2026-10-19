// DATOS DE EJEMPLO para el prototipo de la ficha de comercio (CU-009). No provienen de M2, M3 ni M4.
// La forma sigue los contratos:
//   - comercio: ComercioPublico de M2 (ObtenerDatosComercioPublico, openapi M2 v1.2.1).
//   - productos: ProductoResumenDTO de M1 (PC3, sección 4), que arma M1 con datos de M3.
//   - disponibilidad: valores Disponible / Agotado (M4, consultarDisponibilidadProductoLote).

export const comercio = {
  id_comercio: 7,
  nombre_comercial: 'TecnoPacífico',
  descripcion:
    'Tienda de tecnología en Liberia, Guanacaste. Vendemos redes, cámaras de seguridad, audio y accesorios con garantía local y envío a todo el país.',
  logo_url: null,
  banners: [{ titulo: 'Equipá tu casa inteligente', subtitulo: 'Cámaras, bombillos y enchufes con control desde el celular' }],
  horarios: [
    { dia: 'Lunes a viernes', apertura: '08:00', cierre: '18:00' },
    { dia: 'Sábado', apertura: '09:00', cierre: '14:00' },
    { dia: 'Domingo', apertura: null, cierre: null },
  ],
  contacto: { telefono: '2666-0000', correo: 'ventas@tecnopacifico.example', direccion: 'Liberia centro, 200 m norte del parque' },
  redes_sociales: [
    { red: 'Facebook', url: '#' },
    { red: 'Instagram', url: '#' },
  ],
  politicas: [
    { tipo: 'Envíos', texto: 'Envío a todo el país en 1 a 5 días hábiles.' },
    { tipo: 'Devoluciones', texto: 'Devoluciones dentro de los 8 días naturales, con el producto en su empaque.' },
    { tipo: 'Garantía', texto: 'Garantía de 1 año en todos los equipos electrónicos.' },
  ],
  estado: 'Activo',
};

// Categorías del comercio (M3), con el ícono y el orden del menú de M1 (categorias_portal).
export const categorias = [
  { idCategoria: 1, nombre: 'Cámaras de vigilancia', icono: '📷' },
  { idCategoria: 2, nombre: 'Routers y mesh', icono: '📶' },
  { idCategoria: 3, nombre: 'Switches de red', icono: '🔌' },
  { idCategoria: 4, nombre: 'Casa inteligente', icono: '💡' },
  { idCategoria: 5, nombre: 'Audio', icono: '🎧' },
  { idCategoria: 6, nombre: 'Adaptadores', icono: '🧩' },
];

export const marcas = ['Tapo', 'Archer', 'Omada', 'Mercusys', 'SoundMax'];

const p = (idProducto, nombre, idCategoria, marca, precio, precioHasta = null, disponibilidad = 'Disponible') => ({
  idProducto,
  idComercio: 7,
  nombre,
  imagenPrincipal: null,
  precio,
  precioHasta,
  moneda: 'CRC',
  nombreComercio: 'TecnoPacífico',
  disponibilidad,
  idCategoria,
  marca,
});

export const productos = [
  p(101, 'Cámara de seguridad Wi-Fi para el hogar C210, visión nocturna', 1, 'Tapo', 12000),
  p(102, 'Cámara inteligente Wi-Fi 360° C510 para exteriores', 1, 'Tapo', 21900),
  p(103, 'Cámara con panel solar y batería C660, 2K', 1, 'Tapo', 59900, null, 'Agotado'),
  p(104, 'Cámara de vigilancia exterior Wi-Fi C310', 1, 'Tapo', 21500),
  p(105, 'Cámara doble lente C246D, panorámica 2K', 1, 'Tapo', 32900),
  p(106, 'Cámara Wi-Fi C110 compacta', 1, 'Tapo', 12000, null, 'Agotado'),
  p(107, 'Router Wi-Fi 6 doble banda AX1800', 2, 'Archer', 28800),
  p(108, 'Router Archer AX53 AX3000 Gigabit Wi-Fi 6', 2, 'Archer', 36000),
  p(109, 'Sistema mesh Wi-Fi 6 (paquete de 2 o 3)', 2, 'Archer', 79500, 112000),
  p(110, 'Router móvil 4G LTE', 2, 'Archer', 30900),
  p(111, 'Repetidor Wi-Fi RE315 AC1200', 2, 'Archer', 15900),
  p(112, 'Punto de acceso Omada EAP653 Wi-Fi 6 de techo', 2, 'Omada', 63100),
  p(113, 'Switch de escritorio Gigabit de 8 puertos', 3, 'Omada', 10700),
  p(114, 'Switch Gigabit de 8 puertos con PoE+', 3, 'Omada', 24600),
  p(115, 'Switch inteligente Gigabit de 24 puertos', 3, 'Omada', 46800, null, 'Agotado'),
  p(116, 'Switch inteligente de 8 puertos con PoE', 3, 'Omada', 24900),
  p(117, 'Bombillo inteligente multicolor (1 o 2 unidades)', 4, 'Tapo', 4700, 6600),
  p(118, 'Mini enchufe inteligente Wi-Fi', 4, 'Tapo', 5170),
  p(119, 'Sensor de humedad y temperatura T310', 4, 'Tapo', 6600),
  p(120, 'Tira LED inteligente de 5 m', 4, 'Tapo', 14500),
  p(121, 'Audífonos inalámbricos con cancelación de ruido', 5, 'SoundMax', 18900),
  p(122, 'Parlante Bluetooth resistente al agua', 5, 'SoundMax', 22500),
  p(123, 'Audífonos deportivos Bluetooth 5.3', 5, 'SoundMax', 9900, null, 'Agotado'),
  p(124, 'Barra de sonido 2.1 con subwoofer', 5, 'SoundMax', 54900),
  p(125, 'Adaptador Wi-Fi USB AX300 nano', 6, 'Mercusys', 2600),
  p(126, 'Adaptador Bluetooth USB 5.3', 6, 'Mercusys', 5000),
  p(127, 'Adaptador PCI Express Wi-Fi de doble banda', 6, 'Archer', 12800),
  p(128, 'Adaptador de red USB-C a Ethernet Gigabit', 6, 'Mercusys', 7900),
];
