<?php

use Azuriom\Plugin\Indexnow\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

// Answers 404 unless the optional sitemap is switched on, so the route can be
// registered unconditionally and no cached route table has to be rebuilt when
// the setting changes.
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
