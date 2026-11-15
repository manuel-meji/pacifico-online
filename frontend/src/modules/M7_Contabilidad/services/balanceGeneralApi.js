
const BASE = '/api/m7/v1';

export async function consultarBalanceGeneral(fechaCorte, idComercio = null) {
  const parametros = new URLSearchParams({
    fecha_corte: fechaCorte,
  });

  if (idComercio !== null && idComercio !== undefined) {
    parametros.set('id_comercio', String(idComercio));
  }

  let respuesta;

  try {
    respuesta = await fetch(`${BASE}/balance-general?${parametros}`, {
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
      `Error al consultar el Balance general (HTTP ${respuesta.status}).`;

    throw new Error(mensaje);
  }

  return cuerpo.data;
}
