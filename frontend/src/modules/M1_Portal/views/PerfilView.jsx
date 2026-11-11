import { useEffect, useState } from 'react';
import { Aviso, BotonPrincipal, CampoConIcono, PaginaCuenta } from '../components/componentes';
import DireccionesSeccion from '../components/DireccionesSeccion';
import { usePortal } from '../components/PaginaPortal';
import { actualizarCliente, obtenerClienteSesion } from '../services/m1Api';

// Campos del esquema DatosCliente (contracts/m1-portal/v1/openapi.yaml, versión 1.2.0) y sus límites,
// iguales a las columnas de la tabla clientes.
const campos = [
  { id: 'nombre', etiqueta: 'Nombre', icono: 'persona', max: 80, falta: 'Ingrese su nombre.', autoComplete: 'given-name' },
  { id: 'apellidos', etiqueta: 'Apellidos', icono: 'persona', max: 120, falta: 'Ingrese sus apellidos.', autoComplete: 'family-name' },
  { id: 'telefono', etiqueta: 'Teléfono', icono: 'telefono', max: 20, falta: 'Ingrese su teléfono.', type: 'tel', autoComplete: 'tel' },
];

function validar(f) {
  const errores = {};
  for (const c of campos) {
    const valor = f[c.id].trim();
    if (!valor) errores[c.id] = c.falta;
    else if (valor.length > c.max) errores[c.id] = `Máximo ${c.max} caracteres.`;
  }
  return errores;
}

// ERR-005 trae `detalles` por campo; ERR-M1-001 es el correo de otro cliente (RN-M1-14).
function erroresDelServidor(error) {
  if (error.codigo === 'ERR-M1-001') return { correo_electronico: error.mensaje };
  const errores = {};
  for (const [campo, mensaje] of Object.entries(error.detalles || {})) {
    errores[campo] = Array.isArray(mensaje) ? mensaje[0] : mensaje;
  }
  return Object.keys(errores).length ? errores : { general: error.mensaje };
}

// "Cerrar sesión" (botón secundario, design.md 5.1). Solo se muestra con sesión.
// onCerrarSesion es cerrar() de M1App: con éxito lleva a Iniciar sesión; si falla, M1App marca errorCierre.
function CerrarSesion({ onCerrarSesion, errorCierre }) {
  const { usuario } = usePortal();
  const [cerrando, setCerrando] = useState(false);
  if (!usuario) return null;

  async function cerrar() {
    setCerrando(true);
    await onCerrarSesion();
    setCerrando(false);
  }

  return (
    <div className="mt-6 space-y-3 border-t border-gray-200 pt-6">
      {errorCierre && <Aviso>No se pudo cerrar la sesión. Intente de nuevo.</Aviso>}
      <button type="button" onClick={cerrar} disabled={cerrando}
        className="flex w-full min-h-[44px] items-center justify-center gap-2 rounded-lg border border-brandBlue px-4 py-2 text-base font-semibold text-brandBlue transition enabled:hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-brandBlue focus:ring-offset-2 disabled:cursor-not-allowed disabled:border-gray-300 disabled:bg-gray-100 disabled:text-gray-500">
        {cerrando ? 'Cerrando sesión…' : 'Cerrar sesión'}
      </button>
    </div>
  );
}

