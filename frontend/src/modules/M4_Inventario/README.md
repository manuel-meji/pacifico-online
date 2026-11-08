# M4 Inventario: interfaz (PC4)

Pantallas del encargado del comercio en React + Tailwind, conectadas a la API del contrato
`/api/m4/v1` (openapi_M4_v1.3.0). La plataforma usa React en `frontend/` con la API de cada módulo
(igual que M5); el PC3 decía Inertia, el cambio está en la SC-07.

- **Operaciones**: entrada con costo unitario y salida manual (CU-M4-01, `POST /movimientos`).
- **Existencias**: física − reservada = disponible por variación y ubicación, aviso de stock mínimo y
  edición del mínimo (`GET /existencias`, `PUT /existencias/{id}/stock-minimo`).
- **Kardex**: existencia por ubicación y kardex con filtros de ubicación y periodo (CU-M4-08, `GET /kardex`).

Las transferencias entre ubicaciones son alcance deseable: no están en esta versión (v0.9.0).

Opera desde 360 px sin desplazamiento horizontal. Las vistas no tienen reglas de negocio: solo validan la forma
de los campos (H7) y muestran lo que responde el servidor. El comercio sale de la sesión del núcleo (RN-12).

## Correrlo
    php artisan serve                 # API en http://127.0.0.1:8000
    cd frontend && npm install && npm run dev
    # abrir http://localhost:5173/m4.html e iniciar sesión con un encargado de comercio

`vite.config.js` envía `/api`, `/sanctum`, `/login` y `/logout` a Laravel (misma cookie de sesión).
Los nombres de las ubicaciones salen de `ObtenerSedesComercio` de M2, que ya publica las sedes reales (PR #151):
`id_sede` de M2 es el mismo número que `id_ubicacion` de M4. En la base de demostración, el comercio 1 tiene la sede 1
(«Sede principal») y la 2 («Bodega Liberia»). Si M2 no responde se muestra el número de la ubicación.

## Prototipo del PC3
`frontend/prototipos/M4_Inventario/` conserva las copias de doble clic del prototipo validado en el PC3 (sección 8 del
documento del PC3). Son una simulación en memoria con datos de prueba: no usan esta API ni se regeneran con este código.
