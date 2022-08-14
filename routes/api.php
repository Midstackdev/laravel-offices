<?php

use App\Http\Controllers\OfficeController;
use App\Http\Controllers\TagController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/tags', TagController::class);
Route::controller(OfficeController::class)->group(function() {
    Route::get('/offices', 'index');
    Route::post('/offices', 'create')->middleware(['auth:sanctum', 'verified']);
    Route::get('/offices/{office}', 'show');
    Route::put('/offices/{office}', 'update')->middleware(['auth:sanctum', 'verified']);
});
