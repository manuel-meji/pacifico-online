# Y3 — Informe de cobertura M3

## Alcance

El alcance solicitado es:

- `app/Services/M3_Catalogo`
- `app/Models/M3_Catalogo`
- `app/Listeners/M3_Catalogo`
- `app/Http/Controllers/M3_Catalogo`

## Estado de la auditoría

Xdebug 3.5.3 está instalado y habilitado en modo `coverage`; PCOV no está instalado. El informe Clover se generó fuera del repositorio.

El proyecto sí tiene PHPUnit 12.5.34 y la configuración usa SQLite en memoria para pruebas (`phpunit.xml`).

La comprobación ejecutada fue:

```text
PHP 8.3.33
pdo_sqlite: disponible
pcov: no disponible
xdebug: 3.5.3, coverage habilitado
```

## Pruebas disponibles

Se preparó:

```text
tests/Feature/M3_Catalogo/Contratos/M3_ProveedorContratoTest.php
tests/Feature/M3_Catalogo/Aceptacion/M3_ConsultaEscenariosAceptacionTest.php
```

La prueba de contrato valida las seis operaciones contra las rutas HTTP actualmente registradas. Estas rutas son `/m3/v1/...`; no constituyen todavía cumplimiento completo del servidor contractual `/api/m3/v1`.

Resultado verificado de la última ejecución:

```text
13 tests
13 aprobadas
257 assertions
```

Las seis operaciones del proveedor ya tienen comprobaciones HTTP. El seeder M3 y el listener `ExistenciaAgotada` fueron verificados en PostgreSQL portátil aislado; los dos escenarios PHPUnit de aceptación siguen omitidos porque su flujo completo depende de la integración M5 y la prueba continúa configurada para SQLite en memoria.

## Comandos reproducibles de cobertura

```powershell
php artisan test --coverage
vendor/bin/phpunit --coverage-html coverage-m3 --filter M3_Catalogo
$env:APP_KEY='base64:<clave temporal de pruebas>'
vendor/bin/phpunit tests/Feature/M3_Catalogo tests/Unit/M3_Catalogo --coverage-clover <carpeta-temporal>\coverage.xml
```

La cobertura se ejecutó con Xdebug en la base integrada anterior. Resultado registrado del alcance exigido (21 archivos): 77 métodos, 54 cubiertos; 722 líneas instrumentadas, 579 cubiertas. Porcentaje: 70.13% de métodos y 80.19% de líneas. La suite produjo 70 aprobadas, 2 omitidas y 321 assertions; la ejecución posterior sobre `origin/desarrollo` registra 70 aprobadas, 2 omitidas y 384 assertions.

## Riesgos sin cobertura

- La persistencia real no está cubierta.
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

Xdebug fue instalado en el entorno local autorizado; no se modificaron `composer.json` ni `composer.lock`.

## Pendientes externos

Pendientes: corregir el prefijo HTTP a `/api/m3/v1` mediante una contribución autorizada y completar la integración del pedido M5 con sus promociones, impuestos y envío oficiales.
