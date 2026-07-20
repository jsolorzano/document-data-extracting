<?php

use App\Http\Controllers\Api\DocExtractController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/extract-document', [DocExtractController::class, 'extract'])->name('api.extracting');
