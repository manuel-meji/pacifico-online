<?php

declare(strict_types=1);

namespace Tests\Unit\Nucleo;

use App\Enums\Nucleo\CodigoErrorNucleo;
use PHPUnit\Framework\TestCase;

class CodigoErrorNucleoTest extends TestCase
{
    public function test_contiene_los_seis_codigos_reservados_del_nucleo(): void
    {
        $codigos = array_map(fn (CodigoErrorNucleo $c) => $c->value, CodigoErrorNucleo::cases());

        $this->assertSame([
            'ERR-001',
            'ERR-002',
            'ERR-003',
            'ERR-004',
            'ERR-005',
            'ERR-006',
        ], $codigos);
    }

    public function test_cada_codigo_tiene_el_estado_http_definido_en_pc3(): void
    {
        $this->assertSame(401, CodigoErrorNucleo::SesionInvalida->codigoHttp());
        $this->assertSame(403, CodigoErrorNucleo::RolSinPermiso->codigoHttp());
        $this->assertSame(404, CodigoErrorNucleo::RecursoNoAccesible->codigoHttp());
        $this->assertSame(404, CodigoErrorNucleo::RecursoNoEncontrado->codigoHttp());
        $this->assertSame(422, CodigoErrorNucleo::ValidacionFallida->codigoHttp());
        $this->assertSame(503, CodigoErrorNucleo::ProveedorNoResponde->codigoHttp());
    }

    public function test_cada_codigo_tiene_el_mensaje_oficial_definido_en_pc3(): void
    {
        $this->assertSame('Tu sesión expiró, inicia sesión de nuevo.', CodigoErrorNucleo::SesionInvalida->mensaje());
        $this->assertSame('No se encuentra autorizado para realizar esta acción.', CodigoErrorNucleo::RolSinPermiso->mensaje());
        $this->assertSame('El recurso solicitado no existe o no es accesible.', CodigoErrorNucleo::RecursoNoAccesible->mensaje());
        $this->assertSame('No se encontró lo que buscas.', CodigoErrorNucleo::RecursoNoEncontrado->mensaje());
        $this->assertSame('Revisa los datos marcados (detalle por campo en detalles).', CodigoErrorNucleo::ValidacionFallida->mensaje());
        $this->assertSame('Servicio temporalmente no disponible, intentar de nuevo.', CodigoErrorNucleo::ProveedorNoResponde->mensaje());
    }
}
