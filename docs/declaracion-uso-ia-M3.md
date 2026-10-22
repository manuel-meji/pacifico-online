# Declaración de uso de herramientas de inteligencia artificial

**Proyecto:** Pacíficos Online

**Curso:** IF6100

**Subgrupo:** SG3 — Catálogo de productos (M3)

**Entrega:** PC3

**Estudiante:** Yan Enils Ortiz Barrantes

**Fecha:** 22 de octubre de 2026

## 1. Herramienta utilizada

Se utilizó ChatGPT como herramienta de inteligencia artificial generativa para recibir orientación técnica durante la preparación del entorno local y la revisión inicial del módulo M3.

## 2. Uso que se le dio a la herramienta

La herramienta se utilizó para explicar los procedimientos de Git, ayudar con la instalación de dependencias de PHP y JavaScript, orientar la configuración inicial de Laravel y diagnosticar un error relacionado con el controlador PDO de PostgreSQL.

También se utilizó para interpretar archivos del proyecto, revisar el controlador y el servicio de catálogo, comparar las rutas existentes con el contrato OpenAPI de M3 v1.3.0 y proponer comandos para comprobar las respuestas HTTP del endpoint.

Adicionalmente, brindó orientación para organizar los entregables y el procedimiento de trabajo con ramas y solicitudes de integración en GitHub.

## 3. Verificación de los resultados

Las instrucciones se ejecutaron manualmente en el entorno local y sus resultados se revisaron mediante la terminal de PowerShell.

Se comprobó que la ruta de estado de M3 respondiera correctamente, que el endpoint de detalle devolviera un producto ficticio y que un identificador inexistente produjera una respuesta HTTP 404.

Estas pruebas se realizaron con los datos fijos implementados en el servicio de catálogo; no demuestran por sí mismas que el módulo esté desplegado en el ambiente de integración ni conectado a datos reales.

Durante la revisión se identificó una diferencia entre el prefijo de las rutas registradas en Laravel y el definido en el contrato OpenAPI v1.3.0. La modificación propuesta para corregir esa diferencia no se aplicó mientras se esperaba su confirmación.

## 4. Alcance y responsabilidad académica

La inteligencia artificial se utilizó como apoyo para el aprendizaje, el diagnóstico de errores y la comprensión de la estructura del proyecto. Sus recomendaciones no se consideran automáticamente correctas y deben verificarse contra los requisitos del curso, el contrato publicado y las instrucciones del equipo.

El estudiante mantiene la responsabilidad de comprender, revisar y validar los artefactos que presente como parte de su trabajo.

Al momento de elaborar esta declaración, las pruebas locales descritas se han completado, pero el despliegue en el ambiente de integración y la entrega final todavía están pendientes.

## 5. Transparencia

Esta declaración describe el uso de la herramienta durante el trabajo realizado hasta la fecha. Cualquier uso adicional de inteligencia artificial durante la elaboración de documentación, diagramas o correcciones deberá reflejarse fielmente en la versión final de la declaración.