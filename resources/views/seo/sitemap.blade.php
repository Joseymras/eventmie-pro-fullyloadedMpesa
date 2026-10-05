<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url>
        <loc>{{ url('/') }}</loc>
        <lastmod>{{ now()->toW3cString() }}</lastmod>
    </url>
    @foreach ($events as $event)
        <url>
            <loc>{{ route('eventmie.events_show', [$event->slug]) }}</loc>
            <lastmod>{{ optional($event->updated_at)->toW3cString() }}</lastmod>
        </url>
    @endforeach
</urlset>
