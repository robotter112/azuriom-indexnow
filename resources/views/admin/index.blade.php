@extends('admin.layouts.admin')

@section('title', trans('indexnow::admin.title'))

@section('content')
    <div class="card shadow mb-4">
        <div class="card-body">
            <p class="text-muted">@lang('indexnow::admin.intro')</p>

            @if($enabled)
                <div class="alert alert-success">
                    <i class="bi bi-check-circle"></i> @lang('indexnow::admin.on', ['url' => $keyUrl])
                </div>

                <div class="d-flex flex-wrap gap-2">
                    <form action="{{ route('indexnow.admin.submit') }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-send"></i>
                            {{ trans_choice('indexnow::admin.submit', $sitemapCount, ['count' => $sitemapCount]) }}
                        </button>
                    </form>

                    <form action="{{ route('indexnow.admin.disable') }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-outline-secondary">
                            @lang('indexnow::admin.disable')
                        </button>
                    </form>
                </div>
            @else
                <p>@lang('indexnow::admin.off')</p>

                <form action="{{ route('indexnow.admin.enable') }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-lightning-charge"></i> @lang('indexnow::admin.enable')
                    </button>
                </form>
            @endif
        </div>
    </div>

    <div class="card shadow">
        <div class="card-body">
            <h2 class="h5">@lang('indexnow::admin.settings')</h2>

            <form action="{{ route('indexnow.admin.update') }}" method="POST">
                @csrf

                <div class="form-check form-switch mb-2">
                    <input type="hidden" name="auto" value="0">
                    <input class="form-check-input" type="checkbox" role="switch"
                           id="auto" name="auto" value="1" @checked(old('auto', $auto))>
                    <label class="form-check-label" for="auto">@lang('indexnow::admin.auto')</label>
                </div>
                <p class="text-muted small">@lang('indexnow::admin.auto-hint')</p>

                <hr class="my-4">

                <div class="form-check form-switch mb-2">
                    <input type="hidden" name="serve_sitemap" value="0">
                    <input class="form-check-input" type="checkbox" role="switch"
                           id="serve_sitemap" name="serve_sitemap" value="1"
                           @checked(old('serve_sitemap', $serveSitemap))>
                    <label class="form-check-label" for="serve_sitemap">
                        @lang('indexnow::admin.serve-sitemap')
                    </label>
                </div>
                <p class="text-muted small">@lang('indexnow::admin.serve-sitemap-hint')</p>

                @if($serveSitemap && $sitemapServedBy === 'other')
                    <div class="alert alert-warning small">
                        @lang('indexnow::admin.serve-sitemap-taken', ['url' => $ownSitemapUrl])
                    </div>
                @elseif($serveSitemap && $sitemapServedBy === 'self')
                    <p class="small mb-3">
                        <i class="bi bi-check-circle text-success"></i>
                        @lang('indexnow::admin.serve-sitemap-live', ['url' => $ownSitemapUrl])
                    </p>
                @endif

                <div class="mb-3">
                    <label class="form-label" for="sitemap">@lang('indexnow::admin.sitemap')</label>
                    <input type="url" class="form-control @error('sitemap') is-invalid @enderror"
                           id="sitemap" name="sitemap" spellcheck="false"
                           value="{{ old('sitemap', $sitemapUrl) }}">
                    @error('sitemap')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                    <small class="form-text text-muted">
                        @if($urlSource === 'sitemap')
                            {{ trans_choice('indexnow::admin.sitemap-hint', $sitemapCount, ['count' => $sitemapCount]) }}
                        @else
                            {{ trans_choice('indexnow::admin.source-core', $sitemapCount, ['count' => $sitemapCount]) }}
                        @endif
                    </small>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i> @lang('indexnow::admin.save')
                </button>
            </form>
        </div>
    </div>
@endsection
