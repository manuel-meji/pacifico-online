<?php

use App\Http\Controllers\Nucleo\SesionController;
use Illuminate\Support\Facades\Route;

Route::get('/user', [SesionController::class, 'usuario'])
    ->middleware('auth:sanctum')
    ->name('api.user');
