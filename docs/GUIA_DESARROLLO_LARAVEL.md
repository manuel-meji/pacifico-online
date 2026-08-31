# Guía de Desarrollo en Laravel para Pacíficos Online
**Manual Práctico para Estudiantes (IF6100 - II Ciclo 2026)**

Esta guía está diseñada para que cualquier integrante del curso, incluso **sin experiencia previa en Laravel**, comprenda el flujo de trabajo, aplique las convenciones del proyecto y evite errores críticos que afecten la calificación.

---

## 1. El Ciclo de Vida de una Petición en Nuestra Arquitectura

Cuando un usuario interactúa con la plataforma:
1. **Ruta (`routes/modules/m*.php`):** Recibe la solicitud HTTP y la dirige al controlador.
2. **Controlador (`app/Http/Controllers/M*/...`):** Valida la entrada y delega el trabajo al Servicio. **No contiene consultas SQL ni lógica de negocio pesada.**
3. **Servicio (`app/Services/M*/...`):** Ejecuta las reglas de negocio, interactúa con sus propios Modelos y, si necesita datos de otro módulo, invoca el Contrato correspondiente.
4. **Modelo (`app/Models/M*/...`):** Accede exclusivamente a las tablas de la base de datos pertenecientes a su módulo.
5. **Vista o Respuesta:** El controlador retorna una vista Blade (`resources/views/m*-*`) o una respuesta JSON.

---

## 2. Regla de Oro: ¿Cómo Comunicarse con Otro Módulo?

### ❌ LO QUE ESTÁ TOTALMENTE PROHIBIDO (Error Fatal)

**NUNCA importes el modelo Eloquent de otro subgrupo ni hagas consultas directas a sus tablas:**

```php
// ❌ INCORRECTO: Viola la regla de Prohibición de Lectura Directa (Sección 5.4)
namespace App\Services\M5_Pedidos;

use App\Models\M4_Inventario\Inventario; // ❌ PROHIBIDO
use Illuminate\Support\Facades\DB;

class PedidosService {
    public function procesar() {
        // ❌ NUNCA hagas esto:
        $stock = Inventario::where('variacion_id', 10)->first();
        
        // ❌ TAMPOCO hagas JOIN con tablas de otros módulos:
        $datos = DB::table('pedidos_encabezado')
            ->join('inventario_existencias', 'pedidos_encabezado.variacion_id', '=', 'inventario_existencias.variacion_id')
            ->get();
    }
}
```

---

###  LA FORMA CORRECTA (Inyección de Dependencias por Contrato)

Usa siempre la **Interfaz de Contrato** de `app/Contracts/`:

```php
//  CORRECTO: Inyección del contrato a través del constructor
namespace App\Services\M5_Pedidos;

use App\Contracts\M5_Pedidos\PedidosServiceInterface;
use App\Contracts\M4_Inventario\InventarioServiceInterface; //  Contrato público permitido

class PedidosService implements PedidosServiceInterface
{
    protected InventarioServiceInterface $inventarioService;

    // Laravel inyecta automáticamente la implementación registrada
    public function __construct(InventarioServiceInterface $inventarioService)
    {
        $this->inventarioService = $inventarioService;
    }

    public function crearPedido(array $datosCompra): array
    {
        $variacionId = $datosCompra['variacion_id'];
        $cantidad = $datosCompra['cantidad'];

        //  Consultamos a M4 a través de su interfaz oficial
        $existencias = $this->inventarioService->consultarExistencias($variacionId);

        if ($existencias < $cantidad) {
            throw new \Exception("Existencias insuficientes para la variación $variacionId");
        }

        // Solicitar reserva atómica a M4
        $reserva = $this->inventarioService->solicitarReserva($variacionId, $cantidad, 123);

        return [
            'pedido_id' => 123,
            'reserva' => $reserva,
            'estado' => 'CREADO'
        ];
    }
}
```

---

## 3. Guía Paso a Paso para Desarrollar una Característica

### Paso 1: Definir o Actualizar el Contrato (`app/Contracts/`)
Si tu módulo ofrece una operación a los demás, primero define la firma en tu interfaz:

```php
// app/Contracts/M3_Catalogo/CatalogoServiceInterface.php
namespace App\Contracts\M3_Catalogo;

interface CatalogoServiceInterface
{
    public function obtenerDetalleProducto(int $productoId): ?array;
}
```

Actualiza también el archivo OpenAPI correspondiente en `contracts/m3-catalogo/v1/openapi.yaml`.

---

### Paso 2: Crear Migraciones y Modelos Propios (`app/Models/` y `database/migrations/`)

> **Importante:** Los nombres de las tablas deben ser **descriptivos y con prefijo de módulo** para evitar colisiones.
> -  Ejemplos correctos: `inventario_existencias`, `inventario_kardex`, `pedidos_encabezado`, `pedidos_lineas`.
> - ❌ Ejemplos incorrectos: `existencias`, `kardex`, `lineas`.

Para crear un modelo y migración dentro de tu carpeta de módulo:
```bash
php artisan make:model "M4_Inventario/Existencia" -m
```

Ejemplo de Migración en `database/migrations/xxxx_create_inventario_existencias_table.php`:
```php
public function up(): void
{
    Schema::create('inventario_existencias', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('comercio_id');     // Para aislamiento RN-12
        $table->unsignedBigInteger('variacion_id');    // Llave foránea hacia M3
        $table->integer('cantidad_disponible')->default(0);
        $table->integer('cantidad_reservada')->default(0);
        $table->integer('stock_minimo')->default(5);
        $table->timestamps();

        // Índice compuesto para consultas rápidas y consistencia
        $table->unique(['comercio_id', 'variacion_id']);
    });
}
```

