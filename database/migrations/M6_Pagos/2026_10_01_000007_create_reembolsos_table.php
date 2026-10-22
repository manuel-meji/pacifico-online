use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reembolsos', function (Blueprint $table) {
            $table->id('id_reembolso');
            
            $table->foreignId('id_pago')
                  ->constrained('pagos', 'id_pago');
                  
            $table->unsignedBigInteger('id_subpedido')->nullable();
            
            $table->foreignId('id_nota_credito')
                  ->nullable()
                  ->unique()
                  ->constrained('notas_credito', 'id_nota_credito');
                  
            $table->foreignId('id_estado_reembolso')
                  ->constrained('estados_reembolso', 'id_estado_reembolso');

            $table->decimal('monto', 12, 2);
            $table->string('motivo', 200);
            $table->timestamp('fecha_solicitud')->useCurrent();
            $table->timestamp('fecha_procesado')->nullable();
        });

        DB::statement('ALTER TABLE reembolsos ADD CONSTRAINT reembolsos_monto_check CHECK (monto > 0)');
        DB::statement("ALTER TABLE reembolsos ADD CONSTRAINT reembolsos_motivo_check CHECK (length(trim(motivo)) > 0)");
    }

    public function down(): void
    {
        Schema::dropIfExists('reembolsos');
    }
};