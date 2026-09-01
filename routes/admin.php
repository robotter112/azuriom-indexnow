<?php

use Azuriom\Plugin\Sitemap\Controllers\Admin\AdminController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:sitemap.admin')->group(function () {
    Route::get('/', [AdminController::class, 'index'])->name('index');
    Route::post('/', [AdminController::class, 'update'])->name('update');
    Route::post('/refresh', [AdminController::class, 'refresh'])->name('refresh');
    Route::post('/check', [AdminController::class, 'check'])->name('check');
});
