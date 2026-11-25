
const BASE = '/api/m2/v1';
const METODOS_CON_CSRF = ['POST', 'PUT', 'PATCH', 'DELETE'];


const SIN_CONEXION = {
  codigo: 'ERR-006',
  mensaje: 'No se pudo conectar con la plataforma. Intentá de nuevo en unos minutos.',
  detalles: null,
};

function leerCookie(nombre) {
  const par = document.cookie.split('; ').find((c) => c.startsWith(`${nombre}=`));
  return par ? decodeURIComponent(par.slice(nombre.length + 1)) : null;
}

async function obtenerCookieCsrf() {
  await fetch('/sanctum/csrf-cookie', { credentials: 'include' });
}

function conConsulta(ruta, consulta = {}) {
  const q = new URLSearchParams();
  Object.entries(consulta).forEach(([clave, valor]) => {
    if (valor !== undefined && valor !== null && valor !== '') q.set(clave, valor);
  });
  const texto = q.toString();
  return texto ? `${ruta}?${texto}` : ruta;
}


async function llamar(ruta, opciones = {}, reintentado = false) {
  const metodo = (opciones.method || 'GET').toUpperCase();

  if (METODOS_CON_CSRF.includes(metodo) && !leerCookie('XSRF-TOKEN')) {
    try {
      await obtenerCookieCsrf();
    } catch {
      return { ok: false, estado: 0, error: SIN_CONEXION };
    }
  }

  const xsrf = leerCookie('XSRF-TOKEN');
  const headers = { Accept: 'application/json', ...(xsrf ? { 'X-XSRF-TOKEN': xsrf } : {}) };
  let body;
  if (opciones.formulario) {
    body = opciones.formulario;
  } else if (opciones.json !== undefined) {
    headers['Content-Type'] = 'application/json';
    body = JSON.stringify(opciones.json);
  }

  let resp;
  try {
    resp = await fetch(`${BASE}${ruta}`, { method: metodo, credentials: 'include', headers, body });
  } catch {
    return { ok: false, estado: 0, error: SIN_CONEXION };
  }

  
  if (resp.status === 419 && !reintentado && METODOS_CON_CSRF.includes(metodo)) {
    try {
      await obtenerCookieCsrf();
    } catch {
      return { ok: false, estado: 0, error: SIN_CONEXION };
    }
    return llamar(ruta, opciones, true);
  }

  const cuerpo = await resp.json().catch(() => null);
  if (resp.ok) return { ok: true, datos: cuerpo };

  if (resp.status === 429) {
    return {
      ok: false,
      estado: 429,
      error: { codigo: 'ERR-429', mensaje: 'Demasiados intentos. Esperá un minuto y volvé a intentarlo.', detalles: null },
    };
  }

  return { ok: false, estado: resp.status, error: cuerpo?.error || SIN_CONEXION };
}


function agregar(formulario, clave, valor) {
  if (valor === undefined || valor === null) return;
  if (valor instanceof Blob) {
    formulario.append(clave, valor);
  } else if (Array.isArray(valor)) {
    valor.forEach((v, i) => agregar(formulario, `${clave}[${i}]`, v));
  } else if (typeof valor === 'object') {
    Object.entries(valor).forEach(([k, v]) => agregar(formulario, `${clave}[${k}]`, v));
  } else if (typeof valor === 'boolean') {
    formulario.append(clave, valor ? '1' : '0');
  } else {
    formulario.append(clave, String(valor));
  }
}

function aFormulario(datos, metodoSimulado) {
  const formulario = new FormData();
  Object.entries(datos).forEach(([clave, valor]) => agregar(formulario, clave, valor));
  if (metodoSimulado) formulario.append('_method', metodoSimulado);
  return formulario;
}

const tieneArchivos = (elementos) => elementos.some((e) => e.imagen instanceof Blob);




export function listarComerciosPublicos({ pagina, por_pagina, destacado } = {}) {
  return llamar(conConsulta('/comercios/publico', { pagina, por_pagina, destacado: destacado ? 'true' : undefined }));
}


export function obtenerComercioPublico(idComercio) {
  return llamar(`/comercios/${idComercio}/publico`);
}


// Solo los campos que espera el servidor: la clave de cada fila es de la pantalla (hallazgo 47).
const camposDocumento = (documentos) => documentos.map((d) => ({ tipo: d.tipo, fecha_vencimiento: d.fecha_vencimiento, archivo: d.archivo }));

export function registrarSolicitud(datos, documentos = []) {
  if (documentos.length === 0) return llamar('/solicitudes', { method: 'POST', json: datos });
  return llamar('/solicitudes', { method: 'POST', formulario: aFormulario({ ...datos, documentos: camposDocumento(documentos) }) });
}


