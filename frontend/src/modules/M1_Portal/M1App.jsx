import { useEffect, useMemo, useState } from 'react';
import { Aviso } from './components/componentes';
import PaginaPortal, { PortalContext } from './components/PaginaPortal';
import { obtenerCantidadCarrito } from './services/carritoApi';
import { correoDuplicado, credencialesIncorrectas } from './services/datosMock';
import { listarCategorias, registrarCliente } from './services/m1Api';
import { cerrarSesion, iniciarSesion, obtenerUsuario } from '../Nucleo/services/sesionApi';
import BusquedaView from './views/BusquedaView';
import BusquedaComerciosView from './views/BusquedaComerciosView';
import CrearCuentaView from './views/CrearCuentaView';
import FichaComercioView from './views/FichaComercioView';
import IniciarSesionView from './views/IniciarSesionView';
import InicioView from './views/InicioView';
import PerfilView from './views/PerfilView';
import FichaProductoView from './views/FichaProductoView';
import NoticiaView from './views/NoticiaView';
import GestionView from './views/gestion/GestionView';

const esperar = () => new Promise((r) => setTimeout(r, 600));

// Título de la pestaña por pantalla.
const titulos = {
  inicio: 'Pacíficos Online · Inicio',
  busqueda: 'Pacíficos Online · Búsqueda',
  comercios: 'Pacíficos Online · Comercios',
  producto: 'Pacíficos Online · Producto',
  comercio: 'Pacíficos Online · Comercio',
  noticia: 'Pacíficos Online · Noticia',
  ingresar: 'Pacíficos Online · M1 Iniciar sesión',
  crear: 'Pacíficos Online · M1 Crear cuenta',
  perfil: 'Pacíficos Online · M1 Mi cuenta',
  gestion: 'Pacíficos Online · Panel de gestión',
};

// Parámetros de las pantallas del portal que viajan en la URL (?pantalla=producto&id=1520, etc.).
// Los nombres de la búsqueda son los del contrato de buscarProductos.
const PARAMETROS_NUMERICOS = ['id', 'id_categoria', 'precio_minimo', 'precio_maximo', 'pagina', 'por_pagina'];
const PARAMETROS_TEXTO = ['q', 'orden', 'slug', 'motivo', 'seccion'];

export function leerParametros(busqueda = window.location.search) {
  const url = new URLSearchParams(busqueda);
  const parametros = {};
  PARAMETROS_NUMERICOS.forEach((clave) => {
    const valor = Number(url.get(clave));
    if (url.get(clave) !== null && url.get(clave) !== '' && Number.isFinite(valor)) parametros[clave] = valor;
  });
  PARAMETROS_TEXTO.forEach((clave) => {
    if (url.get(clave)) parametros[clave] = url.get(clave);
  });
  return parametros;
}

function urlDe(pantalla, parametros) {
  const url = new URLSearchParams();
  url.set('pantalla', pantalla);
  Object.entries(parametros).forEach(([clave, valor]) => {
    if (valor !== null && valor !== undefined && valor !== '') url.set(clave, valor);
  });
  // ?simular= (registro e ingreso sin backend) se conserva al navegar.
  const simular = new URLSearchParams(window.location.search).get('simular');
  if (simular) url.set('simular', simular);
  return `${window.location.pathname}?${url.toString()}`;
}

