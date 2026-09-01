@extends('admin.layouts.admin')

@section('title', trans('seo::admin.title'))

@section('content')
    <div class="card shadow mb-4">
        <div class="card-body">
            <h2 class="h5">@lang('seo::admin.url')</h2>

            <div class="input-group mb-2">
                <input type="text" class="form-control" id="sitemap-url" value="{{ $sitemapUrl }}" readonly>
                <a href="{{ $sitemapUrl }}" target="_blank" rel="noopener" class="btn btn-outline-secondary">
                    <i class="bi bi-box-arrow-up-right"></i>
                </a>
            </div>

            <p class="text-muted small mb-3">
                @lang('seo::admin.url-hint', ['url' => $sitemapUrl])
            </p>

            <p class="mb-3">
                <span class="badge bg-primary">{{ trans_choice('seo::admin.count', count($urls), ['count' => count($urls)]) }}</span>
                <span class="text-muted small ms-2">
                    @if($cached)
                        @lang('seo::admin.cached', ['minutes' => $cacheMinutes])
                    @else
                        @lang('seo::admin.not-cached')
                    @endif
                </span>
            </p>

            <form action="{{ route('seo.admin.refresh') }}" method="POST" class="d-inline me-2">
                @csrf
                <button type="submit" class="btn btn-secondary">
                    <i class="bi bi-arrow-clockwise"></i> @lang('seo::admin.refresh')
                </button>
            </form>

            <form action="{{ route('seo.admin.check') }}" method="POST" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-secondary">
                    <i class="bi bi-check2-circle"></i> @lang('seo::admin.check')
                </button>
            </form>

            <p class="text-muted small mt-2 mb-0">@lang('seo::admin.check-hint')</p>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-body">
            <h2 class="h5">@lang('seo::admin.indexnow-title')</h2>
            <p class="text-muted small">@lang('seo::admin.indexnow-hint')</p>

            @if($indexNow['enabled'])
                <div class="alert alert-success">
                    <i class="bi bi-check-circle"></i>
                    @lang('seo::admin.indexnow-on', ['url' => $indexNow['keyUrl']])
                </div>

                <form action="{{ route('seo.admin.indexnow.submit') }}" method="POST" class="d-inline me-2">
                    @csrf
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-send"></i> @lang('seo::admin.indexnow-submit')
                    </button>
                </form>

                <form action="{{ route('seo.admin.indexnow.disable') }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-outline-secondary">
                        @lang('seo::admin.indexnow-disable')
                    </button>
                </form>
            @else
                <p class="text-muted">@lang('seo::admin.indexnow-off')</p>

                <form action="{{ route('seo.admin.indexnow.enable') }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-lightning-charge"></i> @lang('seo::admin.indexnow-enable')
                    </button>
                </form>
            @endif
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-body">
            <h2 class="h5">@lang('seo::admin.robots-title')</h2>

            @if($robots['hasSitemap'])
                <div class="alert alert-success mb-0">
                    <i class="bi bi-check-circle"></i> @lang('seo::admin.robots-ok')
                </div>
            @else
                <div class="alert alert-warning">@lang('seo::admin.robots-missing')</div>

                @if($robots['writable'])
                    <form action="{{ route('seo.admin.robots') }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-file-earmark-plus"></i> @lang('seo::admin.robots-write')
                        </button>
                    </form>
                @else
                    <p class="text-muted small mb-0">
                        @lang('seo::admin.robots-not-writable', ['path' => $robots['path']])
                    </p>
                @endif
            @endif
        </div>
    </div>

    @if(session('checked'))
        @php($ergebnis = session('checked'))
        @php($statusFehler = collect($ergebnis['bad'])->where('status', '!=', 200))
        @php($seoFaelle = collect($ergebnis['bad'])->where('status', 200)->filter(fn ($e) => ! empty($e['issues'])))

        <div class="card shadow mb-4">
            <div class="card-body">
                @if($statusFehler->isEmpty() && $seoFaelle->isEmpty())
                    <div class="alert alert-success mb-0">
                        @lang('seo::admin.check-all-ok', ['count' => $ergebnis['total']])
                    </div>
                @endif

                @if($statusFehler->isNotEmpty())
                    <div class="alert alert-warning">
                        @lang('seo::admin.check-bad', [
                            'count' => $statusFehler->count(),
                            'total' => $ergebnis['total'],
                        ])
                    </div>
                    <ul class="list-group mb-4">
                        @foreach($statusFehler as $eintrag)
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span class="text-break">{{ $eintrag['url'] }}</span>
                                <span class="badge bg-danger">{{ $eintrag['status'] ?: '—' }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if($seoFaelle->isNotEmpty())
                    <h3 class="h6">@lang('seo::admin.issues-title')</h3>
                    <p class="text-muted small">@lang('seo::admin.issues-hint')</p>
                    <ul class="list-group">
                        @foreach($seoFaelle as $eintrag)
                            <li class="list-group-item">
                                <div class="text-break mb-1">{{ $eintrag['url'] }}</div>
                                <ul class="mb-0 small text-muted">
                                    @foreach($eintrag['issues'] as $issue)
                                        <li>
                                            @lang('seo::admin.issue.'.$issue['key'], ['count' => $issue['count'] ?? 0])
                                        </li>
                                    @endforeach
                                </ul>
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if($ergebnis['capped'])
                    <p class="text-muted small mt-2 mb-0">
                        @lang('seo::admin.check-capped', ['limit' => $ergebnis['total']])
                    </p>
                @endif
            </div>
        </div>
    @endif

    <div class="card shadow">
        <div class="card-body">
            <h2 class="h5">@lang('seo::admin.settings')</h2>

            <form action="{{ route('seo.admin.update') }}" method="POST">
                @csrf

                <div class="mb-3">
                    <label class="form-label" for="exclude">@lang('seo::admin.exclude')</label>
                    <textarea class="form-control @error('exclude') is-invalid @enderror"
                              id="exclude" name="exclude" rows="6"
                              spellcheck="false">{{ old('exclude', $exclude) }}</textarea>
                    @error('exclude')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                    <small class="form-text text-muted">@lang('seo::admin.exclude-hint')</small>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="cache_minutes">@lang('seo::admin.cache-minutes')</label>
                    <input type="number" class="form-control @error('cache_minutes') is-invalid @enderror"
                           id="cache_minutes" name="cache_minutes" min="1" max="10080"
                           value="{{ old('cache_minutes', $cacheMinutes) }}" required>
                    @error('cache_minutes')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                    <small class="form-text text-muted">@lang('seo::admin.cache-minutes-hint')</small>
                </div>

                <hr class="my-4">

                <h3 class="h6">@lang('seo::admin.canonical-title')</h3>

                <div class="form-check form-switch mb-2">
                    <input type="hidden" name="canonical" value="0">
                    <input class="form-check-input" type="checkbox" role="switch"
                           id="canonical" name="canonical" value="1"
                           @checked(old('canonical', $canonical))>
                    <label class="form-check-label" for="canonical">
                        @lang('seo::admin.canonical-enable')
                    </label>
                </div>
                <p class="text-muted small">@lang('seo::admin.canonical-hint')</p>

                <div class="mb-3">
                    <label class="form-label" for="canonical_keep">@lang('seo::admin.canonical-keep')</label>
                    <input type="text" class="form-control @error('canonical_keep') is-invalid @enderror"
                           id="canonical_keep" name="canonical_keep"
                           value="{{ old('canonical_keep', $canonicalKeep) }}" spellcheck="false">
                    @error('canonical_keep')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                    <small class="form-text text-muted">@lang('seo::admin.canonical-keep-hint')</small>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i> @lang('seo::admin.save')
                </button>
            </form>
        </div>
    </div>
@endsection
