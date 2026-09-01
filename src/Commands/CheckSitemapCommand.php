<?php

namespace Azuriom\Plugin\Seo\Commands;

use Azuriom\Plugin\Seo\Controllers\SitemapController;
use Azuriom\Plugin\Seo\SeoCheck;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class CheckSitemapCommand extends Command
{
    protected $signature = 'seo:check';

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
        $withIssues = [];

        foreach ($urls as $url) {
            try {
                $response = Http::withoutRedirecting()->timeout(10)->get($url);
                $status = $response->status();
                $issues = $status === 200 ? SeoCheck::issues($response->body()) : [];
            } catch (\Throwable $e) {
                $status = 0;
                $issues = [];
            }

            if ($status !== 200) {
                $bad[] = $url;
                $this->line('  <fg=red>'.($status ?: 'ERR')."</> {$url}");

                continue;
            }

            if ($issues !== []) {
                $withIssues[] = $url;
                $this->line("  <fg=yellow>SEO</> {$url}");

                foreach ($issues as $issue) {
                    $number = isset($issue['count']) ? ' ('.$issue['count'].')' : '';
                    $this->line('        - '.$issue['key'].$number);
                }
            }
        }

        $this->newLine();

        if ($bad === [] && $withIssues === []) {
            $this->info('All URLs answer with 200 and pass the on-page checks.');

            return self::SUCCESS;
        }

        if ($bad !== []) {
            $this->warn(count($bad).' URL(s) do not answer with 200.');
            $this->warn('Add their path to the excluded paths in the admin panel');
            $this->warn('(Admin > Sitemap), which also clears the cache.');
        }

        if ($withIssues !== []) {
            $this->warn(count($withIssues).' URL(s) have on-page issues. Those belong in the');
            $this->warn('sitemap, but a search engine will hold them against you.');
        }

        return self::FAILURE;
    }
}
