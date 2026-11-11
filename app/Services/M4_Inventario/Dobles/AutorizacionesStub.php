<?php

namespace App\Services\M4_Inventario\Dobles;

use App\Contracts\M4_Inventario\Dependencias\VerificadorAutorizacionesInterface;
use App\Services\M4_Inventario\Excepciones\ErrorInventarioException;
use DateTimeImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * Doble del verificador de autorizaciones del núcleo (VerificadorAutorizacionesInterface).
 *
 * El núcleo todavía no publica el flujo de solicitud y aprobación de autorizaciones
 * (supuesto del PC3, sección 4.2); mientras tanto el ambiente usa este doble con las
 * autorizaciones de config/integraciones.php (m4.autorizaciones.registradas), emitidas
 * por el Administrador de cada comercio. Valida existencia, vigencia, comercio,
 * operación, variación, ubicaciones y cantidad, y no permite usarlas dos veces.
 * Cualquier falla responde ERR-002 (403) con el motivo.
 */
class AutorizacionesStub implements VerificadorAutorizacionesInterface
{
    public function verificar(string $idAutorizacion, string $operacion, int $idComercio, int $idVariacion, array $idsUbicacion, int $cantidad): array
    {
        $autorizacion = config("integraciones.m4.autorizaciones.registradas.{$idAutorizacion}");

        $motivo = match (true) {
            ! is_array($autorizacion) => 'La autorización no existe.',
            Cache::has($this->claveUso($idAutorizacion)) => 'La autorización ya fue utilizada.',
            new DateTimeImmutable > new DateTimeImmutable($autorizacion['vence']) => 'La autorización venció.',
            $autorizacion['id_comercio'] !== $idComercio => 'La autorización es de otro comercio.',
            $autorizacion['operacion'] !== $operacion,
            $autorizacion['id_variacion'] !== $idVariacion,
            $autorizacion['ubicaciones'] !== array_values($idsUbicacion),
            $autorizacion['cantidad'] !== $cantidad => 'La autorización no corresponde a la operación solicitada.',
            default => null,
        };

        if ($motivo !== null) {
            throw ErrorInventarioException::accesoDenegado($motivo, ['id_autorizacion' => $idAutorizacion]);
        }

        return [
            'id_autorizacion' => $idAutorizacion,
            'id_autorizador' => $autorizacion['id_autorizador'],
            'fecha_autorizacion' => $autorizacion['fecha_autorizacion'],
        ];
    }

    public function marcarUsada(string $idAutorizacion): void
    {
        Cache::forever($this->claveUso($idAutorizacion), true);
    }

    private function claveUso(string $idAutorizacion): string
    {
        return "m4:autorizacion-usada:{$idAutorizacion}";
    }
}
