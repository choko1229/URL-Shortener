{{--
    サイト設定（表示名と固定ページ）
    @var \App\ViewModels\ViewerData $viewer
    @var array{name: string, tagline: string, operator: string} $identity
    @var array{color: string, color_scheme: string, font: string} $themeValues
    @var string $selectedIcon
    @var bool $hasUploadedIcon
    @var array{mode: string, outsider_action: string, redirect_url: string|null} $accessValues
    @var \Illuminate\Support\Collection<string, \App\Models\SitePage> $pages
    @var string $timezone
    @var \App\Support\SiteIdentity $site
--}}
<x-dashboard.admin-page :viewer="$viewer" title="サイト設定" description="サイト名や運営者名、利用規約・プライバシーポリシーをここで設定します。画面に表示される名前はすべてこの設定に従います。">
    <section aria-labelledby="identity-heading" class="rounded-card border border-border bg-surface p-5 sm:p-6">
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

    <section aria-labelledby="theme-heading" class="rounded-card border border-border bg-surface p-5 sm:p-6">
        <h2 id="theme-heading" class="font-rounded text-[15px] font-bold">見た目</h2>
        <p class="mt-1 text-[13px] text-text-secondary">ボタンや見出しの色、書体を変えられます。薄い色・濃い色・枠線の色は、メインカラーから自動で作ります。</p>

        <form method="POST" action="{{ route('dashboard.admin.site.theme') }}" class="mt-4 max-w-2xl space-y-6">
            @csrf
            @method('PUT')

            @php $currentColor = old('color', $themeValues['color']); @endphp
            <div data-color-group>
                <span class="block text-[13px] font-medium text-text-secondary">メインカラー</span>

                <div class="mt-2 flex flex-wrap gap-2">
                    @foreach (\App\Support\ColorPalette::PRESETS as $hex => $label)
                        <button
                            type="button"
                            data-color-preset="{{ $hex }}"
                            aria-pressed="{{ $currentColor === $hex ? 'true' : 'false' }}"
                            class="flex min-h-10 items-center gap-2 rounded-full border-[1.5px] border-border-input px-3 text-[13px] font-medium text-text-secondary transition-colors hover:border-primary aria-pressed:border-primary aria-pressed:bg-primary-tint aria-pressed:text-primary-dark"
                        >
                            <span class="size-4 rounded-full border border-text-primary/10" style="background-color: {{ $hex }}"></span>
                            {{ $label }}
                        </button>
                    @endforeach
                </div>

                <div class="mt-3 flex flex-wrap items-center gap-3">
                    <label for="theme-color" class="text-[13px] text-text-secondary">カラーコード</label>
                    <input
                        id="theme-color"
                        name="color"
                        type="text"
                        required
                        maxlength="7"
                        spellcheck="false"
                        value="{{ $currentColor }}"
                        class="form-control w-36 font-mono"
                        data-color-text
                        aria-describedby="theme-color-hint{{ $errors->has('color') ? ' theme-color-error' : '' }}"
                        @error('color') aria-invalid="true" @enderror
                    >
                    <input
                        type="color"
                        value="{{ \App\Support\ColorPalette::isValid($currentColor) ? $currentColor : \App\Support\ColorPalette::DEFAULT_COLOR }}"
                        class="size-11 cursor-pointer rounded-control border-[1.5px] border-border-input bg-surface p-1"
                        data-color-picker
                        aria-label="メインカラーを色見本から選ぶ"
                    >
                </div>
                <p id="theme-color-hint" class="mt-1.5 text-xs text-text-secondary">#2ec5e0 の形式（16進数6桁）。文字が読みにくくならないよう、濃さは自動で調整します。</p>
                <x-field-error name="color" id="theme-color-error" />
            </div>

            <fieldset>
                <legend class="text-[13px] font-medium text-text-secondary">ダークモード</legend>
                <div class="mt-2 space-y-2">
                    @foreach (\App\Enums\ColorScheme::cases() as $scheme)
                        <label class="flex cursor-pointer items-start gap-3 text-sm">
                            <input type="radio" name="color_scheme" value="{{ $scheme->value }}" @checked(old('color_scheme', $themeValues['color_scheme']) === $scheme->value) class="mt-1 size-4 shrink-0 accent-primary-dark">
                            <span>
                                {{ $scheme->label() }}
                                <span class="block text-xs text-text-secondary">{{ $scheme->description() }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
                <x-field-error name="color_scheme" />
            </fieldset>

            <fieldset>
                <legend class="text-[13px] font-medium text-text-secondary">書体</legend>
                <div class="mt-2 space-y-2">
                    @foreach (\App\Enums\FontTheme::cases() as $font)
                        <label class="flex cursor-pointer items-start gap-3 text-sm">
                            <input type="radio" name="font" value="{{ $font->value }}" @checked(old('font', $themeValues['font']) === $font->value) class="mt-1 size-4 shrink-0 accent-primary-dark">
                            <span>
                                <span style="font-family: {{ $font->headingStack() }}">{{ $font->label() }}</span>
                                <span class="block text-xs text-text-secondary">{{ $font->description() }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
                <x-field-error name="font" />
            </fieldset>

            <x-button type="submit" size="sm">保存する</x-button>
        </form>
    </section>

    <section aria-labelledby="icon-heading" class="rounded-card border border-border bg-surface p-5 sm:p-6">
        <h2 id="icon-heading" class="font-rounded text-[15px] font-bold">サービスアイコン</h2>
        <p class="mt-1 text-[13px] text-text-secondary">ヘッダーのロゴマークに使います。ブラウザのタブに出るファビコンにも同じものを使います。</p>

        <form method="POST" action="{{ route('dashboard.admin.site.icon') }}" enctype="multipart/form-data" class="mt-4 max-w-2xl space-y-5">
            @csrf

            <fieldset>
                <legend class="sr-only">アイコンの種類</legend>
                <div class="flex flex-wrap gap-2">
                    @foreach (\App\Support\SiteIcon::BUILT_IN as $name => $label)
                        <label class="cursor-pointer">
                            <input type="radio" name="icon" value="{{ $name }}" @checked(old('icon', $selectedIcon) === $name) class="peer sr-only">
                            <span class="flex min-h-10 items-center gap-2 rounded-full border-[1.5px] border-border-input px-3 text-[13px] font-medium text-text-secondary peer-checked:border-primary peer-checked:bg-primary-tint peer-checked:text-primary-dark peer-focus-visible:outline-3 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-primary-dark">
                                <span class="flex size-6 items-center justify-center rounded-[7px] bg-primary text-white">
                                    <x-icon :name="$name" :size="14" :stroke-width="2.2" />
                                </span>
                                {{ $label }}
                            </span>
                        </label>
                    @endforeach

                    <label class="cursor-pointer">
                        <input type="radio" name="icon" value="{{ \App\Support\SiteIcon::UPLOADED }}" @checked(old('icon', $selectedIcon) === \App\Support\SiteIcon::UPLOADED) class="peer sr-only">
                        <span class="flex min-h-10 items-center gap-2 rounded-full border-[1.5px] border-border-input px-3 text-[13px] font-medium text-text-secondary peer-checked:border-primary peer-checked:bg-primary-tint peer-checked:text-primary-dark peer-focus-visible:outline-3 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-primary-dark">
                            @if ($hasUploadedIcon)
                                <img src="{{ route('site-icon') }}?v={{ $siteIcon->version() }}" alt="" width="24" height="24" class="size-6 rounded-[7px] object-contain">
                            @endif
                            画像を使う
                        </span>
                    </label>
                </div>
                <x-field-error name="icon" />
            </fieldset>

            <div>
                <label for="icon-file" class="block text-[13px] font-medium text-text-secondary">画像を選ぶ{{ $hasUploadedIcon ? '（変更する場合のみ）' : '' }}</label>
                <input
                    id="icon-file"
                    name="file"
                    type="file"
                    accept="{{ collect(\App\Support\SiteIcon::ALLOWED_EXTENSIONS)->map(fn (string $extension): string => '.'.$extension)->implode(',') }}"
                    class="mt-2 block w-full text-sm text-text-secondary file:mr-3 file:min-h-10 file:cursor-pointer file:rounded-control file:border-[1.5px] file:border-primary file:bg-surface file:px-4 file:text-[13px] file:font-medium file:text-text-primary hover:file:bg-primary-tint"
                    aria-describedby="icon-file-hint{{ $errors->has('file') ? ' icon-file-error' : '' }}"
                >
                <p id="icon-file-hint" class="mt-1.5 text-xs text-text-secondary">
                    {{ implode(' / ', \App\Support\SiteIcon::ALLOWED_EXTENSIONS) }}、{{ \App\Support\SiteIcon::MAX_KILOBYTES }}KB まで。正方形（512×512 程度）がきれいに表示されます。SVG は中にスクリプトを書けるため受け付けません。
                </p>
                <x-field-error name="file" id="icon-file-error" />
            </div>

            <x-button type="submit" size="sm">保存する</x-button>
        </form>
    </section>

    @php $currentMode = old('mode', $accessValues['mode']); $currentAction = old('outsider_action', $accessValues['outsider_action']); @endphp
    <section aria-labelledby="access-heading" class="rounded-card border border-border bg-surface p-5 sm:p-6">
        <h2 id="access-heading" class="font-rounded text-[15px] font-bold">公開範囲</h2>
        <p class="mt-1 text-[13px] text-text-secondary">自分や身内だけで使う短縮URLにしたい場合は、限定モードにします。発行済みの短縮URLは、どちらのモードでも誰でも開けます。</p>

        <form method="POST" action="{{ route('dashboard.admin.site.access') }}" class="mt-4 max-w-2xl space-y-6">
            @csrf
            @method('PUT')

            <fieldset>
                <legend class="text-[13px] font-medium text-text-secondary">使える人</legend>
                <div class="mt-2 space-y-2">
                    @foreach (\App\Enums\AccessMode::cases() as $mode)
                        <label class="flex cursor-pointer items-start gap-3 text-sm">
                            <input type="radio" name="mode" value="{{ $mode->value }}" @checked($currentMode === $mode->value) class="mt-1 size-4 shrink-0 accent-primary-dark">
                            <span>
                                {{ $mode->label() }}
                                <span class="block text-xs text-text-secondary">{{ $mode->description() }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
                <x-field-error name="mode" />
            </fieldset>

            <fieldset>
                <legend class="text-[13px] font-medium text-text-secondary">限定モードで、許可されていない人がトップページやダッシュボードを開いたとき</legend>
                <div class="mt-2 space-y-2">
                    @foreach (\App\Enums\OutsiderAction::cases() as $action)
                        <label class="flex cursor-pointer items-start gap-3 text-sm">
                            <input type="radio" name="outsider_action" value="{{ $action->value }}" @checked($currentAction === $action->value) class="mt-1 size-4 shrink-0 accent-primary-dark">
                            <span>{{ $action->label() }}</span>
                        </label>
                    @endforeach
                </div>
                <x-field-error name="outsider_action" />

                <div class="mt-3">
                    <label for="access-redirect-url" class="block text-[13px] font-medium text-text-secondary">移動先の URL（「別のURLへ移動する」のとき）</label>
                    <input
                        id="access-redirect-url"
                        name="redirect_url"
                        type="url"
                        maxlength="2048"
                        placeholder="https://example.org/"
                        value="{{ old('redirect_url', $accessValues['redirect_url']) }}"
                        class="form-control mt-2"
                        aria-describedby="access-redirect-url-hint{{ $errors->has('redirect_url') ? ' access-redirect-url-error' : '' }}"
                        @error('redirect_url') aria-invalid="true" @enderror
                    >
                    <p id="access-redirect-url-hint" class="mt-1.5 text-xs text-text-secondary">本来のサイトやポートフォリオなど。このサイト自身の URL は指定できません。</p>
                    <x-field-error name="redirect_url" id="access-redirect-url-error" />
                </div>
            </fieldset>

            <p class="text-xs leading-relaxed text-text-secondary">
                使える人は、管理者と「ユーザー」タブで「利用を許可」した人です。許可された人は <code class="rounded bg-primary-tint-soft px-1">{{ route('auth.login') }}</code> から直接ログインします（限定モードでは、ダッシュボードを開いてもログイン画面には案内しません）。
            </p>

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
        <section aria-labelledby="{{ $slug }}-heading" class="rounded-card border border-border bg-surface p-5 sm:p-6">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 id="{{ $slug }}-heading" class="font-rounded text-[15px] font-bold">{{ $defaultTitle }}</h2>
                <p class="text-[13px] text-text-secondary">
                    @if ($slug === \App\Models\SitePage::ABOUT)
                        限定モードで、許可されていない人に表示します{{ $page ? '（'.$page->updated_at?->setTimezone($timezone)->format('Y/m/d H:i').' 更新）' : '（未作成のときは短い既定の文を表示します）' }}
                    @elseif ($page)
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
</x-dashboard.admin-page>
