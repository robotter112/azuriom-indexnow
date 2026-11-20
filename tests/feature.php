<?php

/*
 * What IndexNow reports, in a real Azuriom with a second plugin that knows nothing
 * about IndexNow (tests/stubs/indexnow-demo). Run from the root of a fresh Azuriom
 * with both plugins enabled (see ci.sh); the IndexNow endpoint is faked.
 */

use Azuriom\Models\Page;
use Azuriom\Models\Post;
use Azuriom\Models\Setting;
use Azuriom\Models\User;
use Azuriom\Plugin\Indexnow\Client;
use Azuriom\Plugin\Indexnow\Reporter;
use Azuriom\Plugin\Indexnow\SiteUrls;
use Azuriom\Plugin\Indexnow\UrlSource;
use Azuriom\Plugin\IndexnowDemo\Models\Item;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

require getcwd().'/vendor/autoload.php';

$app = require getcwd().'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// An uncaught error must fail the run.
set_exception_handler(function (Throwable $e): never {
    echo 'FAIL  uncaught '.$e::class.': '.$e->getMessage().' ('.basename($e->getFile()).':'.$e->getLine().')'.PHP_EOL;
    exit(1);
});

$label = getenv('INDEXNOW_AZBOOST') ? ' [AzBoost]' : '';
$failures = 0;

function check(bool $ok, string $label): void
{
    global $failures;

    echo ($ok ? 'ok    ' : 'FAIL  ').$label.PHP_EOL;
    $failures += $ok ? 0 : 1;
}

// The IndexNow endpoint answers $status. The site itself answers what $site says
// for an address (200 when not listed, 'down' when it cannot be reached): that is
// what a visitor who is not logged in gets.
$status = 200;
$site = [];
Http::fake(function ($request) use (&$status, &$site) {
    if (str_ends_with($request->url(), '/redirected.txt')) {
        return Http::response('', 302, ['Location' => url('/abcdef12.txt')]);
    }

    // Oversized answers for the size limits.
    $loc = '<url><loc>http://localhost/x</loc></url></urlset>';
    $fixed = [
        url('/big-key.txt') => fn () => str_repeat('a', 4096),
        url('/big-sitemap.xml') => fn () => '<?xml version="1.0"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.str_repeat(' ', UrlSource::MAX_BYTES).$loc,
        url('/small-sitemap.xml') => fn () => '<?xml version="1.0"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.$loc,
    ];

    if (isset($fixed[$request->url()])) {
        return Http::response($fixed[$request->url()](), 200);
    }

    if (str_starts_with($request->url(), url('/'))) {
        $file = public_path(basename((string) parse_url($request->url(), PHP_URL_PATH)));

        if (str_ends_with($request->url(), '.txt') && is_file($file)) {
            return Http::response(file_get_contents($file), 200);
        }

        $answer = $site[$request->url()] ?? 200;

        if ($answer === 'down') {
            throw new Illuminate\Http\Client\ConnectionException('The site cannot reach itself');
        }

        return Http::response('', $answer);
    }

    return Http::response('', $status);
});

// What one request reports: the lists sent to IndexNow when it ends.
$seen = 0;
$reported = function () use ($app, &$seen): array {
    $app->terminate();
    $requests = Http::recorded()->slice($seen)->values();
    $seen += $requests->count();

    return $requests->filter(fn ($pair) => $pair[0]->method() === 'POST')->map(fn ($pair) => $pair[0]->data()['urlList'] ?? [])->values()->all();
};

Cache::flush();
Setting::updateSettings(['indexnow.key' => Client::generateKey(), 'indexnow.auto' => '1']);
$reported();

$author = User::forceCreate(['name' => 'IndexNow'.uniqid(), 'email' => uniqid().'@example.test', 'password' => 'secret-'.uniqid()]);
$post = fn (string $slug, string $when = '-1 day') => Post::forceCreate([
    'author_id' => $author->id, 'title' => $slug, 'slug' => $slug, 'description' => $slug, 'content' => "<p>$slug</p>", 'published_at' => now()->modify($when),
]);

