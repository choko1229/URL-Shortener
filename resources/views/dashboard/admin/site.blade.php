{{--
    サイト設定（表示名と固定ページ）
    @var \App\ViewModels\ViewerData $viewer
    @var array{name: string, tagline: string, operator: string} $identity
    @var \Illuminate\Support\Collection<string, \App\Models\SitePage> $pages
    @var string $timezone
    @var \App\Support\SiteIdentity $site
--}}
<x-dashboard.page :viewer="$viewer" title="サイト設定" description="サイト名や運営者名、利用規約・プライバシーポリシーをここで設定します。画面に表示される名前はすべてこの設定に従います。">
    <section aria-labelledby="identity-heading" class="rounded-card border border-border bg-white p-5 sm:p-6">
        <h2 id="identity-heading" class="font-rounded text-[15px] font-bold">サイトの表示</h2>
        <form method="POST" action="{{ route('dashboard.admin.site.update') }}" class="mt-4 max-w-2xl space-y-5">
            @csrf
            @method('PUT')

            @php
                $identityFields = [
                    'name' => ['label' => 'サイト名', 'hint' => 'ヘッダーのロゴやページタイトルに表示されます。', 'max' => 60],
                    'tagline' => ['label' => 'キャッチコピー', 'hint' => 'トップページのタイトル（「サイト名 - キャッチコピー」）に使います。', 'max' => 120],
                    'operator' => ['label' => '運営者名', 'hint' => '利用規約・プライバシーポリシーに表示します。', 'max' => 60],
                ];
            @endphp
            @foreach ($identityFields as $name => $field)
                <div>
                    <label for="site-{{ $name }}" class="block text-[13px] font-medium text-text-secondary">{{ $field['label'] }}</label>
                    <input
                        id="site-{{ $name }}"
                        name="{{ $name }}"
                        type="text"
                        required
                        maxlength="{{ $field['max'] }}"
                        autocomplete="off"
                        value="{{ old($name, $identity[$name]) }}"
                        class="form-control mt-2"
                        aria-describedby="site-{{ $name }}-hint{{ $errors->has($name) ? ' site-'.$name.'-error' : '' }}"
                        @error($name) aria-invalid="true" @enderror
                    >
                    <p id="site-{{ $name }}-hint" class="mt-1.5 text-xs text-text-secondary">{{ $field['hint'] }}</p>
                    <x-field-error :name="$name" :id="'site-'.$name.'-error'" />
                </div>
            @endforeach

            <x-button type="submit" size="sm">保存する</x-button>
        </form>
    </section>

    @foreach (\App\Models\SitePage::AVAILABLE as $slug => $defaultTitle)
        @php
            $page = $pages->get($slug);
            // テンプレートの読み込みや入力エラーの直後は、その編集欄の入力内容を復元する
            $isActive = old('slug') === $slug;
            $title = $isActive ? old('title') : ($page?->title ?? $defaultTitle);
            $body = $isActive ? old('body') : ($page?->body ?? '');
        @endphp
        <section aria-labelledby="{{ $slug }}-heading" class="rounded-card border border-border bg-white p-5 sm:p-6">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 id="{{ $slug }}-heading" class="font-rounded text-[15px] font-bold">{{ $defaultTitle }}</h2>
                <p class="text-[13px] text-text-secondary">
                    @if ($page)
                        公開中: <a href="{{ route('main.'.$slug) }}" target="_blank" rel="noopener noreferrer" class="text-primary-dark underline hover:text-primary-darker">{{ route('main.'.$slug) }}</a>
                        （{{ $page->updated_at?->setTimezone($timezone)->format('Y/m/d H:i') }} 更新）
                    @else
                        未作成（フッターに表示されません）
                    @endif
                </p>
            </div>

            <form method="POST" action="{{ route('dashboard.admin.site.pages.update', ['slug' => $slug]) }}" class="mt-4 space-y-4">
                @csrf
                @method('PUT')
                <input type="hidden" name="slug" value="{{ $slug }}">

                <div>
                    <label for="{{ $slug }}-title" class="block text-[13px] font-medium text-text-secondary">ページのタイトル</label>
                    <input
                        id="{{ $slug }}-title"
                        name="title"
                        type="text"
                        required
                        maxlength="100"
                        autocomplete="off"
                        value="{{ $title }}"
                        class="form-control mt-2 max-w-md"
                        @if ($isActive) @error('title') aria-invalid="true" aria-describedby="{{ $slug }}-title-error" @enderror @endif
                    >
                    @if ($isActive)
                        <x-field-error name="title" :id="$slug.'-title-error'" />
                    @endif
                </div>

                <div>
                    <label for="{{ $slug }}-body" class="block text-[13px] font-medium text-text-secondary">本文（Markdown）</label>
                    <textarea
                        id="{{ $slug }}-body"
                        name="body"
                        rows="18"
                        required
                        maxlength="50000"
                        spellcheck="false"
                        class="form-control mt-2 resize-y font-mono text-[13px] leading-relaxed"
                        @if ($isActive) @error('body') aria-invalid="true" aria-describedby="{{ $slug }}-body-error" @enderror @endif
                    >{{ $body }}</textarea>
                    <p class="mt-1.5 text-xs leading-relaxed text-text-secondary">
                        見出しは <code class="rounded bg-primary-tint-soft px-1">## 見出し</code>、箇条書きは <code class="rounded bg-primary-tint-soft px-1">- 項目</code>、リンクは <code class="rounded bg-primary-tint-soft px-1">[表示文字](URL)</code> のように書きます。HTML は取り除かれます。
                    </p>
                    @if ($isActive)
                        <x-field-error name="body" :id="$slug.'-body-error'" />
                    @endif
                </div>

                <div class="flex flex-wrap items-center gap-2.5">
                    <x-button type="submit" size="sm">保存する</x-button>
                    <x-button type="submit" size="sm" variant="secondary" form="{{ $slug }}-template">テンプレートを入力</x-button>
                    @if ($page)
                        <x-button type="submit" size="sm" variant="danger-ghost" form="{{ $slug }}-delete">ページを削除する</x-button>
                    @endif
                </div>
            </form>

            {{-- ボタンだけの小さなフォーム（上のフォームの中には置けないため外に出す） --}}
            <form id="{{ $slug }}-template" method="POST" action="{{ route('dashboard.admin.site.pages.template', ['slug' => $slug]) }}" class="hidden">
                @csrf
            </form>
            @if ($page)
                <form id="{{ $slug }}-delete" method="POST" action="{{ route('dashboard.admin.site.pages.destroy', ['slug' => $slug]) }}" class="hidden">
                    @csrf
                    @method('DELETE')
                </form>
            @endif
        </section>
    @endforeach

    <p class="text-[13px] leading-relaxed text-text-secondary">
        テンプレートは、このソフトウェアが実際に行っている処理（保存する情報・外部への送信・削除の扱いなど）に沿った下書きです。そのまま使えることを保証するものではないため、内容を確認し、運営の実態に合わせて修正してから公開してください。
    </p>
</x-dashboard.page>
