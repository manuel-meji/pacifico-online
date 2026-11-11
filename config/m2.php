<?php

/*
|--------------------------------------------------------------------------
| Configuración de M2 — Micrositios y administración de comercios
|--------------------------------------------------------------------------
| disco_imagenes: dónde se guardan logotipos, banners y galería.
| - "public" (por defecto): disco local; las imágenes solo se ven en la máquina que las subió.
| - "s3": Supabase Storage (compatible con S3), configurado con las variables AWS_* del .env;
|   las imágenes se ven desde cualquier computadora.
| Los documentos de las solicitudes siguen en el disco privado "local", cifrados (RN-002).
*/

return [
    'disco_imagenes' => env('M2_DISCO_IMAGENES', 'public'),
];
