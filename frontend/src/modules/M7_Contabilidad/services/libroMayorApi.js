
const BASE = '/api/m7/v1';

function construirConsulta(parametros) {
  const consulta = new URLSearchParams();

  Object.entries(parametros).forEach(([clave, valor]) => {
    if (valor !== null && valor !== undefined && valor !== '') {
      consulta.set(clave, valor);
    }
  });

  const texto = consulta.toString();
  return texto ? `?${texto}` : '';
}

async function llamar(ruta) {
  let respuesta;

  try {
    respuesta = await fetch(`${BASE}${ruta}`, {
      method: 'GET',
      credentials: 'include',
      headers: {
        Accept: 'application/json',
      },
    });
  } catch {
    throw new Error('No se pudo conectar con el servidor.');
  }

  const cuerpo = await respuesta.json().catch(() => ({}));

  if (!respuesta.ok || cuerpo.success === false) {
    const mensaje =
      cuerpo.error?.mensaje ||
      cuerpo.message ||
      `Error al consultar el Libro Mayor (HTTP ${respuesta.status}).`;

    throw new Error(mensaje);
  }

  return cuerpo.data;
}

export function listarCuentasMayor() {
  return llamar('/libro-mayor/cuentas');
}

export function consultarLibroMayor(filtros) {
  return llamar(`/libro-mayor${construirConsulta(filtros)}`);
}
