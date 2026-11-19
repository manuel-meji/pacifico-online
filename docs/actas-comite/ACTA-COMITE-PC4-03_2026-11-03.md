# Acta de Sesión del Comité de Arquitectura e Integración
**Anexo D - IF6100 Pacíficos Online**

---

### Datos Generales
- **Número de Acta:** ACTA-COMITE-PC4-03
- **Fecha y Hora:** 2026-11-03 - 21:59 (duración aproximada: 47 minutos)
- **Lugar / Modalidad:** Sesión virtual (Google Meet), con grabación y transcripción. Sesión conjunta de los representantes del Comité y del equipo del núcleo compartido.

---

### Asistencia y Representación
| Subgrupo | Módulo Representado | Nombre de la Persona Representante | Presente (Sí/No) |
| :--- | :--- | :--- | :--- |
| **SG1** | M1 - Portal público y búsqueda | Roberth Ponce | Sí |
| **SG2** | M2 - Micrositios y comercios | Diego Alfaro Alvarado | Sí. Por el equipo del núcleo: Jorge M. Salazar |
| **SG3** | M3 - Catálogo de productos | Jeaustin Morales | Sí, se unió cerca del minuto 36. Por el equipo del núcleo, desde el inicio: Javier Contreras |
| **SG4** | M4 - Inventario multiempresa | Jorge Alvarado Moreno | No. Por el equipo del núcleo: Roger Ruiz |
| **SG5** | M5 - Carrito y pedidos | Manuel Mejicano | Sí |
| **SG6** | M6 - Pagos y facturación | Justin Marenco | Sí, también en nombre de Félix Luna (equipo del núcleo) |
| **SG7** | M7 - ERP contable y reportes | Martin Angulo | Sí. Por el equipo del núcleo: David Ávila Torres |

También participaron Benjamín Pizarro Chaves y Marcos Ortega, del equipo del núcleo.

---

### Orden del Día
1. Coordinación del equipo del núcleo compartido y estado de las tareas de cada representante.
2. Fechas de cierre del desarrollo interno, integración y congelamiento del código.
3. Avance del documento colectivo del PC4.
4. Flujo de integración a la rama desarrollo.

---

### Acuerdos Tomados
| N.° Acuerdo | Descripción del Acuerdo | Módulos Afectados |
| :--- | :--- | :--- |
| **ACU-01** | Benjamín Pizarro Chaves coordina el equipo del núcleo y da seguimiento a que cada representante cumpla sus tareas. Quien no pueda cumplir una tarea la delega en otra persona de su subgrupo y lo avisa en el grupo. | Núcleo, todos |
| **ACU-02** | Las correcciones pendientes del núcleo se entregan a más tardar el miércoles 4 de noviembre a medianoche. | Núcleo |
| **ACU-03** | Cada módulo termina su desarrollo interno a más tardar el martes 10 de noviembre; del 10 al 13 de noviembre se integran los módulos y el 13 se mantiene el congelamiento del código (ACTA-COMITE-PC4-01, ACU-01). Quien pueda integrar antes del 10 lo hace coordinándose con el módulo proveedor. | Todos |
| **ACU-04** | Todo cambio en un contrato o en los tipos de datos que consume otro módulo se avisa a sus consumidores antes de integrarlo. | Todos |
| **ACU-05** | El código se integra a desarrollo solo mediante pull request; una rama subida sin pull request no se revisa. Nadie modifica carpetas de otro módulo. | Todos |

---

### Contratos Aprobados o Modificados
| Módulo Proveedor | Operación / Endpoint | Versión | Estado / Consumidores |
| :--- | :--- | :--- | :--- |
| Ninguno | - | - | No se aprobaron ni modificaron contratos en esta sesión. |

---

### Reporte de Dependencias Bloqueadas
| Módulo Afectado | Módulo Proveedor | Fecha del Bloqueo | Causa y Acción de Contingencia (Doble de Prueba) |
| :--- | :--- | :--- | :--- |
| Todos | Núcleo | 2026-11-03 | El PR #112 (inicio de sesión del núcleo), integrado durante la sesión, configura Sanctum sin instalar el paquete, por lo que la aplicación no arranca en desarrollo. Se corrigió el 2026-11-04 con los PR #113 y #114. |
| M7 | Núcleo | 2026-11-03 | M7 usa nombres provisionales para no depender de las partes del núcleo que aún fallan, hasta que el núcleo quede corregido. |

---

### Tareas Asignadas
| Tarea / Compromiso | Responsable | Módulo | Fecha Límite |
| :--- | :--- | :--- | :--- |
| Corregir el arranque de desarrollo causado por el PR #112 | Equipo del núcleo (Jorge M. Salazar) | Núcleo | 2026-11-04 |
| Corregir la clase de roles y los códigos de error duplicados | Javier Contreras | Núcleo | 2026-11-04 |
| Corregir ContextoComercio y ContextoCliente | Félix Luna | Núcleo | 2026-11-04 |
| Dar seguimiento a las tareas del equipo del núcleo | Benjamín Pizarro Chaves | Núcleo | 2026-11-04 |
| Redactar la sección 6 del documento colectivo (pendiente en la revisión de esta sesión) | Martin Angulo | M7 | 2026-11-04 |
| Terminar el desarrollo interno de cada módulo | Representante de cada subgrupo | Todos | 2026-11-10 |

---

### Puntos Pendientes para la Próxima Sesión
- [ ] Verificar que desarrollo arranque para todos después de las correcciones del núcleo.
- [ ] Revisar el avance de cada módulo antes del cierre del desarrollo interno del 10 de noviembre.

---

**Fuente:** grabación y transcripción de la sesión del 3 de noviembre de 2026 (Notas de Gemini); historial de merges de desarrollo para los PR #112 a #114.
