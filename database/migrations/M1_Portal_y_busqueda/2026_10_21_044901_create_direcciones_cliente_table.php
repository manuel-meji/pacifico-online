<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M1 Portal público — Direcciones de envío de cada cliente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('direcciones_cliente', function (Blueprint $table) {
            $table->id('id_direccion_cliente');
            $table->foreignId('id_cliente')
                  ->constrained('clientes', 'id_cliente')
                  ->cascadeOnDelete();                    
            $table->string('alias_direccion', 40);
            $table->string('nombre_destinatario', 150);
            $table->string('telefono_destinatario', 20);
            $table->string('provincia', 40);
            $table->string('canton', 60);
            $table->string('distrito', 60);
            $table->string('otras_senas', 300);
            $table->boolean('es_principal')->default(false);
            $table->timestamps();

            
            $table->unique(['id_cliente', 'alias_direccion'], 'uq_direcciones_cliente_alias');
        });

        
        DB::statement('CREATE UNIQUE INDEX uq_direcciones_cliente_principal
                       ON direcciones_cliente (id_cliente) WHERE es_principal');

        DB::statement("ALTER TABLE direcciones_cliente ADD CONSTRAINT chk_direcciones_cliente_no_vacios
                       CHECK (btrim(nombre_destinatario) <> '' AND btrim(otras_senas) <> '')");
        DB::statement("ALTER TABLE direcciones_cliente ADD CONSTRAINT chk_direcciones_cliente_telefono
                       CHECK (telefono_destinatario ~ '^[0-9 +-]+$')");
    }

    public function down(): void
    {
        Schema::dropIfExists('direcciones_cliente');
    }
};