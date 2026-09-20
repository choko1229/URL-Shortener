{{--
    SNS のカード生成（クローラー）向けの応答。転送先を出さず、設定された内容だけを返す。
    人が開いた場合はこの画面ではなく中間ページを表示する。
    @var \App\ViewModels\SharePreviewData $preview
--}}
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $preview->title }}</title>
    <meta name="description" content="{{ $preview->description }}">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $site->name() }}">
    <meta property="og:title" content="{{ $preview->title }}">
    <meta property="og:description" content="{{ $preview->description }}">
    <meta property="og:url" content="{{ $preview->url }}">
    @if ($preview->imageUrl)
        <meta property="og:image" content="{{ $preview->imageUrl }}">
    @endif

    <meta name="twitter:card" content="{{ $preview->imageUrl ? 'summary_large_image' : 'summary' }}">
    <meta name="twitter:title" content="{{ $preview->title }}">
    <meta name="twitter:description" content="{{ $preview->description }}">
    @if ($preview->imageUrl)
        <meta name="twitter:image" content="{{ $preview->imageUrl }}">
    @endif
</head>
<body>
    <p>{{ $preview->title }}</p>
    <p>{{ $preview->description }}</p>
</body>
</html>
