<?php

// routes/api.php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CheckInController;

Route::post('/check-in-validate', [CheckInController::class, 'validateMember']);