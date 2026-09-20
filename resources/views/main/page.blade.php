{{--
    固定ページ（利用規約・プライバシーポリシー）。本文は管理画面で編集した Markdown
    @var \App\ViewModels\ViewerData $viewer
    @var \App\Models\SitePage $page
    @var string $timezone
    @var \App\Support\SiteIdentity $site
--}}
<x-layouts.main :viewer="$viewer" :title="$page->title">
    <div class="px-4 pt-12 pb-16 sm:px-8 sm:pt-16">
        <div class="mx-auto max-w-[760px]">
            <h1 class="font-rounded text-section font-bold">{{ $page->title }}</h1>
            @if ($page->updated_at)
                <p class="mt-2 text-[13px] text-text-secondary">最終更新日: {{ $page->updated_at->setTimezone($timezone)->format('Y年n月j日') }}</p>
            @endif

            {{-- Markdown から変換した HTML（本文に書かれた HTML は取り除いている） --}}
            <div class="mt-8 text-sm leading-relaxed [&_a]:text-primary-dark [&_a]:underline [&_a:hover]:text-primary-darker [&_blockquote]:mt-4 [&_blockquote]:border-l-2 [&_blockquote]:border-border-strong [&_blockquote]:pl-4 [&_blockquote]:text-text-secondary [&_code]:rounded [&_code]:bg-primary-tint-soft [&_code]:px-1 [&_h2]:mt-8 [&_h2]:font-rounded [&_h2]:text-base [&_h2]:font-bold [&_h3]:mt-6 [&_h3]:font-rounded [&_h3]:font-bold [&_hr]:my-8 [&_hr]:border-border [&_li]:leading-relaxed [&_ol]:mt-2 [&_ol]:list-decimal [&_ol]:space-y-1 [&_ol]:pl-5 [&_p]:mt-3 [&_strong]:font-medium [&_table]:mt-4 [&_td]:border [&_td]:border-border [&_td]:px-3 [&_td]:py-2 [&_th]:border [&_th]:border-border [&_th]:bg-table-header [&_th]:px-3 [&_th]:py-2 [&_ul]:mt-2 [&_ul]:list-disc [&_ul]:space-y-1 [&_ul]:pl-5">
                {{ $page->renderedBody() }}
            </div>

            <div class="mt-10 rounded-card border border-border bg-surface p-5 text-sm">
                <p>運営者: {{ $site->operator() }}</p>
                <p class="mt-1 text-text-secondary">
                    本ページの内容についてのご質問は<a href="{{ route('main.contact') }}" class="text-primary-dark underline hover:text-primary-darker">お問い合わせ</a>からご連絡ください。
                </p>
            </div>
        </div>
    </div>
</x-layouts.main>
