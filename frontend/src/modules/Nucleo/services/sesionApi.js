// Cliente HTTP de la sesión del núcleo compartido (Nucleo).
// Rutas: POST /login, POST /logout, POST /crear-contrasena y GET /api/user. Viven en el grupo web de Laravel, así que los POST
// llevan el token CSRF de Sanctum (cookie XSRF-TOKEN → cabecera X-XSRF-TOKEN), igual que m1Api.js.
// Los errores llegan en el formato del núcleo: { error: { codigo, mensaje, detalles } }.
// Respuesta de las funciones: { ok: true, datos } o { ok: false, estado, error }.
const METODOS_CON_CSRF = ['POST', 'PUT', 'PATCH', 'DELETE'];

// Respuesta cuando el servidor no contesta o no devuelve el formato del núcleo.
const SIN_CONEXION = {
  codigo: 'ERR-006',
  mensaje: 'No se pudo conectar con el portal. Intente de nuevo en unos minutos.',
  detalles: null,
};

function leerCookie(nombre) {
  const par = document.cookie.split('; ').find((c) => c.startsWith(`${nombre}=`));
  return par ? decodeURIComponent(par.slice(nombre.length + 1)) : null;
}

async function obtenerCookieCsrf() {
  await fetch('/sanctum/csrf-cookie', { credentials: 'include' });
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
  let resp;

  try {
    resp = await fetch(ruta, {
      ...opciones,
      method: metodo,
      credentials: 'include',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        ...(xsrf ? { 'X-XSRF-TOKEN': xsrf } : {}),
        ...(opciones.headers || {}),
      },
    });
  } catch {
    return { ok: false, estado: 0, error: SIN_CONEXION };
  }

  // 419: token CSRF ausente o vencido. Se pide uno nuevo y se reintenta una sola vez.
  if (resp.status === 419 && !reintentado && METODOS_CON_CSRF.includes(metodo)) {
    try {
      await obtenerCookieCsrf();
    } catch {
      return { ok: false, estado: 0, error: SIN_CONEXION };
    }
    return llamar(ruta, opciones, true);
  }

  // El 204 de /logout no trae cuerpo.
  const cuerpo = await resp.json().catch(() => null);

  if (resp.ok) return { ok: true, datos: cuerpo };

  // 429: /login admite 5 intentos por minuto y /crear-contrasena, 6.
  if (resp.status === 429) {
    return {
      ok: false,
      estado: 429,
      error: { codigo: 'ERR-429', mensaje: 'Demasiados intentos. Espere un minuto y vuelva a intentarlo.', detalles: null },
    };
  }

  return { ok: false, estado: resp.status, error: cuerpo?.error || SIN_CONEXION };
}

// POST /login. 200 → { ok: true, datos: { user: { id_usuario, nombre, correo, rol, id_comercio } } }
// · 422 ERR-005 (credenciales incorrectas o cuenta inactiva: error.detalles.correo[0]) · 429 ERR-429.
export function iniciarSesion(correoOParams, contrasena) {
  const correo = typeof correoOParams === 'object' && correoOParams !== null ? correoOParams.correo : correoOParams;
  const pass = typeof correoOParams === 'object' && correoOParams !== null ? correoOParams.contrasena : contrasena;
  return llamar('/login', { method: 'POST', body: JSON.stringify({ correo, contrasena: pass }) });
}

// POST /logout. 204 → { ok: true, datos: null } · 401 si no había sesión.
export function cerrarSesion() {
  return llamar('/logout', { method: 'POST' });
}

// POST /crear-contrasena (invitación del Encargado del comercio). No inicia sesión.
// 200 → { ok: true, datos: { mensaje } } · 422 con error.detalles.token[0] (enlace inválido o vencido)
// o error.detalles.contrasena[0] · 429 ERR-429 (6 intentos por minuto).
export function crearContrasena({ token, correo, contrasena, contrasenaConfirmacion }) {
  return llamar('/crear-contrasena', {
    method: 'POST',
    body: JSON.stringify({ token, correo, contrasena, contrasena_confirmation: contrasenaConfirmacion }),
  });
}

// GET /api/user. 200 → { ok: true, datos: { user } } · 401 si no hay sesión.
export function obtenerUsuario() {
  return llamar('/api/user');
}
