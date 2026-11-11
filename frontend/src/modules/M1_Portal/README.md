# M1 Portal público

Pantallas del portal con React y Tailwind, conectadas a la API real de M1 (`/api/m1/v1`, contrato
`contracts/m1-portal/v1/openapi.yaml` 1.4.0). Se abren con `m1.html` (o `inicio.html`) para no tocar
`src/main.jsx`, que es de M5.

| Pantalla | URL | Endpoint |
|---|---|---|
| Página principal | `m1.html` | `GET /destacados?limite=12` (y `GET /comercios` si no hay comercios destacados) y `GET /promociones` |
| Búsqueda | `m1.html?pantalla=busqueda&q=cafe` o `&id_categoria=10` | `GET /productos/busqueda` |
| Ficha de producto | `m1.html?pantalla=producto&id=1520` | `GET /productos/{id_producto}` |
| Micrositio | `m1.html?pantalla=comercio&id=34` | `GET /comercios/{id_comercio}/micrositio` y, con filtros, `GET /productos/busqueda` con `id_comercio` |
| Menú de categorías (todas) | | `GET /categorias` |

Los parámetros de búsqueda en la URL usan los nombres del contrato (`q`, `id_categoria`, `precio_minimo`,
`precio_maximo`, `orden`, `pagina`, `por_pagina`). Así funcionan el botón Atrás y recargar la página.

| Archivo | Contenido |
|---|---|
| `M1App.jsx`, `main.jsx` | Entrada, navegación con parámetros en la URL y menú de categorías compartido |
| `services/m1Api.js` | Cliente HTTP de `/api/m1/v1` (CSRF de Sanctum y formato de error del núcleo) |
| `services/useConsulta.js` | Estado de cada llamada: cargando, actualizando, listo o error, con reintento |
| `services/formato.js` | Colones (con 2 decimales solo si los tiene), descuento de una promoción, fecha corta, días de la semana y texto de atributos |
| `components/PaginaPortal.jsx` | Marco común (encabezado, contenido y pie) y contexto de navegación |
| `components/EncabezadoPortal.jsx` | Logotipo, buscador, cuenta, carrito (M5) y menú de categorías |
| `components/EstadosVista.jsx` | Estados de carga, vacío y error (422, 404, 503 con Reintentar) |
| `components/ListadoProductos.jsx` | Orden, cantidad por página, filtros en celular, cuadrícula y paginación |
| `components/FiltrosComercio.jsx` | Filtros de búsqueda y micrositio: buscar en el comercio, categoría y precio |
| `components/TarjetaProducto.jsx`, `components/Imagen.jsx` | Tarjeta de ProductoResumen con precio rebajado y etiqueta de promoción; imagen con ícono de respaldo si no carga |
| `components/CarruselComercios.jsx`, `components/InfoComercio.jsx`, `components/Paginacion.jsx` | Comercios en fila, información pública del comercio y paginación |
| `views/InicioView.jsx`, `views/BusquedaView.jsx`, `views/FichaProductoView.jsx`, `views/FichaComercioView.jsx` | Las cuatro pantallas |

## Comportamiento
- Errores con el formato del núcleo: 422 ERR-005 muestra el mensaje del campo; 404 ERR-004, "no disponible";
  503 ERR-006 (o sin conexión), "no disponible temporalmente" con Reintentar.
- Búsqueda (RN-M1-08): sin categoría, el término necesita 2 caracteres; si no, se avisa sin llamar a la API.
- Micrositio: sin filtros muestra los productos del comercio paginados. El buscador del comercio y la categoría
  usan la búsqueda con `id_comercio`; precio y orden se habilitan solo con término o categoría (RN-M1-08).
  Si M3 no responde (`productos_disponibles: false`), se muestra el comercio con un aviso.
- Ficha: una variación solo se compra si `comprable` es true; si no, "Agotado" y el botón deshabilitado.
- Portada (contrato 1.4.0): hasta 12 productos destacados, máximo 3 por comercio, sin agotados y rotados cada
  15 minutos (lo decide el backend). La sección "Promociones" usa `GET /promociones` y se oculta si no hay o si falla.
