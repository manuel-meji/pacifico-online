import { useEffect, useState } from 'react';
import { Encabezado } from './components/componentes';
import { correoDuplicado, credencialesIncorrectas, registroExitoso } from './services/datosMock';
import CrearCuentaView from './views/CrearCuentaView';
import FichaComercioView from './views/FichaComercioView';
import IniciarSesionView from './views/IniciarSesionView';
import InicioView from './views/InicioView';
import FichaProductoView from './views/FichaProductoView';

const esperar = () => new Promise((r) => setTimeout(r, 600));

// Título de la pestaña por pantalla. La ficha de comercio conserva el título de m1.html.
const titulos = {
  inicio: 'Pacíficos Online · Inicio',
  ingresar: 'Pacíficos Online · M1 Iniciar sesión',
  crear: 'Pacíficos Online · M1 Crear cuenta',
};


export default function M1App({ pantalla: pantallaInicial = 'comercio', simular = null }) {
  const [pantalla, setPantalla] = useState(pantallaInicial);

  function navegar(destino) {
    if (destino === pantalla) return;

    window.history.pushState(
      { pantallaM1: destino },
      '',
      window.location.href
    );

    setPantalla(destino);
  }

  useEffect(() => {
    function manejarHistorial(evento) {
      setPantalla(evento.state?.pantallaM1 || pantallaInicial);
    }

    window.addEventListener('popstate', manejarHistorial);

    return () => {
      window.removeEventListener('popstate', manejarHistorial);
    };
  }, [pantallaInicial]);

  useEffect(() => {
    if (titulos[pantalla]) document.title = titulos[pantalla];
  }, [pantalla]);

  // Doble de POST /api/m1/v1/clientes (registrarCliente). Se sustituye por la llamada real cuando el módulo esté desplegado.
  async function registrar() {
    await esperar();
    if (simular === 'correo') return { ok: false, ...correoDuplicado };
    return { ok: true, ...registroExitoso };
  }

  // Doble del ingreso del núcleo compartido (su contrato todavía no está publicado).
  async function ingresar() {
    await esperar();
    if (simular === 'credenciales') return { ok: false, ...credencialesIncorrectas };
    return { ok: true };
  }
  
  if (pantalla === 'inicio') {
    return <InicioView onIr={navegar} />;
  }

  if (pantalla === 'producto') {
    return <FichaProductoView />;
  }

  if (pantalla === 'comercio') {
    return (
      <FichaComercioView
        idComercio={7}
        simular={simular}
        onVerProducto={() => navegar('producto')}
      />
    );
  }

  if (pantalla !== 'ingresar' && pantalla !== 'crear') {
    return <FichaComercioView idComercio={7} simular={simular} />;
  }

  
  return (
    <div className="min-h-screen overflow-x-hidden bg-brandLightBg font-sans font-normal text-gray-800">
      <Encabezado pantalla={pantalla} onIr={navegar} />

      {pantalla === 'ingresar' && (
        <IniciarSesionView
          onIngresar={ingresar}
          onIrCrear={() => navegar('crear')}
        />
      )}

      {pantalla === 'crear' && (
        <CrearCuentaView
          onRegistrar={registrar}
          onIrIngresar={() => navegar('ingresar')}
        />
      )}
    </div>
  );
}
