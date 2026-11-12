async function solicitar(url) {
  const respuesta = await fetch(url, {
    method: 'GET',
    credentials: 'include',
    headers: {
      Accept: 'application/json',
    },
  });

  const datos = await respuesta.json();

  if (!respuesta.ok) {
    const mensaje =
      datos?.error?.mensaje ??
      'No fue posible consultar el plan de cuentas.';

    throw new Error(mensaje);
  }

  return datos.data;
}

export async function listarPlanCuentas({
  soloActivas = false,
} = {}) {
  const parametros = new URLSearchParams();

  if (soloActivas) {
    parametros.set('solo_activas', '1');
  }

  const query = parametros.toString();

  return solicitar(
    `/api/m7/v1/cuentas${query ? `?${query}` : ''}`
  );
}

export async function consultarCuenta(idCuentaContable) {
  return solicitar(
    `/api/m7/v1/cuentas/${idCuentaContable}`
  );
}