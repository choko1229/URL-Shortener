@props([
    'title' => null,
    'description' => null,
    'robots' => null,
])
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ $description ?? $site->name().' はログイン不要で使えるシンプルなURL短縮サービスです。' }}">
    @if ($robots)
        <meta name="robots" content="{{ $robots }}">
    @endif
    <meta name="theme-color" content="{{ $theme->themeColor() }}">
    @if ($theme->scheme()->isSwitchable())
        <meta name="theme-color" media="(prefers-color-scheme: dark)" content="{{ $theme->themeColor(dark: true) }}">
    @endif
    <title>{{ $title ? $title.' | '.$site->name() : $site->titleWithTagline() }}</title>

    @include('partials.favicon')

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="{{ $theme->font()->stylesheetUrl() }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- 管理画面で選んだ配色・書体でデザイントークンを上書きする（ビルドし直さずに変えられる） --}}
    <style>{!! $theme->css() !!}</style>
    @if ($theme->scheme()->isSwitchable())
        {{-- 画面が描かれる前に、訪問者が選んだ表示に切り替える --}}
        <script>
            (function () {
                try {
                    var saved = localStorage.getItem('{{ \App\Support\Theme::STORAGE_KEY }}');
                    if (saved === 'dark' || saved === 'light') {
                        document.documentElement.dataset.theme = saved;
                    }
                } catch (error) {}
            })();
        </script>
    @endif
</head>
<body {{ $attributes->class('min-h-screen') }}>
    <a href="#main-content" class="sr-only rounded-control bg-surface px-4 py-2 font-medium text-primary-dark shadow-card focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50">
        メインコンテンツへスキップ
    </a>

    {{ $slot }}

    {{-- コピー完了などの状態変化を支援技術へ通知する --}}
    <div id="live-region" class="sr-only" aria-live="polite" aria-atomic="true"></div>
</body>
</html>
