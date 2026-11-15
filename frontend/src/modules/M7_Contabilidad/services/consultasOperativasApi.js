async function consultar(ruta, idComercio, periodo = {}) {
  const parametros = new URLSearchParams();
  if (idComercio) parametros.set('id_comercio', idComercio);
  if (periodo.desde) parametros.set('fecha_desde', periodo.desde);
  if (periodo.hasta) parametros.set('fecha_hasta', periodo.hasta);
  const consulta = parametros.size ? `?${parametros}` : '';
  const respuesta = await fetch(`/api/m7/v1/${ruta}${consulta}`, {
    credentials: 'include',
    headers: { Accept: 'application/json' },
  });
  const cuerpo = await respuesta.json();
  if (!respuesta.ok || cuerpo.success === false) {
    const fallo = new Error(cuerpo.error?.mensaje || cuerpo.message || 'No fue posible consultar los registros contables.');
    fallo.estado = respuesta.status;
    throw fallo;
  }
  if (!cuerpo.data) {
    throw new Error('El servidor no devolvió los registros contables esperados.');
  }
  return cuerpo.data;
}

export function consultarComercios() {
  return consultar('comercios').then((datos) => datos.comercios);
}

export function consultarSaldos(idComercio) {
  return consultar('saldos-cobrar-pagar', idComercio);
}

export function consultarCentrosCosto(idComercio, periodo) {
  return consultar('centros-costo', idComercio, periodo);
}

/** Con idCentro null consulta las líneas de ingresos y gastos sin centro asignado. */
export function consultarMovimientosCentro(idCentro, idComercio, periodo) {
  const ruta = idCentro === null ? 'sin-asignar' : idCentro;
  return consultar(`centros-costo/${ruta}/movimientos`, idComercio, periodo);
}

export function consultarAsiento(idAsiento, idComercio) {
  return consultar(`asientos/${idAsiento}`, idComercio);
}
