# Y3 — Informe de cobertura M3

## Alcance

El alcance solicitado es:

- `app/Services/M3_Catalogo`
- `app/Models/M3_Catalogo`
- `app/Listeners/M3_Catalogo`
- `app/Http/Controllers/M3_Catalogo`

## Estado de la auditoría

La extensión `xdebug` y el módulo `pcov` no aparecen en `php -m`. Por esa razón no se declara un porcentaje de cobertura ni se genera un informe HTML en esta etapa.

El proyecto sí tiene PHPUnit 12.5.34 y la configuración usa SQLite en memoria para pruebas (`phpunit.xml`).

La comprobación ejecutada fue:

```text
PHP 8.3.33
pdo_sqlite: disponible
pcov: no disponible
xdebug: no disponible
```

## Pruebas disponibles

Se preparó:

```text
tests/Feature/M3_Catalogo/Contratos/M3_ProveedorContratoTest.php
tests/Feature/M3_Catalogo/Aceptacion/M3_ConsultaEscenariosAceptacionTest.php
```

La ejecución actual de `vendor/bin/phpunit tests/Feature/M3_Catalogo --testdox` valida los esquemas contra las rutas HTTP actualmente registradas para `obtenerProducto` y `obtenerVariacion`, sus respuestas 404 y la versión/operaciones declaradas. Estas rutas son `/m3/v1/...`; no constituyen todavía cumplimiento completo del servidor contractual `/api/m3/v1`.

Resultado verificado de la última ejecución:

```text
13 tests
7 aprobadas
6 omitidas explícitamente
130 assertions
```

Las cuatro operaciones restantes del proveedor y los dos escenarios de aceptación completos quedan omitidos explícitamente hasta que existan sus dependencias.

## Comandos pendientes de ejecución con driver de cobertura

```powershell
php artisan test --coverage
vendor/bin/phpunit --coverage-html coverage-m3 --filter M3_Catalogo
```

Ambos comandos fueron intentados. Ambos terminaron con código 1 y el mensaje `Code coverage driver not available`. No se creó ni se conserva un informe HTML.

## Riesgos sin cobertura

- No hay modelos M3 implementados.
- No hay listeners M3 implementados.
- La persistencia real no está cubierta.
- No se puede evaluar el filtro de comercios activos ni productos publicados.
- No se puede evaluar el efecto de `ExistenciaAgotada`.
- No se puede medir la cobertura de las rutas HTTP contractuales `/api/m3/v1` porque el repositorio solo registra actualmente `/m3/v1`.

## Procedimiento cuando exista el driver

1. Confirmar que PCOV o Xdebug esté cargado con `php -m`.
2. Ejecutar la suite M3 en SQLite en memoria.
3. Ejecutar `php artisan test --coverage`.
4. Ejecutar `vendor/bin/phpunit --coverage-html coverage-m3 --filter M3_Catalogo`.
5. Revisar líneas y bloques no cubiertos únicamente dentro del alcance M3.
6. Agregar pruebas solo para regiones de riesgo que pertenezcan a este entregable.
7. Repetir la cobertura y conservar el HTML fuera de la base compartida.

## Procedimiento local para habilitar cobertura

El entorno actual usa PHP 8.3.33 Thread Safe x64 desde Laragon:

```text
D:\Programas\laragon\bin\php\php-8.3.33-Win32-vs16-x64\php.exe
D:\Programas\laragon\bin\php\php-8.3.33-Win32-vs16-x64\php.ini
```

La opción recomendada es Xdebug compatible exactamente con esa versión, arquitectura y modalidad Thread Safe:

1. Consultar el instalador oficial de Xdebug con la salida de `php -i`.
2. Descargar la DLL compatible y colocarla en el directorio `ext` de esa instalación de PHP.
3. Agregar en `php.ini` una línea `zend_extension=php_xdebug.dll` o la ruta exacta de la DLL.
4. Cerrar y abrir la terminal de Laragon.
5. Confirmar con `php -m` y `php --ri xdebug`.
6. Ejecutar los comandos de cobertura de esta documentación.

PCOV es una alternativa si se dispone de un binario compatible con Windows, PHP 8.3 y esa instalación. No se instala automáticamente ni se recomienda mezclar ambos drivers.

La instalación de Xdebug/PCOV y la edición de `php.ini` requieren autorización específica de Yan porque modifican el entorno local y no son cambios del repositorio. No se requiere modificar `composer.json` ni `composer.lock` para habilitar el driver.

## Pendientes externos

La cobertura final requiere la publicación del proveedor M3, el seeder oficial y autorización para trabajar en `feature/M3-cobertura-manual`. No se inventa porcentaje ni resultado.
