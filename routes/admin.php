<?php

use Azuriom\Plugin\Indexnow\Controllers\Admin\AdminController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:indexnow.admin')->group(function () {
    Route::get('/', [AdminController::class, 'index'])->name('index');
    Route::post('/', [AdminController::class, 'update'])->name('update');
    Route::post('/enable', [AdminController::class, 'enable'])->name('enable');
    Route::post('/disable', [AdminController::class, 'disable'])->name('disable');
    Route::post('/submit', [AdminController::class, 'submit'])->name('submit');
});
