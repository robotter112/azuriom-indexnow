<?php

return [
    'title' => 'Sitemap',
    'permission' => 'Manage the sitemap',

    'url' => 'Your sitemap',
    'url-hint' => 'Submit this address in Google Search Console and Bing Webmaster Tools, and add it to public/robots.txt as "Sitemap: :url".',
    'count' => '{0}No URL yet|{1}1 URL|[2,*]:count URLs',
    'cached' => 'Cached, rebuilt automatically after :minutes minutes.',
    'not-cached' => 'Not cached, the next visit rebuilds it.',

    'settings' => 'Settings',
    'cache-minutes' => 'Cache lifetime (minutes)',
    'cache-minutes-hint' => 'How long the URL list is kept before it is rebuilt. Crawlers fetch far less often than this.',
    'exclude' => 'Excluded paths',
    'exclude-hint' => 'One pattern per line, without the domain, e.g. "shop/*". Pages behind a login are skipped automatically — this list is for pages whose plugin redirects guests from inside its own code.',

    'save' => 'Save',
    'saved' => 'Settings saved, the sitemap was rebuilt.',
    'refresh' => 'Rebuild now',
    'refreshed' => 'Sitemap rebuilt, :count URLs.',

    'check' => 'Check URLs',
    'check-hint' => 'Fetches every URL as a logged-out visitor. A sitemap should only list pages that really answer with 200 — search engines report redirects and login walls as errors.',
    'check-ok' => 'All :count URLs answer with 200.',
    'check-bad' => ':count of :total URLs do not answer with 200. Add their path to the excluded paths above.',
    'check-capped' => 'Only the first :limit URLs were checked. Use "php artisan sitemap:check" for all of them.',
];
