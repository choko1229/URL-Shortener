{{-- ファビコン。サービスアイコンと同じものを使う（$siteIcon / $theme はビューコンポーザーで渡している） --}}
@if ($siteIcon->isUploaded())
    <link rel="icon" type="{{ $siteIcon->mimeType() }}" href="{{ route('site-icon') }}?v={{ $siteIcon->version() }}">
    <link rel="apple-touch-icon" href="{{ route('site-icon') }}?v={{ $siteIcon->version() }}">
@else
    <link rel="icon" type="image/svg+xml" href="{{ $siteIcon->faviconDataUri($theme->themeColor()) }}">
@endif
