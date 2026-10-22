Anexo: declaración de uso de herramientas de inteligencia artificial
- Proyecto: Pacíficos Online
- Curso: IF6100
- Subgrupo: SG3 — Catálogo de productos (M3)
- Entrega: PC3
- Fecha de elaboración: 22 de octubre de 2026
Este anexo reúne las declaraciones de uso de IA recibidas para el trabajo del subgrupo. Las afirmaciones sobre herramientas, actividades y verificaciones se limitan a la información suministrada por cada integrante.
1. Jeaustin Morales Hidalgo
Herramienta utilizada: Claude (Anthropic).

Propósito y partes apoyadas: La herramienta se utilizó como apoyo para distribuir las tareas del PC3 entre los integrantes del subgrupo; orientar el uso de Git, GitHub, Composer y Laravel Herd; redactar las siete migraciones de Laravel de M3 y el SQL asociado; revisar y proponer cambios al contrato OpenAPI de M3 (v1.3.0); desarrollar el servicio, los controladores y las rutas de M3 con datos fijos; elaborar el diagrama entidad-relación de M3; y preparar borradores de las secciones 1 y 6 del documento del módulo y de la sección 1.9 del documento del Comité.

Verificación y alcance: El SQL se probó en una base PostgreSQL local con casos válidos y casos que debían fallar. Las migraciones PHP se revisaron con php -l, pero no se ejecutaron contra la base de datos compartida. Según la información declarada, el equipo revisó el contenido antes de entregarlo.
2. Joshua Soto Hernández (C4K209)
Alcance: Curso y entrega: IF6100, PC3, Módulo 3 (SG3), secciones 2 y 7.

Herramienta utilizada: Claude (Anthropic), modelo Claude Sonnet 5.5, utilizado el 21 y 22 de octubre de 2026.

Propósito y partes apoyadas: Se utilizó para revisar el contrato OpenAPI de M3 (v1.3.0) y el diagrama de clases del módulo; identificar diferencias entre ambos; trasladar a las tablas de la sección 2 la información contenida en el archivo YAML; apoyar la creación y justificación de tres patrones de diseño (DTO, Adapter y Observer) para la sección 7; y recortar fragmentos del diagrama. La persona declara que decidió y aplicó los cambios y resolvió las diferencias señaladas entre el diagrama y el contrato.

Verificación y alcance: La verificación descrita consistió en contrastar el contrato y el diagrama, resolver las diferencias identificadas y aplicar los cambios correspondientes. La información suministrada no especifica pruebas automatizadas adicionales.
3. Adrián Duran Madrigal
Herramienta utilizada: ChatGPT.

Propósito y partes apoyadas: La inteligencia artificial se utilizó como apoyo para organizar y revisar aspectos de la implementación del módulo 3. Ayudó a identificar componentes principales de las pantallas de registro de productos y consulta del catálogo, y a revisar validaciones de formularios y filtros de búsqueda. También se recibió apoyo para detectar posibles inconsistencias entre los campos del prototipo y los requisitos del módulo, así como para preparar y revisar pruebas funcionales relacionadas con campos obligatorios, variaciones de productos, SKU duplicados y visualización del catálogo en dispositivos móviles.

Verificación y alcance: La declaración indica que las recomendaciones debían revisarse y que era necesario ejecutar pruebas y comprobar el funcionamiento del prototipo. El trabajo se centró en desarrollar y ajustar las interfaces; no se implementó todavía la conexión con la base de datos ni la persistencia de los productos.
4. Javier Contreras López
Herramienta utilizada: ChatGPT.

Propósito y partes apoyadas: Se utilizó para revisar los contratos e interfaces existentes del proyecto, identificar los requisitos de los dobles de prueba del módulo M3 (Catálogo), verificar la sintaxis del código PHP y recibir orientación para identificar dependencias entre módulos y respetar el alcance de los archivos asignados.

Verificación y alcance: La información suministrada describe la revisión de contratos e interfaces y la verificación de sintaxis PHP, pero no detalla resultados concretos de ejecución de pruebas; estos no se afirman en esta declaración.
5. Yan Enils Ortiz Barrantes
Alcance: Curso y entrega: IF6100, PC3, Módulo 3 (SG3).

Herramienta utilizada: ChatGPT.

Propósito y partes apoyadas: Se utilizó como apoyo técnico para preparar el entorno local y revisar inicialmente el módulo M3; comprender procedimientos de Git; orientar la instalación de dependencias de PHP y JavaScript; configurar Laravel y diagnosticar un error relacionado con el controlador PDO de PostgreSQL; interpretar archivos del proyecto; revisar el controlador y el servicio de catálogo; comparar las rutas existentes con el contrato OpenAPI de M3 v1.3.0; proponer comandos de comprobación HTTP; y organizar el trabajo con ramas y solicitudes de integración en GitHub. En relación con los diagramas de secuencia DS-01 a DS-06, ChatGPT se utilizó únicamente para recibir orientación y comprobar el trabajo realizado; la elaboración fue propia.

Verificación y alcance: Las instrucciones se ejecutaron manualmente en el entorno local y los resultados se revisaron en PowerShell. Se comprobó que la ruta de estado de M3 respondiera, que el endpoint de detalle devolviera un producto ficticio y que un identificador inexistente produjera HTTP 404. Estas pruebas utilizaron datos fijos del servicio de catálogo; por sí solas no demuestran que el módulo esté desplegado en integración ni conectado a datos reales. Durante la revisión se identificó una diferencia entre el prefijo de las rutas registradas en Laravel y el establecido en el contrato OpenAPI v1.3.0. La modificación propuesta para corregirla no se aplicó mientras se esperaba confirmación.