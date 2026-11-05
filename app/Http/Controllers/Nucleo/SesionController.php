<?php

namespace App\Http\Controllers\Nucleo;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class SesionController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'correo' => ['required', 'string', 'email', 'max:150'],
            'contrasena' => ['required', 'string'],
        ]);

        if (! Auth::guard('web')->attempt([
            'correo' => $credentials['correo'],
            'password' => $credentials['contrasena'],
            'activo' => true,
        ])) {
            throw ValidationException::withMessages([
                'correo' => ['Las credenciales proporcionadas son incorrectas.'],
            ]);
        }

        $request->session()->regenerate();

        return $this->usuario($request);
    }

    public function logout(Request $request): Response
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    public function usuario(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $request->user()->only(['id_usuario', 'nombre', 'correo', 'rol', 'id_comercio']),
        ])->header('Cache-Control', 'no-store');
    }
}
