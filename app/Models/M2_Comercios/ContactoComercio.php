<?php

declare(strict_types=1);

namespace App\Models\M2_Comercios;

use App\Models\Nucleo\Concerns\PerteneceAComercio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Medio de contacto o red social del comercio (CU-0007).
 *
 * Propietario: M2. Pendiente: aplicar el trait PerteneceAComercio del núcleo
 * cuando ContextoComercio quede registrado como servicio compartido (ver DEF del 4/11).
 */
class ContactoComercio extends Model
{
    use PerteneceAComercio;

    protected $table = 'contacto_comercio';

    protected $primaryKey = 'id_contacto';

    protected $guarded = ['id_contacto'];

    protected $casts = [];

    public const TIPOS_CONTACTO = ['telefono', 'correo', 'direccion'];

    public const TIPOS_RED_SOCIAL = ['facebook', 'instagram', 'whatsapp', 'tiktok', 'sitio_web'];

    /** Dominio de cada red social y su nombre para los mensajes. */
    private const REDES = [
        'facebook' => ['facebook.com', 'Facebook'],
        'instagram' => ['instagram.com', 'Instagram'],
        'tiktok' => ['tiktok.com', 'TikTok'],
    ];

    /**
     * CU-0007 (hallazgo 32): valida el valor según su tipo y lo devuelve normalizado.
     *
     * - Teléfono: 8 dígitos de Costa Rica; acepta +506, espacios y guiones; se guarda como 8888-8888.
     * - WhatsApp: el mismo número, guardado como enlace https://wa.me/506XXXXXXXX (el portal lo abre).
     * - Correo: formato válido, en minúscula.
     * - Dirección: de 10 a 255 caracteres, con letras.
     * - Facebook, Instagram y TikTok: acepta el enlace con o sin https:// o www., @usuario o solo el
     *   usuario, y lo guarda como https://red.com/usuario. Un enlace de otro sitio se rechaza.
     * - Sitio web: si falta https:// se agrega; tiene que ser una dirección válida.
     *
     * @return array{0: ?string, 1: ?string} [valor normalizado, mensaje de error]
     */
    public static function normalizar(string $tipo, string $valor): array
    {
        $valor = trim($valor);

        return match ($tipo) {
            'telefono' => self::telefono($valor, comoWhatsapp: false),
            'whatsapp' => self::telefono($valor, comoWhatsapp: true),
            'correo' => filter_var($valor, FILTER_VALIDATE_EMAIL) !== false && mb_strlen($valor) <= 150
                ? [mb_strtolower($valor), null]
                : [null, 'Escribí un correo válido, por ejemplo contacto@tucomercio.com.'],
            'direccion' => mb_strlen($valor) >= 10 && preg_match('/\p{L}/u', $valor) === 1
                ? [$valor, null]
                : [null, 'Escribí la dirección completa (al menos 10 caracteres): provincia, cantón y señas.'],
            'facebook', 'instagram', 'tiktok' => self::redSocial($tipo, $valor),
            'sitio_web' => self::sitioWeb($valor),
            default => [null, 'Elegí un tipo de contacto válido.'],
        };
    }

    /** @return array{0: ?string, 1: ?string} */
    private static function telefono(string $valor, bool $comoWhatsapp): array
    {
        // Un enlace de WhatsApp (wa.me/506...) también se acepta: se toma el número.
        $sinEnlace = preg_replace('#^(https?://)?(www\.)?(wa\.me|api\.whatsapp\.com/send\?phone=)/?#i', '', $valor) ?? $valor;
        $digitos = preg_replace('/[\s().+-]/', '', $sinEnlace) ?? '';
        if (strlen($digitos) === 11 && str_starts_with($digitos, '506')) {
            $digitos = substr($digitos, 3);
        }
        if (preg_match('/^[24-8]\d{7}$/', $digitos) !== 1) {
            return [null, 'Escribí un número de Costa Rica de 8 dígitos, por ejemplo 8888-8888.'];
        }

        return $comoWhatsapp
            ? ['https://wa.me/506'.$digitos, null]
            : [substr($digitos, 0, 4).'-'.substr($digitos, 4), null];
    }

    /** @return array{0: ?string, 1: ?string} */
    private static function redSocial(string $tipo, string $valor): array
    {
        [$dominio, $nombre] = self::REDES[$tipo];
        $ejemplo = "Escribí tu usuario o el enlace de tu perfil, por ejemplo https://{$dominio}/tucomercio.";

        // Solo el usuario (con o sin @): se arma el enlace.
        if (preg_match('/^@?([\p{L}\d._-]{1,60})$/u', $valor, $m) === 1 && ! str_contains($m[1], '.com')) {
            $usuario = $m[1];

            return ['https://'.$dominio.'/'.($tipo === 'tiktok' ? '@'.$usuario : $usuario), null];
        }

        $url = preg_match('#^https?://#i', $valor) === 1 ? $valor : 'https://'.$valor;
        $partes = parse_url($url);
        $host = mb_strtolower((string) ($partes['host'] ?? ''));
        $host = (string) preg_replace('/^(www\.|m\.)/', '', $host);
        if ($host === '' || ($host !== $dominio && ! str_ends_with($host, '.'.$dominio))) {
            return [null, "Ese enlace no es de {$nombre}. {$ejemplo}"];
        }
        $ruta = rtrim((string) ($partes['path'] ?? ''), '/');
        if ($ruta === '') {
            return [null, "Falta tu usuario en el enlace. {$ejemplo}"];
        }

        return ['https://'.$dominio.$ruta, null];
    }

    /** @return array{0: ?string, 1: ?string} */
    private static function sitioWeb(string $valor): array
    {
        $url = preg_match('#^https?://#i', $valor) === 1 ? $valor : 'https://'.$valor;
        $host = (string) parse_url($url, PHP_URL_HOST);
        if (filter_var($url, FILTER_VALIDATE_URL) === false || ! str_contains($host, '.') || str_contains($valor, ' ')) {
            return [null, 'Escribí la dirección de tu sitio, por ejemplo https://www.tucomercio.com.'];
        }

        return [$url, null];
    }

    // --- CU-0007: administrar contactos y redes sociales
    public function actualizar(array $datos): void
    {
        $this->fill($datos);
        $this->save();
    }

    public function comercio(): BelongsTo
    {
        return $this->belongsTo(Comercio::class, 'id_comercio', 'id_comercio');
    }
}
