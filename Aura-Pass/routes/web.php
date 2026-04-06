<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SystemControlController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/check-in', function () {
    return view('scanner');
})->name('scanner');

Route::get('/monitor', function () {
    return view('monitor');
});

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/system/stop', [SystemControlController::class, 'stop'])->name('system.stop');
});