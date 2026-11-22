<?php

use Azuriom\Plugin\Indexnow\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

// Registered only while the sitemap is switched on (see RouteServiceProvider).
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
