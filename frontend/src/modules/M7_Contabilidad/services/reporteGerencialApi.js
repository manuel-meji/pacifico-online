
const BASE = '/api/m7/v1';

export async function consultarReporteGerencial(filtros = {}) {
  const consulta = new URLSearchParams();

  Object.entries(filtros).forEach(([clave, valor]) => {
    if (valor !== null && valor !== undefined && valor !== '') {
      consulta.set(clave, valor);
    }
  });

  let respuesta;

  try {
    respuesta = await fetch(
      `${BASE}/reporte-gerencial?${consulta.toString()}`,
      {
        method: 'GET',
        credentials: 'include',
        headers: {
          Accept: 'application/json',
        },
      }
    );
  } catch {
    throw new Error('No se pudo conectar con el servidor.');
  }

  const cuerpo = await respuesta.json().catch(() => ({}));

  if (!respuesta.ok || cuerpo.success === false) {
    throw new Error(
      cuerpo.error?.mensaje
      || cuerpo.message
      || `Error al consultar el reporte (HTTP ${respuesta.status}).`
    );
  }

  return cuerpo.data;
}