// --- pages and posts ---------------------------------------------------------

$one = $post('first-post');
check($reported() === [[route('posts.show', 'first-post')]], 'a published post is reported when saved'.$label);

$two = $post('second-post');
$three = $post('third-post');
check($reported() === [[route('posts.show', 'second-post'), route('posts.show', 'third-post')]], 'what changes in one request goes out as one report');
check(Azuriom\Plugin\Indexnow\Client::last()['count'] === 2 && Azuriom\Plugin\Indexnow\Client::last()['ok'] && Azuriom\Plugin\Indexnow\Client::today() >= 3, 'the admin page knows the last report and the addresses of today');

$draft = $post('draft-post', '+1 week');
check($reported() === [], 'a post that is not published yet is not reported');

$two->forceFill(['published_at' => now()->addWeek()])->save();
check($reported() === [[route('posts.show', 'second-post')]], 'taking a post offline reports its address, so it is forgotten');

$three->forceFill(['slug' => 'third-post-renamed'])->save();
check($reported() === [[route('posts.show', 'third-post-renamed'), route('posts.show', 'third-post')]], 'a new slug reports the new and the old address');

$three->delete();
check($reported() === [[route('posts.show', 'third-post-renamed')]], 'deleting a published post reports its address');

$page = Page::forceCreate(['title' => 'About', 'slug' => 'about-indexnow', 'description' => 'About', 'content' => '<p>About</p>', 'is_enabled' => true]);
check($reported() === [[route('pages.show', 'about-indexnow')]], 'an enabled page is reported');
$page->forceFill(['is_enabled' => false])->save();
check($reported() === [[route('pages.show', 'about-indexnow')]], 'disabling a page reports its address');

// Pages restricted to roles are never made known, not even by their old address.
$role = Azuriom\Models\Role::query()->first();
$secret = Page::forceCreate(['title' => 'Team', 'slug' => 'team-only', 'description' => 'Team', 'content' => '<p>Team</p>', 'is_enabled' => true]);
$secret->roles()->sync([$role->id]); // as the admin panel does it: roles after saving
check($reported() === [], 'a page restricted to roles is not reported, although its roles come after saving');
$secret->forceFill(['slug' => 'team-only-renamed'])->save();
$secret->forceFill(['is_enabled' => false])->save();
check($reported() === [], 'renaming or disabling a page restricted to roles reports nothing');
$secret->delete();
check($reported() === [], 'deleting a page restricted to roles reports nothing, although the database drops its roles with it');
$public = Page::forceCreate(['title' => 'Public', 'slug' => 'public-page', 'description' => 'Public', 'content' => '<p>Public</p>', 'is_enabled' => true]);
$reported();
$public->delete();
check($reported() === [[route('pages.show', 'public-page')]], 'deleting a public page reports its address');

// --- entries of any other plugin ---------------------------------------------

check(SiteUrls::pluginPages('indexnow-demo') === [url('indexnow-demo')], 'the public pages of a plugin are found in the route table, without its admin pages');
$item = Item::create(['name' => 'Entry']);
check($reported() === [[url('indexnow-demo')]], 'an entry of a plugin that knows nothing about IndexNow reports the public pages of that plugin');
$item->update(['name' => 'Entry 2']);
Item::create(['name' => 'Another']);
check($reported() === [], 'a plugin page is reported at most once per pause, however much changes');
Cache::forget('indexnow.reported.'.sha1(url('indexnow-demo')));
Cache::forget('indexnow.reported.'.sha1(url('indexnow-demo')).'.checked');
$item->delete();
check($reported() === [[url('indexnow-demo')]], 'after the pause, deleting an entry reports the plugin page again');

$author->forceFill(['name' => 'Renamed'.uniqid()])->save();
check($reported() === [], 'models of Azuriom itself without a page of their own report nothing');

