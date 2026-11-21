@extends('admin.layouts.admin')

@section('title', trans('indexnow::admin.title'))

@php
    // Where the addresses come from, as a badge in the field of the sitemap.
    $source = match (true) {
        $serveSitemap && $sitemapServedBy === 'self' => ['own', 'oa-tone-green'],
        $urlSource === 'sitemap' && $sitemapCount > 0 => ['found', 'oa-tone-green'],
        default => ['none', 'oa-tone-amber'],
    };
@endphp

@push('styles')
    {{-- The admin look of the OnAir plugin family (onair-admin.css, the same file in every plugin). --}}
    <link href="{{ plugin_asset('indexnow', 'css/onair-admin.css') }}&p={{ filemtime(plugin_path('indexnow/assets/css/onair-admin.css')) }}" rel="stylesheet">
    <style>
        .indexnow-rows > .oa-row:first-of-type { border-top: 0; padding-top: .875rem; }
        .indexnow-rows > .oa-row:last-child { padding-bottom: 0; }
        .indexnow-key { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .indexnow-served { display: flex; align-items: center; gap: .625rem; margin-top: .875rem; padding: .75rem .875rem; border-radius: .625rem; background: var(--oa-green-bg); color: var(--oa-green-fg); font-size: .8125rem; font-weight: 600; }
        .indexnow-engines > div { display: flex; align-items: center; gap: .75rem; padding: .625rem 0; border-top: 1px solid var(--oa-line); font-size: .875rem; font-weight: 600; color: var(--oa-strong); }
        .indexnow-engines > div:first-child { border-top: 0; }
        .indexnow-engines > div:last-child { padding-bottom: 0; }
        .indexnow-engines span:first-child { flex: 1; }
    </style>
@endpush

@push('footer-scripts')
    {{-- Azuriom switches light and dark mode in place: for one frame nothing moves (onair-admin.css), so no colour
         fades over from the other mode. --}}
    <script>
        new MutationObserver(function () {
            document.body.classList.add('oa-theme-switch');
            requestAnimationFrame(function () {
                requestAnimationFrame(function () { document.body.classList.remove('oa-theme-switch'); });
            });
        }).observe(document.body, { attributes: true, attributeFilter: ['data-bs-theme'] });
    </script>
    <script>
        document.querySelectorAll('[data-copy]').forEach((button) => button.addEventListener('click', function () {
            navigator.clipboard.writeText(button.dataset.copy).then(() => button.textContent = button.dataset.done);
        }));
    </script>
@endpush

@section('content')
    <span class="oa-badge {{ $enabled ? 'oa-tone-green' : 'oa-tone-grey' }}">{{ trans($enabled ? 'indexnow::admin.active' : 'indexnow::admin.inactive') }}</span>

    <div class="oa-actions">
        <button type="submit" form="indexnowSettings" class="btn btn-primary"><i class="bi bi-check-lg" aria-hidden="true"></i><span class="oa-text">{{ trans('indexnow::admin.save') }}</span></button>
    </div>

    <p class="oa-meta">{{ trans('indexnow::admin.intro') }}</p>

    <div class="oa-layout">
        <div class="oa-main">
            <div class="card oa-card">
                <div class="card-body">
                    <div class="oa-card-head mb-1"><h2>{{ trans('indexnow::admin.status') }}</h2></div>

                    @if($enabled)
                        <div class="oa-rows indexnow-rows">
                            <div class="oa-row">
                                <span class="oa-tile oa-tone-green" aria-hidden="true"><i class="bi bi-check-lg"></i></span>
                                <span class="oa-row-main">
                                    <span class="oa-row-title">{{ trans('indexnow::admin.key-file') }}</span>
                                    <span class="oa-row-sub indexnow-key">{{ preg_replace('#^https?://#', '', $keyUrl) }}</span>
                                </span>
                                <button type="button" class="btn oa-btn-neutral" data-copy="{{ $keyUrl }}" data-done="{{ trans('indexnow::admin.copied') }}">{{ trans('indexnow::admin.copy') }}</button>
                            </div>
                            <div class="oa-row">
                                <span class="oa-tile {{ $last === null || $last['ok'] ? 'oa-tone-blue' : 'oa-tone-amber' }}" aria-hidden="true"><i class="bi bi-clock"></i></span>
                                <span class="oa-row-main">
                                    <span class="oa-row-title">{{ trans('indexnow::admin.last') }}</span>
                                    <span class="oa-row-sub">
                                        @if($last === null)
                                            {{ trans('indexnow::admin.never') }}
                                        @else
                                            {{ \Illuminate\Support\Carbon::createFromTimestamp($last['at'])->diffForHumans() }} · {{ $last['ok'] ? trans_choice('indexnow::admin.last-accepted', $last['count'], ['count' => $last['count']]) : trans('indexnow::admin.result-'.$last['reason'], ['count' => $last['count'], 'status' => $last['status']]) }}
                                        @endif
                                    </span>
                                </span>
                            </div>
                            <div class="oa-row flex-wrap">
                                <span class="oa-tile oa-tone-orange" aria-hidden="true"><i class="bi bi-send"></i></span>
                                <span class="oa-row-main">
                                    <span class="oa-row-title">{{ trans('indexnow::admin.all') }}</span>
                                    <span class="oa-row-sub">{{ trans_choice($urlSource === 'sitemap' ? 'indexnow::admin.all-sitemap' : 'indexnow::admin.all-core', $sitemapCount, ['count' => $sitemapCount]) }}</span>
                                </span>
                                <form action="{{ route('indexnow.admin.submit') }}" method="POST">
                                    @csrf
                                    <button type="submit" class="btn oa-btn-tint" @disabled($sitemapCount === 0)>{{ trans('indexnow::admin.submit-now') }}</button>
                                </form>
                            </div>
                        </div>
                        <form action="{{ route('indexnow.admin.disable') }}" method="POST" class="text-end mt-3">
                            @csrf
                            <button type="submit" class="oa-btn-danger-text">{{ trans('indexnow::admin.disable') }}</button>
                        </form>
                    @else
                        <div class="oa-row border-0 pb-0 flex-wrap">
                            <span class="oa-tile oa-tone-grey" aria-hidden="true"><i class="bi bi-lightning-charge"></i></span>
                            <span class="oa-row-main">
                                <span class="oa-row-title">{{ trans('indexnow::admin.off') }}</span>
                                <span class="oa-row-sub">{{ trans('indexnow::admin.off-sub') }}</span>
                            </span>
                            <form action="{{ route('indexnow.admin.enable') }}" method="POST">
                                @csrf
                                <button type="submit" class="btn btn-primary">{{ trans('indexnow::admin.enable') }}</button>
                            </form>
                        </div>
                    @endif
                </div>
            </div>

            <form class="card oa-card" action="{{ route('indexnow.admin.update') }}" method="POST" id="indexnowSettings">
                @csrf
                <div class="card-body">
                    <div class="oa-card-head mb-1"><h2>{{ trans('indexnow::admin.settings') }}</h2></div>

                    <div class="oa-rows indexnow-rows">
                        <input type="hidden" name="auto" value="0">
                        <label class="oa-row oa-switch" for="auto">
                            <span class="oa-row-main"><span class="oa-row-title">{{ trans('indexnow::admin.auto') }}</span><span class="oa-row-sub">{{ trans('indexnow::admin.auto-sub') }}</span></span>
                            <span class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" id="auto" name="auto" value="1" @checked(old('auto', $auto))></span>
                        </label>

                        <input type="hidden" name="serve_sitemap" value="0">
                        <label class="oa-row oa-switch" for="serve_sitemap">
                            <span class="oa-row-main"><span class="oa-row-title">{{ trans('indexnow::admin.serve-sitemap') }}</span><span class="oa-row-sub">{{ trans('indexnow::admin.serve-sitemap-sub') }}</span></span>
                            <span class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" id="serve_sitemap" name="serve_sitemap" value="1" @checked(old('serve_sitemap', $serveSitemap))></span>
                        </label>
                    </div>

                    @if($serveSitemap && $sitemapServedBy === 'other')
                        <div class="alert alert-warning small mt-3 mb-0">{{ trans('indexnow::admin.serve-sitemap-taken', ['url' => $ownSitemapUrl]) }}</div>
                    @elseif($serveSitemap && $sitemapServedBy === 'self')
                        <div class="indexnow-served"><i class="bi bi-check-lg" aria-hidden="true"></i><span>{{ trans('indexnow::admin.serve-sitemap-live', ['url' => preg_replace('#^https?://#', '', $ownSitemapUrl)]) }}</span></div>
                    @endif

                    <div class="mt-3">
                        <label class="form-label" for="sitemap">{{ trans('indexnow::admin.sitemap') }}</label>
                        <div class="oa-field-pill">
                            <input type="url" class="form-control @error('sitemap') is-invalid @enderror" id="sitemap" name="sitemap" spellcheck="false" value="{{ old('sitemap', $sitemapUrl) }}">
                            <span class="oa-pill {{ $source[1] }}">{{ trans('indexnow::admin.source-'.$source[0]) }}</span>
                        </div>
                        @error('sitemap')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                    </div>
                </div>
            </form>
        </div>

        <div class="oa-side">
            <div class="card oa-card">
                <div class="card-body">
                    <div class="oa-card-head"><h2>{{ trans('indexnow::admin.today') }}</h2></div>
                    <div class="oa-stats">
                        <div class="oa-stat">
                            <div class="oa-stat-label">{{ trans('indexnow::admin.reported') }}</div>
                            <div class="oa-stat-value">{{ number_format($today) }}</div>
                            <div class="oa-stat-note">{{ trans('indexnow::admin.reported-sub') }}</div>
                        </div>
                        <div class="oa-stat">
                            <div class="oa-stat-label">{{ trans('indexnow::admin.known') }}</div>
                            <div class="oa-stat-value">{{ number_format($sitemapCount) }}</div>
                            <div class="oa-stat-note">{{ trans('indexnow::admin.known-sub') }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card oa-card">
                <div class="card-body">
                    <div class="oa-card-head mb-1"><h2>{{ trans('indexnow::admin.engines') }}</h2></div>
                    <div class="indexnow-engines">
                        <div><span>Bing, Yandex, Seznam, Naver</span><span class="oa-pill oa-tone-green">{{ trans('indexnow::admin.instant') }}</span></div>
                        <div><span>Google</span><span class="oa-pill oa-tone-grey">{{ trans('indexnow::admin.via-sitemap') }}</span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
