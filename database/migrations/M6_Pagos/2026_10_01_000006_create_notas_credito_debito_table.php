use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notas_credito', function (Blueprint $table) {
            $table->id('id_nota_credito');
            
            $table->foreignId('id_factura_electronica')
                  ->constrained('facturas_electronicas', 'id_factura_electronica');
                  
            $table->decimal('monto', 12, 2);
            $table->string('motivo', 200);
            $table->text('xml_documento');
            $table->timestamp('fecha_emision')->useCurrent();
        });

        DB::statement('ALTER TABLE notas_credito ADD CONSTRAINT notas_credito_monto_check CHECK (monto > 0)');
        DB::statement("ALTER TABLE notas_credito ADD CONSTRAINT notas_credito_motivo_check CHECK (length(trim(motivo)) > 0)");
        DB::statement("ALTER TABLE notas_credito ADD CONSTRAINT notas_credito_xml_check CHECK (length(trim(xml_documento)) > 0)");

        Schema::create('notas_debito', function (Blueprint $table) {
            $table->id('id_nota_debito');
            
            $table->foreignId('id_factura_electronica')
                  ->constrained('facturas_electronicas', 'id_factura_electronica');
                  
            $table->decimal('monto', 12, 2);
            $table->string('motivo', 200);
            $table->text('xml_documento');
            $table->timestamp('fecha_emision')->useCurrent();
        });

        DB::statement('ALTER TABLE notas_debito ADD CONSTRAINT notas_debito_monto_check CHECK (monto > 0)');
        DB::statement("ALTER TABLE notas_debito ADD CONSTRAINT notas_debito_motivo_check CHECK (length(trim(motivo)) > 0)");
        DB::statement("ALTER TABLE notas_debito ADD CONSTRAINT notas_debito_xml_check CHECK (length(trim(xml_documento)) > 0)");
    }

    public function down(): void
    {
        Schema::dropIfExists('notas_debito');
        Schema::dropIfExists('notas_credito');
    }
};