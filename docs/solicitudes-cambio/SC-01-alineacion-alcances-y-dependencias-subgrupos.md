# Solicitud de Cambio (SC)
**Anexo B - IF6100 Pacíficos Online**

---

### 1. Identificación
- **Código de Solicitud:** SC-01
- **Fecha:** 2026-09-08
- **Módulo Solicitante:** Comité de Arquitectura e Integración
- **Persona Solicitante:** Manuel Mejicano / Comité de Arquitectura

---

### 2. Artefacto Afectado
- [x] Documento Visión de la plataforma
- [ ] Modelo Canónico / Glosario
- [ ] Especificación de Requerimientos de Software (ERS)
- [ ] Modelo de Casos de Uso
- [ ] Contrato de Módulo (OpenAPI / PHP Interface)
- [ ] Modelo de Diseño / Base de Datos
- [ ] Código Fuente / Pruebas
- [x] Actas de Constitución y Fichas de Módulo (PC1) de los Subgrupos SG1, SG4, SG5, SG6 y SG7

---

### 3. Descripción y Justificación del Cambio
- **Descripción del cambio solicitado:**  
  Alineación y corrección de inconsistencias, vacíos de información y desalineaciones de dependencias identificadas durante la revisión cruzada de las Actas de Constitución y Fichas de Módulo del Punto de Control 1 (PC1) entre los 7 subgrupos.

- **Origen del cambio:**
  - [ ] Petición del cliente / persona docente
  - [x] Hallazgo en sesión de validación
  - [ ] Defecto detectado en integración
  - [ ] Necesidad interna del equipo

---

### 4. Resumen de Hallazgos y Soluciones Generales

#### 4.1 ¿De dónde sale el costo de compra del producto? (SG7 vs SG3 vs SG4)
- **Problema:** SG7 (ERP Contable) requiere calcular el *Costo de Ventas*, pero en su acta dejó abierta la duda sobre qué módulo provee el costo unitario de adquisición (`M3 o M4`). SG3 (Catálogo) únicamente administra el precio de venta al público.
- **Solución:** Acordar formalmente que **SG4 (Inventario)** gestione el costo unitario de adquisición al registrar las entradas de mercadería (Kardex valorizado) y lo transmita a SG7 al generarse los eventos de despacho/venta.

#### 4.2 Dependencia faltante en la tabla de SG1 hacia SG4
- **Problema:** SG1 (Portal Público) presenta si un producto está disponible o agotado, y SG4 declaró en sus salidas proveer este estado global a SG1, pero SG1 omitió a SG4 en su matriz de dependencias de entrada (únicamente registró a SG2 y SG3).
- **Solución:** Incorporar formalmente a **SG4** dentro de la matriz de dependencias de entrada de SG1.

#### 4.3 Definición de compras multicomercio (SG5 y SG6)
- **Problema:** Existía incertidumbre sobre si un carrito con productos de múltiples comercios produce un solo pedido general o varios pedidos independientes, lo cual afectaba la lógica transaccional de cobro en pasarela y la emisión de facturas electrónicas de Hacienda en SG6.
- **Solución:** Establecer la regla: se procesa **un único cobro unificado en pasarela (M6)** y se realiza una partición interna en **N pedidos independientes (uno por cada comercio involucrado)**, emitiendo una factura electrónica individual por comercio.

#### 4.4 Lógica de cálculo de costos de envío (SG5)
- **Problema:** SG5 asumió el rol de repartidor y el cálculo del flete, pero faltaba precisar el origen de las tarifas de envío.
- **Solución:** Establecer que SG5 toma la tarifa o política de envío configurada por cada comercio en SG2 para liquidar el flete durante el checkout.

---

### 5. Modificaciones Específicas por Documento / Subgrupo

#### A. Documento de SG7 (ERP Contable y Reportes Gerenciales)
*Archivo: `docs/actas-subgrupos/SG7 ERP contable y reportes gerenciales/SG7_M7_Portal_Publico_PC1.docx.pdf`*

1. **Sección 4.1 — Matriz de dependencias (Página 8):**
   - **Texto actual:**  
     *Módulo:* `M3 o M4`  
     *Descripción:* `Costo unitario del producto (costo de adquisición o promedio ponderado) para calcular el asiento de costo de ventas. (Definir con M3/M4 cuál lo provee).`
   - **Cambiar por:**  
     *Módulo:* `M4 (Inventario multiempresa)`  
     *Descripción:* `Costo unitario de adquisición o costo promedio ponderado registrado en las entradas del Kardex valorizado de M4, para la generación automática del asiento de costo de ventas.`
