import { useState } from 'react';
import { Aviso, BotonPrincipal, CampoConIcono, EnlaceCuenta, PaginaCuenta } from '../components/componentes';

const CORREO_VALIDO = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

function validar(f) {
  const errores = {};
  if (!f.correo.trim()) errores.correo = 'Ingrese su correo electrónico.';
  else if (!CORREO_VALIDO.test(f.correo.trim())) errores.correo = 'Ingrese un correo válido, por ejemplo nombre@correo.com.';
  if (!f.contrasena) errores.contrasena = 'Ingrese su contraseña.';
  return errores;
}

// Iniciar sesión. onIngresar(datos) simula el ingreso del núcleo y devuelve { ok } o { ok:false, error }.
export default function IniciarSesionView({ onIngresar, onIrCrear }) {
  const [f, setF] = useState({ correo: '', contrasena: '' });
  const [errores, setErrores] = useState({});
  const [resultado, setResultado] = useState(null);
  const [enviando, setEnviando] = useState(false);
  const set = (k) => (e) => setF({ ...f, [k]: e.target.value });

  async function enviar(e) {
    e.preventDefault();
    setResultado(null);
    const errs = validar(f);
    setErrores(errs);
    if (Object.keys(errs).length) { document.getElementById(errs.correo ? 'correo' : 'contrasena')?.focus(); return; }
    setEnviando(true);
    const respuesta = await onIngresar({ correo_electronico: f.correo.trim(), contrasena: f.contrasena });
    setEnviando(false);
    setResultado(respuesta.ok
      ? { tipo: 'exito', texto: 'Ingresó correctamente (simulado).' }
      : { tipo: 'error', texto: respuesta.error.mensaje });
  }

  return (
    <PaginaCuenta migas="Ingresar" titulo="Iniciar sesión">
      <form onSubmit={enviar} noValidate className="space-y-4">
        <CampoConIcono id="correo" etiqueta="Correo electrónico" icono="correo" type="email" autoComplete="email"
          value={f.correo} onChange={set('correo')} error={errores.correo} />
        <CampoConIcono id="contrasena" etiqueta="Contraseña" icono="candado" type="password" autoComplete="current-password"
          value={f.contrasena} onChange={set('contrasena')} error={errores.contrasena} />
        {resultado && <Aviso tipo={resultado.tipo}>{resultado.texto}</Aviso>}
        <BotonPrincipal type="submit" disabled={enviando}>{enviando ? 'Enviando…' : 'Ingresar'}</BotonPrincipal>
      </form>
      <EnlaceCuenta pregunta="¿No tiene cuenta?" texto="Registrarse" onClick={onIrCrear} />
    </PaginaCuenta>
  );
}
