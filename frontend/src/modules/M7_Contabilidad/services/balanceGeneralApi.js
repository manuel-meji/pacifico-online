
const BASE = '/api/m7/v1';

export async function consultarBalanceGeneral(idComercio, fechaCorte) {
  const parametros = new URLSearchParams({
    id_comercio: String(idComercio),
    fecha_corte: fechaCorte,
  });

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
