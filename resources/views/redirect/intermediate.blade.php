{{--
    中間ページ（requirements.md 3: 手順 3〜4）。JavaScript で redirect サブドメインへ自動 POST する。
    @var string $ticket
--}}
<x-layouts.redirect title="リンク先へ移動しています">
    <form method="POST" action="{{ route('redirect.go') }}" data-auto-submit class="space-y-5">
        <input type="hidden" name="ticket" value="{{ $ticket }}">

        <div class="flex items-center gap-3 text-sm text-text-secondary" role="status">
            <span class="progress-spinner" aria-hidden="true"></span>
            リンク先へ移動しています…
        </div>

        <noscript>
            <p class="text-sm text-text-secondary">JavaScript が無効のため、自動では移動できません。下のボタンから進んでください。</p>
        </noscript>

        <x-button type="submit" variant="secondary" size="sm">移動しない場合はこちら</x-button>
    </form>
    {{-- 画面全体のスクリプト（app.js）の読み込みを待たず、その場で送信して待ち時間を減らす --}}
    <script>
        (function () {
            var form = document.currentScript.previousElementSibling;
            form.dataset.autoSubmitted = 'true';
            form.submit();
        })();
    </script>
</x-layouts.redirect>