- Promociones (contrato 1.4.0): los campos solo vienen con una promoción vigente.
  - En `ProductoResumen.promocion`, si aplica a todo el producto, la tarjeta muestra `precio_promocional` en rojo,
    con el normal tachado, y la etiqueta "-N %" o "Precio especial". Si es solo de algunas variaciones, muestra la
    etiqueta con "en algunas opciones" y el precio de cada una se ve en la ficha.
  - En la ficha, `VariacionFicha.precio_promocional` y `fin_promocion` muestran el precio rebajado y
    "Precio de promoción hasta el …". El carrito (M5) cobra ese precio con `obtenerPreciosVigentes`.
  "Agregar al carrito" abre M5: `/carrito.html?agregar=<id_variacion>&cantidad=1`.
- Portada: la sección que venga en `secciones_no_disponibles` se oculta y el resto sigue.

## Ejecutar
Backend: `php artisan serve`. Desde `frontend/`: `npm install` y `npm run dev`; abrir `http://localhost:5173/m1.html`.
El servidor de Vite pasa `/api`, `/sanctum`, `/login` y `/logout` al backend.

## Iniciar sesión y Crear cuenta (prototipo)

Pantallas de ingreso y autorregistro del cliente. El registro respeta el esquema `RegistroCliente` del contrato de M1
(`POST /api/m1/v1/clientes`); el ingreso lo resuelve el núcleo (real, no simulado).

| Archivo | Contenido |
|---|---|
| `views/IniciarSesionView.jsx` | Correo y contraseña, con validación en pantalla |
| `views/CrearCuentaView.jsx` | Nombre, apellidos, correo, teléfono, dirección de envío, contraseña y confirmación (no se envía), con los límites de `RegistroCliente` |
| `components/componentes.jsx` | migas de pan, campo con ícono, avisos y botón principal de estas pantallas |
| `services/datosMock.js` | Respuestas simuladas: ERR-M1-001 y credenciales incorrectas (solo con `?simular=`) |
| `services/m1Api.js` | Cliente HTTP de `/api/m1/v1` con el token CSRF de Sanctum; hoy expone `registrarCliente` |

Se abren con `m1.html?pantalla=ingresar` o `m1.html?pantalla=crear` (en los archivos de doble clic, `window.PANTALLA_M1`).
Sin `pantalla` se muestra la ficha de comercio.

Simular: `?pantalla=crear&simular=correo` (409 ERR-M1-001, correo ya registrado) y `?pantalla=ingresar&simular=credenciales`.

Crear cuenta llama a `POST /api/m1/v1/clientes` real (contrato 1.2.0: cédula y dirección principal desglosada).
Necesita el backend encendido (`php artisan serve`) y `npm run dev`, que pasa `/api`, `/sanctum`, `/login` y `/logout` al backend.
La ruta admite 10 intentos por minuto.

Iniciar sesión ya usa el ingreso real del núcleo (cliente `frontend/src/modules/Nucleo/services/sesionApi.js`): `POST /login` con `{ correo, contrasena }`, `GET /api/user` al cargar para
saber si hay sesión y `POST /logout`, con el mismo manejo de CSRF que `m1Api.js`. Las credenciales incorrectas o la
cuenta inactiva (422 ERR-005) se muestran junto al correo; el límite es de 5 intentos por minuto (429). Un cliente va
al inicio del portal; con sesión, el encabezado muestra el nombre y "Cerrar sesión". `?simular=credenciales` sigue
mostrando el error sin backend.

Mi cuenta (`?pantalla=perfil`, `views/PerfilView.jsx`) usa `GET /api/m1/v1/clientes/sesion` y
`PUT /api/m1/v1/clientes/{id_cliente}`: muestra y actualiza nombre, apellidos y teléfono de la persona con sesión
(RN-M1-16). El correo se muestra bloqueado hasta que el núcleo ofrezca `actualizarCorreo`. Sin sesión muestra el
aviso y el botón Ingresar.

Debajo, la sección Direcciones de envío (`components/DireccionesSeccion.jsx`) usa
`GET/POST /api/m1/v1/clientes/{id_cliente}/direcciones` y `PUT .../direcciones/{id_direccion_cliente}`: lista las
direcciones (la principal primero), permite agregar, editar y marcar otra como principal. La principal no se
desmarca directamente. Los campos y su validación están en `services/camposDireccion.js`, compartidos con el registro.

Pendiente: panel para `encargado_comercio` y `encargado_plataforma`; hoy, al ingresar, solo ven un aviso.
