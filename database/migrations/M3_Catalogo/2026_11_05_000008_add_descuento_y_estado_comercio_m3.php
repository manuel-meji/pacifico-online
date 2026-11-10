<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tabla de estado del comercio (Proyección local para M3)
        if (!Schema::hasTable('estado_comercios_catalogo')) {
            Schema::create('estado_comercios_catalogo', function (Blueprint $table) {
                $table->unsignedBigInteger('id_comercio')->primary();
                $table->boolean('activo')->default(false);
                $table->timestampTz('fecha_ultimo_evento')->nullable();
                $table->uuid('id_ultimo_evento')->nullable();
                $table->timestampsTz();
            });
        }

        // 2. Columnas de descuento en producto_variaciones
        Schema::table('producto_variaciones', function (Blueprint $table) {
            if (!Schema::hasColumn('producto_variaciones', 'tipo_descuento')) {
                $table->string('tipo_descuento', 20)->default('ninguno');
            }
            if (!Schema::hasColumn('producto_variaciones', 'valor_descuento')) {
                $table->decimal('valor_descuento', 12, 2)->default(0);
            }
        });

        // Restricciones CHECK de PostgreSQL
        DB::statement("ALTER TABLE producto_variaciones DROP CONSTRAINT IF EXISTS chk_variacion_tipo_descuento");
        DB::statement("ALTER TABLE producto_variaciones ADD CONSTRAINT chk_variacion_tipo_descuento 
          CHECK (tipo_descuento IN ('ninguno','porcentaje','monto_fijo'))");
          
        DB::statement("ALTER TABLE producto_variaciones DROP CONSTRAINT IF EXISTS chk_variacion_valor_descuento");
        DB::statement("ALTER TABLE producto_variaciones ADD CONSTRAINT chk_variacion_valor_descuento 
          CHECK (valor_descuento >= 0 
            AND (tipo_descuento <> 'ninguno'    OR valor_descuento = 0) 
            AND (tipo_descuento <> 'porcentaje' OR valor_descuento <= 100) 
            AND (tipo_descuento <> 'monto_fijo' OR valor_descuento <= precio))");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE producto_variaciones DROP CONSTRAINT IF EXISTS chk_variacion_valor_descuento");
        DB::statement("ALTER TABLE producto_variaciones DROP CONSTRAINT IF EXISTS chk_variacion_tipo_descuento");

        Schema::table('producto_variaciones', function (Blueprint $table) {
            $table->dropColumn(['tipo_descuento', 'valor_descuento']);
        });

        Schema::dropIfExists('estado_comercios_catalogo');
    }
};