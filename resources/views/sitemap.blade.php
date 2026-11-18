{!! '<'.'?xml version="1.0" encoding="UTF-8"?'.'>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach ($urls as $url)
    <url>
        <loc>{{ $url }}</loc>
    </url>
@endforeach
</urlset>
{{-- The declaration is split so that no PHP open tag stands in this file: with short_open_tag on, Blade would read it as PHP. It must stay the first output. --}}
