<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('cuentas_plataforma_m7')->updateOrInsert(
            ['codigo' => 'P-COMISION'],
            [
                'nombre' => 'Ingresos por comisiones',
                'tipo' => 'INGRESO',
                'activa' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('cuentas_plataforma_m7')
            ->where('codigo', 'P-COMISION')
            ->delete();
    }
};