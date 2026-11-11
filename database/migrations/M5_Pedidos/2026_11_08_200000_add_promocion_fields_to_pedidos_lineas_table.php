<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos_lineas', function (Blueprint $table) {
            if (! Schema::hasColumn('pedidos_lineas', 'descuento')) {
                $table->decimal('descuento', 10, 2)->default(0.00)->after('tarifa_impuesto');
            }
            if (! Schema::hasColumn('pedidos_lineas', 'id_promocion')) {
                $table->unsignedBigInteger('id_promocion')->nullable()->after('descuento');
            }
        });

        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE pedidos_lineas DROP CONSTRAINT IF EXISTS chk_pedidos_lineas_descuento_positivo');
            DB::statement('ALTER TABLE pedidos_lineas ADD CONSTRAINT chk_pedidos_lineas_descuento_positivo CHECK (descuento >= 0)');
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE pedidos_lineas DROP CONSTRAINT IF EXISTS chk_pedidos_lineas_descuento_positivo');
        }

        Schema::table('pedidos_lineas', function (Blueprint $table) {
            $columnas = array_filter(
                ['descuento', 'id_promocion'],
                fn (string $col): bool => Schema::hasColumn('pedidos_lineas', $col)
            );
            if ($columnas !== []) {
                $table->dropColumn($columnas);
            }
        });
    }
};
