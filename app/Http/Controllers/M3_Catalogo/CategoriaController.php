<?php

namespace App\Http\Controllers\M3_Catalogo;

use App\Http\Controllers\Controller;
use App\Models\M3_Catalogo\Categoria;
use Illuminate\Http\JsonResponse;

class CategoriaController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            Categoria::query()
                ->orderBy('nombre')
                ->get(['id_categoria', 'nombre'])
        );
    }
}