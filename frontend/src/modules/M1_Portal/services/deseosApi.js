// Lista de deseos de M5 desde la ficha de producto del portal.
// Usa la ruta pública de M5 (POST /api/m5/v1/deseos con { id_variacion }); M1 no guarda nada propio.
// La ruta está en el grupo web de Laravel: lleva el token CSRF de Sanctum (cookie XSRF-TOKEN →
// cabecera X-XSRF-TOKEN), igual que services/m1Api.js. Los errores llegan como { error: { codigo, mensaje, detalles } }.
const URL_DESEOS = '/api/m5/v1/deseos';

const SIN_CONEXION = 'No se pudo conectar con el servidor. Intente de nuevo en unos minutos.';

function leerCookie(nombre) {
  const par = document.cookie.split('; ').find((c) => c.startsWith(`${nombre}=`));
  return par ? decodeURIComponent(par.slice(nombre.length + 1)) : null;
}

async function obtenerCookieCsrf() {
  await fetch('/sanctum/csrf-cookie', { credentials: 'include' });
}

async function enviar(idVariacion) {
  if (!leerCookie('XSRF-TOKEN')) await obtenerCookieCsrf();
  const xsrf = leerCookie('XSRF-TOKEN');
  return fetch(URL_DESEOS, {
    method: 'POST',
    credentials: 'include',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      ...(xsrf ? { 'X-XSRF-TOKEN': xsrf } : {}),
    },
    body: JSON.stringify({ id_variacion: idVariacion }),
  });
}

// Guarda una variación en la lista de deseos del cliente con sesión.
// Devuelve { ok: true, mensaje } (201) o { ok: false, mensaje } con el texto para mostrar.
export async function agregarADeseos(idVariacion) {
  let resp;
  try {
    resp = await enviar(idVariacion);
    // 419: token CSRF ausente o vencido. Se pide uno nuevo y se reintenta una sola vez.
    if (resp.status === 419) {
      await obtenerCookieCsrf();
      resp = await enviar(idVariacion);
    }
  } catch {
    return { ok: false, mensaje: SIN_CONEXION };
  }

  const cuerpo = await resp.json().catch(() => null);

  if (resp.ok) return { ok: true, mensaje: cuerpo?.mensaje || 'Guardado en su lista de deseos' };
  if (resp.status === 401) return { ok: false, mensaje: 'Su sesión venció. Inicie sesión para guardar en su lista de deseos.' };
  return { ok: false, mensaje: cuerpo?.error?.mensaje || 'No se pudo guardar el producto en su lista de deseos.' };
}
