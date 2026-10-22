use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comprobantes_pago', function (Blueprint $table) {
            $table->id('id_comprobante_pago');
            
            $table->foreignId('id_pago')
                  ->unique()
                  ->constrained('pagos', 'id_pago');
                  
            $table->text('documento');
            $table->timestamp('fecha_emision')->useCurrent();
        });

        DB::statement("ALTER TABLE comprobantes_pago ADD CONSTRAINT comprobantes_pago_documento_check CHECK (length(trim(documento)) > 0)");
    }

    public function down(): void
    {
        Schema::dropIfExists('comprobantes_pago');
    }
};