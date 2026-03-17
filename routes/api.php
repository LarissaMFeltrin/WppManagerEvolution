<?php

use App\Http\Controllers\Api\EvolutionController;
use App\Http\Controllers\Api\WebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Webhook da Evolution API (autenticado via API key + rate limit)
Route::post('/webhook/evolution', [WebhookController::class, 'handle'])
    ->middleware('throttle:500,1') // 500 requests por minuto
    ->name('webhook.evolution');

// Rotas da Evolution API (protegidas por sessão web)
Route::middleware('web')->group(function () {
    Route::post('/evolution/create-instance', [EvolutionController::class, 'createInstance']);
    Route::get('/evolution/qrcode/{instanceName}', [EvolutionController::class, 'getQrCode']);
    Route::get('/evolution/status/{instanceName}', [EvolutionController::class, 'getStatus']);
});
