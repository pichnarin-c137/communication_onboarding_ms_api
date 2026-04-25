<?php

use App\Http\Controllers\PlaylistController;
use App\Http\Controllers\PlaylistTelegramController;
use App\Http\Controllers\PlaylistVideoController;
use Illuminate\Support\Facades\Route;

Route::middleware(['jwt.auth', 'throttle:api'])->prefix('playlists')->group(function () {
    Route::get('/', [PlaylistController::class, 'index'])->name('playlists.index');
    Route::post('/', [PlaylistController::class, 'store'])->name('playlists.store');
    Route::get('/{id}', [PlaylistController::class, 'show'])->name('playlists.show');
    Route::put('/{id}', [PlaylistController::class, 'update'])->name('playlists.update');
    Route::delete('/{id}', [PlaylistController::class, 'destroy'])->name('playlists.destroy');

    // Videos within a playlist
    Route::get('/{id}/videos', [PlaylistVideoController::class, 'index'])->name('playlists.videos.index');
    Route::post('/{id}/videos', [PlaylistVideoController::class, 'store'])->name('playlists.videos.store');
    Route::get('/{id}/videos/{vid}', [PlaylistVideoController::class, 'show'])->name('playlists.videos.show');
    Route::put('/{id}/videos/{vid}', [PlaylistVideoController::class, 'update'])->name('playlists.videos.update');
    Route::delete('/{id}/videos/{vid}', [PlaylistVideoController::class, 'destroy'])->name('playlists.videos.destroy');
    Route::patch('/{id}/videos/reorder', [PlaylistVideoController::class, 'reorder'])->name('playlists.videos.reorder');

    // Telegram send actions
    Route::post('/{id}/send', [PlaylistTelegramController::class, 'sendPlaylist'])
        ->middleware('throttle:lesson_send')
        ->name('playlists.send');
    Route::post('/{id}/videos/{vid}/send', [PlaylistTelegramController::class, 'sendVideo'])
        ->middleware('throttle:lesson_send')
        ->name('playlists.videos.send');
});
