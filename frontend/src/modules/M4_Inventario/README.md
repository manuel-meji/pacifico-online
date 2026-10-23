# M4 Inventario: prototipo de interfaz (sección 8.1 del PC3)

Prototipo en React + Tailwind CSS de las pantallas de M4 (contrato v1.2.0):
- **Operaciones**: entrada, salida y transferencia (CU-M4-01 y CU-M4-05).
- **Reservas**: cómo se ven en M4 las reservas que crea M5 (CU-M4-06 y CU-M4-07, con la salida por despacho).
- **Ficha y Kardex**: existencia por ubicación y Kardex paginado (CU-M4-08).

Opera desde 360 px de ancho sin desplazamiento horizontal.

## Correrlo solo
    npm install
    npm run dev          # abre http://localhost:5173/m4.html
    # estados de error: /m4.html?simular=suspendido (ERR-M4-015) y /m4.html?simular=m2 (ERR-006)

`package.json` y `vite.config.js` usan las mismas versiones que `frontend/` del repositorio (React 18, Vite 8, Tailwind 4.3).
No hay dependencias nuevas: los íconos son SVG de Lucide en `components/iconos.jsx`.

## Llevarlo al repositorio único (mismo esquema que M1)
Copiar solo:
- `src/modules/M4_Inventario/` → `frontend/src/modules/M4_Inventario/`
- `m4.html` → `frontend/m4.html`

No se reemplaza ningún archivo compartido. `frontend/src/index.css`, `src/main.jsx`, `package.json` y `vite.config.js`
del Comité quedan como están: el módulo trae su propia entrada (`main.jsx`) y su `tema.css` con la paleta oficial de
`design.md`, igual que `M1_Portal/tema.css`. Con `npm run dev` se abre en `/m4.html`, como `/m1.html`.
Si el Comité quiere incluir M4 en `npm run build`, basta con agregar `m4.html` a `build.rollupOptions.input` de su
`vite.config.js` (hoy solo compila `app.html`, también para M1). Esa decisión es del Comité.

## Datos de prueba
Son los de `tests/Doubles/M4/DatosPruebaM4.php` (sección 9): comercio 104 (Activo), sedes 12 (Bodega central) y 13
(Tienda Liberia centro), producto 301 con las variaciones 501 (CAM-PAC-AZ-M) y 502 (CAM-PAC-AZ-L), pedidos 9001 (pagado,
subpedido 9101) y 9002 (pago pendiente, subpedido 9102), las reservas `RESERVA_PAGADA` y `RESERVA_PENDIENTE`, y
`autorizacion-0001` (transferir 5 unidades de la 501 de la sede 12 a la 13). El comercio suspendido se simula con
`?simular=suspendido`.

Los saldos son coherentes: cada existencia es el último saldo de su Kardex, y `cantidad_reservada` es la suma de sus
reservas activas o confirmadas. Variación 501 en la Bodega central: física 40 − reservada 2 − defectuosa 2 = 36 disponibles,
igual que la última línea de su Kardex.

## Relación con el código del subgrupo
- **Jorge (despliegue mínimo).** M4 no expone rutas HTTP: el frontend usará Inertia y los controladores llaman a
  `InventarioModuleInterface`. `services/inventarioApi.js` es un doble que vive en memoria del navegador: respeta cuerpos,
  respuestas, mensajes de `ErrorInventarioException` y el formato `{ error: { codigo, mensaje, detalles } }`, pero **no**
  llama al servicio de Jorge ni a los dobles PHP. Es un prototipo simulado, no una integración funcional; en el PC4 el doble
  se sustituye por props y acciones de Inertia sin tocar las vistas.
- **Eddie (dobles).** Mismos datos y la misma verificación de autorizaciones que `AutorizacionesFake`.
- **Roger (modelo de datos).** Misma precisión que la sección 6.5: costos con 4 decimales (`numeric(14,4)`) y montos con 2
  (`numeric(16,2)`). El servicio real traduce las columnas de la base (`tipo`, `efecto`, `motivo`, `fecha_hora`) a los
  nombres del contrato que usa la vista.