// Mi cuenta: ver y actualizar los datos de la persona con sesión (GET /clientes/sesion y PUT /clientes/{id_cliente})
// y sus direcciones de envío (DireccionesSeccion). Cada persona solo ve sus propios datos (RN-M1-16). El correo no
// se puede cambiar hasta que el núcleo lo permita.
export default function PerfilView({ onIrIngresar, onCerrarSesion, errorCierre = false }) {
  const cierre = <CerrarSesion onCerrarSesion={onCerrarSesion} errorCierre={errorCierre} />;
  const { usuario } = usePortal();
  const esEncargado = ['encargado_comercio', 'encargado_plataforma'].includes(usuario?.rol);
  const [cliente, setCliente] = useState(null);
  const [f, setF] = useState(null);
  const [carga, setCarga] = useState({ estado: 'cargando' });
  const [errores, setErrores] = useState({});
  const [enviando, setEnviando] = useState(false);
  const [guardado, setGuardado] = useState(false);

  useEffect(() => {
    let vigente = true;
    obtenerClienteSesion().then((respuesta) => {
      if (!vigente) return;
      if (respuesta.ok) {
        setCliente(respuesta.datos);
        setF({ nombre: respuesta.datos.nombre, apellidos: respuesta.datos.apellidos, telefono: respuesta.datos.telefono });
        setCarga({ estado: 'listo' });
      } else {
        setCarga({ estado: 'error', http: respuesta.estado, error: respuesta.error });
      }
    });
    return () => { vigente = false; };
  }, []);

  const set = (k) => (e) => { setGuardado(false); setF({ ...f, [k]: e.target.value }); };
  const enfocarPrimerError = (errs) => document.getElementById(campos.find((c) => errs[c.id])?.id)?.focus();

  async function enviar(e) {
    e.preventDefault();
    setGuardado(false);
    const errs = validar(f);
    setErrores(errs);
    if (Object.keys(errs).length) { enfocarPrimerError(errs); return; }
    setEnviando(true);
    const datos = {
      nombre: f.nombre.trim(),
      apellidos: f.apellidos.trim(),
      correo_electronico: cliente.correo_electronico,
      telefono: f.telefono.trim(),
    };
    const respuesta = await actualizarCliente(cliente.id_cliente, datos);
    setEnviando(false);
    if (respuesta.ok) {
      setCliente({ ...cliente, ...datos });
      setGuardado(true);
      return;
    }
    const errsServidor = erroresDelServidor(respuesta.error);
    setErrores(errsServidor);
    enfocarPrimerError(errsServidor);
  }

  if (carga.estado === 'cargando') {
    return (
      <PaginaCuenta migas="Mi cuenta" titulo="Mi cuenta">
        <p role="status" className="text-center text-sm text-gray-600">Cargando sus datos…</p>
      </PaginaCuenta>
    );
  }

  if (carga.estado === 'error') {
    const sinSesion = carga.http === 401;
    const noEsCliente = carga.http === 404;
    return (
      <PaginaCuenta migas="Mi cuenta" titulo="Mi cuenta">
        <div className="space-y-4">
          <Aviso>
            {sinSesion && 'Inicie sesión para ver sus datos.'}
            {noEsCliente && 'Esta cuenta no pertenece a un cliente del portal.'}
            {!sinSesion && !noEsCliente && carga.error.mensaje}
          </Aviso>
          {sinSesion && <BotonPrincipal type="button" onClick={onIrIngresar}>Ingresar</BotonPrincipal>}
          {noEsCliente && esEncargado && (
            <BotonPrincipal type="button" onClick={() => { window.location.href = '/m2.html'; }}>Ir a mi panel</BotonPrincipal>
          )}
        </div>
        {cierre}
      </PaginaCuenta>
    );
  }

  return (
    <PaginaCuenta migas="Mi cuenta" titulo="Mi cuenta">
      {typeof window !== 'undefined' && new URLSearchParams(window.location.search).get('motivo') === 'checkout_dir' && (
        <div className="mb-6 rounded-xl border border-blue-200 bg-blue-50 p-4 space-y-2">
          <p className="text-sm font-bold text-brandDarkBlue">Gestionar dirección de entrega</p>
          <p className="text-xs text-blue-800">
            Agregue o actualice su dirección de envío aquí. Al terminar, regrese al checkout para completar su compra.
          </p>
          <div className="pt-1">
            <a
              href="/carrito.html"
              className="inline-flex items-center gap-1.5 text-xs font-bold text-white bg-brandDarkBlue hover:bg-brandBlue px-4 py-2 rounded-lg shadow-xs transition"
            >
              ← Volver al checkout
            </a>
          </div>
        </div>
      )}
      <form onSubmit={enviar} noValidate className="space-y-4">
        {guardado && <Aviso tipo="exito">Sus datos se actualizaron.</Aviso>}
        {errores.general && <Aviso>{errores.general}</Aviso>}
        {campos.map(({ id, etiqueta, icono, type, autoComplete }) => (
          <CampoConIcono key={id} id={id} etiqueta={etiqueta} icono={icono} type={type}
            autoComplete={autoComplete} value={f[id]} onChange={set(id)} error={errores[id]} />
        ))}
        <div>
          <CampoConIcono id="correo_electronico" etiqueta="Correo electrónico" icono="correo" type="email"
            value={cliente.correo_electronico} disabled readOnly error={errores.correo_electronico}
            {...(errores.correo_electronico ? {} : { 'aria-describedby': 'correo-ayuda' })} />
          {!errores.correo_electronico && (
            <p id="correo-ayuda" className="mt-1 text-xs text-gray-600">Por ahora no se puede cambiar el correo electrónico.</p>
          )}
        </div>
        <BotonPrincipal type="submit" disabled={enviando}>{enviando ? 'Guardando…' : 'Guardar cambios'}</BotonPrincipal>
      </form>
      {/* Fuera del formulario de datos: la sección tiene sus propios formularios. */}
      <div className="mt-6">
        <DireccionesSeccion idCliente={cliente.id_cliente} />
      </div>
      {cierre}
    </PaginaCuenta>
  );
}
