@extends('admin.layouts.admin')

@section('title', trans('indexnow::admin.title'))

@push('styles')
    {{-- The admin look of the OnAir plugin family (onair-admin.css, the same file in every plugin). --}}
    <link href="{{ plugin_asset('indexnow', 'css/onair-admin.css') }}&p={{ filemtime(plugin_path('indexnow/assets/css/onair-admin.css')) }}" rel="stylesheet">
@endpush

@section('content')
    <div class="oa-actions">
        <span class="oa-pill {{ $enabled ? 'oa-tone-green' : 'oa-tone-grey' }}">{{ trans($enabled ? 'indexnow::admin.active' : 'indexnow::admin.inactive') }}</span>
        <button type="submit" form="indexnowSettings" class="btn btn-primary"><i class="bi bi-check-lg" aria-hidden="true"></i><span class="oa-text">{{ trans('indexnow::admin.save') }}</span></button>
    </div>

    <p class="oa-meta">{{ trans('indexnow::admin.intro') }}</p>

    <div class="oa-layout">
        <div class="oa-main">
            <div class="card oa-card">
                <div class="card-body">
                    <div class="oa-card-head"><h2>{{ trans('indexnow::admin.status') }}</h2></div>

                    @if($enabled)
                        <div class="oa-rows">
                            <div class="oa-row">
                                <span class="oa-tile oa-tone-green" aria-hidden="true"><i class="bi bi-key"></i></span>
                                <span class="oa-row-main">
                                    <span class="oa-row-title">{{ trans('indexnow::admin.key-file') }}</span>
                                    <a class="oa-row-sub text-break" href="{{ $keyUrl }}" target="_blank" rel="noopener">{{ preg_replace('#^https?://#', '', $keyUrl) }}</a>
                                </span>
                            </div>
                            <div class="oa-row">
                                <span class="oa-tile {{ $last === null ? 'oa-tone-grey' : ($last['ok'] ? 'oa-tone-blue' : 'oa-tone-amber') }}" aria-hidden="true"><i class="bi bi-send"></i></span>
                                <span class="oa-row-main">
                                    <span class="oa-row-title">{{ trans('indexnow::admin.last') }}</span>
                                    <span class="oa-row-sub">
                                        @if($last === null)
                                            {{ trans('indexnow::admin.never') }}
                                        @else
                                            {{ \Illuminate\Support\Carbon::createFromTimestamp($last['at'])->diffForHumans() }} · {{ trans('indexnow::admin.result-'.$last['reason'], ['count' => $last['count'], 'status' => $last['status']]) }}
                                        @endif
                                    </span>
                                </span>
                            </div>
                            <div class="oa-row flex-wrap">
                                <span class="oa-tile oa-tone-blue" aria-hidden="true"><i class="bi bi-lightning-charge"></i></span>
                                <span class="oa-row-main">
                                    <span class="oa-row-title">{{ trans('indexnow::admin.all') }}</span>
                                    <span class="oa-row-sub">{{ $urlSource === 'sitemap' ? trans_choice('indexnow::admin.sitemap-hint', $sitemapCount, ['count' => $sitemapCount]) : trans_choice('indexnow::admin.source-core', $sitemapCount, ['count' => $sitemapCount]) }}</span>
                                </span>
                                <form action="{{ route('indexnow.admin.submit') }}" method="POST">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-primary oa-btn" @disabled($sitemapCount === 0)>{{ trans_choice('indexnow::admin.submit', $sitemapCount, ['count' => $sitemapCount]) }}</button>
                                </form>
                            </div>
                        </div>
                        <form action="{{ route('indexnow.admin.disable') }}" method="POST" class="mt-3">
                            @csrf
                            <button type="submit" class="btn btn-link link-danger p-0 text-decoration-none">{{ trans('indexnow::admin.disable') }}</button>
                        </form>
                    @else
                        <div class="oa-row border-0 p-0 flex-wrap">
                            <span class="oa-tile oa-tone-grey" aria-hidden="true"><i class="bi bi-lightning-charge"></i></span>
                            <span class="oa-row-main"><span class="oa-row-title">{{ trans('indexnow::admin.off') }}</span></span>
                            <form action="{{ route('indexnow.admin.enable') }}" method="POST">
                                @csrf
                                <button type="submit" class="btn btn-primary oa-btn"><i class="bi bi-lightning-charge" aria-hidden="true"></i> {{ trans('indexnow::admin.enable') }}</button>
                            </form>
                        </div>
                    @endif
                </div>
            </div>

            <form class="card oa-card" action="{{ route('indexnow.admin.update') }}" method="POST" id="indexnowSettings">
                @csrf
                <div class="card-body">
                    <div class="oa-card-head"><h2>{{ trans('indexnow::admin.settings') }}</h2></div>

                    <div class="oa-rows">
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
                        <div class="alert alert-warning small mt-2">{{ trans('indexnow::admin.serve-sitemap-taken', ['url' => $ownSitemapUrl]) }}</div>
                    @elseif($serveSitemap && $sitemapServedBy === 'self')
                        <p class="oa-row-sub mt-2"><i class="bi bi-check2 text-success" aria-hidden="true"></i> {{ trans('indexnow::admin.serve-sitemap-live', ['url' => $ownSitemapUrl]) }}</p>
                    @endif

                    <div class="mt-3">
                        <label class="form-label" for="sitemap">{{ trans('indexnow::admin.sitemap') }}</label>
                        <input type="url" class="form-control @error('sitemap') is-invalid @enderror" id="sitemap" name="sitemap" spellcheck="false" value="{{ old('sitemap', $sitemapUrl) }}">
                        @error('sitemap')<span class="invalid-feedback">{{ $message }}</span>@enderror
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
                            <div class="oa-stat-label">{{ trans('indexnow::admin.reported-today') }}</div>
                            <div class="oa-stat-value">{{ number_format($today) }}</div>
                        </div>
                        <div class="oa-stat">
                            <div class="oa-stat-label">{{ trans('indexnow::admin.known') }}</div>
                            <div class="oa-stat-value">{{ number_format($sitemapCount) }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
