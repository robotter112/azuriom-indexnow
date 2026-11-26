@extends('admin.layouts.admin')

@section('title', trans('indexnow::admin.title'))

@php
    // Source of the addresses, shown in the sitemap field.
    $source = match (true) {
        $serveSitemap && $sitemapServedBy === 'self' => ['own', 'bg-success-subtle text-success-emphasis'],
        $urlSource === 'sitemap' && $sitemapCount > 0 => ['found', 'bg-success-subtle text-success-emphasis'],
        default => ['none', 'bg-warning-subtle text-warning-emphasis'],
    };
@endphp

{{-- The one rule of the admin page: side buttons, softer in light mode. --}}
@push('styles')
    <link href="{{ plugin_asset('indexnow', 'css/admin.css') }}&p={{ filemtime(plugin_path('indexnow/assets/css/admin.css')) }}" rel="stylesheet">
@endpush

@push('footer-scripts')
    <script>
        document.querySelectorAll('[data-copy], [data-copy-field]').forEach((button) => button.addEventListener('click', function () {
            const text = button.dataset.copyField ? document.getElementById(button.dataset.copyField).value : button.dataset.copy;
            navigator.clipboard.writeText(text).then(() => button.textContent = button.dataset.done);
        }));
    </script>
@endpush

@section('content')
    <div class="d-flex flex-wrap align-items-center gap-3 mb-4">
        <span class="badge rounded-pill {{ $enabled ? 'bg-success-subtle text-success-emphasis' : 'bg-secondary-subtle text-secondary-emphasis' }}">{{ trans($enabled ? 'indexnow::admin.active' : 'indexnow::admin.inactive') }}</span>
        <p class="text-body-secondary mb-0 me-auto">{{ trans('indexnow::admin.intro') }}</p>
        <button type="submit" form="indexnowSettings" class="btn btn-primary"><i class="bi bi-check-lg" aria-hidden="true"></i> {{ trans('indexnow::admin.save') }}</button>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-body">
                    <h2 class="h5 fw-bold mb-2">{{ trans('indexnow::admin.status') }}</h2>

                    @if($enabled)
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item bg-transparent px-0 d-flex align-items-center gap-3">
                                <span class="d-inline-flex p-2 rounded-3 bg-success-subtle text-success-emphasis" aria-hidden="true"><i class="bi bi-check-lg fs-5"></i></span>
                                <span class="flex-grow-1 overflow-hidden">
                                    <span class="d-block fw-semibold">{{ trans('indexnow::admin.key-file') }}</span>
                                    <span class="d-block small text-body-secondary text-truncate">{{ preg_replace('#^https?://#', '', $keyUrl) }}</span>
                                </span>
                                <button type="button" class="btn btn-sm btn-secondary indexnow-btn-side" data-copy="{{ $keyUrl }}" data-done="{{ trans('indexnow::admin.copied') }}">{{ trans('indexnow::admin.copy') }}</button>
                            </li>
                            <li class="list-group-item bg-transparent px-0 d-flex align-items-center gap-3">
                                <span class="d-inline-flex p-2 rounded-3 {{ $last === null || $last['ok'] ? 'bg-primary-subtle text-primary-emphasis' : 'bg-warning-subtle text-warning-emphasis' }}" aria-hidden="true"><i class="bi bi-clock fs-5"></i></span>
                                <span class="flex-grow-1">
                                    <span class="d-block fw-semibold">{{ trans('indexnow::admin.last') }}</span>
                                    <span class="d-block small text-body-secondary">
                                        @if($last === null)
                                            {{ trans('indexnow::admin.never') }}
                                        @else
                                            {{ \Illuminate\Support\Carbon::createFromTimestamp($last['at'])->diffForHumans() }} · {{ $last['ok'] ? trans_choice('indexnow::admin.last-accepted', $last['count'], ['count' => $last['count']]) : trans('indexnow::admin.result-'.$last['reason'], ['count' => $last['count'], 'status' => $last['status']]) }}
                                        @endif
                                    </span>
                                </span>
                            </li>
                            <li class="list-group-item bg-transparent px-0 d-flex flex-wrap align-items-center gap-3">
                                <span class="d-inline-flex p-2 rounded-3 bg-info-subtle text-info-emphasis" aria-hidden="true"><i class="bi bi-send fs-5"></i></span>
                                <span class="flex-grow-1">
                                    <span class="d-block fw-semibold">{{ trans('indexnow::admin.all') }}</span>
                                    <span class="d-block small text-body-secondary">{{ trans_choice($urlSource === 'sitemap' ? 'indexnow::admin.all-sitemap' : 'indexnow::admin.all-core', $sitemapCount, ['count' => $sitemapCount]) }}</span>
                                </span>
                                <form action="{{ route('indexnow.admin.submit') }}" method="POST">
                                    @csrf
                                    <button type="submit" class="btn btn-secondary indexnow-btn-side" @disabled($sitemapCount === 0)>{{ trans('indexnow::admin.submit-now') }}</button>
                                </form>
                            </li>
                        </ul>
                        <form action="{{ route('indexnow.admin.disable') }}" method="POST" class="text-end mt-2">
                            @csrf
                            <button type="submit" class="btn btn-outline-danger">{{ trans('indexnow::admin.disable') }}</button>
                        </form>
                    @else
                        <div class="d-flex flex-wrap align-items-center gap-3">
                            <span class="d-inline-flex p-2 rounded-3 bg-secondary-subtle text-secondary-emphasis" aria-hidden="true"><i class="bi bi-lightning-charge fs-5"></i></span>
                            <span class="flex-grow-1">
                                <span class="d-block fw-semibold">{{ trans('indexnow::admin.off') }}</span>
                                <span class="d-block small text-body-secondary">{{ trans('indexnow::admin.off-sub') }}</span>
                            </span>
                            <form action="{{ route('indexnow.admin.enable') }}" method="POST">
                                @csrf
                                <button type="submit" class="btn btn-primary">{{ trans('indexnow::admin.enable') }}</button>
                            </form>
                        </div>
                    @endif
                </div>
            </div>

            <form class="card" action="{{ route('indexnow.admin.update') }}" method="POST" id="indexnowSettings">
                @csrf
                <div class="card-body">
                    <h2 class="h5 fw-bold mb-2">{{ trans('indexnow::admin.settings') }}</h2>

                    <ul class="list-group list-group-flush">
                        <li class="list-group-item bg-transparent px-0">
                            <input type="hidden" name="auto" value="0">
                            <div class="form-check form-switch d-flex align-items-center gap-3 ps-0 mb-0">
                                <label class="form-check-label flex-grow-1" for="auto"><span class="d-block fw-semibold">{{ trans('indexnow::admin.auto') }}</span><span class="d-block small text-body-secondary">{{ trans('indexnow::admin.auto-sub') }}</span></label>
                                <input class="form-check-input fs-4 ms-0 flex-shrink-0" type="checkbox" role="switch" id="auto" name="auto" value="1" @checked(old('auto', $auto))>
                            </div>
                        </li>
                        <li class="list-group-item bg-transparent px-0">
                            <input type="hidden" name="serve_sitemap" value="0">
                            <div class="form-check form-switch d-flex align-items-center gap-3 ps-0 mb-0">
                                <label class="form-check-label flex-grow-1" for="serve_sitemap"><span class="d-block fw-semibold">{{ trans('indexnow::admin.serve-sitemap') }}</span><span class="d-block small text-body-secondary">{{ trans('indexnow::admin.serve-sitemap-sub') }}</span></label>
                                <input class="form-check-input fs-4 ms-0 flex-shrink-0" type="checkbox" role="switch" id="serve_sitemap" name="serve_sitemap" value="1" @checked(old('serve_sitemap', $serveSitemap))>
                            </div>
                        </li>
                    </ul>

                    @if($serveSitemap && $sitemapServedBy === 'other')
                        <div class="alert alert-warning small mt-3 mb-0">{{ trans('indexnow::admin.serve-sitemap-taken', ['url' => $ownSitemapUrl]) }}</div>
                    @elseif($serveSitemap && $sitemapServedBy === 'self')
                        <div class="alert alert-success small d-flex align-items-center gap-2 mt-3 mb-0"><i class="bi bi-check-lg" aria-hidden="true"></i><span>{{ trans('indexnow::admin.serve-sitemap-live', ['url' => preg_replace('#^https?://#', '', $ownSitemapUrl)]) }}</span></div>
                    @endif

                    <div class="mt-3">
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                            <label class="form-label mb-0" for="sitemap">{{ trans('indexnow::admin.sitemap') }}</label>
                            <span class="badge rounded-pill {{ $source[1] }}">{{ trans('indexnow::admin.source-'.$source[0]) }}</span>
                        </div>
                        <div class="input-group">
                            <input type="url" class="form-control @error('sitemap') is-invalid @enderror" id="sitemap" name="sitemap" spellcheck="false" value="{{ old('sitemap', $sitemapUrl) }}">
                            <button type="button" class="btn btn-secondary indexnow-btn-side" data-copy-field="sitemap" data-done="{{ trans('indexnow::admin.copied') }}">{{ trans('indexnow::admin.copy') }}</button>
                        </div>
                        @error('sitemap')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                    </div>
                </div>
            </form>
        </div>

        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-body">
                    <h2 class="h5 fw-bold mb-3">{{ trans('indexnow::admin.today') }}</h2>
                    <div class="row g-3">
                        <div class="col-6">
                            <div class="small text-body-secondary">{{ trans('indexnow::admin.reported') }}</div>
                            <div class="fs-3 fw-bold">{{ number_format($today) }}</div>
                            <div class="small text-body-secondary">{{ trans('indexnow::admin.reported-sub') }}</div>
                        </div>
                        <div class="col-6">
                            <div class="small text-body-secondary">{{ trans('indexnow::admin.known') }}</div>
                            <div class="fs-3 fw-bold">{{ number_format($sitemapCount) }}</div>
                            <div class="small text-body-secondary">{{ trans('indexnow::admin.known-sub') }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <h2 class="h5 fw-bold mb-2">{{ trans('indexnow::admin.engines') }}</h2>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item bg-transparent px-0 d-flex align-items-center gap-3"><span class="flex-grow-1 fw-semibold">Bing, Yandex, Seznam, Naver</span><span class="badge rounded-pill bg-success-subtle text-success-emphasis">{{ trans('indexnow::admin.instant') }}</span></li>
                        <li class="list-group-item bg-transparent px-0 d-flex align-items-center gap-3"><span class="flex-grow-1 fw-semibold">Google</span><span class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis">{{ trans('indexnow::admin.via-sitemap') }}</span></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
@endsection
