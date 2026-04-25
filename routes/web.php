<?php

use App\Http\Controllers\ResetPasswordController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/reset-password', [ResetPasswordController::class, 'showForm']);
Route::post('/reset-password', [ResetPasswordController::class, 'handleForm']);
