<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cuentas_contables', function (Blueprint $table) {
            $table->id('id_cuenta_contable');

            // Clave compartida de M2.
            // No se crea FK física hacia otro módulo.
            $table->unsignedBigInteger('id_comercio');

            $table->string('codigo', 30);
            $table->string('nombre', 150);
            $table->string('tipo', 20);
            $table->unsignedInteger('nivel')->default(1);

            // Relación jerárquica interna de M7.
            $table->unsignedBigInteger('id_cuenta_padre')->nullable();

            $table->boolean('activa')->default(true);

            $table->timestamps();

            $table->unique(
                ['id_comercio', 'codigo'],
                'uq_cuentas_contables_comercio_codigo'
            );

            $table->foreign('id_cuenta_padre')
                ->references('id_cuenta_contable')
                ->on('cuentas_contables')
                ->nullOnDelete();
        });

        DB::statement("
            ALTER TABLE cuentas_contables
            ADD CONSTRAINT chk_cuentas_contables_tipo
            CHECK (tipo IN ('ACTIVO', 'PASIVO', 'PATRIMONIO', 'INGRESO', 'GASTO'))
        ");

        DB::statement("
            ALTER TABLE cuentas_contables
            ADD CONSTRAINT chk_cuentas_contables_nivel
            CHECK (nivel > 0)
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('cuentas_contables');
    }
};