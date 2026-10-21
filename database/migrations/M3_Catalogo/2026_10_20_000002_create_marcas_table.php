<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marcas', function (Blueprint $table) {
            $table->id('id_marca');
            $table->string('nombre', 100);
            $table->string('logotipo_url', 500)->nullable();
            $table->boolean('destacada')->default(false);
            $table->timestamps();

            $table->unique('nombre', 'uq_marcas_nombre');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marcas');
    }
};