<?php

declare(strict_types=1);

namespace App\Http\Requests\M3_Catalogo;

/**
 * POST /productos/{id_producto}/variaciones (crearVariacion).
 *
 * El SKU único por comercio lo valida ProductoService (necesita el comercio de
 * la sesión). El descuento (D1) no se valida aquí: las tablas de M3 todavía no
 * tienen columnas para guardarlo.
 */
class CrearVariacionRequest extends SolicitudCatalogo
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'sku' => ['required', 'string', 'max:100'],
            'precio' => ['required', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
            'tarifa_impuesto' => ['required', 'numeric', 'between:0,100', 'decimal:0,2'],
            'codigo_barras' => ['nullable', 'regex:/^[0-9]{8,14}$/'],
            'codigo_fabricante' => ['nullable', 'string', 'max:100'],
            'disponible' => ['sometimes', 'boolean'],
            'atributos' => ['sometimes', 'array'],
            'atributos.*.tipo_atributo' => ['required', 'string', 'max:50', 'distinct:ignore_case'],
            'atributos.*.valor' => ['required', 'string', 'max:100'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'sku.required' => 'Escribe el SKU de la variación.',
            'sku.max' => 'El SKU no puede tener más de 100 caracteres.',
            'precio.required' => 'Escribe el precio.',
            'precio.numeric' => 'El precio debe ser un número.',
            'precio.min' => 'El precio no puede ser negativo.',
            'precio.decimal' => 'El precio puede tener como máximo 2 decimales.',
            'tarifa_impuesto.required' => 'Escribe la tarifa del impuesto (por ejemplo 13, o 0 si está exento).',
            'tarifa_impuesto.numeric' => 'La tarifa del impuesto debe ser un número.',
            'tarifa_impuesto.between' => 'La tarifa del impuesto debe estar entre 0 y 100.',
            'tarifa_impuesto.decimal' => 'La tarifa del impuesto puede tener como máximo 2 decimales.',
            'codigo_barras.regex' => 'El código de barras debe tener solo números, entre 8 y 14.',
            'atributos.*.tipo_atributo.required' => 'Cada atributo necesita un tipo (por ejemplo Talla).',
            'atributos.*.tipo_atributo.distinct' => 'No repitas el mismo tipo de atributo en una variación.',
            'atributos.*.valor.required' => 'Cada atributo necesita un valor (por ejemplo XL).',
        ];
    }
}