import { useState } from 'react';
import { Aviso, BotonPrincipal, CampoConIcono, EnlaceCuenta, PaginaCuenta } from '../components/componentes';

const CORREO_VALIDO = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

// Campos del esquema RegistroCliente (contracts/m1-portal/v1/openapi.yaml) y sus límites.
// `confirmar` solo existe en pantalla y no se envía.
const campos = [
  { id: 'nombre', etiqueta: 'Nombre', icono: 'persona', max: 100, falta: 'Ingrese su nombre.', autoComplete: 'given-name' },
  { id: 'apellidos', etiqueta: 'Apellidos', icono: 'persona', max: 150, falta: 'Ingrese sus apellidos.', autoComplete: 'family-name' },
  { id: 'correo_electronico', etiqueta: 'Correo electrónico', icono: 'correo', max: 150, falta: 'Ingrese su correo electrónico.', type: 'email', autoComplete: 'email' },
  { id: 'telefono', etiqueta: 'Teléfono', icono: 'telefono', max: 20, falta: 'Ingrese su teléfono.', type: 'tel', autoComplete: 'tel' },
  { id: 'direccion_envio', etiqueta: 'Dirección de envío', icono: 'ubicacion', max: 300, falta: 'Ingrese su dirección de envío.', multilinea: true, autoComplete: 'street-address' },
  { id: 'contrasena', etiqueta: 'Contraseña', icono: 'candado', falta: 'Ingrese una contraseña.', type: 'password', autoComplete: 'new-password' },
  { id: 'confirmar', etiqueta: 'Confirmar contraseña', icono: 'candado', falta: 'Confirme su contraseña.', type: 'password', autoComplete: 'new-password' },
];

const vacio = Object.fromEntries(campos.map((c) => [c.id, '']));

function validar(f) {
  const errores = {};
  for (const c of campos) {
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

// Crear cuenta. onRegistrar(datos) llama a POST /api/m1/v1/clientes (registrarCliente) y devuelve { ok } o { ok:false, error }.
export default function CrearCuentaView({ onRegistrar, onIrIngresar }) {
  const [f, setF] = useState(vacio);
  const [errores, setErrores] = useState({});
  const [enviando, setEnviando] = useState(false);
  const [creada, setCreada] = useState(false);
  const set = (k) => (e) => setF({ ...f, [k]: e.target.value });
  const enfocarPrimerError = (errs) => document.getElementById(campos.find((c) => errs[c.id])?.id)?.focus();

  async function enviar(e) {
    e.preventDefault();
    const errs = validar(f);
    setErrores(errs);
    if (Object.keys(errs).length) { enfocarPrimerError(errs); return; }
    setEnviando(true);
    const respuesta = await onRegistrar({
      nombre: f.nombre.trim(),
      apellidos: f.apellidos.trim(),
      correo_electronico: f.correo_electronico.trim(),
      telefono: f.telefono.trim(),
      direccion_envio: f.direccion_envio.trim(),
      contrasena: f.contrasena,
    });
    setEnviando(false);
    if (respuesta.ok) { setCreada(true); return; }
    // 409 ERR-M1-001: el correo ya pertenece a otro cliente (RN-M1-14).
    const errsServidor = { correo_electronico: `${respuesta.error.mensaje} (${respuesta.error.codigo})` };
    setErrores(errsServidor);
    enfocarPrimerError(errsServidor);
  }

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
    <PaginaCuenta migas="Crear cuenta" titulo="Crear cuenta">
      <form onSubmit={enviar} noValidate className="space-y-4">
        {campos.map(({ id, etiqueta, icono, type, multilinea, autoComplete }) => (
          <CampoConIcono key={id} id={id} etiqueta={etiqueta} icono={icono} type={type} multilinea={multilinea}
            autoComplete={autoComplete} value={f[id]} onChange={set(id)} error={errores[id]} />
        ))}
        <BotonPrincipal type="submit" disabled={enviando}>{enviando ? 'Enviando…' : 'Crear cuenta'}</BotonPrincipal>
      </form>
      <EnlaceCuenta pregunta="¿Ya tiene cuenta?" texto="Ingresar" onClick={onIrIngresar} />
    </PaginaCuenta>
  );
}
