<?php

use App\Http\Controllers\OfficeController;
use App\Http\Controllers\OfficeImageController;
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
    Route::delete('/offices/{office}', 'delete')->middleware(['auth:sanctum', 'verified']);
});

Route::prefix('offices')->middleware(['auth:sanctum', 'verified'])->controller(OfficeImageController::class)->group(function() {
    Route::post('/{office}/images', 'store');
    Route::delete('/{office}/images/{image}', 'delete');
});
