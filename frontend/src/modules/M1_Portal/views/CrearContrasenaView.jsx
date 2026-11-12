import { useState } from 'react';
import { Aviso, BotonPrincipal, CampoConIcono, PaginaCuenta } from '../components/componentes';

const MINIMO_CONTRASENA = 8;

function validar(f) {
  const errores = {};
  if (!f.contrasena) errores.contrasena = 'Ingrese una contraseña.';
  else if (f.contrasena.length < MINIMO_CONTRASENA) errores.contrasena = `La contraseña debe tener al menos ${MINIMO_CONTRASENA} caracteres.`;
  if (!f.confirmacion) errores.confirmacion = 'Confirme la contraseña.';
  else if (f.confirmacion !== f.contrasena) errores.confirmacion = 'Las contraseñas no coinciden.';
  return errores;
}

// Definir la contraseña de una cuenta invitada (Encargado del comercio). `token` y `correo` vienen del enlace
// del correo de invitación (?pantalla=crear-contrasena&token=...&correo=...).
// onCrear({ token, correo, contrasena, contrasenaConfirmacion }) es crearContrasena del núcleo y devuelve
// { ok, datos } o { ok:false, error }. No inicia sesión: al terminar se ofrece ir a la pantalla de ingresar.
export default function CrearContrasenaView({ token, correo, onCrear, onIrIngresar, onIrInicio }) {
  const [f, setF] = useState({ contrasena: '', confirmacion: '' });
  const [errores, setErrores] = useState({});
  const [resultado, setResultado] = useState(null);
  const [enviando, setEnviando] = useState(false);
  const [definida, setDefinida] = useState(false);
  const set = (k) => (e) => setF({ ...f, [k]: e.target.value });

  if (!token || !correo) {
    return (
      <PaginaCuenta migas="Definir contraseña" titulo="Definir contraseña">
        <div className="space-y-4">
          <Aviso tipo="error">
            El enlace de invitación está incompleto. Ábralo de nuevo desde el correo que recibió o solicite una nueva invitación.
          </Aviso>
          <BotonPrincipal type="button" onClick={onIrInicio}>Ir al inicio</BotonPrincipal>
        </div>
      </PaginaCuenta>
    );
  }

  if (definida) {
    return (
      <PaginaCuenta migas="Definir contraseña" titulo="Definir contraseña">
        <div className="space-y-4">
          <Aviso tipo="exito">Contraseña definida. Ya puede iniciar sesión.</Aviso>
          <BotonPrincipal type="button" onClick={onIrIngresar}>Iniciar sesión</BotonPrincipal>
        </div>
      </PaginaCuenta>
    );
  }

  async function enviar(e) {
    e.preventDefault();
    setResultado(null);
    const errs = validar(f);
    setErrores(errs);
    if (Object.keys(errs).length) {
      document.getElementById(errs.contrasena ? 'contrasena' : 'confirmacion')?.focus();
      return;
    }
    setEnviando(true);
    const respuesta = await onCrear({ token, correo, contrasena: f.contrasena, contrasenaConfirmacion: f.confirmacion });
    setEnviando(false);
    if (respuesta.ok) { setDefinida(true); return; }
    // Solo se muestra el mensaje, nunca el código. 422: detalles.contrasena va junto al campo y
    // detalles.token (enlace inválido o vencido), como aviso general.
    const detalles = respuesta.error?.detalles;
    const mensajeContrasena = detalles?.contrasena?.[0];
    if (mensajeContrasena) {
      setErrores({ contrasena: mensajeContrasena });
      document.getElementById('contrasena')?.focus();
      return;
    }
    setResultado(detalles?.token?.[0] || respuesta.error?.mensaje || 'No se pudo definir la contraseña. Intente de nuevo.');
  }

  return (
    <PaginaCuenta migas="Definir contraseña" titulo="Definir contraseña">
      <form onSubmit={enviar} noValidate className="space-y-4">
        <div>
          <p className="text-sm font-medium text-gray-800">Correo electrónico</p>
          <p className="mt-1 flex min-h-[44px] items-center rounded-lg border border-gray-200 bg-gray-100 px-3 py-2 text-base font-medium text-gray-600 break-all">{correo}</p>
        </div>
        <CampoConIcono id="contrasena" etiqueta="Contraseña" icono="candado" type="password" autoComplete="new-password"
          value={f.contrasena} onChange={set('contrasena')} error={errores.contrasena} />
        <p className="-mt-2 text-xs text-gray-500">Mínimo {MINIMO_CONTRASENA} caracteres.</p>
        <CampoConIcono id="confirmacion" etiqueta="Confirmar contraseña" icono="candado" type="password" autoComplete="new-password"
          value={f.confirmacion} onChange={set('confirmacion')} error={errores.confirmacion} />
        {resultado && <Aviso tipo="error">{resultado}</Aviso>}
        <BotonPrincipal type="submit" disabled={enviando}>{enviando ? 'Enviando…' : 'Definir contraseña'}</BotonPrincipal>
      </form>
    </PaginaCuenta>
  );
}
