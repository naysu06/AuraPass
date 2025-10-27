<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/check-in', function () {
    return view('scanner');
})->name('scanner');
