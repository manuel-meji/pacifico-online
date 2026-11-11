<?php

namespace App\Http\Controllers\M3_Catalogo;

use App\Http\Controllers\Controller;
use App\Models\M3_Catalogo\Marca;
use Illuminate\Http\JsonResponse;

class MarcaController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            Marca::query()
                ->orderBy('nombre')
                ->get(['id_marca', 'nombre'])
        );
    }
}