// --- addresses handed over by plugins ----------------------------------------

event('indexnow.submit', [[url('/knowledge/setup'), 'https://example.org/elsewhere', 42]]);
check($reported() === [[url('/knowledge/setup')]], 'a plugin can hand over exact addresses; only those of this site go out');

Event::listen('indexnow.urls', fn () => [url('/knowledge'), url('/knowledge/setup'), 'https://example.org/elsewhere']);
$all = SiteUrls::all();
check(in_array(url('/knowledge/setup'), $all, true) && ! in_array('https://example.org/elsewhere', $all, true), 'a plugin adds its addresses to the sitemap and to "Submit all URLs"');
check(in_array(url('indexnow-demo'), $all, true) && ! in_array(url('admin/indexnow-demo'), $all, true), 'the sitemap has the public plugin page, not its admin page');

// --- only what a visitor who is not logged in can open -------------------------

$site[route('posts.show', 'login-post')] = 302;
$post('login-post');
check($reported() === [], 'an address that sends visitors to the login form is not reported');
foreach ([401, 403] as $answer) {
    $site[route('posts.show', "closed-$answer")] = $answer;
    $post("closed-$answer");
}
check($reported() === [], 'an address that answers 401 or 403 is not reported');
Cache::forget('indexnow.reported.'.sha1(url('indexnow-demo')));
Cache::forget('indexnow.reported.'.sha1(url('indexnow-demo')).'.checked');
$site[url('indexnow-demo')] = 302;
Item::create(['name' => 'Members only']);
check($reported() === [], 'a plugin page behind a login is not reported');
$opened = fn () => Http::recorded()->filter(fn ($pair) => $pair[0]->method() === 'GET' && $pair[0]->url() === url('indexnow-demo'))->count();
$before = $opened();
Item::create(['name' => 'Still members only']);
Item::create(['name' => 'And again']);
check($reported() === [] && $opened() === $before, 'a page that refused visitors is not opened again with every change (at most once per check pause)');
unset($site[url('indexnow-demo')]);
Cache::forget('indexnow.reported.'.sha1(url('indexnow-demo')).'.checked');
Item::create(['name' => 'Public again']);
check($reported() === [[url('indexnow-demo')]], 'once public, it is reported with the next change after the check pause (a refused check starts no report pause)');

$site[route('posts.show', 'rebuilt-post')] = 503;
$rebuilt = $post('rebuilt-post');
$rebuilt->delete();
check($reported() === [], 'under maintenance (503) nothing is reported, neither as changed nor as gone');
$site[route('posts.show', 'gone-post')] = 404;
$gone = $post('gone-post');
$reported();
$gone->delete();
check($reported() === [[route('posts.show', 'gone-post')]], 'a page that is gone (404) is reported, so search engines forget it');

$site[route('posts.show', 'unreachable-post')] = 'down';
$post('unreachable-post');
check($reported() === [[route('posts.show', 'unreachable-post')]], 'if the site cannot reach itself, the rules decide');
$site[url('/knowledge/unchecked')] = 'down';
event('indexnow.submit', [[url('/knowledge/unchecked')]]);
check($reported() === [], 'an address handed over by a plugin that could not be checked stays out (no rules decide for it)');
Setting::updateSettings(['maintenance.enabled' => '1', 'maintenance.paths' => ['/news/*']]);
$site[route('posts.show', 'unreachable-maintained')] = 'down';
$post('unreachable-maintained');
check($reported() === [], 'and an address under maintenance stays out (maintenance of single paths)');
Setting::updateSettings(['maintenance.paths' => []]);
$site[route('posts.show', 'unreachable-everywhere')] = 'down';
$post('unreachable-everywhere');
check($reported() === [], 'maintenance of the whole site keeps every address out');
Setting::updateSettings(['maintenance.enabled' => '0', 'maintenance.paths' => []]);
check(Http::recorded()->every(fn ($pair) => $pair[0]->method() === 'POST' || str_starts_with($pair[0]->url(), url('/'))), 'only addresses of this site are ever opened for the check');

