<?php

namespace Azuriom\Plugin\Sitemap\Events;

use Illuminate\Support\Collection;

/**
 * Fired while the sitemap is being built, before it is cached.
 *
 * Other plugins can listen to this event to add their own URLs:
 *
 *     Event::listen(SitemapBuilding::class, function (SitemapBuilding $event) {
 *         foreach (MyModel::all() as $model) {
 *             $event->add(route('myplugin.show', $model), $model->updated_at);
 *         }
 *     });
 */
class SitemapBuilding
{
    public function __construct(public Collection $urls)
    {
        //
    }

    /**
     * Add a URL to the sitemap. Only add URLs a logged-out visitor can open.
     *
     * @param  \DateTimeInterface|string|null  $lastModified
     */
    public function add(string $url, $lastModified = null): self
    {
        $this->urls->push([
            'loc' => $url,
            'lastmod' => $lastModified instanceof \DateTimeInterface
                ? $lastModified->format(\DateTimeInterface::ATOM)
                : $lastModified,
        ]);

        return $this;
    }
}
