# M1 Portal público · Ficha de comercio (prototipo)

Pantalla del CU-009 *Consultar información pública de un comercio* (micrositio), con React y Tailwind como el prototipo de M5.
Ruta prevista en el contrato de M1: `GET /m1/comercios/{idComercio}/micrositio`.

| Archivo | Contenido |
|---|---|
| `views/FichaComercioView.jsx` | Pantalla: banner y datos del comercio, pestañas Productos / Información, categorías, filtros, orden, paginación y estados alternativos |
| `components/EncabezadoPortal.jsx` | Encabezado del portal (logotipo, buscador, cuenta, carrito, barra de categorías), con la estructura del prototipo de ficha de producto |
| `components/TarjetaProducto.jsx` | Tarjeta de producto (ProductoResumenDTO) con etiqueta de agotado; reutilizable en búsqueda y destacados |
| `components/FiltrosComercio.jsx` | Barra lateral: tipo de producto, marca, precio y disponibilidad |
| `components/Paginacion.jsx`, `components/InfoComercio.jsx` | Paginación y datos públicos del comercio (horarios, contacto, redes, políticas) |
| `services/micrositio.js` | Doble de `obtenerMicrositio`: filtra, ordena, pagina y simula M2, M3 y M4 |
| `services/datosMockComercio.js` | Datos de ejemplo con la forma de ComercioPublico (M2) y ProductoResumenDTO (M1) |
| `tema.css`, `assets/logo-pacificos.png` | Paleta de la marca (brandBlue, brandDarkBlue, brandGreen, brandLightBg) y logotipo |
| `M1App.jsx`, `main.jsx` | Entrada del módulo; se abre con `m1.html` para no tocar `src/main.jsx` (M5) |

## Ejecutar
Desde `frontend/`: `npm install` y `npm run dev`, luego abrir `http://localhost:5173/m1.html`.

## Simular alternativas
`?simular=suspendido` (M2 404), `?simular=vacio` (sin productos), `?simular=sininventario` (M4 sin respuesta).

## Pendiente
- Cambiar `services/micrositio.js` por la llamada real al backend de M1 cuando exista.
- Unificar `EncabezadoPortal` y `TarjetaProducto` con las demás pantallas de M1.
- Filtros y orden: el contrato de `obtenerMicrositio` solo tiene paginación; salen de la búsqueda con el comercio fijo o de un cambio de versión.
- Etiqueta de oferta y precio anterior: solo si M3 publica ese dato en su contrato.

## Iniciar sesión y Crear cuenta (prototipo)

Pantallas de ingreso y autorregistro del cliente. El registro respeta el esquema `RegistroCliente` del contrato de M1
(`POST /api/m1/v1/clientes`); el ingreso lo resuelve el núcleo y aquí se simula.

| Archivo | Contenido |
|---|---|
| `views/IniciarSesionView.jsx` | Correo y contraseña, con validación en pantalla |
| `views/CrearCuentaView.jsx` | Nombre, apellidos, correo, teléfono, dirección de envío, contraseña y confirmación (no se envía), con los límites de `RegistroCliente` |
| `components/componentes.jsx` | Encabezado, migas de pan, campo con ícono, avisos y botón principal de estas pantallas |
| `components/Logo.jsx` | Logotipo (`assets/logo-pacificos.png`) con el nombre al lado |
| `services/datosMock.js` | Respuestas simuladas: registro exitoso, ERR-M1-001 y credenciales incorrectas |

Se abren con `m1.html?pantalla=ingresar` o `m1.html?pantalla=crear` (en los archivos de doble clic, `window.PANTALLA_M1`).
Sin `pantalla` se muestra la ficha de comercio.

Simular: `?pantalla=crear&simular=correo` (409 ERR-M1-001, correo ya registrado) y `?pantalla=ingresar&simular=credenciales`.

Pendiente: cambiar `registrar` e `ingresar` de `M1App.jsx` por las llamadas reales cuando existan el backend de M1
y el contrato de ingreso del núcleo.
