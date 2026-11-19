# Acta de Sesión del Comité de Arquitectura e Integración
**Anexo D - IF6100 Pacíficos Online**

---

### Datos Generales
- **Número de Acta:** ACTA-COMITE-PC4-06
- **Fecha y Hora:** 2026-11-15 - 20:07 (duración aproximada: 2 horas 19 minutos)
- **Lugar / Modalidad:** Sesión virtual (Google Meet), con grabación y transcripción. Sesión convocada en ACTA-COMITE-PC4-05 (ACU-04) para recorrer la plataforma después del congelamiento del código (commit 3f06460, 15/11, 5:58 p. m.).

---

### Asistencia y Representación
| Subgrupo | Módulo Representado | Nombre de la Persona Representante | Presente (Sí/No) |
| :--- | :--- | :--- | :--- |
| **SG1** | M1 - Portal público y búsqueda | Roberth Ponce | Sí |
| **SG2** | M2 - Micrositios y comercios | Diego Alfaro Alvarado | Sí |
| **SG3** | M3 - Catálogo de productos | Jeaustin Morales | No. Por SG3 participó Joshua Hernández, sin computadora, entre los minutos 11 y 76; al cierre de la sesión no había ningún integrante de SG3 |
| **SG4** | M4 - Inventario multiempresa | Jorge Alvarado Moreno | Sí |
| **SG5** | M5 - Carrito y pedidos | Manuel Mejicano | Sí, hasta el minuto 46; después lo sustituyó Marcos Ortega |
| **SG6** | M6 - Pagos y facturación | Justin Marenco | Sí |
| **SG7** | M7 - ERP contable y reportes | Martin Angulo | Sí (con David Ávila Torres) |

También participó Benjamín Pizarro Chaves, coordinador del equipo del núcleo.

---

### Orden del Día
1. Recorrido de la plataforma en el ambiente de integración, con la base de Supabase nueva, después del congelamiento.
2. Defectos encontrados y responsables de corregirlos.
3. Reglas de trabajo entre el congelamiento y la entrega.
4. Preparación de la consultoría del 18/11 y de la defensa.

---

### Acuerdos Tomados
| N.° Acuerdo | Descripción del Acuerdo | Módulos Afectados |
| :--- | :--- | :--- |
| **ACU-01** | Desde el congelamiento no se agregan funcionalidades nuevas; solo se corrigen defectos, empezando por el error entre M4 y M5 que interrumpe el recorrido. | Todos |
| **ACU-02** | Antes de corregir un problema significativo, el módulo lo presenta en el grupo de coordinadores, y se evitan las correcciones que obliguen a otros módulos a cambiar su código. | Todos |
| **ACU-03** | Cada módulo es responsable de cumplir su alcance. Cada integrante recorre las funciones de su módulo en el ambiente para conocer el flujo antes de la defensa. | Todos |
| **ACU-04** | En la consultoría del miércoles 18/11 se le consulta al profesor cómo será la defensa. | Todos |
| **ACU-05** | Se deja constancia (ACTA-COMITE-PC4-01, ACU-02) de que el coordinador de SG3 no asistió a la sesión convocada para revisar la plataforma después del congelamiento, y de que SG3 no pudo atender en la sesión los defectos de su módulo. | M3 |

---

### Defectos encontrados en el recorrido
- **M2:** en la base nueva no existía la columna `cuenta_creada` de `solicitudes_comercio` (migración del PR #363), por lo que fallaba el envío de solicitudes. La corrige M2 aplicando su migración en la base nueva.
- **M4 y M5:** un error entre la reserva de existencias y el pedido interrumpe el recorrido de compra.
- **M3:** el catálogo no muestra las imágenes de los productos y el registro de atributos de las variaciones no funciona como se espera.
- **M1:** la sesión del cliente se cierra al tocar el logo del portal desde la lista de deseos o el historial de pedidos.
- **M7:** no se visualizan los datos que provienen de las facturas de M6.
- **M3, M4 y M5:** se consultó cuál módulo define el costo unitario y el impuesto; M4 aclaró que su reporte valora el inventario a costo, que el precio de venta es de M3 y que las ventas y ganancias son de M7.

---

### Contratos Aprobados o Modificados
| Módulo Proveedor | Operación / Endpoint | Versión | Estado / Consumidores |
| :--- | :--- | :--- | :--- |
| Ninguno | - | - | No se aprobaron ni modificaron contratos en esta sesión. |

---

### Reporte de Dependencias Bloqueadas
| Módulo Afectado | Módulo Proveedor | Fecha del Bloqueo | Causa y Acción de Contingencia (Doble de Prueba) |
| :--- | :--- | :--- | :--- |
| M5 | M4 | 2026-11-15 | Error entre la reserva de existencias y el pedido que interrumpe el recorrido; se corrige antes de la consultoría (ACU-01). |
| M7 | M6 | 2026-11-15 | M7 no recibe en pantalla los datos de las facturas de M6. |

---

### Tareas Asignadas
| Tarea / Compromiso | Responsable | Módulo | Fecha Límite |
| :--- | :--- | :--- | :--- |
| Aplicar en la base nueva la migración de `cuenta_creada` | Diego Alfaro Alvarado | M2 | 2026-11-17 |
| Corregir el error entre la reserva y el pedido | Coordinaciones de SG4 y SG5 | M4, M5 | 2026-11-18 |
| Corregir las imágenes y los atributos del catálogo | Coordinación de SG3 | M3 | 2026-11-18 |
| Corregir el cierre de sesión al tocar el logo del portal | Roberth Ponce | M1 | 2026-11-18 |
| Revisar la recepción de los datos de facturas | Martin Angulo | M7 | 2026-11-18 |

---

### Puntos Pendientes para la Próxima Sesión
- [ ] Verificar en la consultoría del 18/11 que el recorrido se complete con las correcciones.
- [ ] Registrar la respuesta del profesor sobre la forma de la defensa.

---

**Fuente:** grabación y transcripción de la sesión del 15 de noviembre de 2026 (Notas de Gemini).
