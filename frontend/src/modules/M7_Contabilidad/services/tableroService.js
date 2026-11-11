
export const indicadores = [
  {
    titulo: 'Ingresos del período',
    valor: '₡2.450.000',
    detalle: 'Ventas registradas',
    color: 'azul',
  },
  {
    titulo: 'Costo de ventas',
    valor: '₡1.420.000',
    detalle: 'Costo de productos vendidos',
    color: 'verde',
  },
  {
    titulo: 'Utilidad bruta',
    valor: '₡1.030.000',
    detalle: 'Ingresos menos costo de ventas',
    color: 'azul',
  },
  {
    titulo: 'Cuentas pendientes',
    valor: '₡385.000',
    detalle: 'Saldo por cobrar',
    color: 'verde',
  },
];

export const asientos = [
  {
    numero: 'ASI-0004',
    fecha: '18/10/2026',
    descripcion: 'Venta automática',
    tipo: 'Automático',
    estado: 'Registrado',
  },
  {
    numero: 'ASI-0003',
    fecha: '17/10/2026',
    descripcion: 'Costo de ventas',
    tipo: 'Automático',
    estado: 'Registrado',
  },
  {
    numero: 'ASI-0002',
    fecha: '16/10/2026',
    descripcion: 'Ajuste contable',
    tipo: 'Manual',
    estado: 'Registrado',
  },
];

export const datosMensuales = [
  { mes: 'May', ingresos: 55, costos: 35 },
  { mes: 'Jun', ingresos: 68, costos: 42 },
  { mes: 'Jul', ingresos: 48, costos: 30 },
  { mes: 'Ago', ingresos: 76, costos: 47 },
  { mes: 'Sep', ingresos: 65, costos: 39 },
  { mes: 'Oct', ingresos: 88, costos: 51 },
];

export const opcionesContables = [
  'Tablero ejecutivo',
  'Plan de cuentas',
  'Libro diario',
  'Libro mayor',
  'Balance general',
  'Estado de resultados',
  'Cuentas por cobrar y pagar',
  'Centros de costo',
];
