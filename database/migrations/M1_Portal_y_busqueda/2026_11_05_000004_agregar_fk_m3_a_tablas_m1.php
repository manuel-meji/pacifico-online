<?php

// M1 Portal y Búsqueda
// Antes creaba llaves foráneas de M1 hacia las tablas productos y categorias de
// M3 Catálogo (sección 6.5). Se dejó sin efecto el 2026-11-13: M1 guarda
// id_producto e id_categoria como referencias lógicas a M3, sin llave física
// (frontera de datos del PC3), igual que las migraciones de destacados del
// 2026-11-08. M1 lee productos y categorías solo por el contrato de M3.
//
// Motivos:
// - Una llave física acopla las tablas de M3 a M1: M3 no podría borrar o
//   reemplazar productos o categorías sin que M1 lo bloquee.
// - Se acordó con M3 que M1 no cambiaría la estructura de sus tablas.
// - productos_destacados guarda ids del catálogo de prueba (1520 a 1525) que
//   no existen en M3, así que la llave fallaría al crearse.
//
// La migración se conserva vacía para que el historial explique la decisión y
// para que quede registrada como aplicada en todas las bases.

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Sin llaves hacia M3: referencias lógicas por el contrato de M3.
    }

    public function down(): void
    {
        // Nada que revertir.
    }
};
