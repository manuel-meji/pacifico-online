<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categorias', function (Blueprint $table) {
            $table->id('id_categoria');
            $table->foreignId('id_categoria_padre')->nullable()
                ->constrained('categorias', 'id_categoria')->restrictOnDelete();
            $table->string('nombre', 100);
            $table->text('descripcion')->nullable();
            $table->timestamps();

            $table->unique(['id_categoria_padre', 'nombre'], 'uq_categorias_padre_nombre');
        });

        DB::statement('ALTER TABLE categorias ADD CONSTRAINT ck_categorias_no_autopadre CHECK (id_categoria_padre IS NULL OR id_categoria_padre <> id_categoria)');
    }

    public function down(): void
    {
        Schema::dropIfExists('categorias');
    }
};