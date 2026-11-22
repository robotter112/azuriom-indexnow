{!! '<'.'?xml version="1.0" encoding="UTF-8"?'.'>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach ($urls as $url)
    <url>
        <loc>{{ $url }}</loc>
    </url>
@endforeach
</urlset>
{{-- Split so that short_open_tag does not read it as PHP. Must stay the first output. --}}
