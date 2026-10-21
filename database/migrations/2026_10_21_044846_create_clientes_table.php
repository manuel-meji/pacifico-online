<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->id('id_cliente');                          
            $table->foreignId('id_usuario')->unique()          
                  ->constrained('usuarios', 'id');             
            $table->string('cedula', 20)->unique();
            $table->string('nombre', 80);
            $table->string('apellidos', 120);
            $table->string('correo_electronico', 150);
            $table->string('telefono', 20)->nullable();
            $table->string('estado_cliente', 20)->default('activo');
            $table->timestamps();                              
        });

        
        DB::statement("ALTER TABLE clientes ADD CONSTRAINT chk_clientes_estado
                       CHECK (estado_cliente IN ('activo', 'inactivo'))");
        DB::statement('CREATE UNIQUE INDEX uq_clientes_correo
                       ON clientes (LOWER(correo_electronico))');
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
