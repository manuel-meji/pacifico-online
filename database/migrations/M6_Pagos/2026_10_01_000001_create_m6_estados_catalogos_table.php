use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estados_pago', function (Blueprint $table) {
            $table->id('id_estado_pago');
            $table->string('codigo', 30)->unique();
            $table->string('nombre', 60);
            $table->string('descripcion', 200)->nullable();
        });

        Schema::create('estados_notificacion_pago', function (Blueprint $table) {
            $table->id('id_estado_notificacion_pago');
            $table->string('codigo', 30)->unique();
            $table->string('nombre', 60);
            $table->string('descripcion', 200)->nullable();
        });

        Schema::create('estados_factura_hacienda', function (Blueprint $table) {
            $table->id('id_estado_factura_hacienda');
            $table->string('codigo', 30)->unique();
            $table->string('nombre', 60);
            $table->string('descripcion', 200)->nullable();
        });

        Schema::create('estados_reembolso', function (Blueprint $table) {
            $table->id('id_estado_reembolso');
            $table->string('codigo', 30)->unique();
            $table->string('nombre', 60);
            $table->string('descripcion', 200)->nullable();
        });

        Schema::create('estados_conciliacion', function (Blueprint $table) {
            $table->id('id_estado_conciliacion');
            $table->string('codigo', 30)->unique();
            $table->string('nombre', 60);
            $table->string('descripcion', 200)->nullable();
        });

        Schema::create('estados_revision_diferencia', function (Blueprint $table) {
            $table->id('id_estado_revision_diferencia');
            $table->string('codigo', 30)->unique();
            $table->string('nombre', 60);
            $table->string('descripcion', 200)->nullable();
        });

        Schema::create('tipos_diferencia', function (Blueprint $table) {
            $table->id('id_tipo_diferencia');
            $table->string('codigo', 30)->unique();
            $table->string('nombre', 60);
            $table->string('descripcion', 200)->nullable();
        });

        Schema::create('medios_pago', function (Blueprint $table) {
            $table->id('id_medio_pago');
            $table->string('nombre_medio', 30)->unique();
        });

        // Seeder inicial de catálogos requerido por el diseño
        DB::table('estados_pago')->insert([
            ['codigo' => 'pendiente', 'nombre' => 'Pendiente', 'descripcion' => 'Cobro enviado al proveedor, sin confirmación.'],
            ['codigo' => 'confirmado', 'nombre' => 'Confirmado', 'descripcion' => 'El proveedor acreditó el pago exitosamente.'],
            ['codigo' => 'rechazado', 'nombre' => 'Rechazado', 'descripcion' => 'El proveedor rechazó el cobro.'],
        ]);

        DB::table('estados_notificacion_pago')->insert([
            ['codigo' => 'recibida', 'nombre' => 'Recibida', 'descripcion' => 'Notificación recibida, pendiente de procesamiento.'],
            ['codigo' => 'procesada', 'nombre' => 'Procesada', 'descripcion' => 'Notificación validada y aplicada al pago.'],
            ['codigo' => 'duplicada', 'nombre' => 'Duplicada', 'descripcion' => 'Referencia ya procesada previamente (idempotencia).'],
            ['codigo' => 'rechazada', 'nombre' => 'Rechazada', 'descripcion' => 'Intento inexistente o firma no válida.'],
        ]);

        DB::table('estados_factura_hacienda')->insert([
            ['codigo' => 'pendiente_envio', 'nombre' => 'Pendiente de Envío', 'descripcion' => 'XML generado, pendiente de transmisión.'],
            ['codigo' => 'enviada', 'nombre' => 'Enviada', 'descripcion' => 'Transmitida a Hacienda, a la espera de respuesta.'],
            ['codigo' => 'aceptada', 'nombre' => 'Aceptada', 'descripcion' => 'Aceptada por Hacienda (habilita notas de débito).'],
            ['codigo' => 'rechazada', 'nombre' => 'Rechazada', 'descripcion' => 'Rechazada por las reglas de validación de Hacienda.'],
        ]);

        DB::table('estados_reembolso')->insert([
            ['codigo' => 'solicitado', 'nombre' => 'Solicitado', 'descripcion' => 'Reembolso solicitado y en trámite.'],
            ['codigo' => 'procesado', 'nombre' => 'Procesado', 'descripcion' => 'Dinero devuelto y nota de crédito emitida.'],
            ['codigo' => 'rechazado', 'nombre' => 'Rechazado', 'descripcion' => 'Rechazado por incumplimiento de reglas de negocio.'],
        ]);

        DB::table('estados_conciliacion')->insert([
            ['codigo' => 'en_proceso', 'nombre' => 'En Proceso', 'descripcion' => 'Comparando transacciones del periodo.'],
            ['codigo' => 'parcial', 'nombre' => 'Parcial', 'descripcion' => 'Falta confirmación de asiento contable de M7.'],
            ['codigo' => 'completada', 'nombre' => 'Completada', 'descripcion' => 'Periodo conciliado por completo.'],
        ]);

        DB::table('estados_revision_diferencia')->insert([
            ['codigo' => 'pendiente_revision', 'nombre' => 'Pendiente de Revisión', 'descripcion' => 'Discrepancia a la espera de verificación manual.'],
            ['codigo' => 'resuelta', 'nombre' => 'Resuelta', 'descripcion' => 'Diferencia verificada y solventada por el administrador.'],
        ]);

        DB::table('tipos_diferencia')->insert([
            ['codigo' => 'monto_distinto', 'nombre' => 'Monto Distinto', 'descripcion' => 'Pago registrado en ambos lados pero con importes diferentes.'],
            ['codigo' => 'faltante_en_pasarela', 'nombre' => 'Faltante en Pasarela', 'descripcion' => 'Existe en sistema local pero no en reporte de pasarela.'],
            ['codigo' => 'faltante_en_sistema', 'nombre' => 'Faltante en Sistema', 'descripcion' => 'Reportado por pasarela pero ausente en registros locales.'],
        ]);

        DB::table('medios_pago')->insert([
            ['nombre_medio' => 'tarjeta'],
            ['nombre_medio' => 'sinpe'],
            ['nombre_medio' => 'paypal'],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('medios_pago');
        Schema::dropIfExists('tipos_diferencia');
        Schema::dropIfExists('estados_revision_diferencia');
        Schema::dropIfExists('estados_conciliacion');
        Schema::dropIfExists('estados_reembolso');
        Schema::dropIfExists('estados_factura_hacienda');
        Schema::dropIfExists('estados_notificacion_pago');
        Schema::dropIfExists('estados_pago');
    }
};