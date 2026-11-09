<?php

declare(strict_types=1);

namespace Tests\Unit\M6_Pagos;

use App\Enums\M6_Pagos\CodigoErrorM6;
use App\Exceptions\M6_Pagos\ErrorContratoException;
use App\Services\M6_Pagos\Simuladores\PasarelaPagoSimulador;
use RuntimeException;
use Tests\TestCase;

/**
 * PC4, Sección 6.1 — Pruebas unitarias de lógica de negocio de M6.
 *
 * No usan base de datos ni HTTP: prueban la pasarela simulada (CU-001) y el
 * catálogo de errores del contrato (RN-M6-01, RN-M6-02). Cada prueba indica
 * el caso de prueba (CP-M6-Unn) del plan de pruebas del módulo.
 *
 * Se extiende Tests\TestCase solo porque el simulador usa now().
 */
class ReglasNegocioM6Test extends TestCase
{
    private PasarelaPagoSimulador $pasarela;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pasarela = new PasarelaPagoSimulador;
    }

    /** CP-M6-U01 · CU-001 paso 5: la tarjeta aprobada devuelve la referencia del proveedor. */
    public function test_la_tarjeta_aprobada_devuelve_la_referencia_del_proveedor(): void
    {
        $respuesta = $this->pasarela->procesarCobro('M6-PAGO-1', 59840.00, [
            'token_medio_pago' => PasarelaPagoSimulador::TOKEN_APROBADO_TARJETA,
        ]);

        $this->assertTrue($respuesta['exitoso']);
        $this->assertSame('00', $respuesta['codigo_respuesta']);
        $this->assertStringStartsWith('PASARELA-SIM-', $respuesta['referencia_proveedor']);
    }

    /** CP-M6-U02 · CU-001: SINPE y PayPal simulados también se aprueban. */
    public function test_sinpe_y_paypal_simulados_se_aprueban(): void
    {
        foreach ([PasarelaPagoSimulador::TOKEN_APROBADO_SINPE, PasarelaPagoSimulador::TOKEN_APROBADO_PAYPAL] as $token) {
            $respuesta = $this->pasarela->procesarCobro('M6-PAGO-2', 1000.00, ['token_medio_pago' => $token]);

            $this->assertTrue($respuesta['exitoso'], $token);
        }
    }

    /** CP-M6-U03 · CU-001 flujo alterno "el proveedor rechaza el cobro". */
    public function test_el_token_de_rechazo_devuelve_fondos_insuficientes_con_referencia(): void
    {
        foreach ([PasarelaPagoSimulador::TOKEN_RECHAZO, PasarelaPagoSimulador::TOKEN_RECHAZO_SIMULADO] as $token) {
            $respuesta = $this->pasarela->procesarCobro('M6-PAGO-3', 1000.00, ['token_medio_pago' => $token]);

            $this->assertFalse($respuesta['exitoso'], $token);
            $this->assertSame('ERR_FONDOS_INSUFICIENTES', $respuesta['codigo_error']);
            // Con referencia: el rechazo entra por la misma ruta idempotente que el webhook.
            $this->assertStringStartsWith('PASARELA-SIM-R-', $respuesta['referencia_proveedor']);
        }
    }

    /** CP-M6-U04 · CU-001 flujo alterno "el proveedor de pago no responde". */
    public function test_el_token_de_timeout_simula_que_la_pasarela_no_responde(): void
    {
        $this->expectException(RuntimeException::class);

        $this->pasarela->procesarCobro('M6-PAGO-4', 1000.00, ['token_medio_pago' => PasarelaPagoSimulador::TOKEN_TIMEOUT]);
    }

    /** CP-M6-U05 · RN-M6-01: un monto cero o negativo nunca se cobra. */
    public function test_un_monto_no_positivo_se_rechaza_sin_referencia(): void
    {
        foreach ([0.0, -10.0] as $monto) {
            $respuesta = $this->pasarela->procesarCobro('M6-PAGO-5', $monto, ['token_medio_pago' => PasarelaPagoSimulador::TOKEN_APROBADO_TARJETA]);

            $this->assertFalse($respuesta['exitoso']);
            $this->assertSame('ERR_MONTO_INVALIDO', $respuesta['codigo_error']);
            $this->assertNull($respuesta['referencia_proveedor']);
        }
    }

    /** CP-M6-U06 · RN-M6-02: el mismo cobro siempre recibe la misma referencia (base de la idempotencia). */
    public function test_la_referencia_del_proveedor_es_estable_para_el_mismo_cobro(): void
    {
        $primera = $this->pasarela->procesarCobro('M6-PAGO-6', 500.00, []);
        $segunda = $this->pasarela->procesarCobro('M6-PAGO-6', 500.00, []);
        $otra = $this->pasarela->procesarCobro('M6-PAGO-7', 500.00, []);

        $this->assertSame($primera['referencia_proveedor'], $segunda['referencia_proveedor']);
        $this->assertNotSame($primera['referencia_proveedor'], $otra['referencia_proveedor']);
    }

    /** CP-M6-U07 · Contrato: cada error del catálogo tiene un código ERR-M6-nnn único. */
    public function test_cada_error_del_catalogo_tiene_un_codigo_estandar_unico(): void
    {
        $codigos = array_map(fn (CodigoErrorM6 $c) => $c->codigoEstandar(), CodigoErrorM6::cases());

        $this->assertSame($codigos, array_unique($codigos));

        foreach ($codigos as $codigo) {
            $this->assertMatchesRegularExpression('/^ERR-M6-\d{3}$/', $codigo);
        }
    }

    /** CP-M6-U08 · Contrato M5-M6: el pago rechazado sigue siendo ERR-M6-001 / HTTP 402 (igual que PagosStub). */
    public function test_el_pago_rechazado_conserva_err_m6_001_y_http_402(): void
    {
        $this->assertSame('ERR-M6-001', CodigoErrorM6::PagoRechazado->codigoEstandar());
        $this->assertSame(402, CodigoErrorM6::PagoRechazado->codigoHttp());
    }

    /** CP-M6-U09 · RN-M6-01 / RN-M6-02: cada validación del cobro responde con el HTTP publicado. */
    public function test_las_validaciones_del_cobro_usan_el_http_del_contrato(): void
    {
        $esperado = [
            'MONTO_INCONSISTENTE' => 400,
            'NOTIFICACION_NO_AUTENTICA' => 401,
            'PEDIDO_NO_ENCONTRADO' => 404,
            'PEDIDO_NO_CONFIRMADO' => 409,
            'PEDIDO_CON_PAGO_ACTIVO' => 409,
            'CLAVE_IDEMPOTENCIA_EN_CONFLICTO' => 409,
            'MEDIO_PAGO_NO_DISPONIBLE' => 422,
        ];

        foreach ($esperado as $codigo => $http) {
            $this->assertSame($http, CodigoErrorM6::from($codigo)->codigoHttp(), $codigo);
        }
    }

    /** CP-M6-U10 · Núcleo (sección 5.5 del PC3): la excepción sale con el formato de error estándar. */
    public function test_la_excepcion_usa_el_formato_de_error_del_nucleo(): void
    {
        $error = ErrorContratoException::de(CodigoErrorM6::MontoInconsistente, null, ['monto_esperado' => 59840.0]);

        $this->assertSame([
            'error' => [
                'codigo' => 'ERR-M6-003',
                'mensaje' => CodigoErrorM6::MontoInconsistente->mensaje(),
                'detalles' => ['motivo' => 'MONTO_INCONSISTENTE', 'monto_esperado' => 59840.0],
            ],
        ], $error->toArray());
        $this->assertSame('M6_Pagos', $error->getModulo());
    }

    /** CP-M6-U11 · Robustez: un código que no está en el catálogo no rompe el formato. */
    public function test_un_codigo_desconocido_cae_en_err_m6_000_y_422(): void
    {
        $error = new ErrorContratoException('CODIGO_INVENTADO');

        $this->assertSame('ERR-M6-000', $error->getCodigo());
        $this->assertSame(422, $error->getCodigoHttp());
        $this->assertSame('Error del módulo de pagos.', $error->getMessage());
    }
}
