{!! '<'.'?xml version="1.0" encoding="UTF-8"?'.'>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach ($urls as $url)
    <url>
        <loc>{{ $url }}</loc>
@isset($dates[$url])
        <lastmod>{{ $dates[$url] }}</lastmod>
@endisset
    </url>
@endforeach
</urlset>
{{-- Split so that short_open_tag does not read it as PHP. Must stay the first output. --}}
