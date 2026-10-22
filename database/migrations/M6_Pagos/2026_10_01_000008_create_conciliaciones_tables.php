use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conciliaciones', function (Blueprint $table) {
            $table->id('id_conciliacion');
            
            $table->foreignId('id_estado_conciliacion')
                  ->constrained('estados_conciliacion', 'id_estado_conciliacion');
                  
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->decimal('total_cobrado_sistema', 14, 2);
            $table->decimal('total_reportado_pasarela', 14, 2);
            $table->timestamp('fecha_generacion')->useCurrent();
        });

        DB::statement('ALTER TABLE conciliaciones ADD CONSTRAINT conciliaciones_fechas_check CHECK (fecha_fin >= fecha_inicio)');
        DB::statement('ALTER TABLE conciliaciones ADD CONSTRAINT conciliaciones_total_sis_check CHECK (total_cobrado_sistema >= 0)');
        DB::statement('ALTER TABLE conciliaciones ADD CONSTRAINT conciliaciones_total_pas_check CHECK (total_reportado_pasarela >= 0)');

        Schema::create('diferencias_conciliacion', function (Blueprint $table) {
            $table->id('id_diferencia');
            
            $table->foreignId('id_conciliacion')
                  ->constrained('conciliaciones', 'id_conciliacion');
                  
            $table->foreignId('id_pago')
                  ->constrained('pagos', 'id_pago');
                  
            $table->foreignId('id_tipo_diferencia')
                  ->constrained('tipos_diferencia', 'id_tipo_diferencia');
                  
            $table->foreignId('id_estado_revision_diferencia')
                  ->constrained('estados_revision_diferencia', 'id_estado_revision_diferencia');

            $table->decimal('monto_diferencia', 12, 2);
        });

        DB::statement('ALTER TABLE diferencias_conciliacion ADD CONSTRAINT diferencias_conciliacion_monto_check CHECK (monto_diferencia <> 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('diferencias_conciliacion');
        Schema::dropIfExists('conciliaciones');
    }
};