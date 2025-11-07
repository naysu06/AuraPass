<?php

// routes/api.php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CheckInController;
use App\Http\Controllers\Api\ScanController;

Route::post('/check-in-validate', [CheckInController::class, 'validateMember']);

Route::post('/scan', [ScanController::class, 'store']);