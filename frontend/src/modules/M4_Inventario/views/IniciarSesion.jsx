// Inicio de sesión con el núcleo (POST /login). La sesión queda en la cookie de Sanctum y la usan las llamadas a M4.
import { useState } from 'react';
import { Aviso, BotonPrimario, Campo, Tarjeta, claseEntrada } from '../components/componentes';
import { IconoCargando } from '../components/iconos';
import { iniciarSesion, usuarioActual } from '../services/inventarioApi';

export default function IniciarSesion({ alIniciar }) {
  const [correo, setCorreo] = useState('');
  const [contrasena, setContrasena] = useState('');
  const [error, setError] = useState(null);
  const [enviando, setEnviando] = useState(false);

  async function enviar(ev) {
    ev.preventDefault();
    setError(null);
    setEnviando(true);
    try {
      await iniciarSesion(correo.trim(), contrasena);
      alIniciar(await usuarioActual());
    } catch (falla) {
      setError(falla.error);
    } finally {
      setEnviando(false);
    }
  }

  return (
    <div className="mx-auto flex min-h-screen w-full max-w-md flex-col justify-center bg-brandLightBg p-4">
      <Tarjeta className="space-y-4">
        <div>
          <p className="text-xs text-gray-500">Pacíficos Online</p>
          <h1 className="text-lg font-bold text-brandDarkBlue">Inventario del comercio</h1>
        </div>
        {error && <Aviso tipo="error" titulo="No se pudo iniciar sesión" codigo={error.codigo}>{error.mensaje}</Aviso>}
        <form onSubmit={enviar} noValidate className="space-y-4">
          <Campo id="correo" etiqueta="Correo">
            <input id="correo" type="email" autoComplete="username" className={claseEntrada(false)} value={correo} onChange={(e) => setCorreo(e.target.value)} />
          </Campo>
          <Campo id="contrasena" etiqueta="Contraseña">
            <input id="contrasena" type="password" autoComplete="current-password" className={claseEntrada(false)} value={contrasena} onChange={(e) => setContrasena(e.target.value)} />
          </Campo>
          <BotonPrimario type="submit" cargando={enviando} disabled={!correo || !contrasena}>
            {enviando && <IconoCargando className="h-4 w-4 animate-spin" />}
            {enviando ? 'Ingresando…' : 'Ingresar'}
          </BotonPrimario>
        </form>
      </Tarjeta>
    </div>
  );
}
