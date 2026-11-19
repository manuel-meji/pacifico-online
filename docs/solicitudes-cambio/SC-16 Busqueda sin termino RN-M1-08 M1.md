# Solicitud de Cambio (SC-16 - Búsqueda de productos sin término, RN-M1-08)

---

### 1. Identificación
- **Código de Solicitud:** SC-16
- **Fecha:** 2026.11.19
- **Módulo Solicitante:** Comité (el cambio lo hace M1 Portal público y búsqueda)
- **Persona Solicitante:** Comité de Arquitectura e Integración. En M1 lo implementó Roberth Ponce Juárez y redacta la solicitud Benjamín Pizarro Chaves

---

### 2. Artefacto Afectado
- [ ] Documento Visión de la plataforma
- [ ] Modelo Canónico / Glosario
- [x] Especificación de Requerimientos de Software (ERS)
- [x] Modelo de Casos de Uso
- [ ] Contrato de Módulo (OpenAPI / PHP Interface)
- [ ] Modelo de Diseño / Base de Datos
- [x] Código Fuente / Pruebas

---

### 3. Descripción y Justificación del Cambio
- **Descripción del cambio solicitado:**
  La búsqueda de productos (`GET /productos/busqueda`, `PortalPublicoContract::buscarProductos`) deja de exigir un término de al menos 2 caracteres cuando no se elige categoría. Sin término ni categoría muestra todos los productos visibles, paginados. Con un término de cualquier largo, hasta 100 caracteres, muestra los que coinciden.

  1. **Regla actual.** RN-M1-08 (sección 4 del PC2) dice que la búsqueda solo se ejecuta con al menos 2 caracteres o con una categoría elegida. El flujo A1 de CU-002 (criterio vacío) muestra un aviso y no busca.
  2. **Regla propuesta para RN-M1-08.** La búsqueda de productos se puede ejecutar sin término. Sin término ni categoría muestra todos los productos visibles de comercios habilitados, paginados y sin favorecer a ningún comercio (RN-M1-07). Con término, muestra los productos que coinciden.
  3. **Flujo A1 de CU-002.** Deja de ser un flujo alterno de error. Si la persona compradora no escribe nada, el sistema muestra el catálogo completo paginado y el caso continúa por el flujo básico.
  4. **Por qué.** El cambio lo pidió el Comité. La página principal tiene los botones «Ver todos los productos» y «Ver todos los comercios», que abren la búsqueda sin término. En los comercios en línea más comunes una búsqueda vacía no muestra un error, sino el catálogo (observación del coordinador en la revisión del PC3).
  5. **Estado en el código.** El cambio ya está en `desarrollo` (commit 354e601, 14/11/2026). Se quitó la validación de `BusquedaService` y de `BusquedaController`, y la búsqueda vacía se sigue registrando en `busquedas_usuario`. Esta solicitud regulariza el cambio en los artefactos.
  6. **Lo que no cambia.** La búsqueda de comercios por nombre (`GET /comercios?q=`, contrato 1.6.0) sigue pidiendo 2 caracteres para filtrar. Sin `q` ya lista todos los comercios.
- **Origen del cambio:**
  - [ ] Petición del cliente / persona docente
  - [ ] Hallazgo en sesión de validación
  - [ ] Defecto detectado en integración
  - [x] Necesidad interna del equipo

---

### 4. Análisis de Impacto
- **Otros módulos afectados:**
  - **M3:** ninguno. `CatalogoService::buscarProductos` de M3 solo filtra por texto cuando recibe `q`, así que sin término devuelve su catálogo visible paginado.
  - **M5 y M6:** ninguno. No usan la búsqueda.
- **Contratos afectados:**
  - Ninguno. No se publica una versión nueva del contrato de M1, que sigue en 1.7.0. La operación no agrega ni quita campos y solo acepta entradas que antes se rechazaban, así que ningún consumidor se rompe.
- **Artefactos por actualizar en la documentación:**
  - PC2, sección 4. Texto de RN-M1-08.
  - PC2, sección 5.2. Flujo A1 de CU-002 Buscar productos.
  - PC3, sección 3.1. Diagrama de secuencia de CU-002 (bloque break A1, mensajes 6 y 7) y responsabilidad de ControlBusquedaProductos.
  - PC4, secciones 3.4 y 9.1 (DEF-007).
- **Artefactos ya actualizados en el repositorio:**
  - `app/Services/M1_Portal/BusquedaService.php` y `app/Http/Controllers/M1_Portal/BusquedaController.php`.
  - `frontend/src/modules/M1_Portal/views/BusquedaView.jsx`, `BusquedaComerciosView.jsx` e `InicioView.jsx`.
  - Pruebas `BusquedaServiceTest` (`test_un_termino_vacio_sin_categoria_lista_todos_los_productos`, `test_un_termino_nulo_sin_categoria_lista_todos_los_productos` y `test_una_busqueda_vacia_se_registra`), `RutasPublicasTest` (`test_una_busqueda_vacia_sin_categoria_llama_al_contrato_y_responde_200` y `test_un_termino_de_una_letra_sin_categoria_ya_llama_al_contrato`) y `AceptacionBuscarYVerFichaTest`.
- **Esfuerzo estimado:** Ninguno en el código, que ya está hecho. 0,5 días para actualizar los documentos.

---

### 5. Decisión del Comité de Arquitectura e Integración
- **Resolución:** [Aprobada | Rechazada | Diferida]
- **Justificación de la resolución:** [Explicación técnica]
- **Número de Acta del Comité:** ACTA-COMITE-SC-16
- **Fecha de Resolución:** [YYYY-MM-DD]
