use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagos', function (Blueprint $table) {
            $table->id('id_pago');
            $table->unsignedBigInteger('id_pedido');
            $table->unsignedBigInteger('id_cliente');
            
            $table->foreignId('id_medio_pago')
                  ->constrained('medios_pago', 'id_medio_pago');
                  
            $table->foreignId('id_estado_pago')
                  ->constrained('estados_pago', 'id_estado_pago');

            $table->decimal('monto', 12, 2);
            $table->string('clave_idempotencia', 100)->unique();
            $table->string('referencia_proveedor', 100)->nullable();
            
            $table->timestamp('fecha_creacion')->useCurrent();
            $table->timestamp('fecha_confirmacion')->nullable();
        });

        // Restricción CHECK de monto positivo (RN-001)
        DB::statement('ALTER TABLE pagos ADD CONSTRAINT pagos_monto_check CHECK (monto > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos');
    }
};