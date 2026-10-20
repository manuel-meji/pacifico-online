import { useEffect, useState } from 'react';
import { Encabezado } from './components/componentes';
import { correoDuplicado, credencialesIncorrectas, registroExitoso } from './services/datosMock';
import CrearCuentaView from './views/CrearCuentaView';
import FichaComercioView from './views/FichaComercioView';
import IniciarSesionView from './views/IniciarSesionView';
import InicioView from './views/InicioView';

const esperar = () => new Promise((r) => setTimeout(r, 600));

// Título de la pestaña por pantalla. La ficha de comercio conserva el título de m1.html.
const titulos = {
  inicio: 'Pacíficos Online · Inicio',
  ingresar: 'Pacíficos Online · M1 Iniciar sesión',
  crear: 'Pacíficos Online · M1 Crear cuenta',
};

// Punto de entrada del módulo M1 para el prototipo.
// `pantalla` elige la pantalla inicial: 'comercio' (por defecto), 'ingresar' o 'crear'.
// `simular` fuerza las respuestas alternativas durante la validación:
//   Ficha de comercio (CU-009): 'suspendido', 'vacio' o 'sininventario'.
//   'correo'       → POST /api/m1/v1/clientes responde 409 ERR-M1-001 (correo ya registrado, RN-M1-14)
//   'credenciales' → el ingreso responde que el correo o la contraseña no son correctos
export default function M1App({ pantalla: pantallaInicial = 'comercio', simular = null }) {
  const [pantalla, setPantalla] = useState(pantallaInicial);

  useEffect(() => {
    if (titulos[pantalla]) document.title = titulos[pantalla];
  }, [pantalla]);

  // Doble de POST /api/m1/v1/clientes (registrarCliente). Se sustituye por la llamada real cuando el módulo esté desplegado.
  async function registrar() {
    await esperar();
    if (simular === 'correo') return { ok: false, ...correoDuplicado };
    return { ok: true, ...registroExitoso };
  }

  // Doble del ingreso del núcleo compartido (su contrato todavía no está publicado).
  async function ingresar() {
    await esperar();
    if (simular === 'credenciales') return { ok: false, ...credencialesIncorrectas };
    return { ok: true };
  }
   if (pantalla === 'inicio') {
  return <InicioView onIr={setPantalla} />;

}

  if (pantalla !== 'ingresar' && pantalla !== 'crear') {
    return <FichaComercioView idComercio={7} simular={simular} />;
  }

  return (
    <div className="min-h-screen overflow-x-hidden bg-brandLightBg font-sans font-normal text-gray-800">
      <Encabezado pantalla={pantalla} onIr={setPantalla} />
      {pantalla === 'ingresar' && <IniciarSesionView onIngresar={ingresar} onIrCrear={() => setPantalla('crear')} />}
      {pantalla === 'crear' && <CrearCuentaView onRegistrar={registrar} onIrIngresar={() => setPantalla('ingresar')} />}
    </div>
  );
}
