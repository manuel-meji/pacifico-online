<?php

declare(strict_types=1);

namespace App\Http\Controllers\M2_Comercios\Concerns;

use Illuminate\Http\Request;

/**
 * Validación de M2 con mensajes en español (revisión exhaustiva, hallazgo 1).
 *
 * El proyecto corre con APP_LOCALE=en y sin traducciones, así que los mensajes por defecto de Laravel
 * salen en inglés y con el nombre interno del campo («The nombre comercial field is required.»).
 * Cambiar el idioma de toda la plataforma es una decisión del Comité; M2 trae aquí sus propios
 * mensajes y los nombres de sus campos, y los usa en todas sus validaciones.
 *
 * También reúne las reglas de contenido que comparten la solicitud, la corrección y el micrositio
 * (hallazgo 6), para que un mismo dato se valide igual en todas las pantallas.
 */
trait ValidaEnEspanol
{
    /** Al menos una letra (cualquier alfabeto, con tildes). */
    protected const CON_LETRAS = '/\p{L}/u';

    /** Solo letras, espacios, apóstrofo, punto y guion; empieza con letra. */
    protected const SOLO_NOMBRE = "/^\\p{L}[\\p{L}\\s'.-]*$/u";

    /**
     * Cédula jurídica de Costa Rica: X-XXX-XXXXXX (hallazgo 7). El primer dígito es la clase de persona
     * jurídica: 2 (Estado e instituciones), 3 (sociedades y asociaciones), 4 o 5 (hallazgo 46).
     */
    protected const CEDULA_JURIDICA = '/^[2-5]-\d{3}-\d{6}$/';

    /** Teléfono de Costa Rica de 8 dígitos, con +506, guiones o espacios opcionales. */
    protected const TELEFONO_CR = '/^(\+?506[\s-]?)?[24-8]\d{3}[\s-]?\d{4}$/';

    /** Tipos de documento de la solicitud (hallazgo 5 agrega la constancia de Hacienda). */
    public const TIPOS_DOCUMENTO = ['cedula_juridica', 'patente', 'permiso_sanitario', 'constancia_hacienda', 'otro'];

    /**
     * @param  array<string, mixed>  $reglas
     * @param  array<string, string>  $mensajes  Mensajes propios de la ruta; se suman a los de M2.
     * @return array<string, mixed>
     */
    protected function validarM2(Request $request, array $reglas, array $mensajes = []): array
    {
        return $request->validate($reglas, $mensajes + self::mensajesM2(), self::atributosM2());
    }

    /**
     * Reglas de los datos del comercio y de la persona encargada. En la corrección se piden con
     * «sometimes»: lo que no se envía no cambia, pero lo que se envía cumple las mismas reglas.
     *
     * @return array<string, list<string>>
     */
    protected static function reglasDatosSolicitud(bool $parcial = false): array
    {
        $req = $parcial ? ['sometimes', 'required'] : ['required'];

        return [
            'nombre_comercial' => [...$req, 'string', 'min:2', 'max:150', 'regex:'.self::CON_LETRAS],
            'razon_social' => [...$req, 'string', 'min:3', 'max:150', 'regex:'.self::CON_LETRAS],
            'direccion_fiscal' => [...$req, 'string', 'min:10', 'max:255', 'regex:'.self::CON_LETRAS],
            'inscrito_como_contribuyente' => [...$req, 'boolean'],
            'nombre_encargado' => [...$req, 'string', 'min:3', 'max:150', 'regex:'.self::SOLO_NOMBRE],
            'correo_encargado' => [...$req, 'email', 'max:150'],
            'telefono_encargado' => ['sometimes', 'nullable', 'string', 'regex:'.self::TELEFONO_CR],
            'documentos' => ['sometimes', 'array', 'max:5'],
            'documentos.*.archivo' => ['required', 'file', 'mimes:pdf,jpg,png', 'max:5120'],
            'documentos.*.tipo' => ['required', 'string', 'in:'.implode(',', self::TIPOS_DOCUMENTO), 'distinct'],
            'documentos.*.fecha_vencimiento' => ['required', 'date', 'after:today'],
        ];
    }

