import DestacadosGestion from './DestacadosGestion';
import NoticiasGestion from './NoticiasGestion';
import PromocionesGestion from './PromocionesGestion';

// Registro de las pestañas del panel de gestión de M1 (pantalla=gestion).
//
// Para agregar una sección: crear su vista en views/gestion/ (recibe { usuario }) y agregar UNA línea aquí.
// No hace falta tocar M1App.jsx ni GestionView.jsx, así cada integrante trabaja su sección sin conflictos.
//
//   clave:  identificador en la URL (?pantalla=gestion&seccion=<clave>)
//   nombre: texto de la pestaña
//   icono:  nombre de un ícono de components/IconoComercio.jsx
//   roles:  roles del núcleo que ven la pestaña (encargado_comercio, encargado_plataforma)
//   Vista:  componente de la sección
export const SECCIONES_GESTION = [
  { clave: 'destacados', nombre: 'Productos destacados', icono: 'estrella', roles: ['encargado_comercio', 'encargado_plataforma'], Vista: DestacadosGestion },
  { clave: 'promociones', nombre: 'Promociones', icono: 'etiqueta', roles: ['encargado_comercio', 'encargado_plataforma'], Vista: PromocionesGestion },
  { clave: 'noticias', nombre: 'Noticias', icono: 'noticia', roles: ['encargado_plataforma'], Vista: NoticiasGestion },
];

export const ROLES_GESTION = ['encargado_comercio', 'encargado_plataforma'];

export const seccionesPara = (rol) => SECCIONES_GESTION.filter((s) => s.roles.includes(rol));
