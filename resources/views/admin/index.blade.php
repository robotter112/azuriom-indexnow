@extends('admin.layouts.admin')

@section('title', trans('sitemap::admin.title'))

@section('content')
    <div class="card shadow mb-4">
        <div class="card-body">
            <h2 class="h5">@lang('sitemap::admin.url')</h2>

            <div class="input-group mb-2">
                <input type="text" class="form-control" id="sitemap-url" value="{{ $sitemapUrl }}" readonly>
                <a href="{{ $sitemapUrl }}" target="_blank" rel="noopener" class="btn btn-outline-secondary">
                    <i class="bi bi-box-arrow-up-right"></i>
                </a>
            </div>

            <p class="text-muted small mb-3">
                @lang('sitemap::admin.url-hint', ['url' => $sitemapUrl])
            </p>

            <p class="mb-3">
                <span class="badge bg-primary">{{ trans_choice('sitemap::admin.count', count($urls), ['count' => count($urls)]) }}</span>
                <span class="text-muted small ms-2">
                    @if($cached)
                        @lang('sitemap::admin.cached', ['minutes' => $cacheMinutes])
                    @else
                        @lang('sitemap::admin.not-cached')
                    @endif
                </span>
            </p>

            <form action="{{ route('sitemap.admin.refresh') }}" method="POST" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-secondary">
                    <i class="bi bi-arrow-clockwise"></i> @lang('sitemap::admin.refresh')
                </button>
            </form>

            <form action="{{ route('sitemap.admin.check') }}" method="POST" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-secondary">
                    <i class="bi bi-check2-circle"></i> @lang('sitemap::admin.check')
                </button>
            </form>

            <p class="text-muted small mt-2 mb-0">@lang('sitemap::admin.check-hint')</p>
        </div>
    </div>

    @if(session('checked'))
        @php($ergebnis = session('checked'))
        <div class="card shadow mb-4">
            <div class="card-body">
                @if(empty($ergebnis['bad']))
                    <div class="alert alert-success mb-0">
                        @lang('sitemap::admin.check-ok', ['count' => $ergebnis['total']])
                    </div>
                @else
                    <div class="alert alert-warning">
                        @lang('sitemap::admin.check-bad', [
                            'count' => count($ergebnis['bad']),
                            'total' => $ergebnis['total'],
                        ])
                    </div>
                    <ul class="list-group">
                        @foreach($ergebnis['bad'] as $eintrag)
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span class="text-break">{{ $eintrag['url'] }}</span>
                                <span class="badge bg-danger">{{ $eintrag['status'] ?: '—' }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if($ergebnis['capped'])
                    <p class="text-muted small mt-2 mb-0">
                        @lang('sitemap::admin.check-capped', ['limit' => $ergebnis['total']])
                    </p>
                @endif
            </div>
        </div>
    @endif

    <div class="card shadow">
        <div class="card-body">
            <h2 class="h5">@lang('sitemap::admin.settings')</h2>

            <form action="{{ route('sitemap.admin.update') }}" method="POST">
                @csrf

                <div class="mb-3">
                    <label class="form-label" for="exclude">@lang('sitemap::admin.exclude')</label>
                    <textarea class="form-control @error('exclude') is-invalid @enderror"
                              id="exclude" name="exclude" rows="6"
                              spellcheck="false">{{ old('exclude', $exclude) }}</textarea>
                    @error('exclude')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                    <small class="form-text text-muted">@lang('sitemap::admin.exclude-hint')</small>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="cache_minutes">@lang('sitemap::admin.cache-minutes')</label>
                    <input type="number" class="form-control @error('cache_minutes') is-invalid @enderror"
                           id="cache_minutes" name="cache_minutes" min="1" max="10080"
                           value="{{ old('cache_minutes', $cacheMinutes) }}" required>
                    @error('cache_minutes')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                    <small class="form-text text-muted">@lang('sitemap::admin.cache-minutes-hint')</small>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i> @lang('sitemap::admin.save')
                </button>
            </form>
        </div>
    </div>
@endsection