// --- switches and failures ---------------------------------------------------

Setting::updateSettings(['indexnow.auto' => '0']);
$post('quiet-post');
event('indexnow.submit', [[url('/knowledge/other')]]);
check($reported() === [], 'with automatic reporting switched off nothing is sent');
Setting::updateSettings(['indexnow.auto' => '1', 'indexnow.key' => null]);
$post('keyless-post');
check($reported() === [], 'without a key nothing is sent');
Setting::updateSettings(['indexnow.key' => Client::generateKey()]);

$status = 500;
$post('failing-post');
$failed = null;
try {
    $reported();
    $failed = false;
} catch (Throwable) {
    $failed = true;
}
check($failed === false, 'a failing IndexNow never disturbs the save');
$status = 200;

Post::whereIn('slug', ['first-post', 'second-post', 'draft-post', 'quiet-post', 'keyless-post', 'failing-post', 'login-post', 'closed-401', 'closed-403', 'unreachable-post', 'unreachable-maintained', 'unreachable-everywhere'])->delete();
$page->delete();
$author->delete();
$reported();

// --- the site's own address, whatever the request says -------------------------

// A request decides url(): its Host, a /index.php in the path, X-Forwarded-Proto.
// Nothing IndexNow caches for everyone or reports may depend on that.
$asRequest = function (string $address, callable $then) use ($app) {
    $request = Illuminate\Http\Request::create($address, 'GET', [], [], [], [
        'SCRIPT_NAME' => '/index.php', 'SCRIPT_FILENAME' => public_path('index.php'), 'PHP_SELF' => '/index.php',
    ]);
    $before = app('request');
    $app->instance('request', $request);
    app('url')->setRequest($request);

    try {
        return $then();
    } finally {
        $app->instance('request', $before);
        app('url')->setRequest($before);
    }
};
$site_ = Client::siteRoot();
$onSite = fn (array $urls) => $urls !== [] && collect($urls)->every(fn ($url) => (str_starts_with($url, $site_.'/') || $url === $site_) && ! str_contains($url, 'ndex.php'));

foreach (['http://evil.example/sitemap.xml', 'http://localhost/index.php/sitemap.xml', 'http://localhost/%69ndex.php/sitemap.xml'] as $address) {
    Cache::forget('indexnow.sitemap');
    $urls = $asRequest($address, fn () => Azuriom\Plugin\Indexnow\Controllers\SitemapController::urls());
    check($onSite($urls) && $onSite(Azuriom\Plugin\Indexnow\Controllers\SitemapController::urls()), "the sitemap built for a request to $address lists only addresses of the site itself");
}

Cache::forget('indexnow.reported.'.sha1(url('indexnow-demo')));
Cache::forget('indexnow.reported.'.sha1(url('indexnow-demo')).'.checked');
$asRequest('http://10.0.0.5:8080/index.php/vote', fn () => event('indexnow.submit', [[url('/knowledge/forged')]]));
$sent = $reported();
$posts = Http::recorded()->filter(fn ($pair) => $pair[0]->method() === 'POST')->last();
check($sent === [[$site_.'/knowledge/forged']] && $posts[0]->data()['keyLocation'] === Client::keyLocation(setting('indexnow.key'))
    && Http::recorded()->every(fn ($pair) => ! str_contains($pair[0]->url(), '10.0.0.5') && ! str_contains($pair[0]->url(), 'ndex.php')),
    'an address built during a request to another Host and /index.php is checked and reported on the site itself, with its key file there');
Cache::forget('indexnow.reported.'.sha1(url('indexnow-demo')));
Cache::forget('indexnow.reported.'.sha1(url('indexnow-demo')).'.checked');
$asRequest('http://localhost/index.php/vote', fn () => Reporter::queueOncePerPause([url('indexnow-demo')]));
$asRequest('http://localhost/%69ndex.php/vote', fn () => Reporter::queueOncePerPause([url('indexnow-demo')]));
check($reported() === [[url('indexnow-demo')]], 'other spellings of the site\'s address are one page with one pause');

