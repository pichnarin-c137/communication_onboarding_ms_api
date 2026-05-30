<?php

use App\Http\Controllers\CrmContactController;
use App\Http\Controllers\CrmDealController;
use App\Http\Controllers\CrmPipelineController;
use Illuminate\Support\Facades\Route;

Route::middleware(['jwt.auth', 'throttle:api', 'role:sale,admin'])
    ->prefix('crm')
    ->group(function () {
        // Pipeline stats
        Route::get('pipeline/stats', [CrmPipelineController::class, 'stats'])->name('crm.pipeline.stats');

        // Contacts
        Route::get('contacts', [CrmContactController::class, 'index'])->name('crm.contacts.index');
        Route::post('contacts', [CrmContactController::class, 'store'])->name('crm.contacts.store');
        Route::get('contacts/{id}', [CrmContactController::class, 'show'])->name('crm.contacts.show');
        Route::patch('contacts/{id}', [CrmContactController::class, 'update'])->name('crm.contacts.update');
        Route::delete('contacts/{id}', [CrmContactController::class, 'destroy'])->name('crm.contacts.destroy');
        Route::get('contacts/{id}/deals', [CrmContactController::class, 'deals'])->name('crm.contacts.deals');

        // Deals
        Route::get('deals', [CrmDealController::class, 'index'])->name('crm.deals.index');
        Route::post('deals', [CrmDealController::class, 'store'])->name('crm.deals.store');
        Route::get('deals/{id}', [CrmDealController::class, 'show'])->name('crm.deals.show');
        Route::patch('deals/{id}', [CrmDealController::class, 'update'])->name('crm.deals.update');
        Route::post('deals/{id}/won', [CrmDealController::class, 'won'])->name('crm.deals.won');
        Route::post('deals/{id}/lost', [CrmDealController::class, 'lost'])->name('crm.deals.lost');
    });
