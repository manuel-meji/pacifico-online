# Y4 — Manual de instalación y despliegue de M3

## Requisitos

- PHP 8.3 o superior.
- Composer disponible en el PATH.
- PostgreSQL configurado según el entorno del proyecto.
- Ejecutar primero los requisitos de M2 y luego las migraciones de M3.
- Usar una base propia para la validación; nunca la base compartida.

## Extensiones PHP

El entorno auditado tiene `pdo_pgsql`, `pdo_sqlite`, `fileinfo`, `gd` y `exif`. Xdebug 3.5.3 está instalado con cobertura habilitada; PCOV e `imagick` no están instalados.

El contrato M3 v1.3.0 recibe `imagen_url` como una URL y no publica una carga de archivos ni procesamiento de imágenes. Por ello, para la funcionalidad M3 actualmente publicada no se requiere una extensión de edición de imágenes. Si una implementación posterior procesa archivos, `fileinfo` permite detectar el tipo MIME y `gd` está disponible para formatos raster convencionales; el uso de `imagick` requeriría instalarlo y no está verificado.

## Almacenamiento de imágenes

La configuración actual usa el disco `local` por defecto y define el disco público en `storage/app/public`. La configuración publicada es:

```text
FILESYSTEM_DISK=local
```

Después de configurar el disco público debe ejecutarse:

```powershell
php artisan storage:link
```

`FILESYSTEM_DISK=local` selecciona el disco predeterminado `local`; no significa que las imágenes sean públicas. En la configuración actual, `local` usa `storage/app/private`. El disco público es otro disco nombrado `public`, configurado con raíz `storage/app/public`, visibilidad pública y URL `http://localhost/storage`. La configuración efectiva verificada con Laravel es `filesystems.default = local`; no se encontró una variable específica para una carpeta de imágenes M3 (`M3_*`, `IMAGE_*` o `IMAGEN_*`).

## Base de datos

### Validación local aislada ejecutada

Se utilizó PostgreSQL portátil oficial EDB 16.15, sin instalador ni servicio de Windows:

```text
Distribución: %LOCALAPPDATA%\PC4-M3\PostgreSQL\pgsql
Datos:        %LOCALAPPDATA%\PC4-M3\data
Host:         127.0.0.1
Puerto:       5433
Base:         pc4_m3_local
Usuario:      pc4_m3_admin
```

La conexión PHP 8.3 mediante `pdo_pgsql` fue verificada contra esa base. La base inició vacía; después se ejecutaron exclusivamente las ocho migraciones M3 y `M3CatalogoSeeder` en esa misma conexión local.

### Conexión a Supabase y puertos

La base PostgreSQL de Supabase es compartida por los subgrupos. Para el trabajo diario se debe utilizar el Transaction Pooler en el puerto `6543`, porque está destinado a las conexiones concurrentes del equipo.

El puerto `5432` corresponde al Session Mode y se utiliza únicamente para migraciones nuevas previamente autorizadas. Después de finalizar una migración autorizada, se debe regresar la configuración al puerto `6543` para continuar con el trabajo diario.

No se deben ejecutar las pruebas automatizadas contra Supabase. PHPUnit está configurado para utilizar SQLite en memoria (`DB_CONNECTION=sqlite` y `DB_DATABASE=:memory:`), por lo que las pruebas deben conservar esa base aislada y no leer ni escribir la base compartida.

Precauciones obligatorias sobre la base compartida:

- No ejecutar `migrate:fresh`.
- No ejecutar `migrate:reset`.
- No ejecutar `migrate:rollback`.
- No ejecutar `db:wipe`.
- No ejecutar seeders de prueba ni pruebas destructivas contra Supabase.
- No modificar `.env` ni copiar credenciales reales a documentación, evidencias o archivos ZIP.
- Verificar la conexión y la autorización antes de ejecutar cualquier migración en el puerto `5432`.

Orden confirmado de migraciones M3:

1. categorías
2. marcas
3. productos
4. variaciones
5. atributos de variación
6. imágenes de producto
7. productos relacionados

El seeder M3 oficial está publicado en `database/seeders/M3_Catalogo/M3CatalogoSeeder.php`. Define 10 productos y 11 variaciones con identificadores fijos y debe ejecutarse únicamente en una base propia aislada.

Comando ejecutado en la base local aislada:

```powershell
php artisan db:seed --class=Database\Seeders\M3_Catalogo\M3CatalogoSeeder
```

## Colas y listeners

En el entorno publicado la cola predeterminada es `database`. El proceso se inicia con:

```powershell
php artisan queue:work
```

Los listeners M3 de M2 y M4 están publicados en `app/Listeners/M3_Catalogo` y registrados por `M3ListenersServiceProvider`. Incluyen `ExistenciaAgotada`, `ExistenciaRepuesta` y la sincronización del estado de comercio.

## Verificación HTTP actual

Las rutas existentes son `/m3/v1/...`, mientras que el contrato declara `/api/m3/v1/...`. La diferencia permanece pendiente y no se modificó producción.

Solicitudes requeridas por el contrato:

```text
GET /api/m3/v1/productos/1
GET /api/m3/v1/variaciones/1
GET /api/m3/v1/productos?q=camisa&pagina=1&por_pagina=20
```

`q`, `pagina` y `por_pagina` son parámetros publicados por el contrato; `por_pagina` no puede superar 100. La respuesta esperada debe cumplir `ProductoDetalle`, `VariacionDetalle` y `ResultadoProductos` del contrato M3 v1.3.0.

El proveedor real ya implementa categorías, marcas, búsqueda, detalle de producto, detalle de variación y relacionados. La prueba Y1 valida sus respuestas bajo `/m3/v1`; el prefijo contractual `/api/m3/v1` sigue pendiente.

## Problemas frecuentes

- Verificar credenciales de PostgreSQL.
- Si Composer no se reconoce, abrir la terminal de Laragon o agregarlo al PATH.
- Usar el puerto 6543 para el trabajo diario cuando así lo establezca el entorno.
- Usar el puerto 5432 únicamente para migraciones, según el procedimiento del proyecto.

## Reproducibilidad

La prueba aislada de M3 se ejecutó con la distribución portátil anterior. El procedimiento reproducible es:

1. Clonar o copiar el proyecto a una carpeta nueva.
2. Ejecutar `composer install`.
3. Crear `.env` a partir de `.env.example`.
4. Configurar una base de datos propia vacía.
5. Ejecutar las migraciones M2 y M3 en orden.
6. Ejecutar el seeder M3 oficial.
7. Ejecutar `php artisan storage:link`.
8. Iniciar `php artisan queue:work`.
9. Ejecutar las tres solicitudes HTTP del contrato.
10. Ejecutar las pruebas M3.

La instalación y validación aislada de M3 fue exitosa. La prueba completa de integración M5 todavía no se declara exitosa porque requiere confirmar el escenario de pedido y sus datos de promociones/envío.

## Resultado de la comprobación aislada

La comprobación inicial con SQLite temporal se detuvo porque la migración de variaciones contiene la sentencia PostgreSQL:

```sql
codigo_barras IS NULL OR codigo_barras ~ '^[0-9]{8,14}$'
```

El operador `~` no es compatible con SQLite. La validación posterior con PostgreSQL portátil confirmó que las ocho migraciones M3 y el seeder funcionan sin modificar las migraciones ni tocar la base compartida.