- **Josué (concurrencia).** El prototipo no demuestra concurrencia: la reserva de la última unidad bajo dos sesiones
  la prueba `ReservaUltimaUnidadConcurrencyTest` sobre PostgreSQL.

## Supuestos del prototipo
- El contrato no fija el orden de `consultarKardex`: el doble entrega del más reciente al más antiguo y la vista pide las
  páginas siguientes con «Ver movimientos anteriores» (5 por página en el móvil).
- `listarReservas` existe solo en el doble, para el panel de reservas. El contrato v1.2.0 tiene `obtenerReserva` por id,
  pero no una consulta de las reservas de un comercio; en el PC4 llegaría por props de Inertia.
- Las líneas de reserva, confirmación y liberación se asientan con el usuario 0 («M5 · Carrito y Pedidos»), porque las
  inicia M5 y no una persona.

## Hallazgos de la validación (sección 8.2)
H1 a H6 (revisión heurística) y H7 a H12 (evaluación por tareas) están aplicados:
- H7: cantidades enteras solo con dígitos. El costo unitario acepta decimales con punto, hasta dos (`9200.50`). «9.200» se
  rechaza con «Parece un separador de miles…», y «9 200», «9,200» y «₡9200» con un mensaje que explica el formato. Antes de
  registrar se muestra el monto interpretado.
- H8: cuando ERR-002 trae `detalles.id_autorizacion`, el campo «Código de autorización» se marca con error y debajo se
  muestra el motivo, que viene en `mensaje` (por ejemplo «La autorización ya fue utilizada.»). ERR-005 muestra el mensaje de
  cada campo que trae `detalles`.
- H9: el estado del comercio se consulta al abrir la pantalla; si no está Activo se avisa arriba y se deshabilita «Registrar».
- H10: `aria-describedby` en cada campo con error y `tabpanel` con `aria-controls` en las pestañas.
- H11: el ejemplo del código de autorización ya no es un código válido.
- H12: ambos selectores de variación muestran «SKU · atributos».

## Cambios de la v0.5.0 (revisión del 22 de octubre)
- Saldos iniciales coherentes entre la ficha y el Kardex (antes la ficha daba 30 disponibles y el Kardex 32).
- Kardex del más reciente al más antiguo, paginado con «Ver movimientos anteriores» y el texto «Mostrando X de N».
- Sin pérdida de decimales: costo promedio con 4, montos y saldo valorizado con 2; la vista muestra ₡8 640,10.
- Costo unitario con decimales (punto, hasta dos); las cantidades siguen siendo enteras.
- Nueva pantalla Reservas: estado de cada reserva y su efecto en el disponible, con un panel que simula las llamadas de
  M5 (reservar, confirmar, despachar y liberar) y sus errores (ERR-M4-001, -005, -006, -007, -008 y -009).
- Integración como M1: entrada y `tema.css` dentro del módulo, paleta oficial de `design.md`, sin `react-icons`.

## Cambios de la v0.4.0
- Respuestas con el esquema `Movimiento` y `KardexRespuesta { datos, paginacion }`; Kardex con `id_usuario` e `id_autorizador`.
- Autorizaciones con la forma de `DatosPruebaM4` y el orden de verificación de `AutorizacionesFake`; una autorización usada
  responde «La autorización ya fue utilizada.».
- El comercio no activo se cita como RN-M4-009 (el código de error sigue siendo ERR-M4-015).

## Archivos
- `M4App.jsx`: contenedor con encabezado, las tres pantallas y navegación inferior.
- `main.jsx`, `tema.css`: entrada propia del módulo y paleta oficial.
- `views/GestionOperacionesInventario.jsx`: entrada, salida y transferencia.
- `views/ReservasPedidos.jsx`: reservas creadas por M5 y panel de simulación.
- `views/FichaInventarioKardex.jsx`: existencia por ubicación y Kardex paginado en tarjetas.
- `components/componentes.jsx`, `components/iconos.jsx`: tarjeta, aviso, campo, botón, insignias e íconos.
- `services/inventarioApi.js`, `services/errores.js`, `services/datosMock.js`: doble de la API, errores del contrato y datos de prueba.
- `m4.html` (raíz de `frontend/`): página de entrada.
