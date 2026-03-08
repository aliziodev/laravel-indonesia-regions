<?php

use Aliziodev\IndonesiaRegions\Http\Controllers\IndonesiaRegionController;
use Illuminate\Support\Facades\Route;

Route::get('/', [IndonesiaRegionController::class, 'index']);
Route::get('/cascade', [IndonesiaRegionController::class, 'cascade']);
Route::get('/select', [IndonesiaRegionController::class, 'select']);
Route::get('/search', [IndonesiaRegionController::class, 'search']);
Route::get('/search/address', [IndonesiaRegionController::class, 'searchWithAddress']);
Route::get('/search/full-text', [IndonesiaRegionController::class, 'searchWithFullText']);
Route::get('/postal-code/{postalCode}', [IndonesiaRegionController::class, 'findByPostalCode']);
Route::get('/{code}/select-context', [IndonesiaRegionController::class, 'selectContext']);
Route::get('/{code}/info', [IndonesiaRegionController::class, 'showInfo']);
Route::get('/{code}/full-address', [IndonesiaRegionController::class, 'fullAddress']);
Route::get('/{code}', [IndonesiaRegionController::class, 'show']);