check(! Client::isOwnHost(Client::siteRoot().':8099/sitemap.xml') && ! Client::isOwnHost('file://'.parse_url(Client::siteRoot(), PHP_URL_HOST).'/etc/passwd')
    && Client::isOwnHost(Client::siteRoot().'/sitemap.xml'), 'another port of the site\'s own name is not the site');
file_put_contents(public_path('abcdef12.txt'), 'abcdef12');
$verified = Client::verifyKeyFile('abcdef12', url('/redirected.txt'));
@unlink(public_path('abcdef12.txt'));
check($verified['ok'] === false && ($verified['status'] ?? 0) === 302, 'the key file check does not follow redirects');
try {
    Client::keyFileName("abcdef12\n");
    $refused = false;
} catch (InvalidArgumentException) {
    $refused = true;
}
check($refused, 'a key with a trailing line break is no key');

// The sitemap: one build at a time, none right after one died, and a valid file.
Cache::forget('indexnow.sitemap');
Cache::forever('indexnow.sitemap.last', ['http://localhost/last-good']);
Cache::put('indexnow.sitemap.failed', true, 60);
check(Azuriom\Plugin\Indexnow\Controllers\SitemapController::urls() === ['http://localhost/last-good'], 'right after a build died, the sitemap serves the last list instead of building again');
Cache::forget('indexnow.sitemap.failed');
$lock = Cache::lock('indexnow.sitemap.build', 60);
$lock->get();
check(Azuriom\Plugin\Indexnow\Controllers\SitemapController::urls() === ['http://localhost/last-good'], 'while one build runs, others get the last list');
$lock->release();
check(in_array(Client::siteRoot().'/', Azuriom\Plugin\Indexnow\Controllers\SitemapController::urls() ?? [], true) && ! Cache::has('indexnow.sitemap.failed'), 'a build that works replaces the list and clears its mark');
$xml = view('indexnow::sitemap', ['urls' => ['http://localhost/a&b']])->render();
check(str_starts_with($xml, '<?xml version="1.0" encoding="UTF-8"?>') && str_contains($xml, '<loc>http://localhost/a&amp;b</loc>'), 'the sitemap file starts with its XML declaration, also with short_open_tag on (ini: '.(int) ini_get('short_open_tag').')');

// The cached sitemap does not outlive a change of what it lists.
Cache::put('indexnow.sitemap', ['http://localhost/stale'], 3600);
$post('sitemap-refresh-'.uniqid());
check(! Cache::has('indexnow.sitemap'), 'saving a post drops the cached sitemap, so a hidden post does not stay listed for an hour');
$reported();
Cache::put('indexnow.sitemap', ['http://localhost/stale'], 3600);
event('indexnow.submit', [[url('/knowledge/setup')]]);
check(! Cache::has('indexnow.sitemap'), 'so does an address a plugin hands over');
$reported();

// A page opened by the visibility check never starts the next check.
$visitor = $app['request'];
$app->instance('request', Illuminate\Http\Request::create('http://localhost/indexnow-demo', 'GET', [], [], [], ['HTTP_USER_AGENT' => Azuriom\Plugin\Indexnow\Reporter::PROBE_AGENT]));
$post('saved-during-a-check-'.uniqid());
event('indexnow.submit', [[url('/knowledge/during-a-check')]]);
$app->instance('request', $visitor);
check($reported() === [], 'what a page saves while the visibility check opens it reports nothing');

// One plugin that fails does not take the other addresses with it.
Event::listen('indexnow.urls', fn () => throw new RuntimeException('stale route cache'));
$all = SiteUrls::all();
check(in_array(url('/knowledge/setup'), $all, true) && in_array(Client::siteRoot().'/', $all, true), 'a plugin whose list fails is left out, the rest of the sitemap stays');
Event::forget('indexnow.urls');

