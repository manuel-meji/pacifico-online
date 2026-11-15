# Acta de Sesión del Comité de Arquitectura e Integración
**Anexo D - IF6100 Pacíficos Online**

---

### Datos Generales
- **Número de Acta:** ACTA-COMITE-PC4-02
- **Fecha y Hora:** 2026-11-13 (consulta asíncrona)
- **Lugar / Modalidad:** Consulta por escrito en el chat de coordinadores del Comité. Esta acta se redactó el 2026-11-14 a partir de esa consulta.

---

### Asistencia y Representación
En las consultas por chat se registra quién respondió, no una asistencia en tiempo real.

| Subgrupo | Módulo Representado | Nombre de la Persona Representante | Participó en la consulta |
| :--- | :--- | :--- | :--- |
| **SG1** | M1 - Portal público y búsqueda | Roberth Ponce | Sí (aprobó) |
| **SG2** | M2 - Micrositios y comercios | Diego Alfaro Alvarado | Sí (solicitante) |
| **SG3** | M3 - Catálogo de productos | — | No consta respuesta |
| **SG4** | M4 - Inventario multiempresa | — | No consta respuesta en el chat; consultado aparte (ver acuerdo ACU-01) |
| **SG5** | M5 - Carrito y pedidos | Manuel Mejicano | Sí (aprobó) |
| **SG6** | M6 - Pagos y facturación | — | No consta respuesta |
| **SG7** | M7 - ERP contable y reportes | — | No consta respuesta en el chat; consultado aparte (ver acuerdo ACU-01) |

---

### Orden del Día
1. Solicitud de cambio SC-15 de M2: retiro de los consumos no implementados del contrato de M2 (versión 1.2.2).

---

### Acuerdos Tomados
| N.° Acuerdo | Descripción del Acuerdo | Módulos Afectados |
| :--- | :--- | :--- |
| **ACU-01** | Se aprueba la SC-15 de M2. El contrato de M2 pasa de la versión 1.2.1 a la 1.2.2 y retira de sus dependencias consumidas el evento `AlertaStockMinimo` de M4 y la operación `obtenerResumenFinancieroComercio` de M7, que se declararon en el diseño pero no se implementaron. La alerta de reposición la muestra M4 en «Existencias y mínimos» y el resumen financiero lo muestra M7 en su tablero; el panel de M2 enlaza a ambas pantallas (pestañas Inventario y Finanzas). La aprobaron Roberth Ponce (M1) y Manuel Mejicano (M5), con la condición de que el cambio no rompiera nada de los demás módulos y resolviera la necesidad de M2. Antes de la consulta, M4 dio su acuerdo por medio de Abril Chacón (SG2) y M7 indicó que no requiere cambios. | M2, M4, M7 |
| **ACU-02** | M4 actualiza su contrato al mismo tiempo: quita a M2 de los consumidores de `AlertaStockMinimo` en `openapi_M4_v1.3.0.yaml` y en el comentario de la clase del evento. | M4 |

---

### Contratos Aprobados o Modificados
| Módulo Proveedor | Operación / Endpoint | Versión | Estado / Consumidores |
| :--- | :--- | :--- | :--- |
| M2 | Contrato completo (`contracts/m2-comercios/v1/openapi.yaml`) | 1.2.2 | Aprobado. Cambio compatible: no cambia ninguna operación ni evento publicado por M2, solo la lista de dependencias consumidas. Integrado en `desarrollo` con el PR #340. |
| M4 | Evento `AlertaStockMinimo` | 1.0.0 (contrato M4 v1.3.0) | Sin consumidores. Cambio solo de documentación, en la rama `feature/M4-SC-15-consumidores-alerta`. |

---

### Reporte de Dependencias Bloqueadas
| Módulo Afectado | Módulo Proveedor | Fecha del Bloqueo | Causa y Acción de Contingencia (Doble de Prueba) |
| :--- | :--- | :--- | :--- |
| Ninguno reportado | - | - | La SC-15 retira dependencias que no estaban implementadas, así que no deja a ningún módulo bloqueado. |

---

### Tareas Asignadas
| Tarea / Compromiso | Responsable | Módulo | Fecha Límite |
| :--- | :--- | :--- | :--- |
| Registrar la resolución en la sección 5 de la SC-15 | Diego Alfaro Alvarado | M2 | 2026-11-15 |
| Integrar en `desarrollo` el cambio del contrato de M4 (ACU-02) | Coordinación de SG4 | M4 | 2026-11-15 |

---

### Puntos Pendientes para la Próxima Sesión
- [ ] Ninguno derivado de esta consulta.

---

**Fuente:** consulta en el chat de coordinadores del 13 de noviembre de 2026; SC-15 en `docs/solicitudes-cambio/` (PR #332) y PR #340.
