<?php

use App\Http\Controllers\VideoController;
use Illuminate\Support\Facades\Route;

Route::get('/', [VideoController::class, 'create'])->name('home');

Route::controller(VideoController::class)->prefix('videos')->name('videos.')->group(function () {
    Route::get('create', 'create')->name('create');
    Route::post('/', 'store')->middleware('throttle:videos')->name('store');
    Route::get('{video}', 'show')->name('show');
    Route::get('{video}/status', 'status')->name('status');
    Route::get('{video}/file', 'file')->name('file');
    Route::post('{video}/retry', 'retry')->middleware('throttle:videos')->name('retry');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
