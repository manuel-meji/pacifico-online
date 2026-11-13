<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cuentas_plataforma_m7', function (Blueprint $table) {
            $table->id('id_cuenta');
            $table->string('codigo', 30)->unique();
            $table->string('nombre', 150);
            $table->string('tipo', 20);
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });

        Schema::create('asientos_plataforma_m7', function (Blueprint $table) {
            $table->id('id_asiento');
            $table->unsignedBigInteger('id_pago');
            $table->unsignedBigInteger('id_pedido');
            $table->unsignedBigInteger('id_comercio')->nullable();
            $table->unsignedBigInteger('id_subpedido')->nullable();
            $table->uuid('evento_id');
            $table->string('tipo_evento', 80);
            $table->string('tipo', 40);
            $table->timestamp('fecha');
            $table->string('estado', 20)->default('REGISTRADO');
            $table->timestamps();
        });

        Schema::create('detalles_plataforma_m7', function (Blueprint $table) {
            $table->id('id_detalle');
            $table->unsignedBigInteger('id_asiento');
            $table->unsignedBigInteger('id_cuenta');
            $table->integer('numero_linea');
            $table->decimal('debe', 15, 2);
            $table->decimal('haber', 15, 2);
            $table->timestamps();

            $table->foreign('id_asiento')->references('id_asiento')
                ->on('asientos_plataforma_m7')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('id_cuenta')->references('id_cuenta')
                ->on('cuentas_plataforma_m7')->restrictOnDelete()->restrictOnUpdate();
        });

        $ahora = now();

        DB::table('cuentas_plataforma_m7')->insert([
            [
                'codigo' => 'P-BANCOS',
                'nombre' => 'Bancos de plataforma',
                'tipo' => 'ACTIVO',
                'activa' => true,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ],
            [
                'codigo' => 'P-PENDIENTE',
                'nombre' => 'Fondos pendientes de distribución',
                'tipo' => 'PASIVO',
                'activa' => true,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ],
            [
                'codigo' => 'P-COMERCIOS',
                'nombre' => 'Obligaciones con comercios',
                'tipo' => 'PASIVO',
                'activa' => true,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('detalles_plataforma_m7');
        Schema::dropIfExists('asientos_plataforma_m7');
        Schema::dropIfExists('cuentas_plataforma_m7');
    }
};
