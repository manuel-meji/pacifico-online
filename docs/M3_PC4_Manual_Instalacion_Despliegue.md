# Y4 — Manual de instalación y despliegue de M3

## Requisitos

- PHP 8.3 o superior.
- Composer disponible en el PATH.
- PostgreSQL configurado según el entorno del proyecto.
- Ejecutar primero los requisitos de M2 y luego las migraciones de M3.
- Usar una base propia para la validación; nunca la base compartida.

## Extensiones PHP

El entorno auditado tiene `pdo_pgsql`, `pdo_sqlite`, `fileinfo`, `gd` y `exif`. `imagick`, PCOV y Xdebug no están instalados.

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

No existe actualmente un seeder M3 en el repositorio. Por tanto, el comando final queda pendiente de confirmación de Jeaustin y no se inventa un namespace.

Cuando esté publicado, el comando tendrá esta forma, sustituyendo por el nombre real:

```powershell
php artisan db:seed --class=Database\Seeders\M3_Catalogo\M3CatalogoSeeder
```

## Colas y listeners

En el entorno publicado la cola predeterminada es `database`. El proceso se inicia con:

```powershell
php artisan queue:work
```

Los listeners M3 que reciban eventos de M4 y M2 deben estar publicados antes de validar esta integración. Actualmente no existe listener M3 para `ExistenciaAgotada`.

## Verificación HTTP actual

Las rutas existentes son `/m3/v1/...`, mientras que el contrato declara `/api/m3/v1/...`. Esta diferencia debe ser confirmada por el responsable de rutas antes de documentar un resultado exitoso.

Solicitudes requeridas por el contrato:

```text
GET /api/m3/v1/productos/1
GET /api/m3/v1/variaciones/1
GET /api/m3/v1/productos?q=camisa&pagina=1&por_pagina=20
```

`q`, `pagina` y `por_pagina` son parámetros publicados por el contrato; `por_pagina` no puede superar 100. La respuesta esperada debe cumplir `ProductoDetalle`, `VariacionDetalle` y `ResultadoProductos` del contrato M3 v1.3.0.

La aplicación actual registra `/m3/v1/productos/{id_producto}` y `/m3/v1/variaciones/{id_variacion}`, no `/api/m3/v1`, y no registra la búsqueda. No se modifican esas rutas; la validación HTTP contractual queda pendiente del responsable de rutas y del proveedor M3.

## Problemas frecuentes

- Verificar credenciales de PostgreSQL.
- Si Composer no se reconoce, abrir la terminal de Laragon o agregarlo al PATH.
- Usar el puerto 6543 para el trabajo diario cuando así lo establezca el entorno.
- Usar el puerto 5432 únicamente para migraciones, según el procedimiento del proyecto.

## Reproducibilidad

La prueba desde una carpeta limpia y una base propia vacía todavía no se ha ejecutado. Cuando estén disponibles el seeder y las dependencias externas:

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

No se declara una prueba limpia exitosa porque aún no se ha realizado.

## Resultado de la comprobación aislada

Se intentó una comprobación segura usando una base SQLite temporal fuera del repositorio, sin `.env`, sin Supabase y sin seeders. Las migraciones M3 de categorías, marcas y productos se ejecutaron; la migración de variaciones se detuvo porque contiene la sentencia PostgreSQL:

```sql
codigo_barras IS NULL OR codigo_barras ~ '^[0-9]{8,14}$'
```

El operador `~` no es compatible con SQLite. La base temporal y sus artefactos fueron eliminados. Este resultado no autoriza modificar la migración ni ejecutar la prueba contra la base compartida. La reproducibilidad completa sigue pendiente de una base PostgreSQL propia y del seeder M3 oficial.
