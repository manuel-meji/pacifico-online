
const BASE = '/api/m3/v1';
const METODOS_CON_CSRF = ['POST', 'PUT', 'PATCH', 'DELETE'];

const SIN_CONEXION = {
  codigo: 'ERR-006',
  mensaje: 'No se pudo conectar con la plataforma. Intenta de nuevo en unos minutos.',
  detalles: null,
};

function leerCookie(nombre) {
  const par = document.cookie
    .split('; ')
    .find((cookie) => cookie.startsWith(`${nombre}=`));

  return par
    ? decodeURIComponent(par.slice(nombre.length + 1))
    : null;
}

async function obtenerCookieCsrf() {
  await fetch('/sanctum/csrf-cookie', {
    credentials: 'include',
  });
}

function conConsulta(ruta, consulta = {}) {
  const parametros = new URLSearchParams();

  Object.entries(consulta).forEach(([clave, valor]) => {
    if (valor !== undefined && valor !== null && valor !== '') {
      parametros.set(clave, valor);
    }
  });

  const texto = parametros.toString();
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

  const headers = {
    Accept: 'application/json',
    ...(xsrf ? { 'X-XSRF-TOKEN': xsrf } : {}),
  };

  const configuracion = {
    method: metodo,
    credentials: 'include',
    headers,
  };

  if (opciones.json !== undefined) {
    headers['Content-Type'] = 'application/json';
    configuracion.body = JSON.stringify(opciones.json);
  }

  let respuesta;

  try {
    respuesta = await fetch(`${BASE}${ruta}`, configuracion);
  } catch {
    return { ok: false, estado: 0, error: SIN_CONEXION };
  }

  if (
    respuesta.status === 419 &&
    !reintentado &&
    METODOS_CON_CSRF.includes(metodo)
  ) {
    try {
      await obtenerCookieCsrf();
    } catch {
      return { ok: false, estado: 0, error: SIN_CONEXION };
    }

    return llamar(ruta, opciones, true);
  }

  const cuerpo = await respuesta.json().catch(() => null);

  if (respuesta.ok) {
    return { ok: true, datos: cuerpo };
  }

  if (respuesta.status === 429) {
    return {
      ok: false,
      estado: 429,
      error: {
        codigo: 'ERR-429',
        mensaje: 'Demasiados intentos. Espera un minuto y vuelve a intentarlo.',
        detalles: null,
      },
    };
  }

  return {
    ok: false,
    estado: respuesta.status,
    error: cuerpo?.error || SIN_CONEXION,
  };
}

// Categorías
export function listarCategorias() {
  return llamar('/categorias');
}

// Marcas
export function listarMarcas() {
  return llamar('/marcas');
}

// Productos
export function buscarProductos(filtros = {}) {
  return llamar(conConsulta('/productos', filtros));
}

export function obtenerProducto(idProducto) {
  return llamar(`/productos/${idProducto}`);
}

export function crearProducto(datos) {
  return llamar('/productos', {
    method: 'POST',
    json: datos,
  });
}

export function actualizarProducto(idProducto, cambios) {
  return llamar(`/productos/${idProducto}`, {
    method: 'PATCH',
    json: cambios,
  });
}

// Variaciones
export function crearVariacion(idProducto, datos) {
  return llamar(`/productos/${idProducto}/variaciones`, {
    method: 'POST',
    json: datos,
  });
}

export function obtenerVariacion(idVariacion) {
  return llamar(`/variaciones/${idVariacion}`);
}

export function actualizarDisponibilidadVariacion(idVariacion, disponible) {
  return llamar(`/variaciones/${idVariacion}`, {
    method: 'PATCH',
    json: { disponible },
  });
}

// Imágenes
export function agregarImagen(idProducto, datos) {
  return llamar(`/productos/${idProducto}/imagenes`, {
    method: 'POST',
    json: datos,
  });
}
