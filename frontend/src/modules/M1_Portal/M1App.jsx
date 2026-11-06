import { useEffect, useState } from 'react';
import { Encabezado } from './components/componentes';
import { correoDuplicado, credencialesIncorrectas } from './services/datosMock';
import { registrarCliente } from './services/m1Api';
import CrearCuentaView from './views/CrearCuentaView';
import FichaComercioView from './views/FichaComercioView';
import IniciarSesionView from './views/IniciarSesionView';
import InicioView from './views/InicioView';
import PerfilView from './views/PerfilView';
import FichaProductoView from './views/FichaProductoView';

const esperar = () => new Promise((r) => setTimeout(r, 600));

// Título de la pestaña por pantalla. La ficha de comercio conserva el título de m1.html.
const titulos = {
  inicio: 'Pacíficos Online · Inicio',
  ingresar: 'Pacíficos Online · M1 Iniciar sesión',
  crear: 'Pacíficos Online · M1 Crear cuenta',
  perfil: 'Pacíficos Online · M1 Mi cuenta',
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

  // POST /api/m1/v1/clientes (registrarCliente) contra el backend real. Con ?simular=correo se muestra,
  // sin backend, la respuesta 409 ERR-M1-001 (correo ya registrado) para el recorrido del prototipo.
  async function registrar(datos) {
    if (simular === 'correo') {
      await esperar();
      return { ok: false, ...correoDuplicado };
    }
    return registrarCliente(datos);
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

  if (pantalla !== 'ingresar' && pantalla !== 'crear' && pantalla !== 'perfil') {
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

      {pantalla === 'perfil' && (
        <PerfilView onIrIngresar={() => navegar('ingresar')} />
      )}
    </div>
  );
}