// CU-0013 con sesión: la persona encargada corrige su solicitud rechazada (con documentos nuevos si hacen falta).
export function corregirMiSolicitud(datos, documentos = []) {
  if (documentos.length === 0) return llamar('/mi-solicitud/correccion', { method: 'POST', json: datos });
  return llamar('/mi-solicitud/correccion', { method: 'POST', formulario: aFormulario({ ...datos, documentos: camposDocumento(documentos) }) });
}


// Estado de la solicitud del propio comercio (encargado que ingresa antes de la aprobación).
export function miSolicitud() {
  return llamar('/mi-solicitud');
}

export function listarSolicitudes({ estado, pagina, por_pagina } = {}) {
  return llamar(conConsulta('/solicitudes', { estado, pagina, por_pagina }));
}

export function consultarSolicitud(idSolicitud) {
  return llamar(`/solicitudes/${idSolicitud}`);
}

export function aprobarSolicitud(idSolicitud) {
  return llamar(`/solicitudes/${idSolicitud}/aprobar`, { method: 'POST' });
}


export function rechazarSolicitud(idSolicitud, motivo) {
  return llamar(`/solicitudes/${idSolicitud}/rechazar`, { method: 'POST', json: { motivo } });
}


export function evaluarDocumento(idSolicitud, idDocumento, resultado, observacion) {
  return llamar(`/solicitudes/${idSolicitud}/documentos/${idDocumento}/evaluar`, {
    method: 'POST',
    json: { resultado, observacion: observacion || null },
  });
}


export function listarComercios({ estado, pagina, por_pagina } = {}) {
  return llamar(conConsulta('/comercios', { estado, pagina, por_pagina }));
}

export function consultarComercio(idComercio) {
  return llamar(`/comercios/${idComercio}`);
}

export function suspenderComercio(idComercio, motivo) {
  return llamar(`/comercios/${idComercio}/suspender`, { method: 'POST', json: { motivo } });
}

export function reactivarComercio(idComercio) {
  return llamar(`/comercios/${idComercio}/reactivar`, { method: 'POST' });
}

// Destacar comercios (publicidad): el encargado solicita; la plataforma aprueba, rechaza o destaca directamente.
export function consultarPublicidad(idComercio) {
  return llamar(`/comercios/${idComercio}/publicidad`);
}

export function solicitarPublicidad(idComercio, dias) {
  return llamar(`/comercios/${idComercio}/publicidad`, { method: 'POST', json: { dias } });
}

export function listarPublicidadPendiente() {
  return llamar('/publicidad');
}

// Destacados aprobados que no terminaron (vigentes o programados), para que la plataforma los vea o retire.
export function listarPublicidadVigente() {
  return llamar('/publicidad/vigentes');
}

// accion: aprobar, rechazar (con motivo) o retirar (con motivo).
export function gestionarPublicidad(idPublicidad, accion, motivo = null) {
  return llamar(`/publicidad/${idPublicidad}`, { method: 'POST', json: { accion, ...(motivo ? { motivo } : {}) } });
}


export function consultarMicrositio(idComercio) {
  return llamar(`/comercios/${idComercio}/micrositio`);
}

export function actualizarInformacion(idComercio, descripcion) {
  return llamar(`/comercios/${idComercio}/micrositio`, { method: 'PUT', json: { descripcion: descripcion ?? null } });
}

export function quitarLogotipo(idComercio) {
  return llamar(`/comercios/${idComercio}/micrositio/logotipo`, { method: 'DELETE' });
}

export function guardarLogotipo(idComercio, archivo) {
  return llamar(`/comercios/${idComercio}/micrositio/logotipo`, {
    method: 'POST',
    formulario: aFormulario({ logotipo: archivo }),
  });
}


export function guardarHorarios(idComercio, horarios) {
  return llamar(`/comercios/${idComercio}/micrositio/horarios`, { method: 'PUT', json: { horarios } });
}


export function guardarContactos(idComercio, contactos) {
  return llamar(`/comercios/${idComercio}/micrositio/contactos`, { method: 'PUT', json: { contactos } });
}

export function guardarPoliticas(idComercio, politicas) {
  return llamar(`/comercios/${idComercio}/micrositio/politicas`, { method: 'PUT', json: { politicas } });
}

export function gestionarBanners(idComercio, banners) {
  const ruta = `/comercios/${idComercio}/micrositio/banners`;
  if (!tieneArchivos(banners)) return llamar(ruta, { method: 'PUT', json: { banners } });
  return llamar(ruta, { method: 'POST', formulario: aFormulario({ banners }, 'PUT') });
}

export function gestionarGaleria(idComercio, galeria) {
  const ruta = `/comercios/${idComercio}/micrositio/galeria`;
  if (!tieneArchivos(galeria)) return llamar(ruta, { method: 'PUT', json: { galeria } });
  return llamar(ruta, { method: 'POST', formulario: aFormulario({ galeria }, 'PUT') });
}