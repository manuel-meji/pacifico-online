<?php

// M1 Portal y Búsqueda
// El teléfono del cliente pasa a ser obligatorio (decisión del SG1, PC4): la
// columna deja de aceptar NULL y, al igual que la cédula, no puede quedar en
// blanco. La tabla clientes estaba vacía al crear esta migración.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE clientes ALTER COLUMN telefono SET NOT NULL');

        DB::statement("ALTER TABLE clientes ADD CONSTRAINT chk_clientes_telefono_no_vacio
                       CHECK (btrim(telefono) <> '')");
        DB::statement("ALTER TABLE clientes ADD CONSTRAINT chk_clientes_cedula_no_vacia
                       CHECK (btrim(cedula) <> '')");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE clientes DROP CONSTRAINT IF EXISTS chk_clientes_cedula_no_vacia');
        DB::statement('ALTER TABLE clientes DROP CONSTRAINT IF EXISTS chk_clientes_telefono_no_vacio');
        DB::statement('ALTER TABLE clientes ALTER COLUMN telefono DROP NOT NULL');
    }
};
