# Acta de Sesión del Comité de Arquitectura e Integración
**Anexo D - IF6100 Pacíficos Online**

---

### Datos Generales
- **Número de Acta:** ACTA-COMITE-PC4-05
- **Fecha y Hora:** 2026-11-13 - 22:29 (duración aproximada: 1 hora 25 minutos)
- **Lugar / Modalidad:** Sesión virtual (Google Meet), con grabación y transcripción. El mismo día se aprobó por chat la SC-15 (ACTA-COMITE-PC4-02).

---

### Asistencia y Representación
| Subgrupo | Módulo Representado | Nombre de la Persona Representante | Presente (Sí/No) |
| :--- | :--- | :--- | :--- |
| **SG1** | M1 - Portal público y búsqueda | Roberth Ponce | Sí |
| **SG2** | M2 - Micrositios y comercios | Diego Alfaro Alvarado | Sí (con Mia Wilson y Jorge M. Salazar) |
| **SG3** | M3 - Catálogo de productos | Jeaustin Morales | Sí |
| **SG4** | M4 - Inventario multiempresa | Jorge Alvarado Moreno | Sí |
| **SG5** | M5 - Carrito y pedidos | Manuel Mejicano | Sí |
| **SG6** | M6 - Pagos y facturación | Justin Marenco | Sí (con Ian Moraga y Félix Luna) |
| **SG7** | M7 - ERP contable y reportes | Martin Angulo | No. Por SG7 participó David Ávila Torres |

---

### Orden del Día
1. Revisión del estado de la integración de cada módulo en el ambiente.
2. Línea de diseño del frontend.
3. Fecha definitiva del congelamiento del código.
4. Ejecución del recorrido transversal antes de la consultoría.

---

### Acuerdos Tomados
| N.° Acuerdo | Descripción del Acuerdo | Módulos Afectados |
| :--- | :--- | :--- |
| **ACU-01** | El congelamiento del código pasa al domingo 15 de noviembre a las 6 p. m. y es definitivo: no habrá más extensiones. Modifica ACTA-COMITE-PC4-01 (ACU-01) y ACTA-COMITE-PC4-03 (ACU-03). | Todos |
| **ACU-02** | Si a esa hora un módulo no está integrado, los demás lo prueban con su doble o con datos cargados en la base, para que su atraso no afecte al resto. | Todos |
| **ACU-03** | Se deja constancia (ACTA-COMITE-PC4-01, ACU-02) de que el congelamiento se movió del 13 al 15 de noviembre porque, al 13 de noviembre, M3 y M7 aún no estaban integrados con los demás módulos, y de que M3 no cumplió el cierre del desarrollo interno del 10 de noviembre (ACTA-COMITE-PC4-04, ACU-01). | M3, M7 |
| **ACU-04** | Se realiza una sesión, tentativamente el domingo 15 de noviembre en la noche, para ejecutar el recorrido transversal del escenario que se presentará en la defensa. | Todos |
| **ACU-05** | Las pantallas se ajustan a la línea de diseño del frontend acordada por el Comité (documento de M5). | Todos |

---

### Estado reportado por módulo
- **M3:** se revisó el manejo de imágenes y el enlace desde los paneles de M2 y M4 hacia el catálogo; le faltaba integrarse con M2.
- **M4:** se revisó la operación de transferencias entre sedes.
- **M6:** se conectó con M1 la noche anterior y con M2 ese día; se acordó retirar la pestaña de historial que no correspondía.
- **M7:** su representante indicó que la interfaz aún no está integrada con algunos módulos.

---

### Contratos Aprobados o Modificados
| Módulo Proveedor | Operación / Endpoint | Versión | Estado / Consumidores |
| :--- | :--- | :--- | :--- |
| M2 | Contrato completo | 1.2.2 | Aprobado el mismo día por chat (SC-15, ACTA-COMITE-PC4-02). No se trató en esta sesión. |

---

### Reporte de Dependencias Bloqueadas
| Módulo Afectado | Módulo Proveedor | Fecha del Bloqueo | Causa y Acción de Contingencia (Doble de Prueba) |
| :--- | :--- | :--- | :--- |
| M1, M2 y M4 | M3 | 2026-11-09 | Sigue pendiente la integración real con M3; los consumidores usan su doble (ver ACU-02). |
| Módulos que consultan a M7 | M7 | 2026-11-13 | M7 no está integrado por completo; se usa su doble hasta el congelamiento (ver ACU-02). |

---

### Tareas Asignadas
| Tarea / Compromiso | Responsable | Módulo | Fecha Límite |
| :--- | :--- | :--- | :--- |
| Integrar M3 con M2 (enlace desde el panel) y con sus consumidores | Jeaustin Morales | M3 | 2026-11-15, 6 p. m. |
| Integrar M7 con los demás módulos | Martin Angulo | M7 | 2026-11-15, 6 p. m. |
| Retirar la pestaña de historial | Coordinación de SG6 | M6 | 2026-11-15 |
| Convocar la sesión del recorrido transversal | Roberth Ponce | M1 | 2026-11-15 |

---

### Puntos Pendientes para la Próxima Sesión
- [ ] Confirmar la hora de la sesión del recorrido transversal.
- [ ] Verificar al congelamiento qué módulos quedaron integrados y cuáles se prueban con dobles.

---

**Fuente:** grabación y transcripción de la sesión del 13 de noviembre de 2026 (Notas de Gemini).
