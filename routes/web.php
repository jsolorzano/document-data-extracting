<?php

use App\Http\Controllers\BackendExtractController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FrontExtractController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/front-test', [FrontExtractController::class, 'test'])->name('front.test');
Route::get('/front-extracting', [FrontExtractController::class, 'extracting'])->name('front.extracting');
Route::get('/backend-extracting', [BackendExtractController::class, 'extracting'])->name('backend.extracting');