Ejemplo de Modelo en `app/Models/M4_Inventario/Existencia.php`:
```php
namespace App\Models\M4_Inventario;

use Illuminate\Database\Eloquent\Model;

class Existencia extends Model
{
    protected $table = 'inventario_existencias';

    protected $fillable = [
        'comercio_id',
        'variacion_id',
        'cantidad_disponible',
        'cantidad_reservada',
        'stock_minimo',
    ];
}
```

---

### Paso 3: Implementar la Lógica en el Servicio (`app/Services/`)

```php
// app/Services/M4_Inventario/InventarioService.php
namespace App\Services\M4_Inventario;

use App\Contracts\M4_Inventario\InventarioServiceInterface;
use App\Models\M4_Inventario\Existencia;
use Illuminate\Support\Facades\DB;

class InventarioService implements InventarioServiceInterface
{
    public function consultarExistencias(int $variacionId): int
    {
        $existencia = Existencia::where('variacion_id', $variacionId)->first();
        return $existencia ? $existencia->cantidad_disponible : 0;
    }

    public function solicitarReserva(int $variacionId, int $cantidad, int $pedidoId): array
    {
        // Uso de transacción y bloqueo pesimista para resolver RN-04 (Concurrencia)
        return DB::transaction(function () use ($variacionId, $cantidad, $pedidoId) {
            $existencia = Existencia::where('variacion_id', $variacionId)
                ->lockForUpdate() //  Bloqueo pesimista
                ->firstOrFail();

            if ($existencia->cantidad_disponible < $cantidad) {
                throw new \RuntimeException("Existencia insuficiente para reservar.");
            }

            $existencia->cantidad_disponible -= $cantidad;
            $existencia->cantidad_reservada += $cantidad;
            $existencia->save();

            return [
                'reserva_id' => rand(1000, 9999),
                'variacion_id' => $variacionId,
                'cantidad' => $cantidad,
                'pedido_id' => $pedidoId,
                'estado' => 'RESERVADO',
                'expira_en' => now()->addMinutes(15)->toIso8601String(),
            ];
        });
    }

    public function confirmarSalidaPorDespacho(int $reservaId): bool
    {
        // Lógica para descontar definitivamente de cantidad_reservada y registrar kardex
        return true;
    }
}
```

---

### Paso 4: Crear el Controlador Delgado (`app/Http/Controllers/`)

```php
// app/Http/Controllers/M4_Inventario/InventarioController.php
namespace App\Http\Controllers\M4_Inventario;

use App\Http\Controllers\Controller;
use App\Contracts\M4_Inventario\InventarioServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class InventarioController extends Controller
{
    protected InventarioServiceInterface $inventarioService;

    public function __construct(InventarioServiceInterface $inventarioService)
    {
        $this->inventarioService = $inventarioService;
    }

    public function consultar(int $variacionId): JsonResponse
    {
        $stock = $this->inventarioService->consultarExistencias($variacionId);
        return response()->json(['variacion_id' => $variacionId, 'stock_disponible' => $stock]);
    }
}
```

---

### Paso 5: Registrar la Ruta (`routes/modules/`)

```php
// routes/modules/m4_inventario.php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\M4_Inventario\InventarioController;

Route::get('/existencias/{variacionId}', [InventarioController::class, 'consultar'])->name('existencias.consultar');
```

---

## 4. Cómo Trabajar con Pruebas y Dobles de Prueba (Mocks)

Si tu subgrupo (por ejemplo, **SG5 Pedidos**) necesita probar su lógica pero el subgrupo del que depende (**SG4 Inventario**) aún no termina su implementación, **no te quedas bloqueado**. Creas un **Doble de Prueba (Mock)** en tu prueba unitaria:

```php
// tests/Unit/M5_Pedidos/PedidosServiceTest.php
namespace Tests\Unit\M5_Pedidos;

use Tests\TestCase;
use App\Contracts\M4_Inventario\InventarioServiceInterface;
use App\Services\M5_Pedidos\PedidosService;
use Mockery;

class PedidosServiceTest extends TestCase
{
    public function test_crear_pedido_reserva_existencias_correctamente()
    {
        // 1. Crear el Doble de Prueba (Mock) de la interfaz de M4
        $inventarioMock = Mockery::mock(InventarioServiceInterface::class);
        $inventarioMock->shouldReceive('consultarExistencias')
            ->with(101)
            ->once()
            ->andReturn(5);

        $inventarioMock->shouldReceive('solicitarReserva')
            ->once()
            ->andReturn(['reserva_id' => 999, 'estado' => 'RESERVADO']);

        // 2. Instanciar el servicio de M5 inyectando el Mock
        $pedidosService = new PedidosService($inventarioMock);

        // 3. Ejecutar la acción
        $resultado = $pedidosService->crearPedido([
            'variacion_id' => 101,
            'cantidad' => 2
        ]);

        // 4. Aserciones
        $this->assertEquals('CREADO', $resultado['estado']);
        $this->assertEquals(999, $resultado['reserva']['reserva_id']);
    }
}
```

---

## 5. Tabla de Comandos Frecuentes de Laravel Artisan

| Acción | Comando |
| :--- | :--- |
| **Iniciar servidor de desarrollo** | `php artisan serve` |
| **Ejecutar migraciones de BD** | `php artisan migrate` |
| **Revertir y reejecutar migraciones** | `php artisan migrate:fresh --seed` |
| **Listar todas las rutas registradas** | `php artisan route:list` |
| **Ejecutar todas las pruebas** | `php artisan test` |
| **Ejecutar pruebas de tu módulo** | `php artisan test --filter=M4_Inventario` |
| **Consola interactiva Tinker** | `php artisan tinker` |
| **Limpiar caché de rutas y configuración** | `php artisan optimize:clear` |
