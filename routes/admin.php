<?php

use Azuriom\Plugin\Seo\Controllers\Admin\AdminController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:seo.admin')->group(function () {
    Route::get('/', [AdminController::class, 'index'])->name('index');
    Route::post('/', [AdminController::class, 'update'])->name('update');
    Route::post('/refresh', [AdminController::class, 'refresh'])->name('refresh');
    Route::post('/check', [AdminController::class, 'check'])->name('check');
    Route::post('/robots', [AdminController::class, 'robots'])->name('robots');
    Route::post('/indexnow/enable', [AdminController::class, 'indexNowEnable'])->name('indexnow.enable');
    Route::post('/indexnow/disable', [AdminController::class, 'indexNowDisable'])->name('indexnow.disable');
    Route::post('/indexnow/submit', [AdminController::class, 'indexNowSubmit'])->name('indexnow.submit');
});
