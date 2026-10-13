# Frontend · M5 Carrito y Pedidos (prototipo)

Prototipo de las pantallas del módulo M5 con React y Tailwind CSS, el stack acordado por el Comité.
Funciona como un proyecto independiente dentro de `frontend/`; no modifica la configuración de Laravel.

## Pantallas (CU-001 y CU-004)
Código en `src/modulos/m5-carrito-pedidos/`:

| Archivo | Pantalla |
|---|---|
| `CarritoPage.jsx` | Carrito agrupado por comercio, cambio de cantidad, quitar ítem y subtotales |
| `CheckoutPage.jsx` | Dirección, método de pago, cupón, tarjeta de regalo, nota y resumen por comercio |
| `PedidoPendientePage.jsx` | Pedido en estado «Pendiente de Pago» y redirección a la pasarela |
| `M5App.jsx` | Punto de entrada del módulo: navega entre pantallas y simula las respuestas del servidor |
| `componentes.jsx`, `calculos.js` | Componentes comunes y cálculo de subtotales, impuestos, envío y total |
| `datosMock.js` | Datos de ejemplo (productos, comercios, precios y envíos); no vienen de M2, M3 ni M4 |

## Verlo con doble clic (sin instalar nada)
En la carpeta `prototipo-doble-clic/` de esta misma carpeta, abra con Chrome o Edge:
- `1_Abrir_prototipo.html`: flujo normal.
- `2_Abrir_prototipo_producto_agotado.html`: alternativa del paso 4 del CU-004.
- `3_Abrir_prototipo_comercio_suspendido.html`: alternativa del paso 5 del CU-004.

Para verlo a 360 px de ancho: F12, modo dispositivo (Ctrl + Shift + M) y ancho 360.

## Ejecutarlo para desarrollar
Requiere Node.js. Desde esta carpeta (`frontend`):

    npm install
    npm run dev

Se abre `app.html` en el navegador. La entrada del proyecto es `app.html` (no `index.html`).

## Volver a generar los archivos de doble clic
Si cambia el código, desde esta carpeta:

    npm run build:single

Genera `dist-single/app.html`. Copie ese archivo sobre los de `prototipo-doble-clic/` (para las versiones 2 y 3 hay que agregar
`<script>window.SIMULAR_M5='stock';</script>` o `'comercio'` justo después de `<head>`).
Nota: `npm audit` reporta vulnerabilidades en una dependencia de desarrollo de este paso (`vite-plugin-singlefile`); solo se usa al compilar.

## Simular las alternativas del CU-004
Agregue a la dirección:
- `?simular=stock`: M4 no puede reservar; se avisa qué producto se agotó y se vuelve al carrito.
- `?simular=comercio`: un comercio está suspendido; se deshabilitan sus productos y se avisa al cliente.

## Pendiente
- Reemplazar `datosMock.js` y la función `confirmar` de `M5App.jsx` por llamadas reales al contrato de M5
  (`POST /api/m5/v1/pedidos`) cuando el módulo esté desplegado.
- Definir con el coordinador cómo se integra con el resto del proyecto.
