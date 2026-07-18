<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FrontExtractController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/front-test', [FrontExtractController::class, 'test'])->name('front.extracting');
Route::get('/front-extracting', [FrontExtractController::class, 'extracting'])->name('front.extracting');
