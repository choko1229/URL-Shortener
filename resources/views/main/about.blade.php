{{--
    限定モードで、利用を許可されていない人に見せる「このドメインについて」。本文は管理画面で編集した Markdown
    @var \App\ViewModels\ViewerData $viewer
    @var \App\Models\SitePage|null $page
    @var string $timezone
    @var bool $wasLoggedIn
    @var \App\Support\SiteIdentity $site
--}}
<x-layouts.main :viewer="$viewer" :title="$page?->title ?? 'このドメインについて'" :show-usage="false">
    <div class="px-4 pt-12 pb-16 sm:px-8 sm:pt-16">
        <div class="mx-auto max-w-[760px]">
            <x-flash-messages class="mb-6" />

            @if ($wasLoggedIn)
                <p class="mb-6 rounded-control border border-border-strong bg-surface px-4 py-3 text-sm" role="status">
                    このサービスは限定公開のため、ログアウトしました。利用するには、管理者に許可を依頼してください。
                </p>
            @endif

            <h1 class="font-rounded text-section font-bold">{{ $page?->title ?? 'このドメインについて' }}</h1>

            {{-- Markdown から変換した HTML（本文に書かれた HTML は取り除いている） --}}
            <div class="mt-8 text-sm leading-relaxed [&_a]:text-primary-dark [&_a]:underline [&_a:hover]:text-primary-darker [&_blockquote]:mt-4 [&_blockquote]:border-l-2 [&_blockquote]:border-border-strong [&_blockquote]:pl-4 [&_blockquote]:text-text-secondary [&_code]:rounded [&_code]:bg-primary-tint-soft [&_code]:px-1 [&_h2]:mt-8 [&_h2]:font-rounded [&_h2]:text-base [&_h2]:font-bold [&_h3]:mt-6 [&_h3]:font-rounded [&_h3]:font-bold [&_hr]:my-8 [&_hr]:border-border [&_li]:leading-relaxed [&_ol]:mt-2 [&_ol]:list-decimal [&_ol]:space-y-1 [&_ol]:pl-5 [&_p]:mt-3 [&_strong]:font-medium [&_table]:mt-4 [&_td]:border [&_td]:border-border [&_td]:px-3 [&_td]:py-2 [&_th]:border [&_th]:border-border [&_th]:bg-table-header [&_th]:px-3 [&_th]:py-2 [&_ul]:mt-2 [&_ul]:list-disc [&_ul]:space-y-1 [&_ul]:pl-5">
                @if ($page)
                    {{ $page->renderedBody() }}
                @else
                    {{-- 説明文が未作成でも、何のドメインかは伝わるようにする --}}
                    <p>{{ config('shortener.domains.main') }} は、{{ $site->operator() }} が管理している短縮URL専用のドメインです。</p>
                    <p>短縮URLの発行は、許可されたユーザーだけが行えます。発行済みの短縮URLは、どなたでも開けます。</p>
                @endif
            </div>

            <div class="mt-10 rounded-card border border-border bg-surface p-5 text-sm">
                <p>運営者: {{ $site->operator() }}</p>
                <p class="mt-1 text-text-secondary">
                    このドメインについてのご質問や、不審なリンクのご報告は<a href="{{ route('main.contact') }}" class="text-primary-dark underline hover:text-primary-darker">お問い合わせ</a>からご連絡ください。
                </p>
            </div>
        </div>
    </div>
</x-layouts.main>
