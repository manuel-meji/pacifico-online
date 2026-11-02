<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notas_credito', function (Blueprint $table) {
            $table->id('id_nota_credito');

            $table->foreignId('id_factura_electronica')
                  ->constrained('facturas_electronicas', 'id_factura_electronica');

            $table->decimal('monto', 12, 2);
            $table->string('motivo', 200);
            $table->text('xml_documento');
            $table->timestamp('fecha_emision')->useCurrent();
        });

        DB::statement('ALTER TABLE notas_credito ADD CONSTRAINT notas_credito_monto_check CHECK (monto > 0)');
        DB::statement("ALTER TABLE notas_credito ADD CONSTRAINT notas_credito_motivo_check CHECK (length(trim(motivo)) > 0)");
        DB::statement("ALTER TABLE notas_credito ADD CONSTRAINT notas_credito_xml_check CHECK (length(trim(xml_documento)) > 0)");

        Schema::create('notas_debito', function (Blueprint $table) {
            $table->id('id_nota_debito');

            $table->foreignId('id_factura_electronica')
                  ->constrained('facturas_electronicas', 'id_factura_electronica');

            $table->decimal('monto', 12, 2);
            $table->string('motivo', 200);
            $table->text('xml_documento');
            $table->timestamp('fecha_emision')->useCurrent();
        });

        DB::statement('ALTER TABLE notas_debito ADD CONSTRAINT notas_debito_monto_check CHECK (monto > 0)');
        DB::statement("ALTER TABLE notas_debito ADD CONSTRAINT notas_debito_motivo_check CHECK (length(trim(motivo)) > 0)");
        DB::statement("ALTER TABLE notas_debito ADD CONSTRAINT notas_debito_xml_check CHECK (length(trim(xml_documento)) > 0)");

        // Trigger de PostgreSQL para asegurar que el acumulado de notas de crédito no supere la factura
        DB::unprepared("
            CREATE OR REPLACE FUNCTION fn_validar_monto_nota_credito()
            RETURNS TRIGGER AS $$
            DECLARE
                v_monto_factura NUMERIC(12,2);
                v_total_nc_actual NUMERIC(12,2);
            BEGIN
                SELECT monto_total INTO v_monto_factura
                FROM facturas_electronicas
                WHERE id_factura_electronica = NEW.id_factura_electronica;

                SELECT COALESCE(SUM(monto), 0) INTO v_total_nc_actual
                FROM notas_credito
                WHERE id_factura_electronica = NEW.id_factura_electronica
                  AND id_nota_credito <> COALESCE(NEW.id_nota_credito, 0);

                v_total_nc_actual := v_total_nc_actual + NEW.monto;

                IF v_total_nc_actual > v_monto_factura THEN
                    RAISE EXCEPTION 'El monto acumulado de las notas de crédito (%) excede el monto total de la factura (%)',
                        v_total_nc_actual, v_monto_factura;
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER trg_validar_monto_nota_credito
            BEFORE INSERT OR UPDATE ON notas_credito
            FOR EACH ROW
            EXECUTE FUNCTION fn_validar_monto_nota_credito();
        ");
    }

    public function down(): void
    {
        DB::unprepared("DROP TRIGGER IF EXISTS trg_validar_monto_nota_credito ON notas_credito;");
        DB::unprepared("DROP FUNCTION IF EXISTS fn_validar_monto_nota_credito();");

        Schema::dropIfExists('notas_debito');
        Schema::dropIfExists('notas_credito');
    }
};
