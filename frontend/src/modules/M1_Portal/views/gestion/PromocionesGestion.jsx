import { usePortal } from '../../components/PaginaPortal';
import { AvisoVista } from '../../components/EstadosVista';
import { PromocionesComercio, PromocionesPlataforma } from '../../components/gestion/ListaPromociones';

// Pestaña "Promociones" del panel de gestión de M1 (contrato M1 1.4.0, SC-08).
// Muestra la parte del comercio o la de la plataforma según el rol de la sesión.
// El panel la monta con la persona de la sesión ({ rol, id_comercio, ... }); si no la pasa, se toma del PortalContext.
export default function PromocionesGestion({ usuario: usuarioPanel }) {
  const { usuario: usuarioPortal } = usePortal();
  const usuario = usuarioPanel ?? usuarioPortal;

  if (usuario?.rol === 'encargado_comercio' && usuario.id_comercio) return <PromocionesComercio idComercio={usuario.id_comercio} />;
  if (usuario?.rol === 'encargado_plataforma') return <PromocionesPlataforma />;
  return <AvisoVista>Esta sección es solo para encargados de comercio o de la plataforma.</AvisoVista>;
}
