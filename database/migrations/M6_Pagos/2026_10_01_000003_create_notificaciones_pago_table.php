use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notificaciones_pago', function (Blueprint $table) {
            $table->id('id_notificacion_pago');
            
            $table->foreignId('id_pago')
                  ->constrained('pagos', 'id_pago');
                  
            $table->foreignId('id_medio_pago')
                  ->constrained('medios_pago', 'id_medio_pago');
                  
            $table->foreignId('id_estado_notificacion_pago')
                  ->constrained('estados_notificacion_pago', 'id_estado_notificacion_pago');

            $table->string('referencia_proveedor', 100)->unique(); // Asegura idempotencia (RN-002)
            $table->timestamp('fecha_recepcion')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notificaciones_pago');
    }
};