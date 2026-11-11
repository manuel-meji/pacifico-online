# Prototipo M4 · Inventario Multiempresa

Abra directamente con su navegador (Chrome, Edge, etc.), sin instalar nada:
- `1_Abrir_prototipo.html`: flujo normal (comercio 104 activo). Pantallas Operaciones, Reservas y Ficha y Kardex.
- `2_Abrir_prototipo_comercio_suspendido.html`: comercio suspendido; no se permiten movimientos (ERR-M4-015, RN-M4-009).
- `3_Abrir_prototipo_sin_conexion_M2.html`: M2 no responde al consultar el estado del comercio (ERR-006).

### Visualización
Diseño mobile-first: una columna de hasta 672 px. Para la vista móvil abra las herramientas de desarrollador (`F12`),
active el modo dispositivo (`Ctrl + Shift + M`) y ajuste el ancho a 360 px.

### Datos
Los datos son de prueba y viven en memoria del navegador: al recargar la página vuelven al estado inicial.
Son los mismos de `tests/Doubles/M4/DatosPruebaM4.php` (sección 9). Código para probar una transferencia: `autorizacion-0001`
(5 unidades de CAM-PAC-AZ-M, de Bodega central a Tienda Liberia centro).

El código fuente está en `frontend/src/modules/M4_Inventario/`. Estos archivos son una copia compilada en un solo HTML:
si cambia el código, hay que volver a generarlos.