    /** @return array<string, mixed> */
    private static function mensajesM2(): array
    {
        return [
            'required' => 'Completá :attribute.',
            'required_with' => 'Completá :attribute.',
            'required_without' => 'Completá :attribute.',
            'present' => 'Falta :attribute.',
            'string' => ':Attribute debe ser texto.',
            'array' => ':Attribute no tiene el formato esperado.',
            'boolean' => 'Indicá sí o no en :attribute.',
            'integer' => ':Attribute debe ser un número entero.',
            'numeric' => ':Attribute debe ser un número.',
            'email' => 'Escribí un correo válido, por ejemplo nombre@dominio.com.',
            'date' => 'Escribí una fecha válida.',
            'date_format' => 'Escribí la hora en formato HH:MM.',
            'after' => ':Attribute debe ser posterior a hoy.',
            'in' => 'Elegí una opción válida para :attribute.',
            'distinct' => 'Ya agregaste ese tipo; cada tipo va una sola vez.',
            'regex' => ':Attribute no tiene un formato válido.',
            'file' => 'Adjuntá un archivo.',
            'image' => 'El archivo debe ser una imagen.',
            'mimes' => 'El archivo debe ser PDF, JPG o PNG.',
            'confirmed' => 'Las contraseñas no coinciden.',
            'between' => ['numeric' => ':Attribute debe estar entre :min y :max.'],
            'size' => ['array' => ':Attribute debe tener :size elementos.'],
            'min' => [
                'string' => ':Attribute debe tener al menos :min caracteres.',
                'numeric' => ':Attribute no puede ser menor que :min.',
                'array' => ':Attribute debe tener al menos :min elementos.',
            ],
            'max' => [
                'string' => ':Attribute no puede pasar de :max caracteres.',
                'numeric' => ':Attribute no puede ser mayor que :max.',
                'file' => 'El archivo no puede pesar más de 5 MB.',
                'array' => 'Podés agregar como máximo :max.',
            ],

            // Mensajes que dicen qué hacer, por campo.
            'cedula_juridica.regex' => 'La cédula jurídica debe tener el formato X-XXX-XXXXXX y empezar con 2, 3, 4 o 5, por ejemplo 3-101-123456.',
            'nombre_comercial.regex' => 'El nombre comercial debe tener al menos una letra.',
            'razon_social.regex' => 'La razón social debe tener al menos una letra.',
            'direccion_fiscal.regex' => 'La dirección fiscal debe tener letras (provincia, cantón y señas).',
            'direccion_fiscal.min' => 'Escribí la dirección fiscal completa (al menos 10 caracteres): provincia, cantón y señas.',
            'nombre_encargado.regex' => 'El nombre solo puede tener letras y espacios.',
            'telefono_encargado.regex' => 'Escribí un teléfono de Costa Rica de 8 dígitos, por ejemplo 8888-8888.',
            'inscrito_como_contribuyente.required' => 'Indicá si el comercio está inscrito como contribuyente ante Hacienda.',
            'documentos.max' => 'Podés adjuntar como máximo 5 documentos.',
            'documentos.*.tipo.distinct' => 'Ya agregaste un documento de ese tipo; cada tipo va una sola vez.',
            'documentos.*.archivo.mimes' => 'El documento debe ser PDF, JPG o PNG.',
            'documentos.*.archivo.required' => 'Adjuntá el archivo del documento.',
            'documentos.*.fecha_vencimiento.after' => 'El documento debe estar vigente: la fecha de vencimiento tiene que ser posterior a hoy.',
            'contrasena_encargado.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'contrasena_encargado.max' => 'La contraseña no puede tener más de 72 caracteres.',
            'contrasena_encargado.confirmed' => 'Las contraseñas no coinciden.',
            'motivo.required' => 'Escribí el motivo.',
        ];
    }

    /** @return array<string, string> */
    private static function atributosM2(): array
    {
        return [
            'nombre_comercial' => 'el nombre comercial',
            'cedula_juridica' => 'la cédula jurídica',
            'razon_social' => 'la razón social',
            'direccion_fiscal' => 'la dirección fiscal',
            'inscrito_como_contribuyente' => 'si está inscrito como contribuyente',
            'nombre_encargado' => 'el nombre de la persona encargada',
            'correo_encargado' => 'el correo de la persona encargada',
            'telefono_encargado' => 'el teléfono',
            'contrasena_encargado' => 'la contraseña',
            'id_solicitud' => 'el número de solicitud',
            'documentos' => 'los documentos',
            'documentos.*.archivo' => 'el archivo del documento',
            'documentos.*.tipo' => 'el tipo de documento',
            'documentos.*.fecha_vencimiento' => 'la fecha de vencimiento',
            'motivo' => 'el motivo',
            'resultado' => 'el resultado',
            'observacion' => 'la observación',
            'descripcion' => 'la descripción',
            'logotipo' => 'el logotipo',
            'horarios' => 'el horario',
            'horarios.*.dia_semana' => 'el día',
            'horarios.*.cerrado' => 'cerrado',
            'horarios.*.hora_apertura' => 'la hora de apertura',
            'horarios.*.hora_cierre' => 'la hora de cierre',
            'contactos' => 'los contactos',
            'contactos.*.tipo' => 'el tipo de contacto',
            'contactos.*.valor' => 'el contacto',
            'politicas' => 'las políticas',
            'politicas.*.tipo' => 'el tipo de política',
            'politicas.*.contenido' => 'el contenido de la política',
            'politicas.*.configuracion_envio' => 'las tarifas de envío',
            'politicas.*.configuracion_envio.*.zona' => 'la zona',
            'politicas.*.configuracion_envio.*.costo' => 'el costo',
            'politicas.*.configuracion_envio.*.moneda' => 'la moneda',
            'banners' => 'los banners',
            'banners.*.texto_alternativo' => 'la descripción de la imagen',
            'banners.*.orden' => 'el orden',
            'banners.*.activo' => 'visible',
            'galeria' => 'la galería',
            'galeria.*.texto_alternativo' => 'la descripción de la imagen',
            'galeria.*.orden' => 'el orden',
            'dias' => 'el periodo',
            'accion' => 'la acción',
            'estado' => 'el estado',
            'pagina' => 'la página',
            'por_pagina' => 'la cantidad por página',
        ];
    }
}
