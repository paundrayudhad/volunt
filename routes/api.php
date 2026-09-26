<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CertificateVerificationController;
use App\Http\Controllers\Api\V1\EventController;
use App\Http\Controllers\Api\V1\RegistrationController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/ping', function () {
        return response()->json([
            'status' => 'ok',
            'version' => 'v1',
        ]);
    });

    // 1. Auth routes
    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:auth');

        Route::middleware('auth:sanctum')->group(function () {
            Route::get('/me', [AuthController::class, 'me']);
            Route::post('/logout', [AuthController::class, 'logout']);
        });
    });

    // 2. Public Event Discovery
    Route::prefix('events')->middleware('throttle:public-api')->group(function () {
        Route::get('/', [EventController::class, 'index']);
        Route::get('/{slug}', [EventController::class, 'show']);
    });

    // 3. Volunteer Registration
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/events/{slug}/register', [RegistrationController::class, 'register'])
            ->middleware('throttle:registration-submit');
        Route::get('/my/registrations', [RegistrationController::class, 'index']);
        Route::post('/my/registrations/{id}/withdraw', [RegistrationController::class, 'withdraw']);
    });

    // 4. Public Certificate Verification
    Route::get('/certificates/verify/{no}', [CertificateVerificationController::class, 'verify'])
        ->middleware('throttle:public-api');
});
