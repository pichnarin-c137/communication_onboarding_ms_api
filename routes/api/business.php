<?php

use App\Http\Controllers\BusinessTypeController;
use App\Http\Controllers\CompanyController;
use Illuminate\Support\Facades\Route;

Route::middleware(['jwt.auth', 'throttle:api'])->group(function () {
    // Business Types (read: all roles; write: admin + sale)
    Route::get('business-types', [BusinessTypeController::class, 'index'])->name('business-types.index');
    Route::get('business-types/{id}', [BusinessTypeController::class, 'show'])->name('business-types.show');
    Route::middleware('role:admin,sale')->group(function () {
        Route::post('business-types', [BusinessTypeController::class, 'store'])->name('business-types.store');
        Route::put('business-types/{id}', [BusinessTypeController::class, 'update'])->name('business-types.update');
        Route::delete('business-types/{id}', [BusinessTypeController::class, 'destroy'])->name('business-types.destroy');
    });

    // Companies (extract-document must come before {id} to avoid route collision)
    Route::post('companies/extract-document', [CompanyController::class, 'extractDocument'])
        ->middleware('throttle:document_extract')
        ->name('companies.extract-document');
    Route::get('companies', [CompanyController::class, 'index'])->name('companies.index');
    Route::post('companies', [CompanyController::class, 'store'])->name('companies.store');
    Route::get('companies/{id}', [CompanyController::class, 'show'])->name('companies.show');
    Route::put('companies/{id}', [CompanyController::class, 'update'])->name('companies.update');
    Route::delete('companies/{id}', [CompanyController::class, 'destroy'])->name('companies.destroy');
});
