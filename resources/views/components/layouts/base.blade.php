@props([
    'title' => null,
    'description' => 'chok.ooo はログイン不要で使えるシンプルなURL短縮サービスです。',
    'robots' => null,
])
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ $description }}">
    @if ($robots)
        <meta name="robots" content="{{ $robots }}">
    @endif
    <meta name="theme-color" content="#2EC5E0">
    <title>{{ $title ? $title.' | chok.ooo' : 'chok.ooo - シンプルなURL短縮サービス' }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=M+PLUS+Rounded+1c:wght@500;700;800&family=Noto+Sans+JP:wght@400;500;700&display=swap">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body {{ $attributes->class('min-h-screen') }}>
    <a href="#main-content" class="sr-only rounded-control bg-white px-4 py-2 font-medium text-primary-dark shadow-card focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50">
        メインコンテンツへスキップ
    </a>

    {{ $slot }}

    {{-- コピー完了などの状態変化を支援技術へ通知する --}}
    <div id="live-region" class="sr-only" aria-live="polite" aria-atomic="true"></div>
</body>
</html>
