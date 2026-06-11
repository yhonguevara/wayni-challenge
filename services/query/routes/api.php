<?php

declare(strict_types=1);

use App\Http\Controllers\DebtorController;
use App\Http\Controllers\EntityController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/debtors/{cuit}', [DebtorController::class, 'show']);
    Route::get('/debtors/top/{n}', [DebtorController::class, 'top']);
    Route::get('/debtors', [DebtorController::class, 'index']);
    Route::get('/entities/{code}', [EntityController::class, 'show']);
});
