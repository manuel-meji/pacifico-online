import { useEffect, useState } from 'react';
import { Aviso, Encabezado, NavegacionCuentaContext } from './components/componentes';
import { correoDuplicado, credencialesIncorrectas } from './services/datosMock';
import { registrarCliente } from './services/m1Api';
import { cerrarSesion, iniciarSesion, obtenerUsuario } from '../Nucleo/services/sesionApi';
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
  // Persona con sesión ({ id_usuario, nombre, correo, rol, id_comercio }) o null. Viene de GET /api/user.
  const [usuario, setUsuario] = useState(null);
  const [errorCierre, setErrorCierre] = useState(false);

  // Al cargar se consulta la sesión. 401 es "sin sesión"; cualquier otro fallo (por ejemplo sin conexión)
  // se ignora en silencio para no bloquear el portal.
  useEffect(() => {
    let activo = true;
    obtenerUsuario().then((respuesta) => {
      if (activo && respuesta.ok) setUsuario(respuesta.datos.user);
    });
    return () => { activo = false; };
  }, []);

  function navegar(destino) {
    if (destino === pantalla) return;
    setErrorCierre(false);

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

  // POST /login del núcleo compartido. Con ?simular=credenciales se muestra, sin backend, el error 422 del ingreso.
  // Si entra un cliente va al inicio del portal. Los encargados todavía no tienen panel: la vista les avisa.
  async function ingresar({ correo, contrasena }) {
    if (simular === 'credenciales') {
      await esperar();
      return { ok: false, ...credencialesIncorrectas };
    }
    const respuesta = await iniciarSesion(correo, contrasena);
    if (respuesta.ok) {
      setUsuario(respuesta.datos.user);
      if (respuesta.datos.user.rol === 'cliente') navegar('inicio');
    }
    return respuesta;
  }

  // POST /logout. Si el servidor no se pudo contactar se conserva la sesión; un 401 significa que ya no había.
  async function cerrar() {
    setErrorCierre(false);
    const respuesta = await cerrarSesion();
    if (!respuesta.ok && respuesta.estado !== 401) {
      setErrorCierre(true);
      return;
    }
    setUsuario(null);
    navegar('ingresar');
  }

  if (pantalla === 'inicio') {
    // Con sesión, "Mi cuenta" del encabezado del portal lleva a perfil (donde está "Cerrar sesión").
    return <InicioView onIr={(destino) => navegar(usuario && destino === 'ingresar' ? 'perfil' : destino)} />;
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
    <NavegacionCuentaContext.Provider value={navegar}>
    <div className="min-h-screen overflow-x-hidden bg-brandLightBg font-sans font-normal text-gray-800">
      <Encabezado pantalla={pantalla} onIr={navegar} usuario={usuario} onCerrarSesion={cerrar} />

      {errorCierre && (
        <div className="mx-auto w-full max-w-5xl px-4 pt-4">
          <Aviso>No se pudo cerrar la sesión. Intente de nuevo.</Aviso>
        </div>
      )}

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
    </NavegacionCuentaContext.Provider>
  );
}
