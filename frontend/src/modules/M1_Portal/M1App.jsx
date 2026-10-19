import { useState } from 'react';
import { Encabezado } from './components/componentes';
import { correoDuplicado, credencialesIncorrectas, registroExitoso } from './services/datosMock';
import CrearCuentaView from './views/CrearCuentaView';
import IniciarSesionView from './views/IniciarSesionView';

const esperar = () => new Promise((r) => setTimeout(r, 600));

// Punto de entrada del módulo M1. `simular` fuerza las respuestas alternativas durante la validación:
//   'correo'       → POST /api/m1/v1/clientes responde 409 ERR-M1-001 (correo ya registrado, RN-M1-14)
//   'credenciales' → el ingreso responde que el correo o la contraseña no son correctos
export default function M1App({ simular = null }) {
  const [pantalla, setPantalla] = useState('ingresar');

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

  return (
    <div className="min-h-screen overflow-x-hidden bg-brandLightBg font-sans font-normal text-gray-800">
      <Encabezado pantalla={pantalla} onIr={setPantalla} />
      {pantalla === 'ingresar' && <IniciarSesionView onIngresar={ingresar} onIrCrear={() => setPantalla('crear')} />}
      {pantalla === 'crear' && <CrearCuentaView onRegistrar={registrar} onIrIngresar={() => setPantalla('ingresar')} />}
    </div>
  );
}
