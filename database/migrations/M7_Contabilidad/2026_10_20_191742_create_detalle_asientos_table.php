<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detalle_asientos', function (Blueprint $table) {
            $table->id('id_detalle_asiento');

            $table->unsignedBigInteger('id_asiento');
            $table->unsignedBigInteger('id_cuenta_contable');
            $table->unsignedBigInteger('id_centro_costo')->nullable();

            $table->unsignedInteger('numero_linea');

            $table->decimal('debe', 15, 2)->default(0);
            $table->decimal('haber', 15, 2)->default(0);

            $table->string('descripcion', 255)->nullable();

            $table->timestamps();

            $table->foreign('id_asiento')
                ->references('id_asiento')
                ->on('asientos_contables')
                ->cascadeOnDelete();

            $table->foreign('id_cuenta_contable')
                ->references('id_cuenta_contable')
                ->on('cuentas_contables')
                ->restrictOnDelete();

            $table->foreign('id_centro_costo')
                ->references('id_centro_costo')
                ->on('centros_costo')
                ->nullOnDelete();

            $table->unique(
                ['id_asiento', 'numero_linea'],
                'uq_detalle_asiento_numero_linea'
            );
        });

        /*
         * Una línea debe tener importe en el Debe o en el Haber,
         * pero nunca en ambos ni en ninguno.
         */
        DB::statement("
            ALTER TABLE detalle_asientos
            ADD CONSTRAINT chk_detalle_asientos_debe_haber
            CHECK (
                (debe > 0 AND haber = 0)
                OR
                (haber > 0 AND debe = 0)
            )
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('detalle_asientos');
    }
};