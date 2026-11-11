import { FileText, Sparkles, Store } from 'lucide-react';
import SolicitudesPlataforma from '../../components/SolicitudesPlataforma';
import ComerciosPlataforma from '../../components/ComerciosPlataforma';
import DestacadosPlataforma from '../../components/DestacadosPlataforma';

/*
 * Pestañas del panel de gestión de la plataforma (rol encargado_plataforma, en /m2.html).
 *
 * Para agregar una pestaña de otro módulo:
 *   1. Importá su vista (un componente React sin props obligatorios). La vista llama a la API
 *      de su propio módulo; la sesión del núcleo ya está activa.
 *   2. Agregá un objeto a la lista con:
 *        clave   texto único, en minúsculas y sin espacios (se usa para identificar la pestaña)
 *        nombre  texto de la pestaña
 *        icono   un ícono de lucide-react
 *        Vista   el componente
 *        modulo  el módulo dueño de la vista, por ejemplo 'M1'
 *
 * Ejemplo (M1):
 *   import NoticiasPlataforma from '../../../M1_Portal/views/NoticiasPlataforma';
 *   { clave: 'noticias', nombre: 'Noticias', icono: Newspaper, Vista: NoticiasPlataforma, modulo: 'M1' },
 *
 * El orden de la lista es el orden de las pestañas; la primera es la que se abre al entrar.
 */
export const SECCIONES_PLATAFORMA = [
  { clave: 'solicitudes', nombre: 'Solicitudes', icono: FileText, Vista: SolicitudesPlataforma, modulo: 'M2' },
  { clave: 'comercios', nombre: 'Comercios', icono: Store, Vista: ComerciosPlataforma, modulo: 'M2' },
  { clave: 'destacados', nombre: 'Destacados', icono: Sparkles, Vista: DestacadosPlataforma, modulo: 'M2' },
];
