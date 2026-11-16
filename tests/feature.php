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
use Azuriom\Plugin\Indexnow\SiteUrls;
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

$status = 200;
Http::fake(function () use (&$status) {
    return Http::response('', $status);
});

// What one request reports: everything sent to IndexNow when it ends.
$seen = 0;
$reported = function () use ($app, &$seen): array {
    $app->terminate();
    $requests = Http::recorded()->slice($seen)->values();
    $seen += $requests->count();

    return $requests->map(fn ($pair) => $pair[0]->data()['urlList'] ?? [])->all();
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

// --- entries of any other plugin ---------------------------------------------

check(SiteUrls::pluginPages('indexnow-demo') === [url('indexnow-demo')], 'the public pages of a plugin are found in the route table, without its admin pages');
$item = Item::create(['name' => 'Entry']);
check($reported() === [[url('indexnow-demo')]], 'an entry of a plugin that knows nothing about IndexNow reports the public pages of that plugin');
$item->update(['name' => 'Entry 2']);
Item::create(['name' => 'Another']);
check($reported() === [], 'a plugin page is reported at most once per pause, however much changes');
Cache::forget('indexnow.reported.'.sha1(url('indexnow-demo')));
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

Post::whereIn('slug', ['first-post', 'second-post', 'draft-post', 'quiet-post', 'keyless-post', 'failing-post'])->delete();
$page->delete();
$author->delete();
$reported();

echo $failures === 0 ? "All feature checks passed$label.".PHP_EOL : "$failures feature check(s) failed$label.".PHP_EOL;
exit($failures === 0 ? 0 : 1);