2. **Sección 4 — Lista Preliminar de Necesidades (Página 6):**
   - Precisar la necesidad de consumo de eventos de Kardex valorizado provenientes de M4.

---

#### B. Documento de SG4 (Inventario Multiempresa)
*Archivo: `docs/actas-subgrupos/SG4_Inventario_Multiempresa/SG4_M4_Inventario_Multiempresa_PC1.pdf`*

1. **Sección 2.2 — Alcance incluido (Página 4):**
   - **Ajustar el punto de Kardex:**  
     `Historial Kardex valorizado (cantidades físicas y costo unitario de adquisición) consultable por producto/variación.`
2. **Sección 4.1 — Matriz de dependencias / Ofrece a otros módulos (salida) (Página 5):**
   - **En la fila de M7, ajustar a:**  
     `M7 — ERP contable y reportes: eventos e historial de movimientos (Kardex valorizado con costo de compra) para valoración de inventario y generación automática de asientos de costo de ventas.`

---

#### C. Documento de SG1 (Portal Público y Búsqueda)
*Archivo: `docs/actas-subgrupos/SG1_Portal público y búsqueda/SG1_M1_Portal_Publico_PC1.pdf`*

1. **Sección 4.1 — Matriz de dependencias (Página 7):**
   - **Agregar una nueva fila en "Necesita de otros módulos (entrada)":**  
     *Módulo:* `M4 (Inventario multiempresa)`  
     *Descripción:* `Estado global de disponibilidad de stock (Disponible / Agotado) por producto y variación para filtros de búsqueda y visualización en catálogo.`

---

#### D. Documento de SG5 (Carrito y Pedidos)
*Archivo: `docs/actas-subgrupos/SG5_Carrito_y_pedidos/SG1_M5_PC1.pdf`*

1. **Sección 2.2 — Alcance incluido (Página 4):**
   - **Reemplazar:** *"según la decisión sobre el carrito unificado que se tome en el PC2"*  
   - **Por:** `Confirmación de la compra unificada en un solo cobro de pasarela, particionando internamente en N pedidos independientes (uno por cada comercio involucrado).`
2. **Sección 4.1 — Matriz de dependencias (Página 7):**
   - **En entrada de M2, agregar:**  
     `Tarifa o política de costo de envío configurada por cada comercio para calcular el flete en el checkout.`

---

#### E. Documento de SG6 (Pagos y Facturación Electrónica)
*Archivo: `docs/actas-subgrupos/SG6_PAGOS Y FACTURACION ELECTRONICA/ARTEFACTOS DEL MÓDULO_ SG6.pdf`*

1. **Sección 4.1 — Matriz de dependencias (Página 6):**
   - **Reemplazar la condición de carrito unificado por:**  
     `M5 entrega el desglose de montos por comercio para procesar un solo cobro general en pasarela y emitir la factura electrónica individual correspondiente a cada comercio.`

---

### 6. Análisis de Impacto
- **Otros módulos afectados:** M1, M2, M3, M4, M5, M6, M7.
- **Contratos afectados:** Ajuste en contratos preliminares (PC2/PC3) de M4 (Kardex valorizado hacia M7), M1 (consulta disponibilidad a M4), y M5/M6 (esquema de cobro y partición de pedidos).
- **Artefactos por actualizar:** Actas de constitución y fichas de módulo PC1 de SG1, SG4, SG5, SG6 y SG7.
- **Esfuerzo estimado:** 1 día de coordinación y actualización documental.

---

### 7. Decisión del Comité de Arquitectura e Integración
- **Resolución:** Aprobada
- **Justificación de la resolución:** Resuelve ambigüedades tempranas en PC1, asegura la integridad del costeo contable (M7), garantiza la disponibilidad en tiempo real en el portal (M1) y define formalmente el flujo transaccional de compras multicomercio entre Carrito (M5) y Pagos/Facturación (M6).
- **Número de Acta del Comité:** ACTA-COMITE-01
- **Fecha de Resolución:** 2026-09-08