// Bodies are read only up to a limit.
check(Client::verifyKeyFile(str_repeat('a', 4096), url('/big-key.txt'))['ok'] === false, 'a key file far longer than a key is not read to its end');
check(UrlSource::fromSitemap(url('/big-sitemap.xml')) === [], 'a sitemap over the size limit is not read');
check(UrlSource::fromSitemap(url('/small-sitemap.xml')) === ['http://localhost/x'], 'a normal sitemap still is');

// Switching on keeps the verified address; switching on again retires the old key file.
$admin = app(Azuriom\Plugin\Indexnow\Controllers\Admin\AdminController::class);
$admin->enable();
$first = setting('indexnow.key');
$admin->enable();
$second = setting('indexnow.key');
check($first !== $second && ! is_file(public_path($first.'.txt')) && is_file(public_path($second.'.txt')) && setting('indexnow.site') === rtrim(url('/'), '/'),
    'switching on again retires the old key file, and the verified address is kept as the site\'s address');
$admin->disable();

// The site's address is the configured one; a stored address only refines it (run-2).
$configured = rtrim((string) config('app.url'), '/');
$host = parse_url($configured, PHP_URL_HOST);
$stored = function (?string $site) { Azuriom\Models\Setting::updateSettings(['indexnow.site' => $site]); return Client::siteRoot(); };
check($stored('http://other-host.example') === $configured && $stored('http://'.$host.':8098') === $configured, 'a stored address on another host or port is not used');
check($stored('http://'.$host.'/%69ndex.php') === $configured && $stored('http://'.$host.'/index.php') === $configured, 'nor is one that names the front controller, however it is spelled');
check($stored('https://'.$host.'/folder') === 'https://'.$host.'/folder', 'the scheme and folder the site really answers under are kept');
$once = Client::onSiteRoot(rtrim(url('/'), '/').'/news/a');
check(parse_url(url('/'), PHP_URL_SCHEME) === 'https' || ($once === 'https://'.$host.'/folder/news/a' && Client::onSiteRoot($once) === $once), 'moving an address onto the site twice changes nothing the second time');
$stored(null);
// A request that arrives with an IPv6 host in brackets: route() encodes the brackets, the move still recognises its root.
$before = $app['request'];
$bracketed = Illuminate\Http\Request::create('http://[2001:db8::1]/sitemap.xml');
$app->instance('request', $bracketed);
$app['url']->setRequest($bracketed);
$moved = Client::onSiteRoot('http://%5B2001:db8::1%5D/news/a');
$app->instance('request', $before);
$app['url']->setRequest($before);
check($moved === $configured.'/news/a', 'an address built under a bracketed host is moved onto the site like any other ('.$moved.')');
Azuriom\Models\Setting::updateSettings(['indexnow.key' => str_repeat('a', 32)]);
$admin->index();
check(setting('indexnow.site') === null, 'opening the admin page stores nothing (a GET changes nothing)');
Azuriom\Models\Setting::updateSettings(['indexnow.key' => null]);
Setting::updateSettings(['indexnow.key' => Client::generateKey()]);

// --- the admin page ------------------------------------------------------------

// It only counts the addresses: the sitemap is not fetched over HTTP on every view.
$seenBefore = Http::recorded()->count();
app(Azuriom\Plugin\Indexnow\Controllers\Admin\AdminController::class)->index();
app(Azuriom\Plugin\Indexnow\Controllers\Admin\AdminController::class)->index();
$asked = Http::recorded()->slice($seenBefore)->filter(fn ($pair) => str_contains($pair[0]->url(), 'sitemap'))->count();
check($asked <= 1, 'the admin page reads a sitemap at most once in ten minutes, not on every view'.$label);

echo $failures === 0 ? "All feature checks passed$label.".PHP_EOL : "$failures feature check(s) failed$label.".PHP_EOL;
exit($failures === 0 ? 0 : 1);
