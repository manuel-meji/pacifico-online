use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facturas_electronicas', function (Blueprint $table) {
            $table->id('id_factura_electronica');
            $table->unsignedBigInteger('id_subpedido');
            $table->unsignedBigInteger('id_comercio');
            
            $table->foreignId('id_pago')
                  ->constrained('pagos', 'id_pago');
                  
            $table->unsignedBigInteger('id_cliente');
            
            $table->foreignId('id_estado_factura_hacienda')
                  ->constrained('estados_factura_hacienda', 'id_estado_factura_hacienda');

            $table->string('consecutivo', 20)->unique();
            $table->text('xml_documento');
            $table->decimal('monto_total', 12, 2);
            $table->string('razon_social_emisor', 150);
            $table->string('cedula_juridica_emisor', 20);
            $table->string('identificacion_receptor', 20)->nullable();
            
            $table->timestamp('fecha_emision')->useCurrent();
        });

        DB::statement('ALTER TABLE facturas_electronicas ADD CONSTRAINT facturas_electronicas_monto_check CHECK (monto_total > 0)');
        DB::statement("ALTER TABLE facturas_electronicas ADD CONSTRAINT facturas_electronicas_xml_check CHECK (length(trim(xml_documento)) > 0)");
    }

    public function down(): void
    {
        Schema::dropIfExists('facturas_electronicas');
    }
};