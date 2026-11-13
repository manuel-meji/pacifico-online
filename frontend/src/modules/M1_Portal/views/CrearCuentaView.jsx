import { useState } from 'react';
import { Aviso, BotonPrincipal, CampoConIcono, CampoDireccion, EnlaceCuenta, PaginaCuenta } from '../components/componentes';
import { cambiarCampo, camposDireccion, datosDireccion, validarDireccion } from '../services/camposDireccion';

const CORREO_VALIDO = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

// Campos del esquema RegistroCliente (contracts/m1-portal/v1/openapi.yaml, versión 1.2.0) y sus
// límites, iguales a las columnas de la tabla clientes. `confirmar` solo existe en pantalla y no se envía.
// El orden es el de la pantalla: en pantallas anchas van de a dos por fila y `completo` ocupa la fila entera.
const camposCliente = [
  { id: 'nombre', etiqueta: 'Nombre', icono: 'persona', max: 80, falta: 'Ingrese su nombre.', autoComplete: 'given-name' },
  { id: 'apellidos', etiqueta: 'Apellidos', icono: 'persona', max: 120, falta: 'Ingrese sus apellidos.', autoComplete: 'family-name' },
  { id: 'cedula', etiqueta: 'Cédula', icono: 'persona', max: 20, falta: 'Ingrese su cédula.', autoComplete: 'off' },
  { id: 'telefono', etiqueta: 'Teléfono', icono: 'telefono', max: 20, falta: 'Ingrese su teléfono.', type: 'tel', autoComplete: 'tel' },
  { id: 'correo_electronico', etiqueta: 'Correo electrónico', icono: 'correo', max: 150, falta: 'Ingrese su correo electrónico.', type: 'email', autoComplete: 'email', completo: true },
  { id: 'contrasena', etiqueta: 'Contraseña', icono: 'candado', falta: 'Ingrese una contraseña.', type: 'password', autoComplete: 'new-password' },
  { id: 'confirmar', etiqueta: 'Confirmar contraseña', icono: 'candado', falta: 'Confirme su contraseña.', type: 'password', autoComplete: 'new-password' },
];

// El objeto `direccion` del registro usa los campos compartidos de services/camposDireccion.js;
// se guarda como la dirección principal del cliente.
const campos = [...camposCliente, ...camposDireccion];

const vacio = { ...Object.fromEntries(campos.map((c) => [c.id, ''])), alias_direccion: 'Casa' };

function validar(f) {
  const errores = validarDireccion(f);
  for (const c of camposCliente) {
    const valor = c.type === 'password' ? f[c.id] : f[c.id].trim();
    if (!valor) errores[c.id] = c.falta;
    else if (c.max && valor.length > c.max) errores[c.id] = `Máximo ${c.max} caracteres.`;
  }
  if (!errores.correo_electronico && !CORREO_VALIDO.test(f.correo_electronico.trim())) {
    errores.correo_electronico = 'Ingrese un correo válido, por ejemplo nombre@correo.com.';
  }
  if (!errores.contrasena && f.contrasena.length < 8) errores.contrasena = 'La contraseña debe tener al menos 8 caracteres.';
  if (!errores.confirmar && f.confirmar !== f.contrasena) errores.confirmar = 'Las contraseñas no coinciden.';
  return errores;
}

// Cuerpo de POST /api/m1/v1/clientes según RegistroCliente 1.2.0.
function cuerpoRegistro(f) {
  return {
    nombre: f.nombre.trim(),
    apellidos: f.apellidos.trim(),
    cedula: f.cedula.trim(),
    correo_electronico: f.correo_electronico.trim(),
    telefono: f.telefono.trim(),
    contrasena: f.contrasena,
    direccion: datosDireccion(f),
  };
}