export default function M1App({ pantalla: pantallaInicial = 'inicio', simular = null }) {
  const [pantalla, setPantalla] = useState(pantallaInicial);
  const [parametros, setParametros] = useState(() => leerParametros());
  // Persona con sesión ({ id_usuario, nombre, correo, rol, id_comercio }) o null. Viene de GET /api/user.
  const [usuario, setUsuario] = useState(null);
  // true hasta que responde GET /api/user (el panel de gestión espera para no mostrar "sin acceso" por un instante).
  const [cargandoUsuario, setCargandoUsuario] = useState(true);
  const [errorCierre, setErrorCierre] = useState(false);
  // Menú de GET /categorias, compartido por el encabezado y los filtros. Si falla, el menú no se muestra.
  const [categorias, setCategorias] = useState([]);

  useEffect(() => {
    let activo = true;
    listarCategorias().then((respuesta) => {
      if (activo && respuesta.ok) setCategorias(respuesta.datos);
    });
    return () => { activo = false; };
  }, []);

  // Al cargar se consulta la sesión. 401 es "sin sesión"; cualquier otro fallo (por ejemplo sin conexión)
  // se ignora en silencio para no bloquear el portal.
  useEffect(() => {
    let activo = true;
    obtenerUsuario().then((respuesta) => {
      if (!activo) return;
      if (respuesta.ok) setUsuario(respuesta.datos.user);
      setCargandoUsuario(false);
    });
    return () => { activo = false; };
  }, []);

  // Líneas del carrito de M5 para la insignia del encabezado. Solo se consulta con sesión de cliente
  // (sin sesión M5 no tiene a quién asociar el carrito). Se refresca al cambiar de pantalla y al volver
  // a la pestaña, por ejemplo después de agregar productos en el carrito de M5 (carrito.html).
  const [cantidadCarrito, setCantidadCarrito] = useState(null);
  const esCliente = usuario?.rol === 'cliente';

  useEffect(() => {
    if (!esCliente) {
      setCantidadCarrito(null);
      return undefined;
    }

    let activo = true;
    const actualizar = () => {
      obtenerCantidadCarrito().then((respuesta) => {
        if (activo) setCantidadCarrito(respuesta.ok ? respuesta.cantidad : null);
      });
    };
    const alVolver = () => { if (document.visibilityState === 'visible') actualizar(); };

    actualizar();
    document.addEventListener('visibilitychange', alVolver);

    return () => {
      activo = false;
      document.removeEventListener('visibilitychange', alVolver);
    };
  }, [esCliente, pantalla]);

  // Cambia de pantalla y deja los parámetros en la URL, así funcionan el botón Atrás y recargar la página.
  // Con { reemplazar: true } (cambios de filtros) no se agrega una entrada nueva al historial.
  function navegar(destino, nuevosParametros = {}, { reemplazar = false } = {}) {
    if (destino === pantalla && JSON.stringify(nuevosParametros) === JSON.stringify(parametros)) return;
    setErrorCierre(false);

    const estado = { pantallaM1: destino, parametrosM1: nuevosParametros };
    if (reemplazar) window.history.replaceState(estado, '', urlDe(destino, nuevosParametros));
    else window.history.pushState(estado, '', urlDe(destino, nuevosParametros));

    setPantalla(destino);
    setParametros(nuevosParametros);
    if (!reemplazar) window.scrollTo({ top: 0 });
  }

  useEffect(() => {
    function manejarHistorial(evento) {
      setPantalla(evento.state?.pantallaM1 || pantallaInicial);
      setParametros(evento.state?.parametrosM1 ?? leerParametros());
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
    const respuesta = await registrarCliente(datos);
    if (respuesta.ok) {
      const urlParams = new URLSearchParams(window.location.search);
      if (urlParams.get('motivo') === 'checkout' || parametros.motivo === 'checkout') {
        window.location.href = '/carrito.html';
        return respuesta;
      }
    }
    return respuesta;
  }

  // POST /login del núcleo compartido. Con ?simular=credenciales se muestra, sin backend, el error 422 del ingreso.
  // Si entra un cliente va al inicio del portal (o de vuelta al carrito si venía de checkout).
  // Los encargados van a /m2.html, que abre el panel según su rol.
  async function ingresar({ correo, contrasena }) {
    if (simular === 'credenciales') {
      await esperar();
      return { ok: false, ...credencialesIncorrectas };
    }
    const respuesta = await iniciarSesion(correo, contrasena);
    if (respuesta.ok) {
      const { rol } = respuesta.datos.user;
      setUsuario(respuesta.datos.user);
      if (rol === 'encargado_comercio' || rol === 'encargado_plataforma') {
        window.location.href = '/m2.html';
      } else if (rol === 'cliente') {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('motivo') === 'checkout' || parametros.motivo === 'checkout') {
          window.location.href = '/carrito.html';
          return respuesta;
        }
        navegar('inicio');
      }
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

  const portal = useMemo(() => ({ ir: navegar, categorias, usuario, cargandoUsuario, cantidadCarrito }), [categorias, usuario, cargandoUsuario, cantidadCarrito, pantalla, parametros]); // eslint-disable-line react-hooks/exhaustive-deps

  // Pantallas del portal público (encabezado con buscador y categorías).
  if (!['ingresar', 'crear', 'perfil'].includes(pantalla)) {
    const { id, ...criterio } = parametros;
    let vista;
    if (pantalla === 'busqueda') vista = <BusquedaView criterio={criterio} />;
    else if (pantalla === 'comercios') vista = <BusquedaComerciosView criterio={criterio} />;
    else if (pantalla === 'producto') vista = <FichaProductoView key={id} idProducto={id} />;
    else if (pantalla === 'comercio') vista = <FichaComercioView key={id} idComercio={id} criterio={criterio} />;
    else if (pantalla === 'noticia') vista = <NoticiaView key={criterio.slug} slug={criterio.slug} />;
    else if (pantalla === 'gestion') vista = <GestionView seccion={criterio.seccion} />;
    else vista = <InicioView />;

    return <PortalContext.Provider value={portal}>{vista}</PortalContext.Provider>;
  }

  // Pantallas de cuenta: mismo marco del portal (encabezado, ancho y pie) que el resto.
  return (
    <PortalContext.Provider value={portal}>
    <PaginaPortal>
      {(parametros.motivo === 'checkout' || new URLSearchParams(window.location.search).get('motivo') === 'checkout') && (
        <div className="mx-auto w-full max-w-md px-4 pt-4">
          <Aviso tipo="info">
            Inicie sesión o regístrese para continuar con su compra. Los productos de su carrito se encuentran guardados.
          </Aviso>
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
        <PerfilView onIrIngresar={() => navegar('ingresar')} onCerrarSesion={cerrar} errorCierre={errorCierre} />
      )}
    </PaginaPortal>
    </PortalContext.Provider>
  );
}
