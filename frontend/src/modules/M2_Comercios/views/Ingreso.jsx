import { useState } from 'react';
import { LogIn } from 'lucide-react';
import { iniciarSesion } from '../../Nucleo/services/sesionApi';
import { AvisoError, Boton, C, Campo, Tarjeta, erroresDeCampo, mensajeDe, textoDeError } from '../components/comunes';


export default function Ingreso({ onIngreso }) {
  const [f, setF] = useState({ correo: '', contrasena: '' });
  const [resultado, setResultado] = useState(null);
  const [enviando, setEnviando] = useState(false);
  const cambiar = (e) => setF({ ...f, [e.target.name]: e.target.value });
  const errores = erroresDeCampo(resultado);

  const enviar = async (e) => {
    e.preventDefault();
    setEnviando(true);
    const r = await iniciarSesion(f.correo.trim(), f.contrasena);
    setEnviando(false);
    if (r.ok) {
      onIngreso(r.datos.user);
      return;
    }
    setResultado(r);
  };

 
  const general = resultado && Object.keys(errores).length === 0 ? textoDeError(resultado) : '';

  return (
    <Tarjeta className="mx-auto max-w-md">
      <h1 className={`text-2xl font-extrabold ${C.marinoTexto}`}>Ingresar</h1>
      <p className="mt-1 text-sm text-slate-500">Para encargados de comercio y de la plataforma.</p>
      <form onSubmit={enviar} noValidate>
        <Campo etiqueta="Correo" name="correo" type="email" autoComplete="username" required
          value={f.correo} onChange={cambiar} error={mensajeDe(errores, 'correo')} />
        <Campo etiqueta="Contraseña" name="contrasena" type="password" autoComplete="current-password" required
          value={f.contrasena} onChange={cambiar} error={mensajeDe(errores, 'contrasena')} />
        <AvisoError mensaje={general} />
        <Boton type="submit" className="mt-5 w-full py-3 text-base" disabled={enviando || !f.correo || !f.contrasena}>
          <LogIn size={18} /> {enviando ? 'Ingresando…' : 'Ingresar'}
        </Boton>
      </form>
    </Tarjeta>
  );
}