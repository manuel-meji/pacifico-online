<?php

/*
|--------------------------------------------------------------------------
| M5 — Devoluciones de subpedidos (RF-019, RF-020, RF-021)
|--------------------------------------------------------------------------
*/

return [

    // Disco donde se guarda la evidencia. null = disco por defecto de la app (FILESYSTEM_DISK).
    // Ejemplo para Supabase Storage: M5_DEVOLUCIONES_DISCO=s3
    'disco' => env('M5_DEVOLUCIONES_DISCO'),

    // Evidencia: cantidad máxima de archivos, tamaño máximo por archivo (KB) y tipos permitidos.
    'evidencia_max_archivos' => (int) env('M5_DEVOLUCIONES_MAX_ARCHIVOS', 5),
    'evidencia_max_kb' => (int) env('M5_DEVOLUCIONES_MAX_KB', 5120),
    'evidencia_mimes' => ['jpg', 'jpeg', 'png', 'webp', 'pdf'],

    // Días máximos desde la entrega para solicitar una devolución (política de la empresa).
    // 0 = sin límite.
    'plazo_dias' => (int) env('M5_DEVOLUCIONES_PLAZO_DIAS', 0),

    // Cuando es true, la ruta anterior POST /subpedidos/{id}/devolucion deja de crear
    // devoluciones (reembolsaba de inmediato, sin revisión del comercio) y responde con
    // un error que apunta a la ruta nueva. Dejar en false hasta que el frontend use la
    // ruta nueva y se actualicen las pruebas de AlcanceDeseosYDevolucionesTest.
    'bloquear_ruta_legada' => (bool) env('M5_DEVOLUCIONES_BLOQUEAR_RUTA_LEGADA', false),
];