// Errores del servidor → errores por campo. ERR-M1-001 (409) es el correo repetido (RN-M1-14); ERR-005 (422)
// trae `detalles` por campo, y los de la dirección vienen como `direccion.<campo>`. Al cliente se le muestra
// solo el mensaje, sin el código de error.
function erroresDelServidor(error) {
  if (error.codigo === 'ERR-M1-001') {
    return { correo_electronico: error.mensaje };
  }
  const errores = {};
  for (const [campo, mensaje] of Object.entries(error.detalles || {})) {
    const id = campo.replace(/^direccion\./, '');
    if (campos.some((c) => c.id === id)) errores[id] = Array.isArray(mensaje) ? mensaje[0] : mensaje;
  }
  return Object.keys(errores).length ? errores : { general: error.mensaje };
}

// Crear cuenta. onRegistrar(datos) llama a POST /api/m1/v1/clientes (registrarCliente) y devuelve { ok } o { ok:false, error }.
export default function CrearCuentaView({ onRegistrar, onIrIngresar }) {
  const [f, setF] = useState(vacio);
  const [errores, setErrores] = useState({});
  const [enviando, setEnviando] = useState(false);
  const [creada, setCreada] = useState(false);
  const set = (k) => (e) => setF({ ...f, [k]: e.target.value });
  const cambiar = (k) => (valor) => setF((anterior) => cambiarCampo(anterior, k, valor));
  const enfocarPrimerError = (errs) => document.getElementById(campos.find((c) => errs[c.id])?.id)?.focus();

  async function enviar(e) {
    e.preventDefault();
    const errs = validar(f);
    setErrores(errs);
    if (Object.keys(errs).length) { enfocarPrimerError(errs); return; }
    setEnviando(true);
    const respuesta = await onRegistrar(cuerpoRegistro(f));
    setEnviando(false);
    if (respuesta.ok) { setCreada(true); return; }
    const errsServidor = erroresDelServidor(respuesta.error);
    setErrores(errsServidor);
    enfocarPrimerError(errsServidor);
  }

  const campo = ({ id, etiqueta, icono, type, multilinea, autoComplete, completo }) => (
    <CampoConIcono key={id} id={id} etiqueta={etiqueta} icono={icono} type={type} multilinea={multilinea}
      autoComplete={autoComplete} value={f[id]} onChange={set(id)} error={errores[id]} clase={completo ? 'md:col-span-2' : ''} />
  );

  if (creada) {
    return (
      <PaginaCuenta migas="Crear cuenta" titulo="Crear cuenta">
        <div className="space-y-4">
          <Aviso tipo="exito">Cuenta creada. Ya puede ingresar con su correo y contraseña.</Aviso>
          <BotonPrincipal type="button" onClick={onIrIngresar}>Ingresar</BotonPrincipal>
        </div>
      </PaginaCuenta>
    );
  }

  return (
    <PaginaCuenta migas="Crear cuenta" titulo="Crear cuenta" amplia>
      <form onSubmit={enviar} noValidate className="space-y-6">
        {errores.general && <Aviso>{errores.general}</Aviso>}
        <div className="grid gap-4 md:grid-cols-2">
          {camposCliente.map(campo)}
        </div>
        <fieldset className="border-t border-gray-200 pt-4">
          <legend className="text-base font-bold text-gray-800">Dirección de envío principal</legend>
          <p className="mb-4 mt-1 text-sm text-gray-600">Después podrá agregar otras direcciones desde su cuenta.</p>
          <div className="grid gap-4 md:grid-cols-2">
            {camposDireccion.map((c) => (
              <CampoDireccion key={c.id} campo={c} id={c.id} valor={f[c.id]} onCambio={cambiar(c.id)} error={errores[c.id]} formulario={f} />
            ))}
          </div>
        </fieldset>
        <div className="md:mx-auto md:max-w-sm">
          <BotonPrincipal type="submit" disabled={enviando}>{enviando ? 'Enviando…' : 'Crear cuenta'}</BotonPrincipal>
        </div>
      </form>
      <EnlaceCuenta pregunta="¿Ya tiene cuenta?" texto="Ingresar" onClick={onIrIngresar} />
    </PaginaCuenta>
  );
}
