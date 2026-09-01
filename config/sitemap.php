<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cache lifetime
    |--------------------------------------------------------------------------
    |
    | How many minutes the generated URL list is kept before it is rebuilt.
    | Crawlers fetch the sitemap far less often than this, so a stale hour
    | costs nothing and saves the database queries.
    |
    */

    'cache_minutes' => 60,

    /*
    |--------------------------------------------------------------------------
    | Excluded paths
    |--------------------------------------------------------------------------
    |
    | Paths that must never end up in the sitemap, as Str::is() patterns
    | (so "shop/*" works). Routes that are protected by the "auth" middleware
    | are skipped automatically — this list is for pages whose controller
    | redirects guests itself, which cannot be detected without running it.
    |
    | Run "php artisan sitemap:check" to find such pages on your own site.
    |
    */

    'exclude' => [
        'mydailychest', // redirects guests to the login page
    ],

    /*
    |--------------------------------------------------------------------------
    | Canonical URL
    |--------------------------------------------------------------------------
    |
    | Adds <link rel="canonical"> to pages that do not already have one, so the
    | same page reached with tracking or cache-busting parameters is not treated
    | as a separate address.
    |
    | 'canonical_keep' lists the query parameters that genuinely change the
    | content and therefore survive. Removing 'page' from it would tell search
    | engines that page 2 is a copy of page 1, and page 2 would drop out of the
    | index - so only shorten this list on purpose.
    |
    */

    'canonical' => true,

    'canonical_keep' => ['page'],

];
