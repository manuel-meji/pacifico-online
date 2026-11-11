# Bitácora de integración · Pacíficos Online

Registro de los cambios hechos para que los módulos funcionen como **una sola aplicación**.
Regla: no se borra ni se cambia la lógica de otro subgrupo; si hay que tocar un archivo ajeno se anota aquí.

Rama: `feature/integracion-general` · Responsable: Coordinación SG5 (M5 Carrito y Pedidos)

| Fecha | Archivo | Módulo dueño | Qué se hizo | Por qué | ¿Avisado? |
|---|---|---|---|---|---|
| 10/11/2026 | `frontend/app.html` | Compartido | Ahora carga el portal de M1 (`src/modules/M1_Portal/main.jsx`) en vez del carrito de M5. | `app.html` es la página que abre `npm run dev`; la entrada de la aplicación debe ser el portal y no el carrito. | Pendiente |
| 10/11/2026 | `frontend/carrito.html` (nuevo) | M5 | Nueva página del carrito y los pedidos (carga `src/main.jsx`, igual que antes hacía `app.html`). | El carrito de M5 pasa a tener su propia página. | No aplica (M5) |
| 10/11/2026 | `frontend/vite.config.js` | Compartido | `npm run build` compila todas las páginas (`app`, `inicio`, `carrito`, `m1`, `m2`, `m4`, `m6`, `m7`); `build:single` genera `carrito.html`. No se quitó nada del proxy. | Antes solo se compilaba `app.html` y las demás páginas no salían en el build. | Pendiente |
| 10/11/2026 | `frontend/src/modules/M1_Portal/components/EncabezadoPortal.jsx` | M1 | Solo el enlace del ícono del carrito: `/app.html` → `/carrito.html`. | El carrito de M5 se movió de página. | Pendiente (SG1) |
| 10/11/2026 | `frontend/src/modules/M1_Portal/views/FichaProductoView.jsx` | M1 | Solo la URL de "Agregar al carrito": `/app.html?agregar=…` → `/carrito.html?agregar=…`. Mismos parámetros. | El carrito de M5 se movió de página. | Pendiente (SG1) |
| 10/11/2026 | `frontend/src/modules/M1_Portal/M1App.jsx`, `frontend/src/modules/M1_Portal/README.md` | M1 | Solo comentarios/documentación: `app.html` → `carrito.html`. | Mantener la documentación al día. | Pendiente (SG1) |
| 10/11/2026 | `frontend/README.md` | Compartido | Se actualizó qué abre `app.html` y el nombre del archivo de `build:single`. | Documentación. | No aplica |
| 10/11/2026 | `app/Http/Controllers/M5_Pedidos/ControladorDeseos.php`, `ItemDeseo.php`, `create_pedidos_deseos_table.php` | M5 | Implementación completa de Lista de Deseos (Wishlist): listar, agregar, eliminar y mover al carrito. | Cumplir alcance mínimo formal M5 (Tabla 5.1). | SC-09 creada |
| 10/11/2026 | `app/Http/Controllers/M5_Pedidos/ControladorPedido.php` | M5 | Endpoint `solicitarDevolucionSubpedido` para subpedidos entregados con bandera de reembolso M6. | Cumplir alcance mínimo formal M5 (Tabla 5.1). | SC-09 creada |
| 10/11/2026 | `frontend/src/modules/M5_Pedidos/views/ListaDeseosView.jsx`, `componentes.jsx`, `DetallePedidoView.jsx` | M5 | Pantalla amigable de Lista de Deseos, botón en encabezado, logo hacia `/app.html` y modal de Devolución. | Integración y experiencia de usuario. | SC-09 creada |

