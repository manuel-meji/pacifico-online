
# Dobles de prueba de M3 - Catálogo

## Propósito

Esta carpeta contiene dobles para preparar escenarios de prueba de
las dependencias relacionadas con M3, sin depender de servicios
externos ni modificar los módulos de producción.

## Archivos

- ComerciosStub.php: doble de la interfaz de comercios existente.
- ComerciosNoDisponible.php: simula la indisponibilidad de M2.
- InventarioStub.php: doble de la interfaz de inventario existente.
- InventarioNoDisponible.php: simula la indisponibilidad de M4.
- ErrorProveedorException.php: excepción local de los dobles.
- EstadoComercioStub.php: simula estados y eventos de comercio de M2.
- DisponibilidadAgotadaStub.php: simula una respuesta de inventario agotado.

## Sustitución por servicios reales

Los dobles se utilizan únicamente en pruebas. Para utilizar servicios
reales, la aplicación debe resolver las interfaces mediante las
implementaciones registradas por el proyecto y su configuración
de integración.

La selección del servicio real o del doble debe realizarse mediante
el proveedor de servicios o el mecanismo de configuración autorizado
por el proyecto. Los cambios de configuración de producción quedan
fuera del alcance de esta carpeta.

## Limitaciones pendientes

Antes de conectar los eventos simulados con M3, se debe confirmar:

1. El contrato PHP definitivo de los eventos de M2.
2. El contrato del evento de existencias agotadas de M4.
3. El consumidor o mecanismo mediante el cual M3 recibe esos eventos.

Los arrays de este directorio son datos de prueba y no sustituyen
automáticamente los contratos oficiales ni configuran la integración.
