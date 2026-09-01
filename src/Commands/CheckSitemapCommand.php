<?php

namespace Azuriom\Plugin\Sitemap\Commands;

use Azuriom\Plugin\Sitemap\Controllers\SitemapController;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class CheckSitemapCommand extends Command
{
    protected $signature = 'sitemap:check';

    protected $description = 'Fetch every URL of the sitemap as a guest and report the ones that do not answer with 200';

    public function handle(): int
    {
        $urls = collect(app(SitemapController::class)->urls())->pluck('loc');

        if ($urls->isEmpty()) {
            $this->error('The sitemap is empty.');

            return self::FAILURE;
        }

        $this->info("Checking {$urls->count()} URLs...");

        $bad = [];

        foreach ($urls as $url) {
            try {
                $status = Http::withoutRedirecting()->timeout(10)->get($url)->status();
            } catch (\Throwable $e) {
                $status = 0;
            }

            if ($status !== 200) {
                $bad[] = $url;
                $this->line('  <fg=red>'.($status ?: 'ERR')."</> {$url}");
            }
        }

        if ($bad === []) {
            $this->info('All URLs answer with 200.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->warn(count($bad).' URL(s) do not answer with 200.');
        $this->warn('Add their path to the excluded paths in the admin panel');
        $this->warn('(Admin > Sitemap), which also clears the cache.');

        return self::FAILURE;
    }
}
